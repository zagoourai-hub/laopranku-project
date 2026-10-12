<?php

use App\Enums\DocumentStatus;
use App\Jobs\ChapterWriterJob;
use App\Models\Document;
use App\Models\User;
use App\Services\Ai\ChapterAgent;
use App\Services\Ai\ChapterWriter;
use App\Services\Ai\GenerationLimiter;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** A chapter ready to be written, with one section, under an approved outline. */
function qaChapterDoc(User $owner, array $sources = []): Document
{
    $document = Document::factory()->for($owner)->create([
        'status' => DocumentStatus::Generating,
        'content' => [
            'schema_version' => 1,
            'type' => 'makalah',
            'cover' => ['title' => 'Pengaruh Media Sosial'],
            'style' => 'formal',
            'sources' => $sources,
            'chapters' => [['id' => (string) Str::uuid(), 'title' => 'Pendahuluan', 'sections' => [['id' => (string) Str::uuid(), 'title' => 'Latar', 'blocks' => []]]]],
            'brief' => ['input' => 'topic', 'topic' => 'Pengaruh media sosial terhadap belajar', 'chapter_count' => 3, 'length' => 'sedang', 'outline_approved_at' => now()->toIso8601String()],
        ],
    ]);

    return $document;
}

test('an unsourced citation is retried once with a stricter instruction and then accepted when gone', function () {
    $calls = 0;
    ChapterAgent::fake(function (string $prompt) use (&$calls) {
        $calls++;

        return ['sections' => [['index' => 0, 'paragraphs' => [$calls === 1 ? 'Menurut (Santoso, 2010) hal ini benar.' : 'Hal ini penting.']]]];
    });

    $document = qaChapterDoc(User::factory()->create());
    $paragraphs = app(ChapterWriter::class)->write($document, 0);

    expect($calls)->toBe(2)
        ->and($paragraphs)->toBe([0 => ['Hal ini penting.']]);
});

test('a chapter that still cites without sources fails with the agreed message and keeps its old blocks', function () {
    ChapterAgent::fake(fn () => ['sections' => [['index' => 0, 'paragraphs' => ['Lihat [1] dan Santoso (2010).']]]]);

    $document = qaChapterDoc(User::factory()->create());
    $job = $document->jobs()->create(['chapter_index' => 0, 'status' => 'queued']);

    ChapterWriterJob::dispatchSync($document, 0, $job->id);

    expect($job->fresh()->status)->toBe('failed')
        ->and($job->fresh()->error)->toBe(ChapterWriter::UNSOURCED)
        ->and($document->fresh()->content['chapters'][0]['sections'][0]['blocks'])->toBe([]);
});

test('with sources on record the writer is not retried for citations', function () {
    $calls = 0;
    ChapterAgent::fake(function () use (&$calls) {
        $calls++;

        return ['sections' => [['index' => 0, 'paragraphs' => ['Menurut Halliday (2010) hal ini benar.']]]];
    });

    $sources = [['id' => (string) Str::uuid(), 'text' => 'Halliday, Fisika Dasar', 'url' => null, 'doi' => null]];
    $paragraphs = app(ChapterWriter::class)->write(qaChapterDoc(User::factory()->create(), $sources), 0);

    expect($calls)->toBe(1)
        ->and($paragraphs[0])->toBe(['Menurut Halliday (2010) hal ini benar.']);
});

test('a double click on approve queues each chapter once', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $document = qaChapterDoc($owner);
    $document->forceFill(['content' => array_merge($document->content, ['brief' => ['input' => 'topic', 'topic' => 'Topik yang cukup panjang', 'chapter_count' => 3, 'length' => 'sedang']])])->save();

    // Two requests carrying the same stale state: the second must see the approval the first one committed.
    $first = Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document]);
    $second = Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document]);

    $first->call('approve');
    $second->call('approve');
    $first->call('approve');

    Queue::assertPushed(ChapterWriterJob::class, 1);
    expect($document->jobs()->where('status', 'queued')->count())->toBe(1);
});

test('a double click on regenerate queues the chapter only once', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $document = qaChapterDoc($owner);

    $page = Livewire::actingAs($owner)->test('pages::documents.outline', ['document' => $document]);
    $page->call('regenerate', 0)->call('regenerate', 0);

    Queue::assertPushed(ChapterWriterJob::class, 1);
    expect($document->jobs()->where('chapter_index', 0)->count())->toBe(1);
});

test('the ai-generate budget grants five spends a minute and then refuses, even when called back to back', function () {
    $this->actingAs(User::factory()->create());

    $granted = collect(range(1, 7))->map(fn () => app(GenerationLimiter::class)->consume());

    expect($granted->filter()->count())->toBe(5)
        ->and($granted->last())->toBeFalse();
});
