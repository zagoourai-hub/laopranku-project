<?php

use App\Models\Document;
use App\Models\User;

test('owner can view, update, delete and restore their document', function (string $ability) {
    $document = Document::factory()->create();

    expect($document->user->can($ability, $document))->toBeTrue();
})->with(['view', 'update', 'delete', 'restore', 'forceDelete']);

test('other users are forbidden from every ability on the document', function (string $ability) {
    $document = Document::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can($ability, $document))->toBeFalse();
})->with(['view', 'update', 'delete', 'restore', 'forceDelete']);

test('any user can list and create documents', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Document::class))->toBeTrue()
        ->and($user->can('create', Document::class))->toBeTrue();
});

test('document content survives a database round trip and soft delete', function () {
    $content = ['schema_version' => 1, 'chapters' => []];
    $document = Document::factory()->create(['content' => $content]);

    expect($document->fresh()->content)->toEqual($content);

    $document->delete();

    expect(Document::find($document->id))->toBeNull()
        ->and(Document::withTrashed()->find($document->id))->not->toBeNull();
});
