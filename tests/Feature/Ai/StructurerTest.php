<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Jobs\StructureDocument;
use App\Models\Document;
use App\Services\Ai\ParagraphLabeler;
use App\Services\Ai\Structurer;
use App\Services\Documents\SchemaValidator;
use App\Services\Documents\TextNormalizer;

/**
 * Fake model: labels every "pN: text" line of the prompt with $labeler(text).
 */
function fakeLabels(Closure $labeler): void
{
    ParagraphLabeler::fake(function (string $prompt) use ($labeler): array {
        preg_match_all('/^(p\d+): (.*)$/m', $prompt, $m, PREG_SET_ORDER);

        return ['labels' => array_map(fn (array $line): array => ['id' => $line[1], 'label' => $labeler($line[2])], $m)];
    });
}

/** Heuristic stand-in for the model: short lines without a full stop are headings. */
function headingLabeler(string $text): string
{
    return match (true) {
        str_starts_with($text, '- ') => 'bullet',
        (bool) preg_match('/^\d+\. /', $text) => 'numbered',
        str_ends_with($text, '.') => 'paragraph',
        in_array($text, ['Pendahuluan', 'Pembahasan', 'Penutup'], true) => 'chapter',
        default => 'section',
    };
}

/** @return list<string> every text in the chapters, in reading order */
function structuredTexts(array $chapters): array
{
    $texts = [];

    foreach ($chapters as $chapter) {
        $texts[] = $chapter['title'];

        foreach ($chapter['sections'] as $section) {
            $texts[] = $section['title'];

            foreach ($section['blocks'] as $block) {
                array_push($texts, ...($block['type'] === 'list' ? $block['items'] : [$block['text']]));
            }
        }
    }

    return array_values(array_filter($texts, fn (string $t): bool => $t !== Structurer::PLACEHOLDER));
}

function withChapters(array $chapters): array
{
    $content = json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true);
    $content['chapters'] = $chapters;

    return $content;
}

$raw = <<<'TXT'
Pendahuluan

Latar Belakang

Media sosial dipakai setiap hari oleh pelajar.

- Instagram

- TikTok

Rumusan Masalah

1. Bagaimana pola penggunaannya?

2. Apa dampaknya?

Pembahasan

Pola Penggunaan

Rata-rata pemakaian tiga jam sehari.
TXT;

test('labels become chapters, sections, paragraphs and lists that pass the schema', function () use ($raw) {
    fakeLabels(headingLabeler(...));

    $chapters = app(Structurer::class)->structure(app(TextNormalizer::class)->normalize($raw), DocumentType::Makalah);

    expect(array_column($chapters, 'title'))->toBe(['Pendahuluan', 'Pembahasan'])
        ->and(array_column($chapters[0]['sections'], 'title'))->toBe(['Latar Belakang', 'Rumusan Masalah'])
        ->and($chapters[0]['sections'][0]['blocks'][1])->toMatchArray(['type' => 'list', 'ordered' => false, 'items' => ['Instagram', 'TikTok']])
        ->and($chapters[0]['sections'][1]['blocks'][0])->toMatchArray(['type' => 'list', 'ordered' => true, 'items' => ['Bagaimana pola penggunaannya?', 'Apa dampaknya?']])
        ->and(app(SchemaValidator::class)->validate(withChapters($chapters)))->toBe([]);

    ParagraphLabeler::assertPrompted(fn ($prompt) => $prompt->contains('p1: Pendahuluan'));
});

test('every input sentence survives word for word, apart from list markers', function () {
    $sentences = collect(range(1, 300))->map(fn (int $i): string => "Kalimat ke-{$i} berisi data asli yang tidak boleh berubah.");
    $raw = "Pendahuluan\n\n".$sentences->take(150)->implode("\n\n")."\n\nPenutup\n\n".$sentences->skip(150)->implode("\n\n");
    config(['ai.structure_chunk_words' => 200]);
    fakeLabels(headingLabeler(...));

    $paragraphs = app(TextNormalizer::class)->normalize($raw);
    $chapters = app(Structurer::class)->structure($paragraphs, DocumentType::Makalah);

    expect(structuredTexts($chapters))->toBe(array_column($paragraphs, 'text'))
        ->and(app(SchemaValidator::class)->validate(withChapters($chapters)))->toBe([]);
});

test('long texts are labeled in chunks that carry the last heading', function () {
    $raw = "Pendahuluan\n\n".collect(range(1, 40))->map(fn (int $i): string => "Paragraf {$i} punya sepuluh kata isi yang cukup panjang di sini.")->implode("\n\n");
    config(['ai.structure_chunk_words' => 100]);
    $prompts = 0;
    ParagraphLabeler::fake(function (string $prompt) use (&$prompts): array {
        $prompts++;
        preg_match_all('/^(p\d+): (.*)$/m', $prompt, $m, PREG_SET_ORDER);

        return ['labels' => array_map(fn (array $l): array => ['id' => $l[1], 'label' => headingLabeler($l[2])], $m)];
    });

    app(Structurer::class)->structure(app(TextNormalizer::class)->normalize($raw), DocumentType::Makalah);

    expect($prompts)->toBeGreaterThan(3);
    ParagraphLabeler::assertPrompted(fn ($prompt) => $prompt->contains('Judul terakhir sebelum bagian ini: bab: Pendahuluan'));
});

test('missing, duplicate and unknown ids from the model never lose or duplicate text', function () {
    $paragraphs = [
        ['id' => 'p1', 'text' => 'Pendahuluan'],
        ['id' => 'p2', 'text' => 'Paragraf yang tidak diberi label.'],
        ['id' => 'p3', 'text' => 'Paragraf kedua.'],
        ['id' => 'p4', 'text' => 'Kalimat yang AI coba buang.'],
        ['id' => 'p5', 'text' => 'IV.'],
    ];
    ParagraphLabeler::fake([['labels' => [
        ['id' => 'p1', 'label' => 'chapter'],
        ['id' => 'p3', 'label' => 'paragraph'],
        ['id' => 'p3', 'label' => 'chapter'],
        ['id' => 'p99', 'label' => 'chapter'],
        ['id' => 'p4', 'label' => 'drop'],
        ['id' => 'p5', 'label' => 'drop'],
    ]]]);

    $chapters = app(Structurer::class)->structure($paragraphs, DocumentType::Makalah);

    expect($chapters)->toHaveCount(1)
        ->and(structuredTexts($chapters))->toBe(['Pendahuluan', 'Paragraf yang tidak diberi label.', 'Paragraf kedua.', 'Kalimat yang AI coba buang.'])
        ->and($chapters[0]['sections'][0]['title'])->toBe(Structurer::PLACEHOLDER);
});

test('manual numbers are stripped from headings so the schema accepts them', function () {
    ParagraphLabeler::fake([['labels' => [['id' => 'p1', 'label' => 'chapter'], ['id' => 'p2', 'label' => 'section'], ['id' => 'p3', 'label' => 'paragraph']]]]);

    $chapters = app(Structurer::class)->structure([
        ['id' => 'p1', 'text' => 'BAB Pendahuluan'],
        ['id' => 'p2', 'text' => '1. Latar Belakang'],
        ['id' => 'p3', 'text' => 'Isi.'],
    ], DocumentType::Makalah);

    expect($chapters[0]['title'])->toBe('Pendahuluan')
        ->and($chapters[0]['sections'][0]['title'])->toBe('Latar Belakang')
        ->and(app(SchemaValidator::class)->validate(withChapters($chapters)))->toBe([]);
});

test('the job saves the structure and records the run', function () use ($raw) {
    fakeLabels(headingLabeler(...));
    $content = withChapters([]);
    $content['brief']['raw_text'] = $raw;
    $document = Document::factory()->create(['content' => $content, 'title' => $content['cover']['title']]);

    StructureDocument::dispatchSync($document);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and(array_column($document->content['chapters'], 'title'))->toBe(['Pendahuluan', 'Pembahasan'])
        ->and($document->jobs()->sole()->status)->toBe('done')
        ->and($document->content['brief'] ?? [])->not->toHaveKeys(['raw_text', 'paragraphs']);
});

test('an AI error is retried once, then the job is marked failed and the document untouched', function () use ($raw) {
    $calls = 0;
    ParagraphLabeler::fake(function () use (&$calls): never {
        $calls++;
        throw new RuntimeException('provider down');
    });
    $content = withChapters([]);
    $content['brief']['raw_text'] = $raw;
    $document = Document::factory()->create(['content' => $content, 'title' => $content['cover']['title']]);

    rescue(fn () => StructureDocument::dispatchSync($document), report: false);

    $document->refresh();
    $job = $document->jobs()->sole();
    expect($calls)->toBe(2)
        ->and($job->status)->toBe('failed')
        ->and($job->error)->toContain('provider down')
        ->and($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->content['chapters'])->toBe([])
        ->and($document->content['brief']['raw_text'])->toBe($raw);
});
