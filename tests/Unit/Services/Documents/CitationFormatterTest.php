<?php

use App\Services\Documents\CitationFormatter;

test('apa and harvard sort entries alphabetically, ignoring case', function (string $style) {
    $lines = (new CitationFormatter)->format([
        ['text' => 'Kirschner, P. (2006). Buku ajar.'],
        ['text' => 'kaplan, A. (2010). Jurnal.'],
    ], $style);

    expect($lines)->toBe([
        'kaplan, A. (2010). Jurnal.',
        'Kirschner, P. (2006). Buku ajar.',
    ]);
})->with(['apa' => ['apa'], 'harvard' => ['harvard']]);

test('ieee keeps the order the user listed and numbers each entry', function () {
    $lines = (new CitationFormatter)->format([
        ['text' => 'Kirschner, P. (2006). Buku ajar.'],
        ['text' => 'Kaplan, A. (2010). Jurnal.'],
    ], 'ieee');

    expect($lines)->toBe([
        '[1] Kirschner, P. (2006). Buku ajar.',
        '[2] Kaplan, A. (2010). Jurnal.',
    ]);
});

test('appends the doi as a doi.org link when the text does not already hold it', function () {
    $lines = (new CitationFormatter)->format([
        ['text' => 'Kaplan, A. (2010). Jurnal.', 'doi' => '10.1000/xyz'],
    ]);

    expect($lines)->toBe(['Kaplan, A. (2010). Jurnal. https://doi.org/10.1000/xyz']);
});

test('does not repeat a doi the text already contains and keeps a full doi url as written', function () {
    $formatter = new CitationFormatter;

    expect($formatter->format([['text' => 'Kaplan. https://doi.org/10.1000/xyz', 'doi' => '10.1000/xyz']]))
        ->toBe(['Kaplan. https://doi.org/10.1000/xyz'])
        ->and($formatter->format([['text' => 'Kaplan.', 'doi' => 'https://doi.org/10.9/abc']]))
        ->toBe(['Kaplan. https://doi.org/10.9/abc']);
});

test('appends the url of a website entry', function () {
    $lines = (new CitationFormatter)->format([
        ['text' => 'Kementerian Pendidikan. (2024). Panduan penulisan.', 'url' => 'https://example.org/panduan'],
    ], 'harvard');

    expect($lines)->toBe(['Kementerian Pendidikan. (2024). Panduan penulisan. https://example.org/panduan']);
});

test('treats missing or null url and doi as no link', function () {
    expect((new CitationFormatter)->format([['text' => 'Buku.', 'url' => null, 'doi' => null]]))
        ->toBe(['Buku.']);
});
