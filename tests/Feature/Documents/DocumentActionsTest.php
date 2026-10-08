<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Livewire\Livewire;

function ownedDocument(User $owner, array $overrides = []): Document
{
    return Document::factory()->for($owner)->create($overrides + [
        'title' => 'Pengaruh Media Sosial',
        'status' => DocumentStatus::Ready,
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
}

function collectIds(mixed $node): array
{
    $ids = [];

    foreach ((array) $node as $key => $value) {
        if ($key === 'id' && is_string($value)) {
            $ids[] = $value;
        } elseif (is_array($value)) {
            $ids = [...$ids, ...collectIds($value)];
        }
    }

    return $ids;
}

test('owner deletes a document softly and it leaves the list', function () {
    $owner = User::factory()->create();
    $document = ownedDocument($owner);

    Livewire::actingAs($owner)->test('document-table')
        ->call('confirmDelete', $document->id)
        ->call('delete')
        ->assertDontSee('Pengaruh Media Sosial');

    expect(Document::find($document->id))->toBeNull()
        ->and(Document::withTrashed()->find($document->id))->not->toBeNull();
});

test('another user cannot delete the document', function () {
    $document = ownedDocument(User::factory()->create());

    Livewire::actingAs(User::factory()->create())->test('document-table')
        ->call('confirmDelete', $document->id)
        ->assertForbidden();

    expect($document->fresh())->not->toBeNull();
});

test('owner duplicates a document as a draft with all-new ids', function () {
    $owner = User::factory()->create();
    $document = ownedDocument($owner);

    Livewire::actingAs($owner)->test('document-table')->call('duplicate', $document->id);

    $copy = Document::where('title', 'Pengaruh Media Sosial (Salinan)')->firstOrFail();

    expect($copy->user_id)->toBe($owner->id)
        ->and($copy->type)->toBe($document->type)
        ->and($copy->template_key)->toBe($document->template_key)
        ->and($copy->status)->toBe(DocumentStatus::Draft)
        ->and($copy->content['cover'])->toBe($document->content['cover'])
        ->and(collectIds($copy->content))->toHaveCount(count(collectIds($document->content)))
        ->and(array_intersect(collectIds($copy->content), collectIds($document->content)))->toBeEmpty()
        ->and(array_unique(collectIds($copy->content)))->toHaveCount(count(collectIds($copy->content)));
});

test('a document without content can be duplicated', function () {
    $owner = User::factory()->create();
    $document = ownedDocument($owner, ['content' => null]);

    Livewire::actingAs($owner)->test('document-table')->call('duplicate', $document->id);

    expect(Document::where('title', 'Pengaruh Media Sosial (Salinan)')->exists())->toBeTrue();
});

test('another user cannot duplicate the document', function () {
    $document = ownedDocument(User::factory()->create());

    Livewire::actingAs(User::factory()->create())->test('document-table')
        ->call('duplicate', $document->id)
        ->assertForbidden();

    expect(Document::count())->toBe(1);
});

test('owner opens the document page and sees its chapters', function () {
    $owner = User::factory()->create();
    $document = ownedDocument($owner);

    $this->actingAs($owner)->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Pengaruh Media Sosial')
        ->assertSee('Pendahuluan')
        ->assertSee('Latar Belakang')
        ->assertSee('Makalah · Siap')
        ->assertSee('Unduh DOCX');
});

test('a document without content shows the empty message and no download button', function () {
    $owner = User::factory()->create();
    $document = ownedDocument($owner, ['content' => null]);

    $this->actingAs($owner)->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Dokumen belum punya isi')
        ->assertDontSee('Unduh DOCX');
});

test('another user cannot open the document page', function () {
    $document = ownedDocument(User::factory()->create());

    $this->actingAs(User::factory()->create())->get(route('documents.show', $document))->assertForbidden();
});

test('guests are sent to the login page from the document page', function () {
    $document = ownedDocument(User::factory()->create());

    $this->get(route('documents.show', $document))->assertRedirect(route('login'));
});

test('the document list shows type and education level', function () {
    $owner = User::factory()->create();
    Document::factory()->for($owner)->create(['education_level' => 'sma']);

    $this->actingAs($owner)->get(route('dashboard'))->assertSee('Makalah · SMA');
});
