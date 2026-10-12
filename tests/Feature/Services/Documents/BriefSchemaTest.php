<?php

use App\Services\Documents\SchemaValidator;

function briefFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/documents/{$name}.json")), true);
}

test('rejects a key the schema does not know inside brief', function () {
    $document = briefFixture('valid');
    $document['brief'] = ['topic' => 'Kalor', 'kunci_asing' => 'x'];

    expect(app(SchemaValidator::class)->validate($document))->not->toBeEmpty();
});

test('accepts every brief key the form and the AI pipeline write', function () {
    $document = briefFixture('valid');
    $document['brief'] = [
        'input' => 'topic',
        'topic' => 'Pengaruh kalor terhadap pemuaian logam',
        'chapter_count' => 4,
        'length' => 'sedang',
        'instructions' => '',
        'facts' => [],
        'upload_path' => 'document-uploads/1/contoh.docx',
        'section_notes' => ['018f0000-0000-4000-8000-0000000000c1' => 'Fokus pada data hasil.'],
        'outline_approved_at' => '2026-10-12T08:00:00+07:00',
    ];

    expect(app(SchemaValidator::class)->validate($document))->toBe([]);
});

test('keeps the existing fixtures valid after the brief is closed', function (string $name) {
    expect(app(SchemaValidator::class)->validate(briefFixture($name)))->toBe([]);
})->with(['makalah fixture' => ['makalah'], 'valid fixture' => ['valid']]);

test('rejects a section note that is not text', function () {
    $document = briefFixture('valid');
    $document['brief'] = ['section_notes' => ['018f0000-0000-4000-8000-0000000000c1' => ['bukan', 'teks']]];

    expect(app(SchemaValidator::class)->validate($document))->not->toBeEmpty();
});
