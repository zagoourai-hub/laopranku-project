<?php

namespace App\Services\Ai;

use App\Models\Document;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class ChapterWriter
{
    /** Citation shapes the model must not produce without a source: "(Nama, 2010)", "[1]", "Nama (2010)". */
    private const CITATION = '/\([^()]{2,80}\b(?:19|20)\d{2}[a-z]?\)|\[\d+(?:\s*[,–-]\s*\d+)*\]|\b[A-Z][\p{L}\-]+ \((?:19|20)\d{2}[a-z]?\)/u';

    public const UNSOURCED = 'Bab memuat sitasi tanpa sumber';

    public int $tokensIn = 0;

    public int $tokensOut = 0;

    /**
     * Paragraphs per section index for one chapter. Sources are only the ones already in content.sources.
     *
     * @return array<int, list<string>>
     */
    public function write(Document $document, int $chapterIndex): array
    {
        $content = $document->content;
        $chapter = $content['chapters'][$chapterIndex] ?? throw new RuntimeException('Bab tidak ditemukan.');
        $brief = $content['brief'] ?? [];
        $notes = $brief['section_notes'] ?? [];

        $sectionLines = [];

        foreach ($chapter['sections'] as $i => $section) {
            $sectionLines[] = $i.': '.$section['title'].' — '.($notes[$section['id']] ?? '');
        }

        $sources = array_map(fn (array $s): string => '- '.$s['text'], $content['sources'] ?? []);

        $prompt = 'Jenis dokumen: '.$document->type->label()."\n"
            .'Gaya bahasa: '.($content['style'] ?? 'formal')."\n"
            .'Panjang per bab: '.($brief['length'] ?? 'sedang')."\n"
            .'Topik dokumen: '.($brief['topic'] ?? $content['cover']['title'] ?? '')."\n"
            .'Data pokok (hanya ini yang boleh dipakai): '.json_encode($brief['facts'] ?? [], JSON_UNESCAPED_UNICODE)."\n\n"
            .'Bab: '.$chapter['title']."\n"
            ."Subbab (index: judul — catatan):\n".implode("\n", $sectionLines)."\n\n"
            .'SUMBER yang boleh dikutip: '.($sources === [] ? '(kosong, jangan menulis sitasi)' : "\n".implode("\n", $sources));

        $paragraphs = $this->paragraphs($this->ask($prompt), $chapter);

        // Without sources, any citation is invented. Retry once with a stricter instruction, then fail the chapter.
        if ($sources === [] && $this->cites($paragraphs)) {
            $paragraphs = $this->paragraphs($this->ask($prompt."\n\nPENTING: tidak ada sumber. Jangan menulis sitasi apa pun: tanpa (Nama, tahun), tanpa [1], tanpa Nama (tahun)."), $chapter);

            if ($this->cites($paragraphs)) {
                throw new RuntimeException(self::UNSOURCED);
            }
        }

        return $paragraphs;
    }

    /**
     * @param  array<string, mixed>  $structured
     * @param  array<string, mixed>  $chapter
     * @return array<int, list<string>>
     */
    private function paragraphs(array $structured, array $chapter): array
    {
        $result = [];

        foreach ((array) ($structured['sections'] ?? []) as $item) {
            $index = $item['index'] ?? null;

            if (! is_int($index) || ! isset($chapter['sections'][$index]) || isset($result[$index])) {
                continue;
            }

            $paragraphs = array_values(array_filter(array_map(
                fn ($p): string => is_string($p) ? trim($p) : '',
                (array) ($item['paragraphs'] ?? []),
            ), fn (string $p): bool => $p !== ''));

            if ($paragraphs !== []) {
                $result[$index] = $paragraphs;
            }
        }

        return $result;
    }

    /** @param  array<int, list<string>>  $paragraphs */
    private function cites(array $paragraphs): bool
    {
        foreach ($paragraphs as $section) {
            foreach ($section as $text) {
                if (preg_match(self::CITATION, $text) === 1) {
                    return true;
                }
            }
        }

        return false;
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
                $response = (new ChapterAgent)->prompt($prompt, provider: Lab::OpenRouter, model: config('ai.models.write'));
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
}
