<?php

namespace Pterodactyl\Kazama;

use Carbon\Carbon;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pterodactyl\Kazama\Console\AutoBackupCommand;
use Pterodactyl\Kazama\Console\BackfillCommand;
use Pterodactyl\Kazama\Console\ExpiryCommand;
use Pterodactyl\Kazama\Console\PanelBackupCommand;
use Pterodactyl\Kazama\Http\Middleware\EmailLoginOnly;
use Pterodactyl\Kazama\Support\OwnedScope;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\Settings;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

class KazamaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/kazama'), 'kazama');

        if (!$this->app->routesAreCached()) {
            $this->loadRoutesFrom(base_path('routes/kazama.php'));
        }

        $this->app['router']->pushMiddlewareToGroup('web', EmailLoginOnly::class);

        $this->commands([ExpiryCommand::class, AutoBackupCommand::class, PanelBackupCommand::class, BackfillCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('kazama:expiry')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('kazama:auto-backup')->everyMinute()->withoutOverlapping();
            $schedule->command('kazama:panel-backup --auto')->everyMinute()->withoutOverlapping();
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

        Server::updating(function (Server $server) {
            $actor = Restrict::actor();
            if ($actor && $server->isDirty('owner_id') && !Restrict::allowedOwner($actor, (int) $server->owner_id)) {
                abort(403, 'Akses terbatas: pemilik server harus akun yang Anda buat.');
            }
        });

        Server::deleting(function () {
            if (Restrict::actor()) {
                abort(403, 'Akses terbatas: Anda tidak dapat menghapus server.');
            }
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
