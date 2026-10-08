<?php

use App\Models\Document;
use App\Models\User;

function previewDocument(User $owner, array $overrides = []): Document
{
    return Document::factory()->for($owner)->create($overrides + [
        'title' => 'Pengaruh Media Sosial',
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
}

test('owner sees the numbered preview source with TOC and references', function () {
    $owner = User::factory()->create();
    $document = previewDocument($owner);

    $this->actingAs($owner)
        ->get(route('documents.preview', $document))
        ->assertOk()
        ->assertSee('BAB I PENDAHULUAN', false)
        ->assertSee('1.1 Latar Belakang', false)
        ->assertSee('DAFTAR ISI', false)
        ->assertSee('DAFTAR PUSTAKA', false)
        ->assertSee('Perkiraan tampilan kertas A4', false)
        ->assertSee('data-test="accurate-preview"', false);
});

test('another user is forbidden from the preview', function () {
    $document = previewDocument(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('documents.preview', $document))
        ->assertForbidden();
});

test('a document without chapters shows the empty state and no accurate button', function () {
    $owner = User::factory()->create();
    $document = previewDocument($owner, ['content' => ['chapters' => []]]);

    $this->actingAs($owner)
        ->get(route('documents.preview', $document))
        ->assertOk()
        ->assertSee('data-test="preview-empty"', false)
        ->assertDontSee('data-test="accurate-preview"', false);
});

test('the accurate preview link is a signed inline PDF url', function () {
    $owner = User::factory()->create();
    $document = previewDocument($owner);

    $url = Livewire\Livewire::actingAs($owner)->test('pages::documents.preview', ['document' => $document])
        ->call('accurateUrl')
        ->effects['returns'][0];

    expect($url)->toContain('format=pdf')->toContain('inline=1')->toContain('signature=');
});
