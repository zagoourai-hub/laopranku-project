<?php

namespace App\Services\Documents;

class TextNormalizer
{
    /** Manual heading numbers: "BAB I.", "BAB 2 -", "1.1", "2.3.1." (decimal only before a capital letter; "2.5 Juta" is a known false positive). */
    private const MANUAL_NUMBER = '/^\s*(?:BAB\s+(?:[IVXLC]+|\d+)\b\s*[.:\-–]?|\d+(?:\.\d+)+\.?(?=\s+(?-i:\p{Lu})))\s*/iu';

    /**
     * Clean raw pasted text into paragraphs with stable ids p1..pn. Sentence content is never reworded.
     *
     * @return list<array{id: string, text: string}>
     */
    public function normalize(string $raw): array
    {
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $raw);
        $text = preg_replace('/[^\S\n]+/u', ' ', str_replace(["\r\n", "\r"], "\n", $text));
        $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);

        $paragraphs = [];
        /** @var list<string> $current */
        $current = [];

        $flush = function () use (&$paragraphs, &$current): void {
            if ($current !== []) {
                $paragraphs[] = implode(' ', $current);
                $current = [];
            }
        };

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                $flush();

                continue;
            }

            if (preg_match(self::MANUAL_NUMBER, $line)) {
                $flush();
                $line = trim(preg_replace(self::MANUAL_NUMBER, '', $line));

                if ($line !== '') {
                    $paragraphs[] = $line;
                }

                continue;
            }

            $current[] = $line;
        }

        $flush();

        $result = [];

        foreach ($paragraphs as $index => $paragraph) {
            $result[] = ['id' => 'p'.($index + 1), 'text' => $paragraph];
        }

        return $result;
    }
}
