<?php

use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// In routes/web.php
Route::prefix('auth/{provider}')->middleware(['throttle:10,1'])->group(function () {
    Route::get('redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});
