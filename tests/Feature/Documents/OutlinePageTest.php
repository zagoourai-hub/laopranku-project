<?php

use App\Enums\DocumentStatus;
use App\Jobs\ChapterWriterJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** A topic document with a drafted outline: two chapters, the first with two sections. */
function outlineDoc(User $owner, bool $approved = false): Document
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
                ['id' => (string) Str::uuid(), 'title' => 'Pendahuluan', 'sections' => [$section('Latar Belakang'), $section('Rumusan Masalah')]],
                ['id' => (string) Str::uuid(), 'title' => 'Pembahasan', 'sections' => [$section('Dampak')]],
            ],
            'brief' => ['input' => 'topic', 'topic' => 'Pengaruh media sosial terhadap belajar', 'chapter_count' => 3, 'length' => 'sedang']
                + ($approved ? ['outline_approved_at' => now()->toIso8601String()] : []),
        ],
    ]);
}

test('the outline page is only for the owner', function () {
    $document = outlineDoc(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('documents.outline', $document))
        ->assertForbidden();
});

test('a non-topic document has no outline page', function () {
    $owner = User::factory()->create();
    $document = outlineDoc($owner);
    $content = $document->content;
    $content['brief']['input'] = 'paste';
    $document->forceFill(['content' => $content])->save();

    $this->actingAs($owner)->get(route('documents.outline', $document))->assertNotFound();
});

test('the owner can add, rename, move and remove chapters and sections before approval', function () {
    $owner = User::factory()->create();
    $document = outlineDoc($owner);

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('addChapter')
        ->call('renameChapter', 0, 'Pendahuluan Baru')
        ->call('moveChapter', 0, 1)
        ->call('addSection', 1)
        ->call('removeSection', 1, 0)
        ->call('moveSection', 1, 0, 1);

    $chapters = $document->fresh()->content['chapters'];

    expect($chapters)->toHaveCount(3)
        ->and(array_column($chapters, 'title'))->toBe(['Pembahasan', 'Pendahuluan Baru', 'Bab baru'])
        ->and(array_column($chapters[0]['sections'], 'title'))->toBe(['Dampak'])
        ->and(array_column($chapters[1]['sections'], 'title'))->toBe(['Subbab baru', 'Rumusan Masalah']);
});

test('a numbered title the schema forbids is rejected and nothing is saved', function () {
    $owner = User::factory()->create();
    $document = outlineDoc($owner);

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('renameChapter', 0, '1. Pendahuluan');

    expect($document->fresh()->content['chapters'][0]['title'])->toBe('Pendahuluan');
});

test('removing a section also drops its note', function () {
    $owner = User::factory()->create();
    $document = outlineDoc($owner);
    $content = $document->content;
    $removed = $content['chapters'][0]['sections'][0]['id'];
    $content['brief']['section_notes'] = [$removed => 'Catatan lama'];
    $document->forceFill(['content' => $content])->save();

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('removeSection', 0, 0);

    expect($document->fresh()->content['brief'])->not->toHaveKey('section_notes');
});

test('approving queues one writer job per chapter and locks the outline', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $document = outlineDoc($owner);

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('approve');

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Generating)
        ->and($document->content['brief'])->toHaveKey('outline_approved_at')
        ->and($document->jobs()->where('status', 'queued')->count())->toBe(2);

    Queue::assertPushed(ChapterWriterJob::class, 2);

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('addChapter')
        ->call('approve');

    expect($document->fresh()->content['chapters'])->toHaveCount(2);
    Queue::assertPushed(ChapterWriterJob::class, 2);
});

test('after approval only a single chapter can be written again', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $document = outlineDoc($owner, approved: true);

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('regenerate', 1);

    Queue::assertPushed(ChapterWriterJob::class, fn (ChapterWriterJob $job): bool => $job->chapterIndex === 1);
    Queue::assertPushed(ChapterWriterJob::class, 1);
});

test('a chapter that is already queued is not queued twice', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $document = outlineDoc($owner, approved: true);
    $document->jobs()->create(['chapter_index' => 0, 'status' => 'running']);

    Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document])
        ->call('regenerate', 0);

    Queue::assertNotPushed(ChapterWriterJob::class);
});
