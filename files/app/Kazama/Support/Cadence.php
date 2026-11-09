<?php

namespace Pterodactyl\Kazama\Support;

use Carbon\Carbon;

final class Cadence
{
    public const FREQUENCIES = ['hourly', 'daily', 'weekly'];

    /** Waktu eksekusi berikutnya (UTC) untuk jadwal yang ditulis dalam WIB. */
    public static function next(string $frequency, string $time = '02:00', int $weekday = 1, ?Carbon $from = null): Carbon
    {
        $tz = config('kazama.timezone', 'Asia/Jakarta');
        $now = ($from ? $from->copy() : Carbon::now('UTC'))->setTimezone($tz);

        $parts = explode(':', $time);
        $h = max(0, min(23, (int) ($parts[0] ?? 2)));
        $m = max(0, min(59, (int) ($parts[1] ?? 0)));

        if ($frequency === 'hourly') {
            $next = $now->copy()->setTime($now->hour, $m, 0);
            if ($next->lessThanOrEqualTo($now)) {
                $next->addHour();
            }
        } elseif ($frequency === 'weekly') {
            $next = $now->copy()->setTime($h, $m, 0);
            while ($next->lessThanOrEqualTo($now) || $next->dayOfWeek !== $weekday) {
                $next->addDay();
            }
        } else {
            $next = $now->copy()->setTime($h, $m, 0);
            if ($next->lessThanOrEqualTo($now)) {
                $next->addDay();
            }
        }

        return $next->setTimezone('UTC');
    }

    public static function wib($value, string $format = 'd M Y H:i'): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value, 'UTC')->setTimezone(config('kazama.timezone', 'Asia/Jakarta'))->format($format) . ' WIB';
    }
}
