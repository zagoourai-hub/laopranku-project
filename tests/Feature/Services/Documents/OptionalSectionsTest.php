<?php

use App\Enums\EducationLevel;
use App\Services\Documents\DocxBuilder;
use App\Services\Documents\SchemaValidator;
use App\Services\Documents\Template;

const APPENDIX_ID = '018f0000-0000-4000-8000-0000000000a1';
const SOURCE_ID = '018f0000-0000-4000-8000-0000000000b1';

function withOptions(array $options, array $extra = []): array
{
    return $extra + ['options' => $options] + fixtureDocument();
}

test('leaves approval page, abstract and appendices out unless the user turns them on', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->not->toContain('LEMBAR PENGESAHAN')
        ->not->toContain('ABSTRAK')
        ->not->toContain('LAMPIRAN');
});

test('puts the approval page, then the abstract, before Kata Pengantar', function () {
    $document = withOptions(['approval_page' => true, 'abstract' => true], ['abstract' => 'Penelitian ini membahas pengaruh media sosial.']);
    $xml = docxPart(builtDocx($document), 'word/document.xml');

    expect(strpos($xml, 'LEMBAR PENGESAHAN'))->toBeLessThan(strpos($xml, 'ABSTRAK'))
        ->and(strpos($xml, 'ABSTRAK'))->toBeLessThan(strpos($xml, 'KATA PENGANTAR'))
        ->and($xml)->toContain('Penelitian ini membahas pengaruh media sosial.')
        ->and($xml)->toContain('Budi Santoso')
        ->and($xml)->toContain('NIM: ');
});

test('uses the school number label on the approval page for an SMA document', function () {
    $document = withOptions(['approval_page' => true]);
    $path = app(DocxBuilder::class)->build($document, Template::fromConfig(), EducationLevel::Sma);
    generatedDocx($path);

    expect(docxPart($path, 'word/document.xml'))->toContain('NISN/NIS: 2201234567');
});

test('shows a placeholder, not an invented abstract, when the abstract option is on but empty', function () {
    $xml = docxPart(builtDocx(withOptions(['abstract' => true])), 'word/document.xml');

    expect($xml)->toContain('ABSTRAK')
        ->toContain('[Tambahkan abstrak]');
});

test('puts lampiran after the bibliography, one paragraph per line of appendix text', function () {
    $document = withOptions(['appendices' => true], [
        'appendices' => [['id' => APPENDIX_ID, 'title' => 'Kuesioner penelitian', 'text' => "Pertanyaan satu.\n\nPertanyaan dua."]],
    ]);
    $xml = docxPart(builtDocx($document), 'word/document.xml');

    expect(strpos($xml, 'DAFTAR PUSTAKA'))->toBeLessThan(strpos($xml, 'LAMPIRAN'))
        ->and($xml)->toContain('Kuesioner penelitian')
        ->and($xml)->toContain('Pertanyaan satu.')
        ->and($xml)->toContain('Pertanyaan dua.');
});

test('uses apa by default and numbers entries in ieee when the user picks it', function () {
    $default = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');
    $ieee = docxPart(builtDocx(fixtureDocument() + ['style_citation' => 'ieee']), 'word/document.xml');

    expect($default)->not->toContain('[1] ')
        ->and($ieee)->toContain('[1] ')
        ->and($ieee)->toContain('[2] ');
});

test('includes sources with their url in the bibliography', function () {
    $document = fixtureDocument();
    $document['sources'] = [['id' => SOURCE_ID, 'text' => 'Kementerian Pendidikan. (2024). Panduan.', 'url' => 'https://example.org/panduan', 'doi' => null]];
    $xml = docxPart(builtDocx($document), 'word/document.xml');

    expect($xml)->toContain('Kementerian Pendidikan. (2024). Panduan. https://example.org/panduan');
});

test('keeps the hanging indent on bibliography lines with a link added', function () {
    $document = fixtureDocument();
    $document['sources'] = [['id' => SOURCE_ID, 'text' => 'Situs.', 'url' => 'https://example.org/x', 'doi' => null]];

    expect(docxPart(builtDocx($document), 'word/document.xml'))->toMatch('/w:hanging="709"/');
});

test('schema accepts the new optional fields and rejects bad values', function (array $patch, bool $valid) {
    $document = array_merge(fixtureDocument(), $patch);
    $errors = app(SchemaValidator::class)->validate($document);

    expect($errors === [])->toBe($valid);
})->with([
    'all new fields valid' => [['style_citation' => 'harvard', 'abstract' => 'Ringkasan.', 'options' => ['abstract' => true], 'appendices' => [['id' => APPENDIX_ID, 'title' => 'Data', 'text' => 'Isi.']]], true],
    'abstract longer than 1500 characters' => [['abstract' => str_repeat('a', 1501)], false],
    'unknown citation style' => [['style_citation' => 'mla'], false],
    'unknown option key' => [['options' => ['cover_photo' => true]], false],
    'appendix without text' => [['appendices' => [['id' => APPENDIX_ID, 'title' => 'Data']]], false],
]);
