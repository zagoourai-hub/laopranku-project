<?php

use App\Models\Document;
use App\Models\User;
use Livewire\Livewire;

function editableDocument(User $owner): Document
{
    return Document::factory()->for($owner)->create([
        'title' => 'Pengaruh Media Sosial',
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
}

test('owner saves edited content from the editor', function () {
    $owner = User::factory()->create();
    $document = editableDocument($owner);
    $content = $document->content;
    $content['cover']['title'] = 'Judul Baru';
    $content['chapters'][0]['title'] = 'Pendahuluan Baru';

    Livewire::actingAs($owner)->test('pages::documents.show', ['document' => $document])
        ->call('save', $content)
        ->assertReturned([]);

    $document->refresh();

    expect($document->title)->toBe('Judul Baru')
        ->and($document->content['chapters'][0]['title'])->toBe('Pendahuluan Baru');
});

test('invalid content returns errors and is not saved', function () {
    $owner = User::factory()->create();
    $document = editableDocument($owner);
    $original = $document->content;
    $content = $original;
    $content['chapters'][0]['title'] = 'BAB I Pendahuluan';

    $component = Livewire::actingAs($owner)->test('pages::documents.show', ['document' => $document])
        ->call('save', $content);

    expect($component->effects['returns'][0])->not->toBe([])
        ->and($document->refresh()->content)->toBe($original);
});

test('another user cannot save', function () {
    $document = editableDocument(User::factory()->create());
    $content = $document->content;
    $content['cover']['title'] = 'Dibajak';

    Livewire::actingAs(User::factory()->create())->test('pages::documents.show', ['document' => $document])
        ->assertForbidden();

    expect($document->refresh()->title)->toBe('Pengaruh Media Sosial');
});

test('the show page renders the editor with PDF and DOCX buttons', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('documents.show', editableDocument($owner)))
        ->assertOk()
        ->assertSee('data-test="cover-title"', false)
        ->assertSee('data-test="download-pdf"', false)
        ->assertSee('data-test="download-docx"', false);
});
