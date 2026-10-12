<?php

use App\Enums\DocumentStatus;
use App\Jobs\ChapterWriterJob;
use App\Models\Document;
use App\Services\Ai\GenerationLimiter;
use App\Services\Documents\ContentUpdater;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Outline')] class extends Component {
    public Document $document;

    public function mount(Document $document): void
    {
        Gate::authorize('view', $document);
        abort_unless(($document->content['brief']['input'] ?? null) === 'topic', 404);

        $this->document = $document;
    }

    public function refresh(): void
    {
        $this->document->refresh();
    }

    public function locked(): bool
    {
        return $this->isLocked($this->document);
    }

    public function addChapter(): void
    {
        $this->edit(function (array &$c): void {
            $c['chapters'][] = ['id' => (string) Str::uuid(), 'title' => __('Bab baru'), 'sections' => []];
        });
    }

    public function renameChapter(int $i, string $title): void
    {
        $this->edit(function (array &$c) use ($i, $title): void {
            $c['chapters'][$i]['title'] = trim($title);
        });
    }

    public function removeChapter(int $i): void
    {
        $this->edit(function (array &$c) use ($i): void {
            $this->forgetNotes($c, $c['chapters'][$i]['sections'] ?? []);
            array_splice($c['chapters'], $i, 1);
        });
    }

    public function moveChapter(int $i, int $dir): void
    {
        $this->edit(function (array &$c) use ($i, $dir): void {
            $j = $i + $dir;

            if (isset($c['chapters'][$j])) {
                [$c['chapters'][$i], $c['chapters'][$j]] = [$c['chapters'][$j], $c['chapters'][$i]];
            }
        });
    }

    public function addSection(int $i): void
    {
        $this->edit(function (array &$c) use ($i): void {
            $c['chapters'][$i]['sections'][] = ['id' => (string) Str::uuid(), 'title' => __('Subbab baru'), 'blocks' => []];
        });
    }

    public function renameSection(int $i, int $s, string $title): void
    {
        $this->edit(function (array &$c) use ($i, $s, $title): void {
            $c['chapters'][$i]['sections'][$s]['title'] = trim($title);
        });
    }

    public function removeSection(int $i, int $s): void
    {
        $this->edit(function (array &$c) use ($i, $s): void {
            $this->forgetNotes($c, [$c['chapters'][$i]['sections'][$s]]);
            array_splice($c['chapters'][$i]['sections'], $s, 1);
        });
    }

    public function moveSection(int $i, int $s, int $dir): void
    {
        $this->edit(function (array &$c) use ($i, $s, $dir): void {
            $t = $s + $dir;
            $sections = &$c['chapters'][$i]['sections'];

            if (isset($sections[$t])) {
                [$sections[$s], $sections[$t]] = [$sections[$t], $sections[$s]];
            }
        });
    }

    /**
     * Approve the outline and queue one writer job per chapter. The row lock makes a double click queue nothing twice.
     */
    public function approve(): void
    {
        Gate::authorize('update', $this->document);

        $result = DB::transaction(function (): array {
            $document = Document::query()->lockForUpdate()->findOrFail($this->document->id);

            if ($this->isLocked($document) || ($document->content['chapters'] ?? []) === []) {
                return ['outcome' => 'noop'];
            }

            if (! app(GenerationLimiter::class)->consume()) {
                return ['outcome' => 'limited'];
            }

            $content = $document->content;
            $content['brief']['outline_approved_at'] = now()->toIso8601String();

            if (($errors = app(ContentUpdater::class)->apply($document, $content)) !== []) {
                return ['outcome' => 'error', 'message' => $errors[0]];
            }

            $document->update(['status' => DocumentStatus::Generating]);

            return ['outcome' => 'queued', 'jobs' => $this->createJobs($document, array_keys($content['chapters']))];
        });

        $this->dispatchResult($result);
    }

    /**
     * Write one chapter again. Queued only if that chapter has no job still waiting or running.
     */
    public function regenerate(int $i): void
    {
        Gate::authorize('update', $this->document);

        $result = DB::transaction(function () use ($i): array {
            $document = Document::query()->lockForUpdate()->findOrFail($this->document->id);

            if (! $this->isLocked($document) || ! isset($document->content['chapters'][$i]) || $this->isBusy($document, $i)) {
                return ['outcome' => 'noop'];
            }

            if (! app(GenerationLimiter::class)->consume()) {
                return ['outcome' => 'limited'];
            }

            $document->update(['status' => DocumentStatus::Generating]);

            return ['outcome' => 'queued', 'jobs' => $this->createJobs($document, [$i])];
        });

        $this->dispatchResult($result);
    }

    /** Latest job per chapter index. */
    public function chapterStatuses(): array
    {
        return $this->document->jobs()
            ->whereNotNull('chapter_index')
            ->orderBy('id')
            ->get()
            ->groupBy('chapter_index')
            ->map(fn ($rows) => $rows->last())
            ->all();
    }

    private function isLocked(Document $document): bool
    {
        return ($document->content['brief']['outline_approved_at'] ?? null) !== null;
    }

    private function isBusy(Document $document, int $i): bool
    {
        return $document->jobs()->where('chapter_index', $i)->whereIn('status', ['queued', 'running'])->exists();
    }

    /**
     * @param  list<int>  $chapters
     * @return list<array{0: int, 1: int}> chapter index and job id
     */
    private function createJobs(Document $document, array $chapters): array
    {
        return array_map(fn (int $i): array => [$i, $document->jobs()->create(['chapter_index' => $i, 'status' => 'queued'])->id], $chapters);
    }

    /** Runs after the transaction commits, so a worker never sees a job whose row is not visible yet. */
    private function dispatchResult(array $result): void
    {
        match ($result['outcome']) {
            'limited' => Flux::toast(variant: 'warning', text: __('Terlalu banyak permintaan AI. Coba lagi beberapa menit lagi.')),
            'error' => Flux::toast(variant: 'danger', text: $result['message']),
            'queued' => $this->queue($result['jobs']),
            default => null,
        };

        $this->document->refresh();
    }

    /** @param  list<array{0: int, 1: int}>  $jobs */
    private function queue(array $jobs): void
    {
        foreach ($jobs as [$i, $jobId]) {
            ChapterWriterJob::dispatch($this->document, $i, $jobId);
        }
    }

    /** Drop notes of removed sections so the brief keeps no dead ids. */
    private function forgetNotes(array &$c, array $sections): void
    {
        foreach ($sections as $section) {
            unset($c['brief']['section_notes'][$section['id']]);
        }

        // An empty map would be stored as a JSON list, which the schema rejects.
        if (($c['brief']['section_notes'] ?? null) === []) {
            unset($c['brief']['section_notes']);
        }
    }

    /** Structural edits, only before approval. A rejected change is shown and not saved. */
    private function edit(callable $change): void
    {
        Gate::authorize('update', $this->document);

        if ($this->locked()) {
            return;
        }

        $content = $this->document->content;
        $change($content);

        if (($errors = app(ContentUpdater::class)->apply($this->document, $content)) !== []) {
            Flux::toast(variant: 'danger', text: $errors[0]);
        }

        $this->document->refresh();
    }
};
?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6" @if (in_array($document->status, [DocumentStatus::Generating, DocumentStatus::Outlining], true)) wire:poll.3s="refresh" @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Outline') }}</flux:heading>
            <flux:subheading>{{ $document->title }} · {{ $document->status->label() }}</flux:subheading>
        </div>
        <flux:button :href="route('documents.show', $document)" wire:navigate icon="arrow-left">{{ __('Kembali') }}</flux:button>
    </div>

    @php($chapters = $document->content['chapters'] ?? [])
    @php($notes = $document->content['brief']['section_notes'] ?? [])
    @php($statuses = $this->chapterStatuses())
    @php($locked = $this->locked())
    @php($done = collect($statuses)->where('status', 'done')->count())

    @if ($chapters === [])
        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-white/10" wire:poll.3s="refresh" data-test="outline-empty">
            <flux:heading size="lg">{{ __('Outline sedang disusun') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Mohon tunggu sebentar. Halaman ini akan terisi otomatis.') }}</flux:text>
        </div>
    @endif

    @if ($locked)
        <flux:callout variant="secondary" icon="information-circle" data-test="outline-locked">
            <flux:callout.heading>{{ __('Outline sudah disetujui. Bab selesai: :done dari :total.', ['done' => $done, 'total' => count($chapters)]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Bab yang gagal bisa ditulis ulang satu per satu. Struktur bab tidak bisa diubah lagi.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($chapters !== [])
        <flux:text>{{ __('Tinjau judul bab dan subbab. Setelah disetujui, AI menulis isi tiap bab.') }}</flux:text>
    @endif

    @foreach ($chapters as $i => $chapter)
        <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-white/10" wire:key="chapter-{{ $chapter['id'] }}" data-test="outline-chapter">
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge size="sm">{{ __('Bab') }} {{ $i + 1 }}</flux:badge>
                <flux:input class="min-w-0 flex-1" size="sm" value="{{ $chapter['title'] }}" :disabled="$locked" wire:change="renameChapter({{ $i }}, $event.target.value)" aria-label="{{ __('Judul bab') }}" />
                @if (! $locked)
                    <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="moveChapter({{ $i }}, -1)" aria-label="{{ __('Naik') }}" />
                    <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="moveChapter({{ $i }}, 1)" aria-label="{{ __('Turun') }}" />
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeChapter({{ $i }})" aria-label="{{ __('Hapus bab') }}" />
                @else
                    @php($status = $statuses[$i]->status ?? null)
                    <flux:badge size="sm" :color="match($status) { 'done' => 'green', 'failed' => 'red', null => 'zinc', default => 'amber' }" data-test="chapter-status">
                        {{ match($status) { 'done' => __('Selesai'), 'failed' => __('Gagal'), 'running' => __('Menulis…'), 'queued' => __('Antre'), default => __('Belum mulai') } }}
                    </flux:badge>
                    @if (in_array($status, ['done', 'failed'], true))
                        <flux:button size="sm" variant="subtle" wire:click="regenerate({{ $i }})" data-test="regenerate-chapter">{{ __('Tulis ulang') }}</flux:button>
                    @endif
                @endif
            </div>

            <div class="space-y-3 ps-2 sm:ps-6">
                @foreach ($chapter['sections'] as $s => $section)
                    <div class="space-y-1" wire:key="section-{{ $section['id'] }}">
                        <div class="flex items-center gap-2">
                            <flux:input size="sm" class="min-w-0 flex-1" value="{{ $section['title'] }}" :disabled="$locked" wire:change="renameSection({{ $i }}, {{ $s }}, $event.target.value)" aria-label="{{ __('Judul subbab') }}" />
                            @if (! $locked)
                                <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="moveSection({{ $i }}, {{ $s }}, -1)" aria-label="{{ __('Naik') }}" />
                                <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="moveSection({{ $i }}, {{ $s }}, 1)" aria-label="{{ __('Turun') }}" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeSection({{ $i }}, {{ $s }})" aria-label="{{ __('Hapus subbab') }}" />
                            @endif
                        </div>
                        @if (! empty($notes[$section['id']] ?? ''))
                            <flux:text size="sm" class="ps-1 text-zinc-500">{{ $notes[$section['id']] }}</flux:text>
                        @endif
                    </div>
                @endforeach

                @if (! $locked)
                    <flux:button size="sm" variant="subtle" icon="plus" wire:click="addSection({{ $i }})">{{ __('Tambah subbab') }}</flux:button>
                @endif
            </div>
        </section>
    @endforeach

    @if (! $locked && $chapters !== [])
        <div class="flex flex-wrap justify-between gap-3">
            <flux:button icon="plus" wire:click="addChapter" data-test="add-chapter">{{ __('Tambah bab') }}</flux:button>
            <flux:button variant="primary" wire:click="approve" data-test="approve-outline" wire:confirm="{{ __('Setujui outline? Setelah itu struktur bab tidak bisa diubah dan AI mulai menulis isi.') }}">{{ __('Setujui dan tulis isi') }}</flux:button>
        </div>
    @endif
</div>
