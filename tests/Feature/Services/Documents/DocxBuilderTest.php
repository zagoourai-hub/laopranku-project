<?php

use App\Services\Documents\DocxBuilder;
use App\Services\Documents\Template;

function fixtureDocument(string $name = 'makalah'): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/documents/{$name}.json")), true);
}

function builtDocx(array $document): string
{
    $path = app(DocxBuilder::class)->build($document, Template::fromConfig());
    generatedDocx($path);

    return $path;
}

function generatedDocx(?string $path = null): array
{
    static $paths = [];

    if ($path !== null) {
        $paths[] = $path;
    }

    return $paths;
}

function docxPart(string $docxPath, string $part): string
{
    $zip = new ZipArchive;
    $zip->open($docxPath);
    $xml = (string) $zip->getFromName($part);
    $zip->close();

    return $xml;
}

afterAll(function () {
    array_map('unlink', array_filter(generatedDocx(), 'file_exists'));
});

test('numbers chapters and sections itself, in roman and decimal', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->toContain('BAB I PENDAHULUAN')
        ->toContain('BAB III PENUTUP')
        ->toContain('2.2 Dampak terhadap Prestasi');
});

test('numbers front matter in lower roman starting at i, body in arabic starting at 1', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect(substr_count($xml, 'w:fmt="lowerRoman" w:start="1"'))->toBe(1)
        ->and(substr_count($xml, '<w:pgNumType w:start="1"/>'))->toBe(1);
});

test('writes a real TOC field for heading levels 1 and 2', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->toContain('TOC \o "1-2" \h \z \u')
        ->not->toContain('[[TOC]]');
});

test('gives Heading 1 and Heading 2 an outline level', function () {
    $styles = docxPart(builtDocx(fixtureDocument()), 'word/styles.xml');

    expect($styles)->toMatch('/w:styleId="Heading1".*?<w:outlineLvl w:val="0"\/><\/w:pPr>/s')
        ->toMatch('/w:styleId="Heading2".*?<w:outlineLvl w:val="1"\/><\/w:pPr>/s');
});

test('escapes xml special characters in user text', function () {
    $document = fixtureDocument();
    $document['chapters'][0]['sections'][0]['blocks'][0]['text'] = 'Tom & Jerry <b> "kutip"';

    $xml = docxPart(builtDocx($document), 'word/document.xml');

    expect($xml)->toContain('Tom &amp; Jerry &lt;b&gt;');
});

test('uses A4 page size and whole-number twips', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->toContain('w:w="11906"')
        ->toContain('w:h="16838"')
        ->not->toMatch('/w:(?:w|h|left|right|top|bottom|firstLine|hanging)="\d+\.\d+"/');
});

test('writes hanging indent for references without a conflicting firstLine', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->toMatch('/w:hanging="709"/')
        ->not->toContain('w:firstLine="0" w:hanging');
});

test('sorts references alphabetically', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect(strpos($xml, 'Kaplan'))->toBeLessThan(strpos($xml, 'Kirschner'));
});

test('rejects a document that fails schema validation', function () {
    $document = fixtureDocument();
    $document['chapters'][0]['title'] = 'BAB I Pendahuluan';

    app(DocxBuilder::class)->build($document, Template::fromConfig());
})->throws(InvalidArgumentException::class, 'Invalid document');
