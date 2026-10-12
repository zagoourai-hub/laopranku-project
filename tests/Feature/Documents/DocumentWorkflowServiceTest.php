<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Jobs\ChapterWriterJob;
use App\Jobs\GenerateOutline;
use App\Jobs\StructureDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Documents\OutlineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

// These run with an explicit user and no signed-in session, the way MCP calls the services.

beforeEach(fn () => Queue::fake());

/** @return array<string, mixed> */
function topicDraft(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Pengaruh Media Sosial',
        'type' => DocumentType::Makalah,
        'level' => EducationLevel::Mahasiswa,
        'input' => 'topic',
        'topic' => 'Pengaruh media sosial terhadap prestasi belajar',
        'chapter_count' => 3,
        'length' => 'sedang',
        'sources' => ['https://example.org/kajian'],
    ];
}

function approvedOutlineDoc(User $owner, bool $approved = false): Document
{
    $section = fn (string $title): array => ['id' => (string) Str::uuid(), 'title' => $title, 'blocks' => []];

    return Document::factory()->for($owner)->create([
        'status' => DocumentStatus::OutlineReady,
        'content' => [
            'schema_version' => 1,
            'type' => 'makalah',
            'cover' => ['title' => 'Pengaruh Media Sosial'],
            'style' => 'formal',
            'chapters' => [
                ['id' => (string) Str::uuid(), 'title' => 'Pendahuluan', 'sections' => [$section('Latar Belakang')]],
                ['id' => (string) Str::uuid(), 'title' => 'Pembahasan', 'sections' => [$section('Dampak')]],
                ['id' => (string) Str::uuid(), 'title' => 'Penutup', 'sections' => [$section('Kesimpulan')]],
            ],
            'brief' => ['input' => 'topic', 'topic' => 'Pengaruh media sosial terhadap belajar', 'chapter_count' => 3, 'length' => 'sedang']
                + ($approved ? ['outline_approved_at' => now()->toIso8601String()] : []),
        ],
    ]);
}

function unitsSpent(User $user): int
{
    return DB::table('ai_generations')->where('user_id', $user->id)->count();
}

test('a topic draft is created from the service alone and queues the outline', function () {
    $user = User::factory()->create();

    $result = app(DocumentService::class)->createDraft($user, topicDraft());

    expect($result['errors'])->toBe([])
        ->and($result['document']->content['brief']['topic'])->toBe('Pengaruh media sosial terhadap prestasi belajar')
        ->and($result['document']->content['sources'][0]['url'])->toBe('https://example.org/kajian')
        ->and(unitsSpent($user))->toBe(1);

    Queue::assertPushed(GenerateOutline::class, 1);
});

test('a pasted draft queues structuring', function () {
    $user = User::factory()->create();

    $result = app(DocumentService::class)->createDraft($user, topicDraft(['input' => 'paste', 'raw_text' => "Pendahuluan\n\nIsi paragraf pertama."]));

    expect($result['document'])->not->toBeNull()
        ->and($result['document']->content['brief']['raw_text'])->toContain('Isi paragraf pertama.');

    Queue::assertPushed(StructureDocument::class, 1);
    Queue::assertNotPushed(GenerateOutline::class);
});

test('ready-made chapters need no AI and spend no quota', function () {
    $user = User::factory()->create();

    $result = app(DocumentService::class)->createDraft($user, topicDraft(['input' => 'chapters', 'chapters' => []]));

    expect($result['document'])->not->toBeNull()
        ->and($result['document']->content)->not->toHaveKey('brief')
        ->and(unitsSpent($user))->toBe(0);

    Queue::assertNothingPushed();
});

test('the monthly document quota is enforced by the service, not only by the page', function () {
    $user = User::factory()->create();
    Document::factory()->count(3)->for($user)->create();

    $result = app(DocumentService::class)->createDraft($user, topicDraft());

    expect($result['document'])->toBeNull()
        ->and($result['field'])->toBe('quota');

    Queue::assertNothingPushed();
});

test('an exhausted daily AI quota creates no document', function () {
    $user = User::factory()->create();
    DB::table('ai_generations')->insert(array_fill(0, 5, ['user_id' => $user->id, 'kind' => 'topic', 'created_at' => now()]));

    $result = app(DocumentService::class)->createDraft($user, topicDraft());

    expect($result['document'])->toBeNull()
        ->and($result['field'])->toBe('generate')
        ->and($result['errors'][0])->toContain('Kuota generate AI hari ini')
        ->and(Document::where('user_id', $user->id)->count())->toBe(0);
});

test('approving an outline from the service queues one job and one unit per chapter, once', function () {
    $user = User::factory()->create();
    $document = approvedOutlineDoc($user);
    $outline = app(OutlineService::class);

    $first = $outline->approve($document, $user);
    $second = $outline->approve($document, $user);

    expect($first['outcome'])->toBe('queued')
        ->and($second['outcome'])->toBe('noop')
        ->and(unitsSpent($user))->toBe(3)
        ->and($outline->isLocked($document->fresh()))->toBeTrue();

    Queue::assertPushed(ChapterWriterJob::class, 3);
});

test('an approval the daily quota cannot cover is refused as a whole', function () {
    $user = User::factory()->create();
    DB::table('ai_generations')->insert(array_fill(0, 3, ['user_id' => $user->id, 'kind' => 'topic', 'created_at' => now()]));

    $result = app(OutlineService::class)->approve(approvedOutlineDoc($user), $user);

    expect($result['outcome'])->toBe('limited')
        ->and($result['message'])->toContain('Kuota generate AI hari ini')
        ->and(unitsSpent($user))->toBe(3);

    Queue::assertNothingPushed();
});

test('outline edits are refused once approved', function () {
    $user = User::factory()->create();
    $document = approvedOutlineDoc($user, approved: true);

    $errors = app(OutlineService::class)->addChapter($document);

    expect($errors)->toHaveCount(1)
        ->and($document->fresh()->content['chapters'])->toHaveCount(3);
});
