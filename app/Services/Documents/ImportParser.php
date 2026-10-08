<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Numbering;

/**
 * Reads .md, .txt and .docx into a flat token list, then maps headings to chapters/sections without AI.
 * Tokens: ['h', level, text] | ['t', text] | ['li', ordered, text] | ['br'].
 */
class ImportParser
{
    /** Manual numbering the schema forbids in headings: "BAB I.", "1.2 ", "3. ", "4) ". */
    private const HEADING_NUMBER = '/^\s*(?:BAB\s+(?:[IVXLC]+|\d+)\b\s*[.:\-–]?|\d+(?:\.\d+)+\.?|\d+[.)])\s+/iu';

    public function __construct(private TextNormalizer $normalizer) {}

    /**
     * Headings map straight to chapters. Without usable headings the text comes back as p1..pn paragraphs
     * for the Structurer (AI) path.
     *
     * @return array{chapters: list<array<string, mixed>>}|array{paragraphs: list<array{id: string, text: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'docx') {
            $tokens = $this->docxTokens((string) $file->getRealPath());
            $raw = implode("\n\n", array_map(fn (array $t) => end($t), array_filter($tokens, fn (array $t) => $t[0] !== 'br')));
        } else {
            $raw = (string) file_get_contents($file->getRealPath());
            $tokens = $extension === 'md' ? $this->markdownTokens($raw) : [];
        }

        $chapters = $this->chapters($tokens);

        return $chapters === null
            ? ['paragraphs' => $this->normalizer->normalize($raw)]
            : ['chapters' => $chapters];
    }

    /**
     * @return list<array<string, mixed>>|null null when there are no headings or text sits before the first one
     */
    public function parseMarkdown(string $markdown): ?array
    {
        return $this->chapters($this->markdownTokens($markdown));
    }

    /**
     * @return list<array<mixed>>
     */
    private function markdownTokens(string $markdown): array
    {
        $tokens = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown)) as $line) {
            $line = trim($line);

            if (preg_match('/^(#{1,6})\s+(.+?)(?:\s+#+)?$/u', $line, $m)) {
                $tokens[] = ['h', strlen($m[1]), $m[2]];
            } elseif ($line === '') {
                $tokens[] = ['br'];
            } elseif (preg_match('/^(?:([-*+])|\d+[.)])\s+(.+)$/u', $line, $m)) {
                $tokens[] = ['li', $m[1] === '', $m[2]];
            } else {
                $tokens[] = ['t', $line];
            }
        }

        return $tokens;
    }

    /**
     * Word's Title style (depth 0) is the document title and is dropped; Heading N becomes level N.
     *
     * @return list<array<mixed>>
     */
    private function docxTokens(string $path): array
    {
        $tokens = [];

        foreach (IOFactory::load($path)->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = trim($this->plain($element));

                if ($text === '') {
                    continue;
                }

                if ($element instanceof Title) {
                    if ($element->getDepth() > 0) {
                        $tokens[] = ['h', $element->getDepth(), $text];
                    }
                } elseif ($element instanceof ListItemRun) {
                    $numbering = Style::getStyle($element->getStyle()->getNumStyle());
                    $level = $numbering instanceof Numbering ? ($numbering->getLevels()[$element->getDepth()] ?? null) : null;
                    $tokens[] = ['li', $level?->getFormat() !== 'bullet', $text];
                } else {
                    $tokens[] = ['t', $text];
                    $tokens[] = ['br'];
                }
            }
        }

        return $tokens;
    }

    private function plain(mixed $element): string
    {
        return match (true) {
            is_string($element) => $element,
            $element instanceof AbstractContainer => implode('', array_map($this->plain(...), $element->getElements())),
            method_exists($element, 'getText') => $this->plain($element->getText()),
            default => '',
        };
    }

    /**
     * The shallowest heading level is the chapter level, the next one the section level; deeper headings
     * stay as paragraph text. A single top heading with deeper ones below is the document title and is skipped.
     * Text before the first chapter has no honest home, so it returns null.
     *
     * @param  list<array<mixed>>  $tokens
     * @return list<array<string, mixed>>|null
     */
    private function chapters(array $tokens): ?array
    {
        $levels = array_column(array_filter($tokens, fn (array $t) => $t[0] === 'h'), 1);

        if ($levels === []) {
            return null;
        }

        $top = min($levels);

        if (count($levels) > 1 && count(array_keys($levels, $top)) === 1) {
            $top++;
        }

        /** @var list<array{id: string, title: string, sections: list<array{id: string, title: string, blocks: list<array<string, mixed>>}>}> $chapters */
        $chapters = [];
        $body = [];
        $flush = function () use (&$chapters, &$body): void {
            if (($blocks = $this->blocks($body)) !== []) {
                $chapter = &$chapters[array_key_last($chapters)];

                if ($chapter['sections'] === []) {
                    $chapter['sections'][] = ['id' => (string) Str::uuid(), 'title' => $chapter['title'], 'blocks' => []];
                }

                $section = &$chapter['sections'][array_key_last($chapter['sections'])];
                $section['blocks'] = [...$section['blocks'], ...$blocks];
            }

            $body = [];
        };

        foreach ($tokens as $token) {
            if ($token[0] !== 'h') {
                if ($chapters === []) {
                    if ($token[0] !== 'br') {
                        return null;
                    }

                    continue;
                }

                $body[] = $token;

                continue;
            }

            [, $level, $text] = $token;

            if ($level > $top + 1) {
                if ($chapters === []) {
                    return null;
                }

                $body[] = ['t', $text];
                $body[] = ['br'];

                continue;
            }

            $title = trim((string) preg_replace(self::HEADING_NUMBER, '', $text));

            if ($level < $top || $title === '') {
                continue;
            }

            if ($level > $top && $chapters === []) {
                return null;
            }

            $flush();
            $node = ['id' => (string) Str::uuid(), 'title' => $title];

            if ($level === $top) {
                $chapters[] = $node + ['sections' => []];
            } else {
                $chapters[array_key_last($chapters)]['sections'][] = $node + ['blocks' => []];
            }
        }

        $flush();

        return $chapters;
    }

    /**
     * @param  list<array<mixed>>  $tokens
     * @return list<array<string, mixed>>
     */
    private function blocks(array $tokens): array
    {
        $blocks = [];
        $paragraph = [];
        $list = null;

        foreach ($tokens as $token) {
            $closes = match ($token[0]) {
                'br' => true,
                'li' => $paragraph !== [] || ($list !== null && $list['ordered'] !== $token[1]),
                default => $list !== null,
            };

            if ($closes) {
                $blocks = [...$blocks, ...$this->run($paragraph, $list)];
                $paragraph = [];
                $list = null;
            }

            if ($token[0] === 'li') {
                $list ??= ['ordered' => $token[1], 'items' => []];
                $list['items'][] = $token[2];
            } elseif ($token[0] === 't') {
                $paragraph[] = $token[1];
            }
        }

        return [...$blocks, ...$this->run($paragraph, $list)];
    }

    /**
     * The block for a finished run of text lines or list items (at most one of the two is filled).
     *
     * @param  list<string>  $paragraph
     * @param  array{ordered: bool, items: list<string>}|null  $list
     * @return list<array<string, mixed>>
     */
    private function run(array $paragraph, ?array $list): array
    {
        return match (true) {
            $paragraph !== [] => [['id' => (string) Str::uuid(), 'type' => 'paragraph', 'text' => implode(' ', $paragraph)]],
            $list !== null => [['id' => (string) Str::uuid(), 'type' => 'list', ...$list]],
            default => [],
        };
    }
}
