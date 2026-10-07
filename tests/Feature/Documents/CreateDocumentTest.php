<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\SchemaValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('documents.create'))->assertRedirect(route('login'));
});

test('unverified users are redirected to email verification', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get(route('documents.create'))->assertRedirect(route('verification.notice'));
});

test('create page is displayed for verified users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('documents.create'))->assertOk()->assertSee('Dokumen baru');
});

test('a pasted text draft is created for the user without calling any processing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::documents.create')
        ->set('type', 'laporan_pkl')
        ->set('title', 'Laporan PKL di Dinas Kominfo')
        ->set('nim', '2201234567')
        ->set('prodi', 'Teknik Informatika')
        ->set('campus', 'Universitas Padjadjaran')
        ->set('year', '2026')
        ->set('rawText', 'BAB 1 pendahuluan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $document = Document::sole();

    expect($document->user_id)->toBe($user->id)
        ->and($document->type)->toBe(DocumentType::LaporanPkl)
        ->and($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->content)->toMatchArray([
            'schema_version' => 1,
            'type' => 'laporan_pkl',
            'chapters' => [],
            'references' => [],
            'sources' => [],
            'cover' => [
                'title' => 'Laporan PKL di Dinas Kominfo',
                'name' => $user->name,
                'nim' => '2201234567',
                'prodi' => 'Teknik Informatika',
                'campus' => 'Universitas Padjadjaran',
                'year' => '2026',
            ],
        ])
        ->and($document->content['brief']['raw_text'])->toBe('BAB 1 pendahuluan');

    expect(app(SchemaValidator::class)->validate($document->content))->toBe([]);
});

test('a topic draft stores the brief', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::documents.create')
        ->set('title', 'Hukum Hooke')
        ->set('inputMode', 'topic')
        ->set('topic', 'Pengukuran konstanta pegas')
        ->set('chapterCount', 4)
        ->set('length', 'ringkas')
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::sole()->content['brief'])->toMatchArray([
        'input' => 'topic',
        'topic' => 'Pengukuran konstanta pegas',
        'chapter_count' => 4,
        'length' => 'ringkas',
    ]);
});

test('an uploaded file is stored and referenced by the draft', function () {
    Storage::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::documents.create')
        ->set('title', 'Naskah unggahan')
        ->set('inputMode', 'upload')
        ->set('upload', UploadedFile::fake()->create('naskah.docx', 100))
        ->call('save')
        ->assertHasNoErrors();

    $path = Document::sole()->content['brief']['upload_path'];

    expect($path)->toStartWith('document-uploads/'.$user->id.'/');
    Storage::assertExists($path);
});

test('invalid input is rejected and no document is created', function (array $input, string $field) {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::documents.create')->set('title', 'Judul valid');

    foreach ($input as $property => $value) {
        $component->set($property, $value);
    }

    $component->call('save')->assertHasErrors($field);

    expect(Document::count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'unknown type' => [['type' => 'skripsi'], 'type'],
    'non numeric nim' => [['nim' => '22A01'], 'nim'],
    'short year' => [['year' => '26'], 'year'],
    'topic mode without topic' => [['inputMode' => 'topic', 'topic' => ''], 'topic'],
    'topic too short' => [['inputMode' => 'topic', 'topic' => 'pendek'], 'topic'],
    'upload mode without file' => [['inputMode' => 'upload'], 'upload'],
    'unsupported upload extension' => [['inputMode' => 'upload', 'upload' => UploadedFile::fake()->create('naskah.exe', 10)], 'upload'],
]);

test('the dashboard lists only the documents of the signed in user', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->create(['title' => 'Makalah milik saya']);
    Document::factory()->create(['title' => 'Makalah orang lain']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Makalah milik saya')
        ->assertDontSee('Makalah orang lain');
});

test('the dashboard shows an empty state without documents', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada dokumen');
});
