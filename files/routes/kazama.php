<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Kazama\Http\Controllers\Admin\AdminController;
use Pterodactyl\Kazama\Http\Controllers\Admin\PanelBackupController;
use Pterodactyl\Kazama\Http\Controllers\ApiController;
use Pterodactyl\Kazama\Http\Controllers\BackgroundController;
use Pterodactyl\Kazama\Http\Controllers\GuideController;
use Pterodactyl\Kazama\Http\Controllers\McpEndpointController;
use Pterodactyl\Kazama\Http\Controllers\McpOAuthController;
use Pterodactyl\Kazama\Http\Controllers\McpPanelController;
use Pterodactyl\Kazama\Http\Controllers\OAuthController;
use Pterodactyl\Kazama\Http\Controllers\RegisterController;
use Pterodactyl\Kazama\Http\Controllers\TrashController;
use Pterodactyl\Kazama\Http\Middleware\McpCors;

/*
 * Prinsip: manfaatkan route lama Pterodactyl. Route di file ini didaftarkan LEBIH DULU daripada route inti,
 * jadi untuk URL yang sama Kazama menangani lebih dulu lalu mendelegasikan ke controller asli bila bukan form Kazama.
 */

// ---- Login: OAuth (Google/GitHub) + pendaftaran + info publik ----
Route::middleware(['web'])->group(function () {
    Route::get('/auth/providers', [RegisterController::class, 'providers']);
    Route::middleware(['guest'])->group(function () {
        Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->where('provider', 'google|github');
        Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->where('provider', 'google|github');
    });
    Route::middleware('throttle:10,1')->post('/auth/register', [RegisterController::class, 'register']);

    // Panduan MCP: publik (tanpa akun), dipersonalisasi bila sedang login.
    Route::get('/guide/mcp', [GuideController::class, 'mcp']);
});

// ---- MCP: endpoint, OAuth 2.1 ----
Route::middleware([McpCors::class])->group(function () {
    Route::get('/.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResource']);
    Route::get('/.well-known/oauth-protected-resource/api/mcp', [McpOAuthController::class, 'protectedResource']);
    Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'metadata']);
    Route::match(['OPTIONS'], '/.well-known/{any}', fn () => response('', 204))->where('any', '.*');

    Route::match(['POST', 'OPTIONS'], '/api/mcp', [McpEndpointController::class, 'handle'])->middleware('throttle:240,1');
    Route::get('/api/mcp', [McpEndpointController::class, 'notAllowed']);
    Route::delete('/api/mcp', [McpEndpointController::class, 'revokeSelf']);
    Route::match(['POST', 'OPTIONS'], '/api/mcp/oauth/register', [McpOAuthController::class, 'register'])->middleware('throttle:30,1');
    Route::match(['POST', 'OPTIONS'], '/api/mcp/oauth/token', [McpOAuthController::class, 'token'])->middleware('throttle:60,1');
});
// Halaman persetujuan (butuh sesi login panel; web group menyediakan sesi + CSRF).
Route::middleware(['web'])->group(function () {
    Route::get('/api/mcp/oauth/authorize', [McpOAuthController::class, 'authorizeForm']);
    Route::post('/api/mcp/oauth/authorize', [McpOAuthController::class, 'authorizeSubmit'])->middleware('auth');
});

// ---- Trash: menimpa route lama "hapus file" (rute lama, perilaku baru: pindah ke Trash 7 hari) ----
Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {
    Route::middleware(['client-api', 'throttle:api.client'])->prefix('/api/client')->scopeBindings()->group(function () {
        Route::post('/servers/{server}/files/delete', [TrashController::class, 'delete'])
            ->middleware([ServerSubject::class, AuthenticateServerAccess::class, ResourceBelongsToServer::class]);
    });
});

// ---- API internal theme (sesi login panel) ----
Route::middleware(['web', 'auth'])->prefix('kazama/api')->group(function () {
    Route::get('/overview', [ApiController::class, 'overview']);
    Route::get('/notifications', [ApiController::class, 'notifications']);
    Route::post('/notifications/read-all', [ApiController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [ApiController::class, 'read'])->whereNumber('id');

    Route::get('/servers/{uuid}/auto-backup', [ApiController::class, 'autoBackup']);
    Route::post('/servers/{uuid}/auto-backup', [ApiController::class, 'saveAutoBackup']);
    Route::get('/servers/{uuid}/console', [ApiController::class, 'consoleLines']);

    Route::get('/servers/{uuid}/trash', [TrashController::class, 'index']);
    Route::post('/servers/{uuid}/trash/empty', [TrashController::class, 'empty']);
    Route::post('/servers/{uuid}/trash/{id}/restore', [TrashController::class, 'restore'])->whereNumber('id');
    Route::delete('/servers/{uuid}/trash/{id}', [TrashController::class, 'purge'])->whereNumber('id');

    Route::get('/background', [BackgroundController::class, 'show']);
    Route::post('/background', [BackgroundController::class, 'update']);
    Route::post('/background/upload', [BackgroundController::class, 'upload']);

    Route::prefix('mcp')->group(function () {
        Route::get('/', [McpPanelController::class, 'overview']);
        Route::post('/defaults', [McpPanelController::class, 'saveDefaults']);
        Route::post('/connections', [McpPanelController::class, 'create']);
        Route::patch('/connections/{id}', [McpPanelController::class, 'update'])->whereNumber('id');
        Route::delete('/connections/{id}', [McpPanelController::class, 'destroy'])->whereNumber('id');
        Route::get('/activity', [McpPanelController::class, 'activity']);
        Route::get('/tools', [McpPanelController::class, 'tools']);
        Route::get('/stats', [McpPanelController::class, 'stats']);
    });
});

// ---- Admin: override route lama (Masa Aktif di halaman server; pengaturan Kazama di halaman Settings) ----
$admin = ['web', 'auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class];

Route::middleware($admin)->prefix('admin')->group(function () {
    Route::patch('/servers/view/{server:id}/details', [AdminController::class, 'serverDetails']);
    Route::patch('/settings', [AdminController::class, 'settings']);
});

// ---- Admin: halaman baru yang memang belum ada (Logs, Notifikasi, Backup Panel) ----
Route::middleware($admin)->prefix('admin/kazama')->group(function () {
    Route::get('/logs', [AdminController::class, 'logs'])->name('kazama.admin.logs');

    Route::get('/notifications', [AdminController::class, 'notifications'])->name('kazama.admin.notifications');
    Route::post('/notifications', [AdminController::class, 'sendNotification'])->name('kazama.admin.notifications.send');
    Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification'])->whereNumber('id')->name('kazama.admin.notifications.delete');

    Route::get('/backup', [PanelBackupController::class, 'index'])->name('kazama.admin.backup');
    Route::post('/backup', [PanelBackupController::class, 'store'])->name('kazama.admin.backup.store');
    Route::post('/backup/settings', [PanelBackupController::class, 'settings'])->name('kazama.admin.backup.settings');
    Route::post('/backup/restore', [PanelBackupController::class, 'restore'])->name('kazama.admin.backup.restore');
    Route::get('/backup/{name}', [PanelBackupController::class, 'download'])->name('kazama.admin.backup.download');
    Route::delete('/backup/{name}', [PanelBackupController::class, 'destroy'])->name('kazama.admin.backup.delete');
});
