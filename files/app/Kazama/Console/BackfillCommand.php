<?php

namespace Pterodactyl\Kazama\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCommand extends Command
{
    protected $signature = 'kazama:backfill {--days=30 : masa aktif untuk server lama yang belum punya masa aktif}';
    protected $description = 'Kazama: beri masa aktif pada server yang dibuat sebelum theme dipasang.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $n = DB::table('servers')->whereNull('kazama_expires_at')
            ->update(['kazama_expires_at' => now('UTC')->addDays($days)->format('Y-m-d H:i:s')]);
        $this->info("{$n} server diberi masa aktif {$days} hari.");

        return 0;
    }
}
