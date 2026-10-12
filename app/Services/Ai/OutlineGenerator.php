<?php

namespace App\Services\Ai;

use App\Models\Document;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class OutlineGenerator
{
    public const MAX_CHAPTERS = 10;

    public const MAX_SECTIONS = 6;

    public int $tokensIn = 0;

    public int $tokensOut = 0;

    /**
     * Draft chapters and sections (titles only, empty blocks). Returns the schema chapters and each section's note.
     *
     * @return array{chapters: list<array<string, mixed>>, notes: array<string, string>}
     */
    public function generate(Document $document): array
    {
        $content = $document->content;
        $brief = $content['brief'] ?? [];

        $prompt = 'Jenis dokumen: '.$document->type->label()."\n"
            .'Jumlah bab yang diminta: '.($brief['chapter_count'] ?? 5)."\n"
            .'Gaya bahasa: '.($content['style'] ?? 'formal')."\n"
            .'Topik: '.($brief['topic'] ?? '')."\n"
            .'Instruksi tambahan: '.($brief['instructions'] ?? '')."\n"
            .'Data pokok (hanya ini yang boleh dipakai): '.json_encode($brief['facts'] ?? [], JSON_UNESCAPED_UNICODE);

        $structured = $this->ask($prompt);

        $chapters = [];
        $notes = [];

        foreach (array_slice((array) ($structured['chapters'] ?? []), 0, self::MAX_CHAPTERS) as $chapter) {
            $title = $this->title($chapter['title'] ?? '');

            if ($title === '') {
                continue;
            }

            $sections = [];

            foreach (array_slice((array) ($chapter['sections'] ?? []), 0, self::MAX_SECTIONS) as $section) {
                $sectionTitle = $this->title($section['title'] ?? '');

                if ($sectionTitle === '') {
                    continue;
                }

                $id = (string) Str::uuid();
                $sections[] = ['id' => $id, 'title' => $sectionTitle, 'blocks' => []];
                $notes[$id] = trim((string) ($section['note'] ?? ''));
            }

            $chapters[] = ['id' => (string) Str::uuid(), 'title' => $title, 'sections' => $sections];
        }

        if ($chapters === []) {
            throw new RuntimeException('Model tidak menghasilkan outline yang bisa dipakai.');
        }

        return ['chapters' => $chapters, 'notes' => $notes];
    }

    /**
     * One retry on failure, then the error propagates to the job.
     *
     * @return array<string, mixed>
     */
    private function ask(string $prompt): array
    {
        $attempt = 0;

        while (true) {
            try {
                $response = (new OutlineAgent)->prompt($prompt, provider: Lab::OpenRouter, model: config('ai.models.outline'));
                break;
            } catch (\Throwable $e) {
                if (++$attempt > 1) {
                    throw $e;
                }
            }
        }

        $this->tokensIn += $response->usage->inputTokens;
        $this->tokensOut += $response->usage->outputTokens;

        return $response instanceof StructuredAgentResponse ? $response->structured : [];
    }

    /** Strip manual numbering the schema forbids, and cap the length. */
    private function title(string $raw): string
    {
        return Str::limit(trim((string) preg_replace(Structurer::HEADING_NUMBER, '', trim($raw))), 150, '');
    }
}
