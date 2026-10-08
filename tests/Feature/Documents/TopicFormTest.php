<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\SchemaValidator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function topicForm(string $type): Testable
{
    return Livewire::test('pages::documents.create')
        ->set('title', 'Judul dokumen uji')
        ->set('type', $type)
        ->set('inputMode', 'topic')
        ->set('topic', 'Pengukuran konstanta pegas dengan hukum Hooke');
}

test('a practicum topic draft stores structured facts and sources that pass the schema', function () {
    $this->actingAs(User::factory()->create());

    topicForm('laporan_praktikum')
        ->assertSet('chapterCount', 5)
        ->set('facts.experiment_title', 'Hukum Hooke')
        ->set('facts.objective', 'Menentukan konstanta pegas')
        ->set('facts.materials', "- Pegas\n- Beban 50 g\n\nPenggaris")
        ->set('facts.steps', "1. Gantung pegas\n2. Tambah beban")
        ->set('facts.observations', 'Beban 50 g: 2 cm; 100 g: 4 cm')
        ->set('sourcesText', "Halliday, Fisika Dasar\nhttps://example.org/hooke")
        ->call('save')
        ->assertHasNoErrors();

    $content = Document::sole()->content;

    expect($content['brief']['facts'])->toBe([
        'experiment_title' => 'Hukum Hooke',
        'objective' => 'Menentukan konstanta pegas',
        'materials' => ['Pegas', 'Beban 50 g', 'Penggaris'],
        'steps' => ['Gantung pegas', 'Tambah beban'],
        'observations' => 'Beban 50 g: 2 cm; 100 g: 4 cm',
    ])
        ->and($content['brief']['chapter_count'])->toBe(5)
        ->and(array_column($content['sources'], 'text'))->toBe(['Halliday, Fisika Dasar', 'https://example.org/hooke'])
        ->and(array_column($content['sources'], 'url'))->toBe([null, 'https://example.org/hooke'])
        ->and(app(SchemaValidator::class)->validate($content))->toBe([]);
});

test('a PKL topic draft requires every fact field with Indonesian messages', function () {
    $this->actingAs(User::factory()->create());

    topicForm('laporan_pkl')
        ->set('facts.institution', 'Dinas Kominfo')
        ->call('save')
        ->assertHasErrors(['facts.division', 'facts.period', 'facts.activities'])
        ->assertHasNoErrors('facts.institution')
        ->assertSee('Divisi/bagian wajib diisi.');

    expect(Document::count())->toBe(0);
});

test('a whitespace-only list does not count as filled', function () {
    $this->actingAs(User::factory()->create());

    topicForm('laporan_pkl')
        ->set('facts.institution', 'Dinas Kominfo')
        ->set('facts.division', 'Aplikasi')
        ->set('facts.period', 'Juli 2026')
        ->set('facts.activities', "  \n  ")
        ->call('save')
        ->assertHasErrors('facts.activities');
});

test('facts are not required outside the topic path', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::documents.create')
        ->set('title', 'Laporan tempel')
        ->set('type', 'laporan_pkl')
        ->set('rawText', 'Isi laporan')
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::sole()->content['brief'])->not->toHaveKey('facts');
});

test('makalah topic drafts carry no facts and default to three chapters', function () {
    $this->actingAs(User::factory()->create());

    topicForm('makalah')->assertSet('chapterCount', 3)->call('save')->assertHasNoErrors();

    expect(Document::sole()->content['brief'])->not->toHaveKey('facts');
});

test('SMK students see Prakerin/PKL labels', function () {
    $this->actingAs(User::factory()->create());

    topicForm('laporan_pkl')
        ->set('educationLevel', 'smk')
        ->assertSee('Laporan Prakerin/PKL')
        ->assertSee('Data pokok Prakerin/PKL');
});

test('the document page announces the pending outline for topic drafts', function () {
    $owner = User::factory()->create();
    $document = Document::factory()->for($owner)->create([
        'content' => ['schema_version' => 1, 'type' => 'makalah', 'cover' => ['title' => 'X'], 'style' => 'formal', 'chapters' => [], 'brief' => ['input' => 'topic', 'topic' => 'Topik yang cukup panjang']],
    ]);

    $this->actingAs($owner)->get(route('documents.show', $document))->assertSee('Outline akan dibuat AI (segera)');
});

test('the schema validates the shape of raw_text and paragraphs in the brief', function () {
    $content = json_decode(file_get_contents(base_path('tests/Fixtures/documents/valid.json')), true);
    $validator = app(SchemaValidator::class);

    $content['brief'] += ['raw_text' => 'Teks mentah', 'paragraphs' => [['id' => 'p1', 'text' => 'Satu.'], ['id' => 'p2', 'text' => 'Dua.']]];
    expect($validator->validate($content))->toBe([]);

    $content['brief']['paragraphs'][] = ['id' => 'p3', 'text' => 'Tiga.', 'extra' => true];
    expect($validator->validate($content))->not->toBe([]);

    $content['brief']['paragraphs'] = [];
    $content['brief']['raw_text'] = ['bukan string'];
    expect($validator->validate($content))->not->toBe([]);
});

test('the schema rejects facts with unknown keys or empty lists', function (array $facts) {
    $content = json_decode(file_get_contents(base_path('tests/Fixtures/documents/valid.json')), true);
    $content['brief']['facts'] = $facts;

    expect(app(SchemaValidator::class)->validate($content))->not->toBe([]);
})->with([
    'unknown key' => [['institution' => 'A', 'division' => 'B', 'period' => 'C', 'activities' => ['D'], 'extra' => 'x']],
    'empty list' => [['institution' => 'A', 'division' => 'B', 'period' => 'C', 'activities' => []]],
    'missing field' => [['experiment_title' => 'A', 'objective' => 'B', 'materials' => ['C'], 'steps' => ['D']]],
]);
