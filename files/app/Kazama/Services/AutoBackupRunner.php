<?php

namespace Pterodactyl\Kazama\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Backups\InitiateBackupService;

class AutoBackupRunner
{
    public function __construct(private InitiateBackupService $backups)
    {
    }

    public function run(): int
    {
        $ran = 0;
        $due = DB::table('kazama_auto_backups')->where('enabled', true)
            ->whereNotNull('next_run_at')->where('next_run_at', '<=', Carbon::now('UTC')->format('Y-m-d H:i:s'))->get();

        foreach ($due as $cfg) {
            $now = Carbon::now('UTC');
            $next = Cadence::next($cfg->frequency, $cfg->time, (int) $cfg->weekday, $now);
            DB::table('kazama_auto_backups')->where('id', $cfg->id)->update([
                'last_run_at' => $now->format('Y-m-d H:i:s'),
                'next_run_at' => $next->format('Y-m-d H:i:s'),
            ]);

            $server = Server::withoutGlobalScopes()->find($cfg->server_id);
            if (!$server || $server->status !== null) {
                continue; // sedang install/suspend/transfer → lewati
            }

            try {
                $label = 'Auto ' . now(config('kazama.timezone'))->format('d-m-Y H:i') . ' WIB';
                $this->backups->handle($server, $label, true);
                ++$ran;
            } catch (\Throwable $e) {
                Log::error("[kazama:auto-backup] server {$server->id}: " . $e->getMessage());
            }
        }

        return $ran;
    }
}
