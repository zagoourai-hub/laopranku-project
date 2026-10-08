<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Ai\Structurer;
use App\Services\Documents\ContentUpdater;
use App\Services\Documents\TextNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Label pasted/uploaded paragraphs with AI and save them as chapters. Dispatch behind the `ai-generate` limiter.
 */
class StructureDocument implements ShouldQueue
{
    use Queueable;

    /** The structurer already retries the AI call once (PRD pipeline rule 5). */
    public int $tries = 1;

    public function __construct(public Document $document) {}

    public function handle(Structurer $structurer, ContentUpdater $updater, TextNormalizer $normalizer): void
    {
        $job = $this->document->jobs()->create(['status' => 'running']);
        $this->document->update(['status' => DocumentStatus::Structuring]);

        try {
            $content = $this->document->content;
            $paragraphs = $content['brief']['paragraphs'] ?? $normalizer->normalize($content['brief']['raw_text'] ?? '');

            if ($paragraphs === []) {
                throw new RuntimeException('Tidak ada teks untuk disusun.');
            }

            $content['chapters'] = $structurer->structure($paragraphs, $this->document->type);
            unset($content['brief']['raw_text'], $content['brief']['paragraphs']); // only kept for a retry after failure

            if ($content['brief'] === []) {
                unset($content['brief']); // an empty PHP array would serialize as a JSON list, not the schema's object
            }

            $errors = $updater->apply($this->document, $content);

            if ($errors !== []) {
                throw new RuntimeException('Hasil struktur tidak valid: '.implode('; ', $errors));
            }

            $this->document->update(['status' => DocumentStatus::Ready]);
            $job->update(['status' => 'done', 'tokens_in' => $structurer->tokensIn, 'tokens_out' => $structurer->tokensOut]);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error' => $e->getMessage(), 'tokens_in' => $structurer->tokensIn, 'tokens_out' => $structurer->tokensOut]);
            $this->document->update(['status' => DocumentStatus::Draft]);
            $this->fail($e);
        }
    }
}
