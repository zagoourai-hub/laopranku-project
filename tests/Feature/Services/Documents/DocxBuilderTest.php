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

/** Count header or footer parts that hold a PAGE field with the given alignment. */
function numberedParts(string $docxPath, string $kind, string $alignment): int
{
    $zip = new ZipArchive;
    $zip->open($docxPath);
    $count = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $xml = str_starts_with($name, "word/{$kind}") ? (string) $zip->getFromName($name) : '';
        $count += (int) (str_contains($xml, 'PAGE') && str_contains($xml, 'w:jc w:val="'.$alignment.'"'));
    }

    $zip->close();

    return $count;
}

afterAll(function () {
    array_map('unlink', array_filter(generatedDocx(), 'file_exists'));
});

test('numbers chapters and sections itself, in roman and decimal', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->toContain('BAB I</w:t></w:r><w:r><w:br/></w:r><w:r><w:rPr/><w:t xml:space="preserve">PENDAHULUAN')
        ->toContain('BAB III</w:t>')
        ->toContain('2.2 Dampak terhadap Prestasi')
        ->not->toMatch('/<\/w:r><w:br\/><w:r>/');
});

test('starts every chapter on a new page with its number bottom-centre, later pages top-right', function () {
    $path = builtDocx(fixtureDocument());
    $xml = docxPart($path, 'word/document.xml');

    // Three chapters plus the references section.
    expect(substr_count($xml, '<w:titlePg/>'))->toBe(4)
        ->and(substr_count($xml, '<w:headerReference w:type="default"'))->toBe(4)
        ->and(substr_count($xml, '<w:footerReference w:type="first"'))->toBe(4)
        ->and(numberedParts($path, 'header', 'end'))->toBe(4)
        ->and(numberedParts($path, 'footer', 'center'))->toBe(5); // front matter + 4 first pages
});

test('keeps headings with the paragraph that follows them', function () {
    $styles = docxPart(builtDocx(fixtureDocument()), 'word/styles.xml');

    expect($styles)->toMatch('/w:styleId="Heading1".*?<w:keepNext w:val="1"\/>/s')
        ->toMatch('/w:styleId="Heading2".*?<w:keepNext w:val="1"\/>/s');
});

test('lists Daftar Isi itself in the TOC and styles entries with a dot leader', function () {
    $path = builtDocx(fixtureDocument());

    expect(docxPart($path, 'word/document.xml'))->toMatch('/<w:pStyle w:val="Heading1"\/>.*?DAFTAR ISI/s')
        ->and(docxPart($path, 'word/styles.xml'))
        ->toMatch('/w:styleId="TOC1">.*?w:leader="dot" w:pos="7937".*?<w:b\/>/s')
        ->toContain('w:styleId="TOC2"');
});

test('puts the name and the student number on separate cover lines', function () {
    $xml = docxPart(builtDocx(fixtureDocument()), 'word/document.xml');

    expect($xml)->toMatch('/<w:u w:val="single"\/>.*?Budi Santoso<\/w:t>/s')
        ->toContain('>2201234567</w:t>')
        ->not->toContain('Budi Santoso (2201234567)');
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
