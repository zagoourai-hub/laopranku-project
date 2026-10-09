<?php

use App\Http\Controllers\MidtransNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('upgrade', 'pages::billing.upgrade')->name('billing.upgrade');
    Route::livewire('settings/billing', 'pages::settings.billing')->name('settings.billing');
});

// Server-to-server from Midtrans: no session/CSRF, authenticity = signature_key + status re-check.
Route::post('midtrans/notification', MidtransNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('midtrans.notification');
