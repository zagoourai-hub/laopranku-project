<?php

use App\Services\Documents\DocxBuilder;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function gotenbergIsReachable(): bool
{
    try {
        return Http::timeout(2)->get(config('documents.gotenberg_url').'/health')->ok()
            && Process::run(['pdftotext', '-v'])->exitCode() !== 127;
    } catch (Throwable) {
        return false;
    }
}

function pdfPageText(string $pdf, int $page): string
{
    return Process::run(['pdftotext', '-layout', '-f', (string) $page, '-l', (string) $page, $pdf, '-'])->output();
}

function pdfPageCount(string $pdf): int
{
    preg_match('/Pages:\s+(\d+)/', Process::run(['pdfinfo', $pdf])->output(), $matches);

    return (int) $matches[1];
}

beforeEach(function () {
    $docx = app(DocxBuilder::class)->build(
        json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
        Template::fromConfig(),
    );
    $this->pdf = app(PdfConverter::class)->convert($docx);
    $this->generatedFiles = [$docx, $this->pdf];
})->skip(fn () => ! gotenbergIsReachable(), 'Gotenberg or pdftotext is not reachable');

afterEach(function () {
    array_map('unlink', array_filter($this->generatedFiles ?? [], 'file_exists'));
});

test('renders cover, front matter, chapters and references on separate pages with no blank page', function () {
    expect(pdfPageCount($this->pdf))->toBe(7);

    foreach (range(1, 7) as $page) {
        expect(trim(pdfPageText($this->pdf, $page)))->not->toBe('');
    }
});

test('puts no page number on the cover', function () {
    $cover = pdfPageText($this->pdf, 1);

    preg_match_all('/^\s*(\d+|[ivx]+)\s*$/mi', $cover, $standalone);

    expect($cover)->toContain('MAKALAH')
        ->and($standalone[1])->toBe(['2026']);
});

test('numbers front matter i and ii, then restarts body at 1 on Bab I', function () {
    expect(pdfPageText($this->pdf, 2))->toMatch('/^\s*i\s*$/m')
        ->and(pdfPageText($this->pdf, 3))->toMatch('/^\s*ii\s*$/m')
        ->and(pdfPageText($this->pdf, 4))->toContain('BAB I PENDAHULUAN')->toMatch('/^\s*1\s*$/m')
        ->and(pdfPageText($this->pdf, 6))->toMatch('/^\s*3\s*$/m');
});

test('fills the table of contents with every level 1 and 2 heading', function () {
    $toc = pdfPageText($this->pdf, 3);

    foreach ([
        'BAB I PENDAHULUAN', '1.1 Latar Belakang', '1.2 Rumusan Masalah',
        'BAB II PEMBAHASAN', '2.1 Pola Penggunaan', '2.2 Dampak terhadap Prestasi',
        'BAB III PENUTUP', '3.1 Kesimpulan', '3.2 Saran', 'DAFTAR PUSTAKA',
    ] as $heading) {
        expect($toc)->toContain($heading);
    }
});
