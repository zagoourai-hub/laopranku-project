<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Jobs\ChapterWriterJob;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\User;
use App\Services\Ai\GenerationLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Outline workflow for topic documents, shared by the outline page and MCP. Callers authorize;
 * this enforces the lock after approval, the schema, and the AI budget.
 */
class OutlineService
{
    public function __construct(
        private ContentUpdater $updater,
        private GenerationLimiter $generations,
    ) {}

    public function isLocked(Document $document): bool
    {
        return ($document->content['brief']['outline_approved_at'] ?? null) !== null;
    }

    /** @return list<string> */
    public function addChapter(Document $document): array
    {
        return $this->edit($document, function (array &$c): void {
            $c['chapters'][] = ['id' => (string) Str::uuid(), 'title' => __('Bab baru'), 'sections' => []];
        });
    }

    /** @return list<string> */
    public function renameChapter(Document $document, int $i, string $title): array
    {
        return $this->edit($document, function (array &$c) use ($i, $title): void {
            $c['chapters'][$i]['title'] = trim($title);
        });
    }

    /** @return list<string> */
    public function removeChapter(Document $document, int $i): array
    {
        return $this->edit($document, function (array &$c) use ($i): void {
            $this->forgetNotes($c, $c['chapters'][$i]['sections'] ?? []);
            array_splice($c['chapters'], $i, 1);
        });
    }

    /** @return list<string> */
    public function moveChapter(Document $document, int $i, int $dir): array
    {
        return $this->edit($document, function (array &$c) use ($i, $dir): void {
            $j = $i + $dir;

            if (isset($c['chapters'][$j])) {
                [$c['chapters'][$i], $c['chapters'][$j]] = [$c['chapters'][$j], $c['chapters'][$i]];
            }
        });
    }

    /** @return list<string> */
    public function addSection(Document $document, int $i): array
    {
        return $this->edit($document, function (array &$c) use ($i): void {
            $c['chapters'][$i]['sections'][] = ['id' => (string) Str::uuid(), 'title' => __('Subbab baru'), 'blocks' => []];
        });
    }

    /** @return list<string> */
    public function renameSection(Document $document, int $i, int $s, string $title): array
    {
        return $this->edit($document, function (array &$c) use ($i, $s, $title): void {
            $c['chapters'][$i]['sections'][$s]['title'] = trim($title);
        });
    }

    /** @return list<string> */
    public function removeSection(Document $document, int $i, int $s): array
    {
        return $this->edit($document, function (array &$c) use ($i, $s): void {
            $this->forgetNotes($c, [$c['chapters'][$i]['sections'][$s]]);
            array_splice($c['chapters'][$i]['sections'], $s, 1);
        });
    }

    /** @return list<string> */
    public function moveSection(Document $document, int $i, int $s, int $dir): array
    {
        return $this->edit($document, function (array &$c) use ($i, $s, $dir): void {
            $t = $s + $dir;
            $sections = &$c['chapters'][$i]['sections'];

            if (isset($sections[$t])) {
                [$sections[$s], $sections[$t]] = [$sections[$t], $sections[$s]];
            }
        });
    }

    /**
     * Approve the outline and queue one writer job per chapter (one AI unit each).
     * The row lock makes a double approval queue nothing twice.
     *
     * @return array{outcome: 'queued'|'limited'|'error'|'noop', message?: string}
     */
    public function approve(Document $document, User $user, ?string $ip = null): array
    {
        $result = DB::transaction(function () use ($document, $user, $ip): array {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $chapters = $locked->content['chapters'] ?? [];

            if ($this->isLocked($locked) || $chapters === []) {
                return ['outcome' => 'noop'];
            }

            if (! $this->generations->consumeFor($user, count($chapters), 'chapter', $ip)) {
                return ['outcome' => 'limited', 'message' => $this->generations->refusalMessage($user, count($chapters), $ip)];
            }

            $content = $locked->content;
            $content['brief']['outline_approved_at'] = now()->toIso8601String();

            if (($errors = $this->updater->apply($locked, $content)) !== []) {
                return ['outcome' => 'error', 'message' => $errors[0]];
            }

            $locked->update(['status' => DocumentStatus::Generating]);

            return ['outcome' => 'queued', 'jobs' => $this->createJobs($locked, range(0, count($chapters) - 1))];
        });

        return $this->dispatch($document, $result);
    }

    /**
     * Write one chapter again. Queued only if that chapter has no job still waiting or running.
     *
     * @return array{outcome: 'queued'|'limited'|'error'|'noop', message?: string}
     */
    public function regenerate(Document $document, User $user, int $i, ?string $ip = null): array
    {
        $result = DB::transaction(function () use ($document, $user, $i, $ip): array {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);

            if (! $this->isLocked($locked) || ! isset($locked->content['chapters'][$i]) || $this->isBusy($locked, $i)) {
                return ['outcome' => 'noop'];
            }

            if (! $this->generations->consumeFor($user, 1, 'chapter', $ip)) {
                return ['outcome' => 'limited', 'message' => $this->generations->refusalMessage($user, 1, $ip)];
            }

            $locked->update(['status' => DocumentStatus::Generating]);

            return ['outcome' => 'queued', 'jobs' => $this->createJobs($locked, [$i])];
        });

        return $this->dispatch($document, $result);
    }

    /**
     * Latest job per chapter index.
     *
     * @return array<int, DocumentJob>
     */
    public function chapterStatuses(Document $document): array
    {
        return $document->jobs()
            ->whereNotNull('chapter_index')
            ->orderBy('id')
            ->get()
            ->groupBy('chapter_index')
            ->map(fn ($rows) => $rows->last())
            ->all();
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

    /**
     * Runs after the transaction commits, so a worker never sees a job whose row is not visible yet.
     *
     * @param  array{outcome: 'queued'|'limited'|'error'|'noop', message?: string, jobs?: list<array{0: int, 1: int}>}  $result
     * @return array{outcome: 'queued'|'limited'|'error'|'noop', message?: string}
     */
    private function dispatch(Document $document, array $result): array
    {
        foreach ($result['jobs'] ?? [] as [$i, $jobId]) {
            ChapterWriterJob::dispatch($document, $i, $jobId);
        }

        unset($result['jobs']);

        return $result;
    }

    /**
     * Drop notes of removed sections so the brief keeps no dead ids.
     *
     * @param  array<string, mixed>  $c
     * @param  list<array<string, mixed>>  $sections
     */
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

    /**
     * Structural edits, only before approval. A rejected change is returned and not saved.
     *
     * @return list<string>
     */
    private function edit(Document $document, callable $change): array
    {
        if ($this->isLocked($document)) {
            return [__('Outline sudah disetujui dan tidak bisa diubah lagi.')];
        }

        $content = $document->content;
        $change($content);

        return $this->updater->apply($document, $content);
    }
}
