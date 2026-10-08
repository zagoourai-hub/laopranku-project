<?php

namespace App\Services\Ai;

use App\Enums\DocumentType;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class Structurer
{
    /** Leading manual numbering the schema forbids in headings: "BAB II", "1.2", "3.", "4)". */
    private const HEADING_NUMBER = '/^\s*(?:BAB\s+(?:[IVXLC]+|\d+)\b\s*[.:\-–]?|BAB\b\s*[.:\-–]?|\d+(?:\.\d+)+\.?|\d+[.)])\s*/iu';

    /** List markers the builder renders itself. */
    private const LIST_MARKER = '/^\s*(?:[-–•*·]|\d+[.)]|[a-z][.)])\s+/u';

    /** "drop" is only honoured for leftovers with no words, so no sentence can disappear. */
    private const DROPPABLE = '/^[\s\pP\pS\d]*$|^\s*[IVXLC]+\.?\s*$/u';

    public const PLACEHOLDER = '[lengkapi judul]';

    public int $tokensIn = 0;

    public int $tokensOut = 0;

    /**
     * Turn normalized paragraphs into schema chapters. The model only labels ids; every text comes from the input.
     *
     * @param  list<array{id: string, text: string}>  $paragraphs
     * @return list<array<string, mixed>>
     */
    public function structure(array $paragraphs, DocumentType $type): array
    {
        $labels = [];
        $lastHeading = null;

        foreach ($this->chunks($paragraphs) as $chunk) {
            $chunkLabels = $this->label($chunk, $type, $lastHeading);
            $labels += $chunkLabels;

            foreach ($chunk as $paragraph) {
                if (in_array($chunkLabels[$paragraph['id']] ?? null, ['chapter', 'section'], true)) {
                    $lastHeading = ($chunkLabels[$paragraph['id']] === 'chapter' ? 'bab' : 'subbab').': '.$paragraph['text'];
                }
            }
        }

        return $this->assemble($paragraphs, $labels);
    }

    /**
     * @param  list<array{id: string, text: string}>  $paragraphs
     * @return list<list<array{id: string, text: string}>>
     */
    private function chunks(array $paragraphs): array
    {
        $limit = max(1, (int) config('ai.structure_chunk_words', 1500));
        $chunks = [];
        $current = [];
        $words = 0;

        foreach ($paragraphs as $paragraph) {
            $count = str_word_count($paragraph['text']) ?: 1;

            if ($current !== [] && $words + $count > $limit) {
                $chunks[] = $current;
                $current = [];
                $words = 0;
            }

            $current[] = $paragraph;
            $words += $count;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Ask the model for labels; one retry on failure, then the error propagates to the job.
     *
     * @param  list<array{id: string, text: string}>  $chunk
     * @return array<string, string> id => label, only for ids that exist in this chunk
     */
    private function label(array $chunk, DocumentType $type, ?string $lastHeading): array
    {
        $prompt = "Jenis dokumen: {$type->label()}\n"
            .'Judul terakhir sebelum bagian ini: '.($lastHeading ?? '(belum ada)')."\n\nParagraf:\n"
            .collect($chunk)->map(fn (array $p): string => $p['id'].': '.Str::limit($p['text'], 300))->implode("\n");

        $attempt = 0;

        while (true) {
            try {
                $response = (new ParagraphLabeler)->prompt(
                    $prompt,
                    provider: Lab::OpenRouter,
                    model: config('ai.models.structure'),
                );
                break;
            } catch (Throwable $e) {
                if (++$attempt > 1) {
                    throw $e;
                }
            }
        }

        $this->tokensIn += $response->usage->inputTokens;
        $this->tokensOut += $response->usage->outputTokens;

        $ids = array_column($chunk, 'id');
        $labels = [];

        $structured = $response instanceof StructuredAgentResponse ? $response->structured : [];

        foreach ((array) ($structured['labels'] ?? []) as $item) {
            $id = $item['id'] ?? null;

            // Unknown ids are ignored and the first label wins on duplicates.
            if (in_array($id, $ids, true) && ! isset($labels[$id]) && in_array($item['label'] ?? null, ParagraphLabeler::LABELS, true)) {
                $labels[$id] = $item['label'];
            }
        }

        return $labels;
    }

    /**
     * @param  list<array{id: string, text: string}>  $paragraphs
     * @param  array<string, string>  $labels
     * @return list<array<string, mixed>>
     */
    private function assemble(array $paragraphs, array $labels): array
    {
        /** @var list<array{id: string, title: string, sections: list<array{id: string, title: string, blocks: list<array<string, mixed>>}>}> $chapters */
        $chapters = [];

        foreach ($paragraphs as $paragraph) {
            $text = trim($paragraph['text']);
            $label = $labels[$paragraph['id']] ?? 'paragraph';
            $title = '';

            if ($label === 'drop') {
                if (preg_match(self::DROPPABLE, $text)) {
                    continue;
                }

                $label = 'paragraph';
            }

            if ($label === 'chapter' || $label === 'section') {
                $title = trim((string) preg_replace(self::HEADING_NUMBER, '', $text));

                if ($title === '') {
                    continue; // the heading was only a number
                }

                if ($label === 'chapter') {
                    $chapters[] = ['id' => (string) Str::uuid(), 'title' => $title, 'sections' => []];

                    continue;
                }
            }

            if ($chapters === []) {
                $chapters[] = ['id' => (string) Str::uuid(), 'title' => self::PLACEHOLDER, 'sections' => []];
            }

            $c = array_key_last($chapters);

            if ($label === 'section') {
                $chapters[$c]['sections'][] = ['id' => (string) Str::uuid(), 'title' => $title, 'blocks' => []];

                continue;
            }

            if ($chapters[$c]['sections'] === []) {
                $chapters[$c]['sections'][] = ['id' => (string) Str::uuid(), 'title' => self::PLACEHOLDER, 'blocks' => []];
            }

            $blocks = &$chapters[$c]['sections'][array_key_last($chapters[$c]['sections'])]['blocks'];
            $last = $blocks === [] ? null : $blocks[array_key_last($blocks)];

            if ($label === 'bullet' || $label === 'numbered') {
                $item = trim((string) preg_replace(self::LIST_MARKER, '', $text)) ?: $text;
                $ordered = $label === 'numbered';

                if (($last['type'] ?? null) === 'list' && ($last['ordered'] ?? null) === $ordered) {
                    $blocks[array_key_last($blocks)]['items'][] = $item;
                } else {
                    $blocks[] = ['id' => (string) Str::uuid(), 'type' => 'list', 'ordered' => $ordered, 'items' => [$item]];
                }
            } else {
                $blocks[] = ['id' => (string) Str::uuid(), 'type' => 'paragraph', 'text' => $text];
            }

            unset($blocks);
        }

        return $chapters;
    }
}
