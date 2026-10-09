<?php

use App\Http\Controllers\McpDownloadController;
use App\Http\Middleware\DenyGuestUsers;
use App\Mcp\Servers\LaporankuServer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

RateLimiter::for('mcp', fn ($request) => Limit::perMinute(60)->by($request->user()?->currentAccessToken()->id ?? $request->ip()));

// Link handed out by the export tool: no session, access comes from the signature (see McpDownloadController).
Route::get('mcp/downloads/{document}', McpDownloadController::class)
    ->middleware(['signed', 'throttle:downloads'])
    ->name('mcp.download');

// Personal access token (Sanctum); guests cannot use MCP.
Mcp::web('/mcp', LaporankuServer::class)->middleware(['auth:sanctum', DenyGuestUsers::class, 'throttle:mcp']);
