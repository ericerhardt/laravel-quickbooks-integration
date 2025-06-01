<?php

use Illuminate\Support\Facades\Route;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Controllers\QuickBooksAuthController;

/*
|--------------------------------------------------------------------------
| QuickBooks Integration Routes
|--------------------------------------------------------------------------
|
| These routes handle the OAuth flow for QuickBooks Online integration.
| They are automatically registered by the service provider.
|
*/

Route::prefix('quickbooks')->name('quickbooks.')->group(function () {
    
    // OAuth Connection Routes
    Route::get('connect', [QuickBooksAuthController::class, 'connect'])
        ->name('connect')
        ->middleware(['web', 'auth']);
    
    Route::get('callback', [QuickBooksAuthController::class, 'callback'])
        ->name('callback')
        ->middleware(['web']);
    
    Route::post('disconnect', [QuickBooksAuthController::class, 'disconnect'])
        ->name('disconnect')
        ->middleware(['web', 'auth']);
    
    // Status and Error Routes
    Route::get('status', [QuickBooksAuthController::class, 'status'])
        ->name('status')
        ->middleware(['web', 'auth']);
    
    Route::get('error', [QuickBooksAuthController::class, 'error'])
        ->name('error')
        ->middleware(['web', 'auth']);
    
    // Token Refresh Route (for AJAX calls)
    Route::post('refresh-token', [QuickBooksAuthController::class, 'refreshToken'])
        ->name('refresh-token')
        ->middleware(['web', 'auth']);
    
});

