<?php

use App\Models\Document;
use App\Models\User;

function documentWithContent(User $owner, array $overrides = []): Document
{
    return Document::factory()->for($owner)->create($overrides + [
        'title' => 'Pengaruh Media Sosial',
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
}

test('owner downloads a DOCX named after the title, and the temporary file is removed', function () {
    $owner = User::factory()->create();
    $document = documentWithContent($owner);

    $response = $this->actingAs($owner)->get($document->downloadUrl());

    $response->assertOk()->assertDownload('pengaruh-media-sosial.docx');

    $file = $response->baseResponse->getFile()->getPathname();
    expect(file_get_contents($file, length: 2))->toBe('PK');

    $response->streamedContent();

    expect(file_exists($file))->toBeFalse();
});

test('another user is forbidden from downloading the document', function () {
    $document = documentWithContent(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get($document->downloadUrl())
        ->assertForbidden();
});

test('guests are sent to the login page', function () {
    $document = documentWithContent(User::factory()->create());

    $this->get($document->downloadUrl())->assertRedirect(route('login'));
});

test('a link without a valid signature is rejected', function () {
    $owner = User::factory()->create();
    $document = documentWithContent($owner);

    $this->actingAs($owner)->get(route('documents.download', $document))->assertForbidden();
    $this->actingAs($owner)->get($document->downloadUrl().'x')->assertForbidden();
});

test('a link older than ten minutes is rejected', function () {
    $owner = User::factory()->create();
    $document = documentWithContent($owner);
    $url = $document->downloadUrl();

    $this->travel(11)->minutes();

    $this->actingAs($owner)->get($url)->assertForbidden();
});

test('a document without chapters answers 422 with an Indonesian message', function (?array $content) {
    $owner = User::factory()->create();
    $document = documentWithContent($owner, ['content' => $content]);

    $this->actingAs($owner)
        ->get($document->downloadUrl())
        ->assertStatus(422)
        ->assertSee('Dokumen belum punya isi yang valid');
})->with([
    'no content yet' => [null],
    'empty chapters' => [['schema_version' => 1, 'chapters' => []]],
    'schema-invalid content' => [['schema_version' => 1, 'chapters' => [['title' => 'tanpa id']]]],
]);
