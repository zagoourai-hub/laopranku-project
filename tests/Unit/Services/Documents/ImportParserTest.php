<?php

use App\Services\Documents\ImportParser;
use App\Services\Documents\SchemaValidator;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

uses(TestCase::class);

function importFile(string $name, string $content): array
{
    return app(ImportParser::class)->parse(UploadedFile::fake()->createWithContent($name, $content));
}

/** Wrap parsed chapters as a full document so the real schema and unique-id check judge them. */
function expectValidChapters(array $chapters): void
{
    $document = ['schema_version' => 1, 'type' => 'makalah', 'cover' => ['title' => 'Uji'], 'style' => 'formal', 'chapters' => $chapters];

    expect((new SchemaValidator)->validate($document))->toBe([]);
}

function docxWith(callable $build): string
{
    $word = new PhpWord;
    $word->addTitleStyle(1, ['bold' => true]);
    $word->addTitleStyle(2, ['bold' => true]);
    $word->addNumberingStyle('num', ['type' => 'multilevel', 'levels' => [['format' => 'decimal', 'text' => '%1.', 'left' => 360, 'hanging' => 360]]]);
    $word->addNumberingStyle('bul', ['type' => 'multilevel', 'levels' => [['format' => 'bullet', 'text' => '•', 'left' => 360, 'hanging' => 360]]]);
    $build($word->addSection());

    $path = tempnam(sys_get_temp_dir(), 'docx');
    IOFactory::createWriter($word, 'Word2007')->save($path);
    $bytes = file_get_contents($path);
    unlink($path);

    return $bytes;
}

test('markdown headings become chapters and sections with paragraphs', function () {
    $result = importFile('a.md', "# Pendahuluan\n\n## Latar Belakang\n\nKalimat satu\nlanjut.\n\nParagraf dua.\n\n## Tujuan\n\nTujuan utama.\n\n# Penutup\n\nSelesai.");

    expect($result)->toHaveKey('chapters');

    $chapters = $result['chapters'];
    expect(array_column($chapters, 'title'))->toBe(['Pendahuluan', 'Penutup'])
        ->and(array_column($chapters[0]['sections'], 'title'))->toBe(['Latar Belakang', 'Tujuan'])
        ->and(array_column($chapters[0]['sections'][0]['blocks'], 'text'))->toBe(['Kalimat satu lanjut.', 'Paragraf dua.'])
        // Text straight under a chapter gets one section named after the chapter.
        ->and($chapters[1]['sections'][0]['title'])->toBe('Penutup');

    expectValidChapters($chapters);
});

test('a single top heading is the document title and is skipped', function () {
    $result = importFile('a.md', "# Judul Makalah\n\n## Pendahuluan\n\nIsi.\n\n### Latar\n\nIsi latar.\n\n## Penutup\n\nSelesai.");

    expect(array_column($result['chapters'], 'title'))->toBe(['Pendahuluan', 'Penutup'])
        ->and(array_column($result['chapters'][0]['sections'], 'title'))->toBe(['Pendahuluan', 'Latar']);

    expectValidChapters($result['chapters']);
});

test('headings deeper than the section level stay as paragraph text', function () {
    $result = importFile('a.md', "# Bab\n\n## Sub\n\n#### Bagian dalam\n\nIsi.");

    expect(array_column($result['chapters'][0]['sections'][0]['blocks'], 'text'))->toBe(['Bagian dalam', 'Isi.']);
});

test('markdown without headings falls back to paragraphs', function () {
    expect(importFile('a.md', "Satu.\n\nDua."))->toBe(['paragraphs' => [['id' => 'p1', 'text' => 'Satu.'], ['id' => 'p2', 'text' => 'Dua.']]]);
});

test('markdown with text before the first heading falls back to paragraphs', function () {
    $result = importFile('a.md', "Kata pengantar.\n\n# Bab\n\nIsi.");

    expect($result)->toHaveKey('paragraphs')->and(array_column($result['paragraphs'], 'text'))->toBe(['Kata pengantar.', '# Bab', 'Isi.']);
});

test('manual numbers in headings are removed so the schema accepts them', function () {
    $result = importFile('a.md', "# BAB I. Pendahuluan\n\n## 1.1 Latar Belakang\n\nIsi.\n\n# 2. Pembahasan\n\n## 2) Hasil\n\nIsi.\n\n# 2024 dalam Angka\n\nIsi.");

    expect(array_column($result['chapters'], 'title'))->toBe(['Pendahuluan', 'Pembahasan', '2024 dalam Angka'])
        ->and($result['chapters'][0]['sections'][0]['title'])->toBe('Latar Belakang');

    expectValidChapters($result['chapters']);
});

test('markdown lists become list blocks, ordered or not', function () {
    $blocks = importFile('a.md', "# Bab\n\n## Sub\n\nPengantar:\n\n- Hiburan\n- Komunikasi\n1. Satu\n2) Dua\n\nPenutup.")['chapters'][0]['sections'][0]['blocks'];

    expect(array_column($blocks, 'type'))->toBe(['paragraph', 'list', 'list', 'paragraph'])
        ->and($blocks[1])->toMatchArray(['ordered' => false, 'items' => ['Hiburan', 'Komunikasi']])
        ->and($blocks[2])->toMatchArray(['ordered' => true, 'items' => ['Satu', 'Dua']]);

    expectValidChapters(importFile('a.md', "# Bab\n\n## Sub\n\n- a\n1. b")['chapters']);
});

test('txt always goes the paragraph route, even when it looks like markdown', function () {
    expect(array_column(importFile('a.txt', "# Bukan heading\n\nIsi.")['paragraphs'], 'text'))->toBe(['# Bukan heading', 'Isi.']);
});

test('docx Heading styles become chapters, sections, paragraphs and lists', function () {
    $bytes = docxWith(function ($section) {
        $section->addTitle('Pendahuluan', 1);
        $section->addTitle('1.1 Latar Belakang', 2);
        $section->addText('Paragraf satu.');
        $section->addListItem('Satu', 0, null, 'num');
        $section->addListItem('Dua', 0, null, 'num');
        $section->addListItem('Poin', 0, null, 'bul');
        $section->addText('Penutup sub.');
        $section->addTitle('Penutup', 1);
        $section->addText('Selesai.');
    });

    $chapters = importFile('a.docx', $bytes)['chapters'];

    expect(array_column($chapters, 'title'))->toBe(['Pendahuluan', 'Penutup'])
        ->and($chapters[0]['sections'][0]['title'])->toBe('Latar Belakang');

    $blocks = $chapters[0]['sections'][0]['blocks'];
    expect(array_column($blocks, 'type'))->toBe(['paragraph', 'list', 'list', 'paragraph'])
        ->and($blocks[1])->toMatchArray(['ordered' => true, 'items' => ['Satu', 'Dua']])
        ->and($blocks[2])->toMatchArray(['ordered' => false, 'items' => ['Poin']])
        ->and($chapters[1]['sections'][0]['blocks'][0]['text'])->toBe('Selesai.');

    expectValidChapters($chapters);
});

test('docx without headings falls back to paragraphs', function () {
    $bytes = docxWith(function ($section) {
        $section->addText('Satu.');
        $section->addText('Dua.');
    });

    expect(array_column(importFile('a.docx', $bytes)['paragraphs'], 'text'))->toBe(['Satu.', 'Dua.']);
});
