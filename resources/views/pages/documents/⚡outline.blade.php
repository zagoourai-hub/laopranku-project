<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Documents\OutlineService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        return $this->outline()->isLocked($this->document);
    }

    public function addChapter(): void
    {
        $this->edit(fn (OutlineService $o) => $o->addChapter($this->document));
    }

    public function renameChapter(int $i, string $title): void
    {
        $this->edit(fn (OutlineService $o) => $o->renameChapter($this->document, $i, $title));
    }

    public function removeChapter(int $i): void
    {
        $this->edit(fn (OutlineService $o) => $o->removeChapter($this->document, $i));
    }

    public function moveChapter(int $i, int $dir): void
    {
        $this->edit(fn (OutlineService $o) => $o->moveChapter($this->document, $i, $dir));
    }

    public function addSection(int $i): void
    {
        $this->edit(fn (OutlineService $o) => $o->addSection($this->document, $i));
    }

    public function renameSection(int $i, int $s, string $title): void
    {
        $this->edit(fn (OutlineService $o) => $o->renameSection($this->document, $i, $s, $title));
    }

    public function removeSection(int $i, int $s): void
    {
        $this->edit(fn (OutlineService $o) => $o->removeSection($this->document, $i, $s));
    }

    public function moveSection(int $i, int $s, int $dir): void
    {
        $this->edit(fn (OutlineService $o) => $o->moveSection($this->document, $i, $s, $dir));
    }

    public function approve(): void
    {
        Gate::authorize('update', $this->document);

        $this->report($this->outline()->approve($this->document, Auth::user(), request()->ip()));
    }

    public function regenerate(int $i): void
    {
        Gate::authorize('update', $this->document);

        $this->report($this->outline()->regenerate($this->document, Auth::user(), $i, request()->ip()));
    }

    /** Latest job per chapter index. */
    public function chapterStatuses(): array
    {
        return $this->outline()->chapterStatuses($this->document);
    }

    private function outline(): OutlineService
    {
        return app(OutlineService::class);
    }

    /** @param  array{outcome: string, message?: string}  $result */
    private function report(array $result): void
    {
        match ($result['outcome']) {
            'limited' => Flux::toast(variant: 'warning', text: $result['message']),
            'error' => Flux::toast(variant: 'danger', text: $result['message']),
            default => null,
        };

        $this->document->refresh();
    }

    /** @param  callable(OutlineService): list<string>  $change */
    private function edit(callable $change): void
    {
        Gate::authorize('update', $this->document);

        if (($errors = $change($this->outline())) !== []) {
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
