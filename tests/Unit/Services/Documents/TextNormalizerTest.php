<?php

use App\Services\Documents\TextNormalizer;

function normalizedTexts(string $raw): array
{
    return array_column((new TextNormalizer)->normalize($raw), 'text');
}

test('returns no paragraphs for empty or blank input', function (string $raw) {
    expect((new TextNormalizer)->normalize($raw))->toBe([]);
})->with(['empty' => [''], 'whitespace only' => ["  \n\t \r\n  "]]);

test('splits on blank lines and numbers paragraphs p1..pn', function () {
    $result = (new TextNormalizer)->normalize("Paragraf satu.\n\nParagraf dua.\n\n\n\nParagraf tiga.");

    expect($result)->toBe([
        ['id' => 'p1', 'text' => 'Paragraf satu.'],
        ['id' => 'p2', 'text' => 'Paragraf dua.'],
        ['id' => 'p3', 'text' => 'Paragraf tiga.'],
    ]);
});

test('joins hard-wrapped lines and collapses repeated spaces', function () {
    expect(normalizedTexts("Kalimat  yang\tterpotong\r\noleh  PDF  copy."))
        ->toBe(['Kalimat yang terpotong oleh PDF copy.']);
});

test('removes invisible copy-paste characters and converts non-breaking spaces', function () {
    expect(normalizedTexts("Ha\u{200B}lo\u{FEFF} da\u{00AD}ri\u{00A0}web"))->toBe(['Halo dari web']);
});

test('strips manual heading numbers but keeps the title', function (string $line, string $expected) {
    expect(normalizedTexts($line."\nIsi paragraf."))->toBe([$expected, 'Isi paragraf.']);
})->with([
    'BAB with arabic number' => ['BAB 1. PENDAHULUAN', 'PENDAHULUAN'],
    'BAB with roman numeral' => ['BAB II: Tinjauan Pustaka', 'Tinjauan Pustaka'],
    'decimal subsection' => ['1.1 Latar Belakang', 'Latar Belakang'],
    'deep decimal subsection' => ['2.3.1. Metode', 'Metode'],
]);

test('drops a heading line that is only a manual number', function () {
    expect(normalizedTexts("BAB I\nPENDAHULUAN"))->toBe(['PENDAHULUAN']);
});

test('keeps text that only looks like a number', function () {
    expect(normalizedTexts('3.5 juta mahasiswa memakai media sosial.'))
        ->toBe(['3.5 juta mahasiswa memakai media sosial.']);
});

test('leaves xml-special characters, emoji and mixed languages untouched', function () {
    $text = 'Tom & Jerry <b>"quoted"</b> 😀 — the quick brown fox melompat.';

    expect(normalizedTexts($text))->toBe([$text]);
});
