<?php

namespace Pterodactyl\Kazama;

use Carbon\Carbon;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pterodactyl\Kazama\Console\AutoBackupCommand;
use Pterodactyl\Kazama\Console\BackfillCommand;
use Pterodactyl\Kazama\Console\ExpiryCommand;
use Pterodactyl\Kazama\Console\McpPruneCommand;
use Pterodactyl\Kazama\Console\PanelBackupCommand;
use Pterodactyl\Kazama\Console\TrashPurgeCommand;
use Pterodactyl\Kazama\Http\Middleware\EmailLoginOnly;
use Pterodactyl\Kazama\Services\Notifier;
use Pterodactyl\Kazama\Support\Actor;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\KLog;
use Pterodactyl\Kazama\Support\OwnedScope;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\Settings;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

class KazamaServiceProvider extends ServiceProvider
{
    /** @var array<int,array> info server yang sedang dihapus (alamat, pembuat) */
    private array $deleteInfo = [];

    /** Jalankan setelah model tersimpan (hindari log untuk perubahan yang gagal). */
    private function afterSave(callable $fn): void
    {
        DB::afterCommit($fn);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/kazama'), 'kazama');

        if (!$this->app->routesAreCached()) {
            $this->loadRoutesFrom(base_path('routes/kazama.php'));
        }

        $this->app['router']->pushMiddlewareToGroup('web', EmailLoginOnly::class);

        $this->commands([ExpiryCommand::class, AutoBackupCommand::class, PanelBackupCommand::class, BackfillCommand::class, TrashPurgeCommand::class, McpPruneCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('kazama:expiry')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('kazama:auto-backup')->everyMinute()->withoutOverlapping();
            $schedule->command('kazama:panel-backup --auto')->everyMinute()->withoutOverlapping();
            $schedule->command('kazama:trash-purge')->hourly()->withoutOverlapping();
            $schedule->command('kazama:mcp-prune')->hourly()->withoutOverlapping();
        });

        // Penegakan "Akses Terbatas" (UI admin + Application API + Client API).
        Event::listen(Authenticated::class, function (Authenticated $event) {
            if (!$this->app->runningInConsole()) {
                Restrict::enforce($this->app['request'], $event->user);
            }
        });

        $this->registerModelHooks();
    }

    private function registerModelHooks(): void
    {
        Server::addGlobalScope('kazama_owned', new OwnedScope('server'));
        User::addGlobalScope('kazama_owned', new OwnedScope('user'));

        Server::creating(function (Server $server) {
            $user = $this->app->runningInConsole() ? null : Auth::user();

            if ($user && $server->kazama_created_by === null) {
                $server->kazama_created_by = $user->id;
            }
            if ($server->kazama_expires_at === null) {
                $days = Settings::int('active_days', (int) config('kazama.server.active_days', 30));
                $server->kazama_expires_at = Carbon::now('UTC')->addDays($days)->format('Y-m-d H:i:s');
            }
            if (Restrict::applies($user) && !Restrict::allowedOwner($user, (int) $server->owner_id)) {
                abort(403, 'Akses terbatas: pemilik server harus akun yang Anda buat.');
            }
        });

        Server::created(function (Server $server) {
            KLog::write('server.create', ['server' => $server, 'detail' => 'Masa aktif hingga ' . Cadence::wib($server->kazama_expires_at)]);
        });

        Server::updating(function (Server $server) {
            $actor = Restrict::actor();
            if ($actor && $server->isDirty('owner_id') && !Restrict::allowedOwner($actor, (int) $server->owner_id)) {
                abort(403, 'Akses terbatas: pemilik server harus akun yang Anda buat.');
            }

            // Keterangan + nama pelaku setiap suspend / unsuspend.
            if ($server->isDirty('status')) {
                $new = $server->status;
                $old = $server->getOriginal('status');
                if ($new === Server::STATUS_SUSPENDED && $old !== Server::STATUS_SUSPENDED) {
                    $who = Actor::name();
                    $why = Actor::reason();
                    $server->kazama_suspended_by = $who;
                    $server->kazama_suspend_reason = $why;
                    $server->kazama_suspended_at = Carbon::now('UTC')->format('Y-m-d H:i:s');
                    $this->afterSave(function () use ($server, $who, $why) {
                        KLog::write('server.suspend', ['server' => $server, 'detail' => $why]);
                        Notifier::send($server->owner_id, 'Server disuspend', "Server {$server->name} disuspend oleh {$who}. Keterangan: {$why}", 'danger', $server->id);
                    });
                } elseif ($old === Server::STATUS_SUSPENDED && $new !== Server::STATUS_SUSPENDED) {
                    $server->kazama_suspended_by = null;
                    $server->kazama_suspend_reason = null;
                    $server->kazama_suspended_at = null;
                    $this->afterSave(function () use ($server) {
                        KLog::write('server.unsuspend', ['server' => $server]);
                        Notifier::send($server->owner_id, 'Server diaktifkan kembali', "Server {$server->name} diaktifkan kembali oleh " . Actor::name() . '.', 'success', $server->id);
                    });
                }
            }
        });

        Server::deleting(function (Server $server) {
            if (Restrict::actor()) {
                abort(403, 'Akses terbatas: Anda tidak dapat menghapus server.');
            }
            $alloc = DB::table('allocations')->where('id', $server->allocation_id)->first(['ip', 'ip_alias', 'port']);
            $this->deleteInfo[$server->id] = [
                'address' => $alloc ? (($alloc->ip_alias ?: $alloc->ip) . ':' . $alloc->port) : null,
                'created_by' => DB::table('users')->where('id', $server->kazama_created_by)->value('username') ?: 'Sistem',
            ];
        });

        Server::deleted(function (Server $server) {
            $who = Actor::name();
            $why = Actor::reason();
            $info = $this->deleteInfo[$server->id] ?? ['address' => null, 'created_by' => 'Sistem'];
            DB::table('kazama_tombstones')->insert([
                'server_name' => $server->name, 'owner_id' => $server->owner_id, 'address' => $info['address'], 'created_by_name' => $info['created_by'],
                'deleted_by_name' => $who, 'reason' => $why, 'deleted_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'expires_at' => Carbon::now('UTC')->addDay()->format('Y-m-d H:i:s'),
            ]);
            KLog::write('server.delete', ['server' => ['id' => $server->id, 'name' => $server->name], 'detail' => $why]);
            Notifier::send($server->owner_id, 'Server dihapus', "Server {$server->name} dihapus oleh {$who}. Keterangan: {$why}", 'danger');
            DB::table('kazama_auto_backups')->where('server_id', $server->id)->delete();
            DB::table('kazama_console_lines')->where('server_id', $server->id)->delete();
        });

        User::created(function (User $user) {
            KLog::write('user.create', ['target' => $user->username, 'detail' => $user->email]);
        });

        User::creating(function (User $user) {
            $actor = $this->app->runningInConsole() ? null : Auth::user();
            if ($actor) {
                $user->kazama_created_by = $actor->id;
            }
            if (Restrict::applies($actor)) {
                $user->root_admin = false;
            }
        });

        User::updating(function (User $user) {
            if (Restrict::actor() && $user->isDirty('root_admin')) {
                $user->root_admin = (bool) $user->getOriginal('root_admin');
            }
        });

        User::deleting(function () {
            if (Restrict::actor()) {
                abort(403, 'Akses terbatas: Anda tidak dapat menghapus user.');
            }
        });
    }
}
