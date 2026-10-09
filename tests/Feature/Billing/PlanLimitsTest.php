<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function planDocument(User $user, array $overrides = []): Document
{
    return Document::factory()->for($user)->create($overrides + [
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
}

test('free users are limited to the monthly document quota, pro users are not', function () {
    $free = User::factory()->create();
    Document::factory()->for($free)->count(3)->create();

    expect(app(PlanLimits::class)->canCreateDocument($free))->toBeFalse();

    $pro = User::factory()->pro()->create();
    Document::factory()->for($pro)->count(5)->create();

    expect(app(PlanLimits::class)->canCreateDocument($pro))->toBeTrue();
});

test('only documents created this month count against the quota', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->count(3)->create(['created_at' => now()->subMonth()]);

    expect(app(PlanLimits::class)->documentsThisMonth($user))->toBe(0)
        ->and(app(PlanLimits::class)->canCreateDocument($user))->toBeTrue();
});

test('deleting a document does not free quota', function () {
    $user = User::factory()->create();
    $documents = Document::factory()->for($user)->count(3)->create();

    $documents->first()->delete();

    expect(app(PlanLimits::class)->documentsThisMonth($user))->toBe(3)
        ->and(app(PlanLimits::class)->canCreateDocument($user))->toBeFalse();
});

test('pdf export is pro only, extending stacks, and pro expires', function () {
    $user = User::factory()->create();
    $plans = app(PlanLimits::class);

    expect($plans->canExportPdf($user))->toBeFalse();

    $user->extendPro(30);
    expect($plans->canExportPdf($user))->toBeTrue()
        ->and($user->pro_until->isBetween(now()->addDays(29), now()->addDays(31)))->toBeTrue();

    $user->extendPro(30);
    expect($user->pro_until->isAfter(now()->addDays(59)))->toBeTrue();

    $user->forceFill(['pro_until' => now()->subSecond()])->save();
    expect($user->isPro())->toBeFalse();
});

test('quota summary reports usage and pro state', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->count(2)->create();

    expect(app(PlanLimits::class)->quotaSummary($user))
        ->toMatchArray(['used' => 2, 'limit' => 3, 'isPro' => false, 'proUntil' => null]);
});

test('create page blocks a free user over quota and shows the upgrade call to action', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->count(3)->create();

    $this->actingAs($user)->get(route('documents.create'))
        ->assertOk()
        ->assertSee('data-test="quota-full"', false)
        ->assertSee('data-test="upgrade-cta"', false);

    Livewire::actingAs($user)->test('pages::documents.create')
        ->set('title', 'Judul Baru')
        ->set('rawText', str_repeat('Kalimat uji. ', 20))
        ->call('save')
        ->assertHasErrors('quota');

    expect(Document::where('user_id', $user->id)->count())->toBe(3);
});

test('duplicating counts against the quota', function () {
    $user = User::factory()->create();
    $documents = Document::factory()->for($user)->count(3)->create();

    Livewire::actingAs($user)->test('document-table')->call('duplicate', $documents->first()->id);

    expect(Document::where('user_id', $user->id)->count())->toBe(3);
});

test('free users get 403 on pdf downloads, docx still works, pro gets pdf', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fake')]);
    $free = User::factory()->create();
    $document = planDocument($free);

    $this->actingAs($free)->get($document->downloadUrl('pdf'))->assertForbidden();
    $this->actingAs($free)->get($document->downloadUrl())->assertOk();

    $pro = User::factory()->pro()->create();
    $this->actingAs($pro)->get(planDocument($pro)->downloadUrl('pdf'))->assertOk();
});

test('free user sees locked pdf entries and pro does not', function () {
    $free = User::factory()->create();
    $document = planDocument($free);

    $this->actingAs($free)->get(route('documents.show', $document))->assertSee('data-test="download-pdf-locked"', false);
    $this->actingAs($free)->get(route('documents.preview', $document))->assertSee('data-test="accurate-preview-locked"', false);

    $pro = User::factory()->pro()->create();
    $this->actingAs($pro)->get(route('documents.show', planDocument($pro)))->assertDontSee('download-pdf-locked', false);
});

test('sidebar shows real quota for free and the expiry date for pro', function () {
    $free = User::factory()->create();
    Document::factory()->for($free)->count(2)->create();

    $this->actingAs($free)->get(route('dashboard'))->assertSee('2 dari 3 dokumen bulan ini');

    $pro = User::factory()->pro()->create();
    $this->actingAs($pro)->get(route('dashboard'))->assertSee('data-test="pro-until"', false);
});
