<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MarkerFlow
{
    public static function allowedTypes(Collection $marks): array
    {
        $types = $marks->pluck('mark_type');
        if ($types->contains('exit')) return [];
        if (!$types->contains('entry')) return $types->isEmpty() ? ['entry'] : [];
        if (!$types->contains('break_out') && !$types->contains('break_in')) return ['break_out', 'exit'];
        if ($types->contains('break_out') && !$types->contains('break_in')) return ['break_in', 'exit'];
        return ['exit'];
    }
}
