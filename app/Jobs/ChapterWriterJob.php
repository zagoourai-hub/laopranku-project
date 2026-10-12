<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Services\Ai\ChapterWriter;
use App\Services\Documents\ContentUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Write the body of one chapter. Only that chapter's sections change; other chapters and the outline stay as they are.
 */
class ChapterWriterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Document $document, public int $chapterIndex, public int $jobId) {}

    public function handle(ChapterWriter $writer, ContentUpdater $updater): void
    {
        $job = DocumentJob::findOrFail($this->jobId);
        $job->update(['status' => 'running']);

        try {
            $paragraphs = $writer->write($this->document, $this->chapterIndex);

            DB::transaction(function () use ($paragraphs, $updater): void {
                // Chapter jobs run in parallel: re-read under a row lock so one chapter never overwrites another.
                $document = Document::query()->lockForUpdate()->findOrFail($this->document->id);
                $content = $document->content;

                foreach ($content['chapters'][$this->chapterIndex]['sections'] as $i => $section) {
                    $content['chapters'][$this->chapterIndex]['sections'][$i]['blocks'] = array_map(
                        fn (string $text): array => ['id' => (string) Str::uuid(), 'type' => 'paragraph', 'text' => $text],
                        $paragraphs[$i] ?? [],
                    );
                }

                if (($errors = $updater->apply($document, $content)) !== []) {
                    throw new RuntimeException('Isi bab tidak valid: '.implode('; ', $errors));
                }
            });

            $job->update(['status' => 'done', 'tokens_in' => $writer->tokensIn, 'tokens_out' => $writer->tokensOut]);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500), 'tokens_in' => $writer->tokensIn, 'tokens_out' => $writer->tokensOut]);
            $this->fail($e);
        } finally {
            $this->settle();
        }
    }

    /** Back to "Siap" once no chapter is still queued or running. Failed chapters can be regenerated one by one. */
    private function settle(): void
    {
        $pending = DocumentJob::query()
            ->where('document_id', $this->document->id)
            ->whereNotNull('chapter_index')
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if (! $pending) {
            Document::query()->whereKey($this->document->id)->update(['status' => DocumentStatus::Ready]);
        }
    }
}
