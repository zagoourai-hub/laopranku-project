<?php

use App\Enums\DocumentStatus;
use App\Jobs\ChapterWriterJob;
use App\Jobs\GenerateOutline;
use App\Jobs\StructureDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\Ai\ChapterAgent;
use App\Services\Ai\OutlineAgent;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** A topic document whose outline is drafted but not yet approved. */
function topicDocument(User $owner, array $brief = []): Document
{
    return Document::factory()->for($owner)->create([
        'status' => DocumentStatus::Draft,
        'content' => [
            'schema_version' => 1,
            'type' => 'makalah',
            'cover' => ['title' => 'Pengaruh Media Sosial'],
            'style' => 'formal',
            'chapters' => [],
            'brief' => $brief + ['input' => 'topic', 'topic' => 'Pengaruh media sosial terhadap belajar', 'chapter_count' => 3, 'length' => 'sedang'],
        ],
    ]);
}

/** @return array{id: string, title: string, sections: list<array{id: string, title: string, blocks: list}>} */
function section(string $title): array
{
    return ['id' => (string) Str::uuid(), 'title' => $title, 'blocks' => []];
}

test('pasting text queues structuring and is rate limited per user', function () {
    Queue::fake();
    $user = User::factory()->pro()->create();
    $this->actingAs($user);

    for ($i = 1; $i <= 5; $i++) {
        Livewire::test('pages::documents.create')
            ->set('title', "Judul uji {$i}")
            ->set('inputMode', 'paste')
            ->set('rawText', "Pendahuluan\n\nIsi paragraf pertama.")
            ->call('save')
            ->assertHasNoErrors();
    }

    Livewire::test('pages::documents.create')
        ->set('title', 'Judul ditolak')
        ->set('inputMode', 'paste')
        ->set('rawText', "Pendahuluan\n\nIsi paragraf pertama.")
        ->call('save')
        ->assertHasErrors(['generate']);

    Queue::assertPushed(StructureDocument::class, 5);
    expect(Document::count())->toBe(5);
});

test('a topic draft queues the outline job, not structuring', function () {
    Queue::fake();
    $this->actingAs(User::factory()->pro()->create());

    Livewire::test('pages::documents.create')
        ->set('title', 'Topik uji')
        ->set('inputMode', 'topic')
        ->set('topic', 'Pengaruh media sosial terhadap belajar')
        ->call('save')
        ->assertHasNoErrors();

    Queue::assertPushed(GenerateOutline::class, 1);
    Queue::assertNotPushed(StructureDocument::class);
});

test('the outline job stores titles only, with the numbering stripped and no body text', function () {
    OutlineAgent::fake(fn () => ['chapters' => [
        ['title' => 'BAB I Pendahuluan', 'sections' => [
            ['title' => '1.1 Latar Belakang', 'note' => 'Alasan memilih topik.'],
            ['title' => 'Rumusan Masalah', 'note' => 'Pertanyaan utama.'],
        ]],
        ['title' => 'Pembahasan', 'sections' => [['title' => 'Dampak', 'note' => 'Efek positif dan negatif.']]],
    ]]);

    $document = topicDocument(User::factory()->create());
    GenerateOutline::dispatchSync($document);

    $document->refresh();
    $chapters = $document->content['chapters'];

    expect($document->status)->toBe(DocumentStatus::OutlineReady)
        ->and(array_column($chapters, 'title'))->toBe(['Pendahuluan', 'Pembahasan'])
        ->and(array_column($chapters[0]['sections'], 'title'))->toBe(['Latar Belakang', 'Rumusan Masalah'])
        ->and($document->content['brief']['section_notes'])->toHaveCount(3)
        ->and(json_encode($chapters))->not->toContain('"type":"paragraph"');
});

test('a failed outline marks the job failed and leaves the document as a draft', function () {
    OutlineAgent::fake(fn () => throw new RuntimeException('provider down'));

    $document = topicDocument(User::factory()->create());
    GenerateOutline::dispatchSync($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->content['chapters'])->toBe([])
        ->and($document->jobs()->first()->status)->toBe('failed');
});

test('a chapter is written into its own sections only, and the document is ready when none is pending', function () {
    $owner = User::factory()->create();
    $one = section('Latar Belakang');
    $two = section('Rumusan Masalah');
    $other = section('Dampak');

    $document = topicDocument($owner, ['outline_approved_at' => now()->toIso8601String()]);
    $content = $document->content;
    $content['chapters'] = [
        ['id' => (string) Str::uuid(), 'title' => 'Pendahuluan', 'sections' => [$one, $two]],
        ['id' => (string) Str::uuid(), 'title' => 'Pembahasan', 'sections' => [$other]],
    ];
    $document->forceFill(['content' => $content, 'status' => DocumentStatus::Generating])->save();

    ChapterAgent::fake(fn () => ['sections' => [
        ['index' => 0, 'paragraphs' => ['Latar belakang pertama.', 'Latar belakang kedua.']],
        ['index' => 1, 'paragraphs' => ['Rumusan masalah.']],
    ]]);

    $first = $document->jobs()->create(['chapter_index' => 0, 'status' => 'queued']);
    $second = $document->jobs()->create(['chapter_index' => 1, 'status' => 'queued']);

    ChapterWriterJob::dispatchSync($document, 0, $first->id);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Generating)
        ->and($first->fresh()->status)->toBe('done')
        ->and($document->content['chapters'][0]['sections'][0]['blocks'])->toHaveCount(2)
        ->and($document->content['chapters'][0]['sections'][1]['blocks'][0]['text'])->toBe('Rumusan masalah.')
        ->and($document->content['chapters'][1]['sections'][0]['blocks'])->toBe([]);

    ChapterAgent::fake(fn () => ['sections' => [['index' => 0, 'paragraphs' => ['Dampak positif.']]]]);
    ChapterWriterJob::dispatchSync($document, 1, $second->id);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content['chapters'][1]['sections'][0]['blocks'][0]['text'])->toBe('Dampak positif.');
});

test('one failing chapter does not erase the chapters already written', function () {
    $owner = User::factory()->create();
    $document = topicDocument($owner, ['outline_approved_at' => now()->toIso8601String()]);
    $content = $document->content;
    $content['chapters'] = [
        ['id' => (string) Str::uuid(), 'title' => 'Pertama', 'sections' => [section('A')]],
        ['id' => (string) Str::uuid(), 'title' => 'Kedua', 'sections' => [section('B')]],
    ];
    $document->forceFill(['content' => $content, 'status' => DocumentStatus::Generating])->save();

    ChapterAgent::fake(function (string $prompt) {
        if (str_contains($prompt, 'Bab: Kedua')) {
            throw new RuntimeException('timeout');
        }

        return ['sections' => [['index' => 0, 'paragraphs' => ['Isi bab pertama.']]]];
    });

    $one = $document->jobs()->create(['chapter_index' => 0, 'status' => 'queued']);
    $two = $document->jobs()->create(['chapter_index' => 1, 'status' => 'queued']);

    ChapterWriterJob::dispatchSync($document, 0, $one->id);
    ChapterWriterJob::dispatchSync($document, 1, $two->id);

    $document->refresh();

    expect($document->content['chapters'][0]['sections'][0]['blocks'][0]['text'])->toBe('Isi bab pertama.')
        ->and($document->content['chapters'][1]['sections'][0]['blocks'])->toBe([])
        ->and($two->fresh()->status)->toBe('failed')
        ->and($document->status)->toBe(DocumentStatus::Ready);
});

test('without sources the writer is told not to cite, and with sources only those are offered', function () {
    $owner = User::factory()->create();
    $document = topicDocument($owner, ['outline_approved_at' => now()->toIso8601String()]);
    $content = $document->content;
    $content['chapters'] = [['id' => (string) Str::uuid(), 'title' => 'Pertama', 'sections' => [section('A')]]];
    $content['sources'] = [];
    $document->forceFill(['content' => $content])->save();

    $prompts = [];
    ChapterAgent::fake(function (string $prompt) use (&$prompts) {
        $prompts[] = $prompt;

        return ['sections' => []];
    });

    $job = $document->jobs()->create(['chapter_index' => 0, 'status' => 'queued']);
    ChapterWriterJob::dispatchSync($document, 0, $job->id);

    $content['sources'] = [['id' => (string) Str::uuid(), 'text' => 'Halliday, Fisika Dasar', 'url' => null, 'doi' => null]];
    $document->forceFill(['content' => $content])->save();
    $job2 = $document->jobs()->create(['chapter_index' => 0, 'status' => 'queued']);
    ChapterWriterJob::dispatchSync($document, 0, $job2->id);

    expect($prompts[0])->toContain('jangan menulis sitasi')
        ->and($prompts[1])->toContain('- Halliday, Fisika Dasar')
        ->and($prompts[1])->not->toContain('jangan menulis sitasi');
});
