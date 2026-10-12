<?php

use App\Enums\DocumentType;
use App\Jobs\GenerateOutline;
use App\Jobs\StructureDocument;
use App\Mcp\Servers\LaporankuServer;
use App\Mcp\Tools\CreateDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function practicumFacts(): array
{
    return [
        'experiment_title' => 'Hukum Ohm',
        'objective' => 'Mengukur hubungan tegangan dan arus',
        'materials' => ['Resistor 100 ohm', 'Multimeter'],
        'steps' => ['Rangkai rangkaian', 'Catat tegangan'],
        'observations' => 'Arus naik seiring tegangan.',
    ];
}

test('create_document with topic and practicum facts queues the outline like the web form does', function () {
    $user = User::factory()->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, [
            'title' => 'Laporan Hukum Ohm',
            'type' => 'laporan_praktikum',
            'input' => 'topic',
            'topic' => 'Hukum Ohm pada rangkaian seri',
            'facts' => practicumFacts(),
        ])
        ->assertOk()
        ->assertSee('"generating":true');

    $document = Document::where('user_id', $user->id)->sole();
    expect($document->content['brief']['input'])->toBe('topic')
        ->and($document->content['brief']['facts']['materials'])->toBe(['Resistor 100 ohm', 'Multimeter']);

    Queue::assertPushed(GenerateOutline::class, 1);
    expect(DB::table('ai_generations')->where('user_id', $user->id)->count())->toBe(1);
});

test('a practicum topic without its data is refused and nothing is created', function () {
    $user = User::factory()->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, [
            'title' => 'Laporan Hukum Ohm',
            'type' => 'laporan_praktikum',
            'input' => 'topic',
            'topic' => 'Hukum Ohm pada rangkaian seri',
            'facts' => ['experiment_title' => 'Hukum Ohm'],
        ])
        ->assertHasErrors(['Data pokok belum lengkap']);

    expect(Document::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a list fact given as one string is split into items, not stored as one item', function () {
    $user = User::factory()->create();
    $facts = practicumFacts();
    $facts['materials'] = "- Resistor 100 ohm\n2. Multimeter\n";

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, [
            'title' => 'Laporan Hukum Ohm',
            'type' => 'laporan_praktikum',
            'input' => 'topic',
            'topic' => 'Hukum Ohm pada rangkaian seri',
            'facts' => $facts,
        ])
        ->assertOk();

    expect(Document::where('user_id', $user->id)->sole()->content['brief']['facts']['materials'])->toBe(['Resistor 100 ohm', 'Multimeter']);
});

test('create_document with paste queues structuring and needs raw_text', function () {
    $user = User::factory()->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, ['title' => 'Makalah Tempel', 'type' => 'makalah', 'input' => 'paste'])
        ->assertHasErrors(['Raw text wajib diisi']);

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, [
            'title' => 'Makalah Tempel',
            'type' => 'makalah',
            'input' => 'paste',
            'raw_text' => "Pendahuluan\n\nIsi paragraf pertama.",
        ])
        ->assertOk();

    Queue::assertPushed(StructureDocument::class, 1);
});

test('topic and paste draws on the same daily AI quota as the web form', function () {
    $user = User::factory()->create();
    DB::table('ai_generations')->insert(array_fill(0, 5, ['user_id' => $user->id, 'kind' => 'topic', 'created_at' => now()]));

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, [
            'title' => 'Makalah Lagi',
            'type' => DocumentType::Makalah->value,
            'input' => 'topic',
            'topic' => 'Pengaruh media sosial terhadap belajar',
        ])
        ->assertHasErrors(['Kuota generate AI hari ini']);

    expect(Document::count())->toBe(0);
});

test('ready-made chapters still need no AI and no quota', function () {
    $user = User::factory()->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, ['title' => 'Makalah Bab', 'type' => 'makalah', 'input' => 'chapters', 'chapters' => []])
        ->assertOk();

    expect(DB::table('ai_generations')->where('user_id', $user->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});
