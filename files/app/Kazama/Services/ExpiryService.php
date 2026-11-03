<?php

namespace Pterodactyl\Kazama\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\Settings;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Servers\SuspensionService;

class ExpiryService
{
    public function __construct(
        private SuspensionService $suspension,
        private ServerDeletionService $deletion
    ) {
    }

    public function run(): array
    {
        $stats = ['warned' => 0, 'suspended' => 0, 'deleted' => 0];
        $grace = Settings::int('grace_days', (int) config('kazama.server.grace_days', 3));
        $now = Carbon::now('UTC');

        Server::withoutGlobalScopes()->whereNotNull('kazama_expires_at')->orderBy('id')->chunkById(100, function ($servers) use (&$stats, $grace, $now) {
            foreach ($servers as $server) {
                try {
                    $expires = Carbon::parse($server->getRawOriginal('kazama_expires_at'), 'UTC');
                    $deleteAt = $expires->copy()->addDays($grace);
                    $key = $server->id . ':' . $expires->timestamp;

                    if ($now->greaterThanOrEqualTo($deleteAt)) {
                        Notifier::send($server->owner_id, 'Server dihapus', "Server {$server->name} dihapus karena tidak diperpanjang.", 'danger', null, "deleted:$key");
                        $this->deletion->withForce(true)->handle($server);
                        ++$stats['deleted'];
                    } elseif ($now->greaterThanOrEqualTo($expires)) {
                        if (!$server->kazama_expired || $server->status !== Server::STATUS_SUSPENDED) {
                            if ($server->status !== Server::STATUS_SUSPENDED) {
                                $this->suspension->toggle($server, SuspensionService::ACTION_SUSPEND);
                            }
                            DB::table('servers')->where('id', $server->id)->update(['kazama_expired' => true]);
                            ++$stats['suspended'];
                        }
                        Notifier::send($server->owner_id, 'Server kedaluwarsa', "Server {$server->name} disuspend karena masa aktif habis. Perpanjang dalam {$grace} hari atau server akan dihapus.", 'danger', $server->id, "expired:$key");
                    } else {
                        $daysLeft = (int) ceil(($expires->getTimestamp() - $now->getTimestamp()) / 86400);
                        if ($daysLeft <= 1) {
                            Notifier::send($server->owner_id, 'Server segera berakhir', "Server {$server->name} berakhir dalam kurang dari 1 hari (" . Cadence::wib($expires) . ').', 'danger', $server->id, "warn1:$key");
                            ++$stats['warned'];
                        } elseif ($daysLeft <= 3) {
                            Notifier::send($server->owner_id, 'Server akan berakhir', "Server {$server->name} berakhir dalam {$daysLeft} hari (" . Cadence::wib($expires) . ').', 'warning', $server->id, "warn3:$key");
                            ++$stats['warned'];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('[kazama:expiry] server ' . $server->id . ': ' . $e->getMessage());
                }
            }
        });

        return $stats;
    }

    /** Perpanjang masa aktif; bila sedang disuspend karena expired, otomatis diaktifkan kembali. */
    public function extend(Server $server, int $days): Carbon
    {
        $current = $server->getRawOriginal('kazama_expires_at');
        $base = $current ? Carbon::parse($current, 'UTC') : Carbon::now('UTC');
        if ($base->lessThan(Carbon::now('UTC'))) {
            $base = Carbon::now('UTC');
        }
        $new = $base->copy()->addDays($days);

        DB::table('servers')->where('id', $server->id)->update([
            'kazama_expires_at' => $new->format('Y-m-d H:i:s'),
        ]);

        if ($server->kazama_expired) {
            DB::table('servers')->where('id', $server->id)->update(['kazama_expired' => false]);
            if ($server->status === Server::STATUS_SUSPENDED) {
                $this->suspension->toggle($server->refresh(), SuspensionService::ACTION_UNSUSPEND);
            }
        }

        Notifier::send($server->owner_id, 'Server diperpanjang', "Masa aktif server {$server->name} diperpanjang {$days} hari hingga " . Cadence::wib($new) . '.', 'success', $server->id);

        return $new;
    }
}
