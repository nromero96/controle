<?php

namespace App\Support;

class AttendanceTime
{
    public static function minutes(?string $time): string
    {
        return $time ? implode(':', array_slice(explode(':', $time), 0, 2)) : '—';
    }

    public static function duration(?int $seconds): string
    {
        return $seconds === null ? '—' : sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
