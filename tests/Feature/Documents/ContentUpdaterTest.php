<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\ContentUpdater;

function updaterContent(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true);
}

function updaterDocument(?array $content = null): Document
{
    return Document::factory()->for(User::factory()->create())->create([
        'content' => $content ?? updaterContent(),
        'type' => 'makalah',
    ]);
}

test('valid content is saved and the title follows cover.title', function () {
    $document = updaterDocument();
    $content = updaterContent();
    $content['cover']['title'] = 'Judul Baru';
    $content['chapters'][0]['sections'][0]['blocks'][0]['text'] = 'Teks diubah.';

    expect(app(ContentUpdater::class)->apply($document, $content))->toBe([]);

    $fresh = $document->fresh();
    expect($fresh->title)->toBe('Judul Baru')
        ->and($fresh->content['chapters'][0]['sections'][0]['blocks'][0]['text'])->toBe('Teks diubah.')
        ->and($fresh->status)->toBe($document->status);
});

test('duplicate ids are rejected and nothing is saved', function () {
    $document = updaterDocument();
    $before = $document->content;
    $content = updaterContent();
    $content['chapters'][1]['id'] = $content['chapters'][0]['id'];

    $errors = app(ContentUpdater::class)->apply($document, $content);

    expect($errors)->not->toBeEmpty()
        ->and($document->fresh()->content)->toBe($before);
});

test('schema-invalid content is rejected and the title stays', function () {
    $document = updaterDocument();
    $title = $document->title;
    $content = updaterContent();
    unset($content['cover']);

    expect(app(ContentUpdater::class)->apply($document, $content))->not->toBeEmpty()
        ->and($document->fresh()->title)->toBe($title);
});

test('changing the document type is rejected', function () {
    $document = updaterDocument();
    $content = updaterContent();
    $content['type'] = 'proposal';

    expect(app(ContentUpdater::class)->apply($document, $content))->toContain('/type: jenis dokumen tidak boleh diganti')
        ->and($document->fresh()->content['type'])->toBe('makalah');
});

test('a soft-deleted document cannot be updated', function () {
    $document = updaterDocument();
    $document->delete();

    expect(app(ContentUpdater::class)->apply($document, updaterContent()))->not->toBeEmpty();
});

test('only the owner may update a document', function () {
    $document = updaterDocument();

    expect($document->user->can('update', $document))->toBeTrue()
        ->and(User::factory()->create()->can('update', $document))->toBeFalse();
});
