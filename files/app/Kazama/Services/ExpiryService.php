<?php

namespace Pterodactyl\Kazama\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Kazama\Support\Actor;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\KLog;
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
        $stats = ['warned' => 0, 'suspended' => 0, 'deleted' => 0, 'tombstones_purged' => 0];
        $grace = Settings::int('grace_days', (int) config('kazama.server.grace_days', 3));
        $now = Carbon::now('UTC');

        Server::withoutGlobalScopes()->whereNotNull('kazama_expires_at')->orderBy('id')->chunkById(100, function ($servers) use (&$stats, $grace, $now) {
            foreach ($servers as $server) {
                try {
                    $expires = Carbon::parse($server->getRawOriginal('kazama_expires_at'), 'UTC');
                    $deleteAt = $expires->copy()->addDays($grace);
                    $key = $server->id . ':' . $expires->timestamp;

                    if ($now->greaterThanOrEqualTo($deleteAt)) {
                        Actor::as('Sistem (Masa Aktif)', 'system', "Tidak diperpanjang selama {$grace} hari setelah masa aktif habis ({$expires->setTimezone(config('kazama.timezone'))->format('d M Y H:i')} WIB).");
                        $this->deletion->withForce(true)->handle($server);
                        ++$stats['deleted'];
                    } elseif ($now->greaterThanOrEqualTo($expires)) {
                        if ($server->status !== Server::STATUS_SUSPENDED) {
                            Actor::as('Sistem (Masa Aktif)', 'system', 'Masa aktif server habis. Perpanjang melalui admin sebelum ' . Cadence::wib($deleteAt) . ' atau server akan dihapus.');
                            $this->suspension->toggle($server, SuspensionService::ACTION_SUSPEND);
                            ++$stats['suspended'];
                        }
                        DB::table('servers')->where('id', $server->id)->update(['kazama_expired' => true]);
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
                } finally {
                    Actor::clear();
                }
            }
        });

        $stats['tombstones_purged'] = DB::table('kazama_tombstones')->where('expires_at', '<', $now->format('Y-m-d H:i:s'))->delete();
        DB::table('kazama_console_lines')->where('created_at', '<', $now->copy()->subDay()->format('Y-m-d H:i:s'))->delete();

        return $stats;
    }

    /** Set tanggal berakhir (UTC). Bila server sedang disuspend karena expired dan tanggal baru di masa depan, otomatis diaktifkan. */
    public function set(Server $server, Carbon $newExpiry): Carbon
    {
        $old = $server->getRawOriginal('kazama_expires_at');
        DB::table('servers')->where('id', $server->id)->update(['kazama_expires_at' => $newExpiry->format('Y-m-d H:i:s')]);

        KLog::write('server.expiry', [
            'server' => $server,
            'detail' => 'Dari ' . Cadence::wib($old) . ' menjadi ' . Cadence::wib($newExpiry),
        ]);

        if ($server->kazama_expired && $newExpiry->isFuture()) {
            DB::table('servers')->where('id', $server->id)->update(['kazama_expired' => false]);
            if ($server->status === Server::STATUS_SUSPENDED) {
                $this->suspension->toggle($server->refresh(), SuspensionService::ACTION_UNSUSPEND);
            }
        }

        Notifier::send($server->owner_id, 'Masa aktif diperbarui', "Masa aktif server {$server->name} berlaku hingga " . Cadence::wib($newExpiry) . ' (diubah oleh ' . Actor::name() . ').', 'success', $server->id);

        return $newExpiry;
    }

    public function extend(Server $server, int $days): Carbon
    {
        $current = $server->getRawOriginal('kazama_expires_at');
        $base = $current ? Carbon::parse($current, 'UTC') : Carbon::now('UTC');
        if ($base->lessThan(Carbon::now('UTC'))) {
            $base = Carbon::now('UTC');
        }

        return $this->set($server, $base->copy()->addDays($days));
    }
}
