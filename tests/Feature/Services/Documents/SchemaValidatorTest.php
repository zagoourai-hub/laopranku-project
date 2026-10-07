<?php

use App\Services\Documents\SchemaValidator;

function validDocument(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/documents/valid.json')), true);
}

test('accepts a well-formed document', function () {
    expect((new SchemaValidator)->validate(validDocument()))->toBe([]);
});

test('rejects a document that breaks the contract', function (callable $break) {
    $document = $break(validDocument());

    expect((new SchemaValidator)->validate($document))->not->toBeEmpty();
})->with([
    'unknown document type' => [fn (array $d) => [...$d, 'type' => 'skripsi']],
    'unsupported schema version' => [fn (array $d) => [...$d, 'schema_version' => 2]],
    'missing cover title' => [function (array $d) {
        $d['cover']['title'] = '';

        return $d;
    }],
    'unknown block type' => [function (array $d) {
        $d['chapters'][0]['sections'][0]['blocks'][0]['type'] = 'image';

        return $d;
    }],
    'empty list' => [function (array $d) {
        $d['chapters'][0]['sections'][0]['blocks'][1]['items'] = [];

        return $d;
    }],
    'id that is not a uuid' => [function (array $d) {
        $d['chapters'][0]['id'] = 'bab-1';

        return $d;
    }],
    'chapter title with manual number "BAB I"' => [function (array $d) {
        $d['chapters'][0]['title'] = 'BAB I Pendahuluan';

        return $d;
    }],
    'section title with manual number "1.1"' => [function (array $d) {
        $d['chapters'][0]['sections'][0]['title'] = '1.1 Latar Belakang';

        return $d;
    }],
]);

test('rejects ids that are used more than once', function () {
    $document = validDocument();
    $document['references'][0]['id'] = $document['chapters'][0]['id'];

    $errors = (new SchemaValidator)->validate($document);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain($document['chapters'][0]['id']);
});

test('keeps titles that merely start with a number word', function () {
    $document = validDocument();
    $document['chapters'][0]['title'] = '5G dan Pendidikan';

    expect((new SchemaValidator)->validate($document))->toBe([]);
});
