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
    $owner = User::factory()->pro()->create();

    $this->actingAs($owner)->get(route('documents.show', editableDocument($owner)))
        ->assertOk()
        ->assertSee('data-test="cover-title"', false)
        ->assertSee('data-test="download-pdf"', false)
        ->assertSee('data-test="download-docx"', false);
});

test('reordered chapters and sections are saved with their ids unchanged', function () {
    $owner = User::factory()->create();
    $document = editableDocument($owner);
    $content = $document->content;
    $chapterIds = array_column($content['chapters'], 'id');
    $content['chapters'] = array_reverse($content['chapters']);
    $content['chapters'][0]['sections'] = array_reverse($content['chapters'][0]['sections']);
    $sectionIds = array_column($content['chapters'][0]['sections'], 'id');

    Livewire::actingAs($owner)->test('pages::documents.show', ['document' => $document])
        ->call('save', $content)
        ->assertReturned([]);

    $saved = $document->refresh()->content;

    expect(array_column($saved['chapters'], 'id'))->toBe(array_reverse($chapterIds))
        ->and(array_column($saved['chapters'][0]['sections'], 'id'))->toBe($sectionIds);
});

test('the editor renders reorder handles and undo/redo buttons', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('documents.show', editableDocument($owner)))
        ->assertOk()
        ->assertSee('js-handle-chapter', false)
        ->assertSee('data-test="chapter-up"', false)
        ->assertSee('data-test="open-preview"', false)
        ->assertSee('data-test="undo"', false)
        ->assertSee('data-test="redo"', false)
        ->assertSee('data-test="chapter-number"', false)
        ->assertSee('data-test="save-status"', false);
});
