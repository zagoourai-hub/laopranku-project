<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\EducationLevel;

/**
 * The one place that decides what a document contains and in what order: cover, front matter,
 * chapters with their numbers, bibliography, appendices, and the table of contents.
 *
 * DocxBuilder (the real output) and the A4 preview both render this plan, so they cannot drift apart.
 * It holds no formatting: page size, fonts, and page breaks stay with each renderer.
 */
class DocumentLayout
{
    private const BLANK = '..............................';

    public function __construct(private CitationFormatter $citations) {}

    /**
     * @param  array<string, mixed>  $content  document-v1 content
     *                                         Shape (every heading also carries an `id`): cover, front, approval, abstract, preface, chapters,
     *                                         references, appendices, toc. Renderers read these keys directly.
     * @return array<string, mixed>
     *                              Full shape: array{
     *                              cover: array{label: string, title: string, name: ?string, nim: ?string, foot: list<string>},
     *                              front: list<array{key: string, title: string}>,
     *                              approval: array{sentence: string, lines: list<array{label: string, value: string}>}|null,
     *                              abstract: string|null,
     *                              preface: array{title: string, text: string, signature: string},
     *                              chapters: list<array{index: int, label: string, title: string, heading: string, sections: list<array{number: string, title: string, blocks: list<array<string, mixed>>}>}>,
     *                              references: array{title: string, entries: list<string>},
     *                              appendices: array{title: string, items: list<array{title: string, lines: list<string>}>}|null,
     *                              toc: list<array{level: int, text: string}>
     *                              }
     */
    public function plan(array $content, EducationLevel $level): array
    {
        $type = DocumentType::from($content['type']);
        $cover = $content['cover'] ?? [];
        $title = (string) ($cover['title'] ?? '');
        $options = $content['options'] ?? [];
        $label = $type->coverLabel();

        $plan = [
            'cover' => [
                'label' => $label,
                'title' => mb_strtoupper($title),
                'name' => $cover['name'] ?? null,
                'nim' => $cover['nim'] ?? null,
                'foot' => array_map(mb_strtoupper(...), $level->cover($cover)['foot']),
            ],
            'front' => [],
            'approval' => null,
            'abstract' => null,
            'preface' => [
                'title' => 'KATA PENGANTAR',
                'text' => 'Puji syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa atas terselesaikannya '.mb_strtolower($label).' berjudul "'.$title.'" ini.',
                'signature' => 'Penulis',
            ],
            'chapters' => [],
            'references' => ['title' => 'DAFTAR PUSTAKA', 'entries' => []],
            'appendices' => null,
            'toc' => [],
        ];

        if ($options['approval_page'] ?? false) {
            $plan['approval'] = [
                'sentence' => mb_strtolower($label).' berjudul "'.$title.'" ini telah diperiksa dan disetujui.',
                'lines' => [
                    ['label' => 'Nama', 'value' => $cover['name'] ?? self::BLANK],
                    ['label' => $level->coverLabels()['nim'], 'value' => $cover['nim'] ?? self::BLANK],
                    ['label' => 'Tanda tangan', 'value' => self::BLANK],
                    ['label' => 'Tanggal', 'value' => self::BLANK],
                ],
            ];
        }

        if ($options['abstract'] ?? false) {
            $plan['abstract'] = ($content['abstract'] ?? '') !== '' ? $content['abstract'] : '[Tambahkan abstrak]';
        }

        foreach ($content['chapters'] ?? [] as $index => $chapter) {
            $number = $index + 1;
            $roman = $this->roman($number);
            $plan['chapters'][] = [
                'index' => $index,
                'label' => 'BAB '.$roman,
                'title' => mb_strtoupper($chapter['title']),
                'heading' => 'BAB '.$roman.' '.mb_strtoupper($chapter['title']),
                'sections' => array_map(fn (array $section, int $s): array => [
                    'number' => $number.'.'.($s + 1),
                    'title' => $section['title'],
                    'blocks' => $section['blocks'],
                ], $chapter['sections'], range(0, count($chapter['sections']) - 1)),
            ];
        }

        $entries = array_values([...($content['references'] ?? []), ...($content['sources'] ?? [])]);
        if ($entries !== []) {
            $plan['references']['entries'] = $this->citations->format($entries, $content['style_citation'] ?? CitationFormatter::DEFAULT_STYLE);
        }

        if ($options['appendices'] ?? false) {
            $plan['appendices'] = [
                'title' => 'LAMPIRAN',
                'items' => array_map(fn (array $appendix): array => [
                    'title' => $appendix['title'],
                    'lines' => array_values(array_filter(array_map('trim', explode("\n", $appendix['text'])), fn (string $line): bool => $line !== '')),
                ], $content['appendices'] ?? []),
            ];
        }

        $plan['front'] = $this->frontOrder($plan);
        $this->assignIds($plan);
        $plan['toc'] = $this->toc($plan);

        return $plan;
    }

    /**
     * Every heading gets a stable id in document order. The preview links its table of contents to these
     * ids, and paged.js reads the page number from the link target; DOCX ignores them.
     *
     * @param  array<string, mixed>  $plan
     */
    private function assignIds(array &$plan): void
    {
        $n = 0;
        $next = function () use (&$n): string {
            return 'h'.(++$n);
        };

        foreach ($plan['front'] as $i => $item) {
            $plan['front'][$i]['id'] = $next();
        }

        foreach ($plan['chapters'] as $c => $chapter) {
            $plan['chapters'][$c]['id'] = $next();

            foreach ($chapter['sections'] as $s => $section) {
                $plan['chapters'][$c]['sections'][$s]['id'] = $next();
            }
        }

        $plan['references']['id'] = $next();

        if ($plan['appendices'] !== null) {
            $plan['appendices']['id'] = $next();

            foreach ($plan['appendices']['items'] as $i => $appendix) {
                $plan['appendices']['items'][$i]['id'] = $next();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array{key: string, title: string}>
     */
    private function frontOrder(array $plan): array
    {
        $front = [];

        if ($plan['approval'] !== null) {
            $front[] = ['key' => 'approval', 'title' => 'LEMBAR PENGESAHAN'];
        }

        if ($plan['abstract'] !== null) {
            $front[] = ['key' => 'abstract', 'title' => 'ABSTRAK'];
        }

        $front[] = ['key' => 'preface', 'title' => $plan['preface']['title']];
        $front[] = ['key' => 'toc', 'title' => 'DAFTAR ISI'];

        return $front;
    }

    /**
     * What the table of contents lists, in document order. Renderers that cannot build a real TOC field
     * (the preview) show this list; the DOCX builds its field from the same headings.
     *
     * @param  array<string, mixed>  $plan
     * @return list<array{level: int, text: string}>
     */
    private function toc(array $plan): array
    {
        $toc = [];

        foreach ($plan['front'] as $item) {
            $toc[] = ['level' => 1, 'text' => $item['title'], 'id' => $item['id'], 'front' => true];
        }

        foreach ($plan['chapters'] as $chapter) {
            $toc[] = ['level' => 1, 'text' => $chapter['heading'], 'id' => $chapter['id']];

            foreach ($chapter['sections'] as $section) {
                $toc[] = ['level' => 2, 'text' => $section['number'].' '.$section['title'], 'id' => $section['id']];
            }
        }

        $toc[] = ['level' => 1, 'text' => $plan['references']['title'], 'id' => $plan['references']['id']];

        if ($plan['appendices'] !== null) {
            $toc[] = ['level' => 1, 'text' => $plan['appendices']['title'], 'id' => $plan['appendices']['id']];

            foreach ($plan['appendices']['items'] as $appendix) {
                $toc[] = ['level' => 2, 'text' => $appendix['title'], 'id' => $appendix['id']];
            }
        }

        return $toc;
    }

    private function roman(int $number): string
    {
        $out = '';

        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $symbol => $value) {
            while ($number >= $value) {
                $out .= $symbol;
                $number -= $value;
            }
        }

        return $out;
    }
}
