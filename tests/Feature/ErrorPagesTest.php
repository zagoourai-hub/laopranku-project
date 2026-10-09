<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('404 page is in Indonesian and links guests home', function () {
    $this->get('/tidak-ada-halaman-ini')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('Kembali ke Beranda')
        ->assertDontSee('Not Found');
});

test('403 on another user\'s document is in Indonesian and links back to documents', function () {
    $document = Document::factory()->for(User::factory())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('documents.show', $document))
        ->assertForbidden()
        ->assertSee('Akses ditolak')
        ->assertSee('Kembali ke Dokumen')
        ->assertDontSee('This action is unauthorized');
});

test('503 shows the reason given by the app', function () {
    $owner = User::factory()->pro()->create();
    $document = Document::factory()->for($owner)->create([
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
    Http::fake(['*' => Http::response('boom', 500)]);

    $this->actingAs($owner)
        ->get($document->downloadUrl('pdf'))
        ->assertStatus(503)
        ->assertSee('Konversi PDF sedang tidak tersedia');
});
