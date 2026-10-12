<?php

use App\Enums\EducationLevel;
use App\Services\Documents\DocumentLayout;
use App\Services\Documents\SchemaValidator;

/** @return array<string, mixed> */
function layoutContent(array $options = [], array $extra = []): array
{
    $content = json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true);
    $content['options'] = $options;

    return array_replace($content, $extra);
}

test('front matter follows one fixed order and drops what is not switched on', function () {
    $plan = app(DocumentLayout::class)->plan(layoutContent(), EducationLevel::Mahasiswa);

    expect(array_column($plan['front'], 'key'))->toBe(['preface', 'toc'])
        ->and($plan['approval'])->toBeNull()
        ->and($plan['abstract'])->toBeNull();

    $all = app(DocumentLayout::class)->plan(layoutContent(['approval_page' => true, 'abstract' => true], ['abstract' => 'Ringkasan.']), EducationLevel::Mahasiswa);

    expect(array_column($all['front'], 'key'))->toBe(['approval', 'abstract', 'preface', 'toc'])
        ->and($all['abstract'])->toBe('Ringkasan.');
});

test('chapters are numbered by the plan, roman for the chapter and decimal for each section', function () {
    $plan = app(DocumentLayout::class)->plan(layoutContent(), EducationLevel::Mahasiswa);

    expect($plan['chapters'][0]['label'])->toBe('BAB I')
        ->and($plan['chapters'][0]['heading'])->toBe('BAB I PENDAHULUAN')
        ->and($plan['chapters'][0]['sections'][0]['number'])->toBe('1.1')
        ->and($plan['chapters'][1]['label'])->toBe('BAB II');
});

test('every heading has a unique id and the table of contents points at those same ids', function () {
    $plan = app(DocumentLayout::class)->plan(layoutContent(['approval_page' => true, 'appendices' => true], [
        'appendices' => [['id' => '018f0000-0000-4000-8000-0000000000a1', 'title' => 'Kuesioner', 'text' => "Pertanyaan 1\nPertanyaan 2"]],
    ]), EducationLevel::Mahasiswa);

    $ids = array_column($plan['toc'], 'id');

    expect($ids)->each->toBeString()
        ->and(array_unique($ids))->toHaveCount(count($ids))
        ->and($plan['toc'][0]['text'])->toBe('LEMBAR PENGESAHAN')
        ->and(collect($plan['toc'])->last()['text'])->toBe('Kuesioner');
});

test('an empty appendices section still appears with its placeholder', function () {
    $plan = app(DocumentLayout::class)->plan(layoutContent(['appendices' => true]), EducationLevel::Mahasiswa);

    expect($plan['appendices'])->toMatchArray(['title' => 'LAMPIRAN', 'items' => []])
        ->and(collect($plan['toc'])->pluck('text'))->toContain('LAMPIRAN');
});

test('the bibliography is formatted by the citation style and includes sources', function () {
    $plan = app(DocumentLayout::class)->plan(layoutContent(['style_citation' => 'apa'] + [], [
        'style_citation' => 'apa',
        'sources' => [['id' => '018f0000-0000-4000-8000-0000000000b1', 'text' => 'Halliday, D. (2010). Fisika Dasar.', 'url' => null, 'doi' => null]],
    ]), EducationLevel::Mahasiswa);

    expect($plan['references']['entries'])->not->toBeEmpty()
        ->and(implode("\n", $plan['references']['entries']))->toContain('Halliday');
});

test('the plan is built from schema-valid content', function () {
    $content = layoutContent(['approval_page' => true, 'abstract' => true, 'appendices' => true]);

    expect(app(SchemaValidator::class)->validate($content))->toBe([]);
});
