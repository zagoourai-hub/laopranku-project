<?php

namespace App\Services\Documents;

/**
 * Turns bibliography entries into one line each. The user writes the entry text (author, year, title, ...);
 * this class only orders it and appends the link, it does not parse or rewrite the author/title parts.
 */
class CitationFormatter
{
    public const DEFAULT_STYLE = 'apa';

    public const STYLES = ['apa', 'ieee', 'harvard'];

    /**
     * @param  list<array{text: string, url?: ?string, doi?: ?string}>  $entries
     * @return list<string> lines in display order
     */
    public function format(array $entries, string $style = self::DEFAULT_STYLE): array
    {
        $lines = array_map(fn (array $entry): string => $this->withLink($entry), $entries);

        if ($style === 'ieee') {
            // ponytail: IEEE numbers by the order the user listed sources; no in-text citation tracking yet, add when 3.2 writes [n] markers.
            return array_map(fn (string $line, int $index): string => '['.($index + 1).'] '.$line, $lines, array_keys($lines));
        }

        usort($lines, fn (string $a, string $b): int => strcmp(mb_strtolower($a), mb_strtolower($b)));

        return $lines;
    }

    /**
     * @param  array{text: string, url?: ?string, doi?: ?string}  $entry
     */
    private function withLink(array $entry): string
    {
        $text = trim($entry['text']);

        if (! empty($entry['doi'])) {
            $link = str_starts_with($entry['doi'], 'http') ? $entry['doi'] : 'https://doi.org/'.$entry['doi'];

            return str_contains($text, $link) || str_contains($text, $entry['doi']) ? $text : $text.' '.$link;
        }

        if (! empty($entry['url']) && ! str_contains($text, $entry['url'])) {
            return $text.' '.$entry['url'];
        }

        return $text;
    }
}
