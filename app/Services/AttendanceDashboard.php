<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;

class AttendanceDashboard
{
    public function build(?int $branchId = null): array
    {
        $now = now();
        $today = $now->toDateString();
        $day = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'][$now->format('l')];
        $todayMarks = Attendance::with('employee')->where('mark_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_snapshot_captured', true)->where('recorded_branch_id', $branchId))
            ->orderByDesc('mark_time')->orderByDesc('id')->get();
        $byEmployee = $todayMarks->groupBy('employee_id');
        $scheduled = Employee::where('status', 'activo')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('schedules', fn ($q) => $q->where('day', $day));
        $stats = [
            'scheduled' => (clone $scheduled)->count(),
            'without_entry' => (clone $scheduled)->whereDoesntHave('attendances', fn ($q) => $q->where('mark_date', $today)->where('mark_type', 'entry'))->count(),
            'entered' => $byEmployee->filter(fn ($marks) => $marks->contains('mark_type', 'entry'))->count(),
            'at_lunch' => $byEmployee->filter(fn ($marks) => $marks->contains('mark_type', 'break_out') && !$marks->contains('mark_type', 'break_in') && !$marks->contains('mark_type', 'exit'))->count(),
            'finished' => $byEmployee->filter(fn ($marks) => $marks->contains('mark_type', 'exit'))->count(),
        ];
        $rules = array_map(fn ($key, $default) => (int) Setting::getValue($key, $default),
            ['overtime_minimum_minutes', 'reference_lunch_minutes', 'minimum_lunch_minutes', 'maximum_lunch_minutes', 'lunch_tolerance_minutes', 'exit_tolerance_minutes'],
            [0, 60, 45, 180, 5, 5]);
        $pending = ['review_days' => 0, 'overtime_days' => 0, 'overtime_seconds' => 0];
        $items = collect();
        $calculator = new AttendanceDayCalculator();
        // Solo jornadas con salida: una jornada todavía abierta no es una incidencia de almuerzo.
        Attendance::where('mark_type', 'exit')->where('mark_date', '<=', $today)
            ->when($branchId, fn ($q) => $q->where('branch_snapshot_captured', true)->where('recorded_branch_id', $branchId))
            ->chunkById(100, function ($exits) use (&$pending, &$items, $calculator, $rules) {
                $groups = Attendance::with(['employee', 'lunchAdjustments.recorder'])->where(function ($q) use ($exits) {
                    foreach ($exits as $exit) $q->orWhere(fn ($pair) => $pair->where('employee_id', $exit->employee_id)->where('mark_date', $exit->mark_date));
                })->get()->groupBy(fn ($mark) => $mark->employee_id.'|'.$mark->mark_date);
                foreach ($exits as $exit) {
                    $marks = $groups[$exit->employee_id.'|'.$exit->mark_date];
                    $result = $calculator->calculate($marks, ...$rules);
                    if (!$result['complete']) {
                        $pending['review_days']++;
                        $kind = 'review';
                    } elseif ($result['status'] === 'pending') {
                        $pending['overtime_days']++;
                        $pending['overtime_seconds'] += $result['eligible'];
                        $kind = 'overtime';
                    } else {
                        continue;
                    }
                    $items->push(['employee' => $marks->first()->employee, 'date' => $exit->mark_date, 'kind' => $kind,
                        'branch' => ($marks->firstWhere('mark_type', 'entry') ?? $exit)->recordedBranchLabel(),
                        'reason' => $result['reason'], 'seconds' => $result['eligible']]);
                }
                $items = $items->sortByDesc('date')->take(8)->values();
            });
        return ['today' => $today, 'now' => $now, 'stats' => $stats, 'pending' => $pending, 'pendingItems' => $items, 'latestMarks' => $todayMarks->take(10)->values()];
    }
}
