<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\CarbonPeriod;

class AttendanceReport
{
    public function build($employees, string $from, string $to, ?int $branchId = null): array
    {
        $settings = [
            (int) Setting::getValue('overtime_minimum_minutes', 0),
            (int) Setting::getValue('reference_lunch_minutes', 60),
            (int) Setting::getValue('minimum_lunch_minutes', 45),
            (int) Setting::getValue('maximum_lunch_minutes', 180),
            (int) Setting::getValue('lunch_tolerance_minutes', 5),
            (int) Setting::getValue('exit_tolerance_minutes', 5),
        ];
        $marks = Attendance::with(['reviewer', 'lunchAdjustments.recorder'])
            ->whereIn('employee_id', $employees->pluck('id'))->whereBetween('mark_date', [$from, $to])->get()
            ->groupBy(fn ($mark) => $mark->employee_id.'|'.$mark->mark_date);
        $rows = collect();
        $summaries = collect();
        $calculator = new AttendanceDayCalculator();
        foreach ($employees as $employee) {
            $schedules = $employee->schedules->keyBy('day');
            $summary = ['employee' => $employee, 'scheduled' => 0, 'worked' => 0, 'lunch' => 0, 'raw_extra' => 0, 'eligible' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'incomplete_days' => 0, 'unmarked_days' => 0, 'estimated_days' => 0, 'complete_days' => 0, 'unknown_schedule_days' => 0];
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $dateString = $date->toDateString();
                $group = $marks->get($employee->id.'|'.$dateString, collect());
                $dayName = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'][$date->format('l')];
                $entry = $group->firstWhere('mark_type', 'entry');
                $branchSource = $entry ?? $group->first();
                if ($branchId !== null) {
                    if ($branchSource && (!$branchSource->branch_snapshot_captured || (int) $branchSource->recorded_branch_id !== $branchId)) continue;
                    if (!$branchSource && (int) $employee->branch_id !== $branchId) continue;
                }

                $schedule = $schedules->get($dayName);
                if ($group->isEmpty() && !$schedule) continue;
                $result = $calculator->calculate($group, ...$settings);
                $planned = $this->plannedSeconds($entry, $schedule, $settings[1]);
                $result['planned'] = $planned;
                $result['schedule_estimated'] = !$entry && (bool) $schedule;
                $result['unmarked'] = $group->isEmpty();
                $result['employee'] = $employee;
                $result['branch_label'] = $branchSource ? $branchSource->recordedBranchLabel() : (($employee->branch?->name ?? 'Sin sede asignada').' (sede actual estimada)');
                $result['date'] = $dateString;
                $result['display_start'] = $entry ? $entry->scheduled_start_time : $schedule?->start_time;
                $result['display_end'] = $entry ? $entry->scheduled_end_time : $schedule?->end_time;
                $result['display_break_start'] = $entry ? $entry->scheduled_break_start : $schedule?->break_start;
                $result['display_break_end'] = $entry ? $entry->scheduled_break_end : $schedule?->break_end;
                $result['uses_reference_lunch'] = !$result['is_unscheduled_day'] && !$result['display_break_start'] && !$result['display_break_end'];
                $result['reason'] = $result['unmarked'] ? 'Sin marcaciones registradas para este día.' : $result['reason'];
                $rows->push($result);
                $summary['scheduled'] += $planned ?? 0;
                $summary['unknown_schedule_days'] += (int) ($planned === null);
                $summary['estimated_days'] += (int) $result['schedule_estimated'];
                if ($result['unmarked']) {
                    $summary['unmarked_days']++;
                } elseif (!$result['complete']) {
                    $summary['incomplete_days']++;
                } else {
                    $summary['complete_days']++;
                    foreach (['worked', 'lunch', 'raw_extra', 'eligible', 'approved'] as $field) $summary[$field] += $result[$field];
                    if ($result['status'] === 'pending') $summary['pending'] += $result['eligible'];
                    if ($result['status'] === 'rejected') $summary['rejected'] += $result['eligible'];
                }
            }
            $summaries->push($summary);
        }
        return ['rows' => $rows->sortByDesc('date')->values(), 'summaries' => $summaries];
    }

    private function plannedSeconds(?Attendance $entry, $schedule, int $referenceLunch): ?int
    {
        if ($entry?->is_unscheduled_day) return 0;
        $start = $entry ? $entry->scheduled_start_time : $schedule?->start_time;
        $end = $entry ? $entry->scheduled_end_time : $schedule?->end_time;
        $breakStart = $entry ? $entry->scheduled_break_start : $schedule?->break_start;
        $breakEnd = $entry ? $entry->scheduled_break_end : $schedule?->break_end;
        if (!$start || !$end || (bool) $breakStart !== (bool) $breakEnd) return null;
        $seconds = function ($time) {
            $parts = explode(':', $time);
            return (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) ($parts[2] ?? 0);
        };
        $span = $seconds($end) - $seconds($start);
        if ($breakStart && $breakEnd) {
            if ($seconds($breakStart) <= $seconds($start) || $seconds($breakEnd) >= $seconds($end) || $seconds($breakEnd) <= $seconds($breakStart)) return null;
            $lunch = $seconds($breakEnd) - $seconds($breakStart);
        } else {
            $lunch = (int) ($entry?->reference_lunch_minutes ?? $referenceLunch) * 60;
        }
        return $span > $lunch ? $span - $lunch : null;
    }
}
