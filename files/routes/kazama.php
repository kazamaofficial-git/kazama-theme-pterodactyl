<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Kazama\Http\Controllers\Admin\AdminController;
use Pterodactyl\Kazama\Http\Controllers\Admin\PanelBackupController;
use Pterodactyl\Kazama\Http\Controllers\ApiController;
use Pterodactyl\Kazama\Http\Controllers\GoogleAuthController;
use Pterodactyl\Kazama\Http\Controllers\RegisterController;

// Autentikasi (Google OAuth + pendaftaran email)
Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
});
Route::middleware(['web', 'throttle:10,1'])->post('/auth/register', RegisterController::class);

// API internal theme (sesi login panel)
Route::middleware(['web', 'auth'])->prefix('kazama/api')->group(function () {
    Route::get('/overview', [ApiController::class, 'overview']);
    Route::get('/notifications', [ApiController::class, 'notifications']);
    Route::post('/notifications/read-all', [ApiController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [ApiController::class, 'read'])->whereNumber('id');
    Route::get('/servers/{uuid}/auto-backup', [ApiController::class, 'autoBackup']);
    Route::post('/servers/{uuid}/auto-backup', [ApiController::class, 'saveAutoBackup']);
});

// Halaman admin
Route::middleware(['web', 'auth', 'admin'])->prefix('admin/kazama')->group(function () {
    Route::get('/expiry', [AdminController::class, 'expiry'])->name('kazama.admin.expiry');
    Route::post('/expiry/{id}/extend', [AdminController::class, 'extend'])->whereNumber('id')->name('kazama.admin.expiry.extend');

    Route::get('/notifications', [AdminController::class, 'notifications'])->name('kazama.admin.notifications');
    Route::post('/notifications', [AdminController::class, 'sendNotification'])->name('kazama.admin.notifications.send');
    Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification'])->whereNumber('id')->name('kazama.admin.notifications.delete');

    Route::get('/settings', [AdminController::class, 'settings'])->name('kazama.admin.settings');
    Route::post('/settings', [AdminController::class, 'saveSettings'])->name('kazama.admin.settings.save');

    Route::get('/backup', [PanelBackupController::class, 'index'])->name('kazama.admin.backup');
    Route::post('/backup', [PanelBackupController::class, 'store'])->name('kazama.admin.backup.store');
    Route::post('/backup/settings', [PanelBackupController::class, 'settings'])->name('kazama.admin.backup.settings');
    Route::post('/backup/restore', [PanelBackupController::class, 'restore'])->name('kazama.admin.backup.restore');
    Route::get('/backup/{name}', [PanelBackupController::class, 'download'])->name('kazama.admin.backup.download');
    Route::delete('/backup/{name}', [PanelBackupController::class, 'destroy'])->name('kazama.admin.backup.delete');
});
