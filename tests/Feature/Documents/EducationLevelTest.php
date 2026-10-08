<?php

use App\Enums\EducationLevel;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocxBuilder;
use App\Services\Documents\Template;
use Livewire\Livewire;

test('cover labels follow the education level', function () {
    expect(EducationLevel::Mahasiswa->coverLabels())->toMatchArray(['nim' => 'NIM', 'campus' => 'Kampus'])
        ->and(EducationLevel::Sma->coverLabels())->toMatchArray(['nim' => 'NISN/NIS', 'prodi' => 'Kelas', 'campus' => 'Sekolah'])
        ->and(EducationLevel::Smk->coverLabels())->toMatchArray(['prodi' => 'Kelas/Jurusan', 'campus' => 'Sekolah']);
});

test('a SMA document stores its level and hides the proposal type', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('pages::documents.create')
        ->set('type', 'proposal')
        ->set('educationLevel', 'sma')
        ->assertSet('type', 'makalah')
        ->set('title', 'Dampak Gawai pada Siswa')
        ->set('nim', '0012345678')
        ->set('prodi', 'XII IPA 2')
        ->set('campus', 'SMA Negeri 1 Bandung')
        ->set('rawText', 'BAB 1 pendahuluan')
        ->call('save')
        ->assertHasNoErrors();

    expect($component->get('typeOptions'))->not->toHaveKey('proposal')
        ->and(Document::first()->education_level)->toBe(EducationLevel::Sma);
});

test('the DOCX cover uses school wording for a SMA document', function () {
    $document = json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true);
    $document['cover'] = ['title' => 'Dampak Gawai', 'name' => 'Sari', 'nim' => '0012345678', 'prodi' => 'XII IPA 2', 'campus' => 'SMA Negeri 1 Bandung', 'year' => '2026'];

    $path = app(DocxBuilder::class)->build($document, Template::fromConfig(), EducationLevel::Sma);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    expect($xml)->toContain('KELAS XII IPA 2')->toContain('SMA NEGERI 1 BANDUNG')->not->toContain('PROGRAM STUDI');
});

test('duplicating keeps the education level', function () {
    $document = Document::factory()->create(['education_level' => EducationLevel::Smk, 'content' => ['chapters' => []]]);

    expect($document->duplicate()->education_level)->toBe(EducationLevel::Smk);
});
