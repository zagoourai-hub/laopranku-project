<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Ai\OutlineGenerator;
use App\Services\Documents\ContentUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Draft the outline of a topic-based document. Titles only; the body is written after the user approves it.
 */
class GenerateOutline implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Document $document) {}

    public function handle(OutlineGenerator $generator, ContentUpdater $updater): void
    {
        $job = $this->document->jobs()->create(['status' => 'running']);
        $this->document->update(['status' => DocumentStatus::Outlining]);

        try {
            $outline = $generator->generate($this->document);

            $content = $this->document->content;
            $content['chapters'] = $outline['chapters'];
            if ($outline['notes'] !== []) {
                $content['brief']['section_notes'] = $outline['notes'];
            }

            if (($errors = $updater->apply($this->document, $content)) !== []) {
                throw new RuntimeException('Outline tidak valid: '.implode('; ', $errors));
            }

            $this->document->update(['status' => DocumentStatus::OutlineReady]);
            $job->update(['status' => 'done', 'tokens_in' => $generator->tokensIn, 'tokens_out' => $generator->tokensOut]);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error' => $e->getMessage(), 'tokens_in' => $generator->tokensIn, 'tokens_out' => $generator->tokensOut]);
            $this->document->update(['status' => DocumentStatus::Draft]);
            $this->fail($e);
        }
    }
}
