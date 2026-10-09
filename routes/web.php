<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\GuestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('guest', GuestController::class)
    ->middleware(['guest', 'throttle:10,1'])
    ->name('guest.start');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('documents/create', 'pages::documents.create')->name('documents.create');
    Route::livewire('documents/{document}', 'pages::documents.show')->name('documents.show');
    Route::livewire('documents/{document}/preview', 'pages::documents.preview')->name('documents.preview');
});

Route::get('documents/{document}/download', DocumentDownloadController::class)
    ->middleware(['auth', 'signed', 'throttle:downloads'])
    ->name('documents.download');

require __DIR__.'/settings.php';
require __DIR__.'/billing.php';
