<?php

use App\Http\Controllers\Settings\AdvertisementController;
use App\Http\Controllers\Settings\LogoController;
use App\Http\Controllers\Settings\PriceManagementController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\Settings\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/payment-tenders', [\App\Http\Controllers\Settings\PaymentTenderSettingsController::class, 'index'])
        ->name('settings.payment-tenders')
        ->middleware('role:admin');

    // User management (admin only)
    Route::get('settings/users', [UserManagementController::class, 'index'])
        ->name('settings.users')
        ->middleware('role:admin');
    Route::post('settings/users', [UserManagementController::class, 'store'])
        ->name('settings.users.store')
        ->middleware('role:admin');
    Route::patch('settings/users/{user}', [UserManagementController::class, 'update'])
        ->name('settings.users.update')
        ->middleware('role:admin');
    Route::delete('settings/users/{user}', [UserManagementController::class, 'destroy'])
        ->name('settings.users.destroy')
        ->middleware('role:admin');

    // Logo (admin only)
    Route::get('settings/logo', [LogoController::class, 'edit'])
        ->name('settings.logo')
        ->middleware('role:admin');

    // System (admin only)
    Route::get('settings/system', [SystemController::class, 'index'])
        ->name('settings.system')
        ->middleware('role:admin');
    Route::post('settings/system/reset', [SystemController::class, 'reset'])
        ->name('settings.system.reset')
        ->middleware('role:admin');
    Route::post('settings/system/factory-reset', [SystemController::class, 'factoryReset'])
        ->name('settings.system.factory-reset')
        ->middleware('role:admin');
    Route::post('settings/logo', [LogoController::class, 'update'])
        ->name('settings.logo.update')
        ->middleware('role:admin');
    Route::delete('settings/logo', [LogoController::class, 'destroy'])
        ->name('settings.logo.destroy')
        ->middleware('role:admin');

    // Price management (admin only)
    Route::get('settings/prices', [PriceManagementController::class, 'index'])
        ->name('settings.prices')
        ->middleware('role:admin');
    Route::patch('settings/prices', [PriceManagementController::class, 'update'])
        ->name('settings.prices.update')
        ->middleware('role:admin');

    // Advertisements (admin only)
    Route::get('settings/advertisements', [AdvertisementController::class, 'index'])
        ->name('settings.advertisements')
        ->middleware('role:admin');
    Route::post('settings/advertisements', [AdvertisementController::class, 'store'])
        ->name('settings.advertisements.store')
        ->middleware('role:admin');
    Route::patch('settings/advertisements/{advertisement}', [AdvertisementController::class, 'update'])
        ->name('settings.advertisements.update')
        ->middleware('role:admin');
    Route::delete('settings/advertisements/{advertisement}', [AdvertisementController::class, 'destroy'])
        ->name('settings.advertisements.destroy')
        ->middleware('role:admin');
    Route::post('settings/advertisements/{advertisement}/toggle', [AdvertisementController::class, 'toggle'])
        ->name('settings.advertisements.toggle')
        ->middleware('role:admin');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    Route::inertia('settings/printing', 'settings/Printing')->name('settings.printing');
});
