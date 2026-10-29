<?php

namespace App\Services;

use Illuminate\Support\Collection;

class AttendanceDayCalculator
{
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private function seconds(?string $time): ?int
    {
        if (!$time || !preg_match('/^(\d{2}):([0-5]\d)(?::([0-5]\d))?$/', $time, $match) || (int) $match[1] > 23) {
            return null;
        }
        return (int) $match[1] * 3600 + (int) $match[2] * 60 + (int) ($match[3] ?? 0);
    }

    public function calculate(Collection $marks, int $minimumMinutes, int $referenceLunchMinutes, int $minimumLunchMinutes = 45, int $maximumLunchMinutes = 180, int $lunchToleranceMinutes = 5, int $exitToleranceMinutes = 5): array
    {
        $byType = $marks->keyBy('mark_type');
        $entry = $byType->get('entry');
        $exit = $byType->get('exit');
        $minimum = $entry?->overtime_minimum_minutes ?? $minimumMinutes;
        $referenceLunch = $entry?->reference_lunch_minutes ?? $referenceLunchMinutes;
        $minimumLunch = (int) ($entry?->minimum_lunch_minutes ?? $minimumLunchMinutes);
        $maximumLunch = (int) ($entry?->maximum_lunch_minutes ?? $maximumLunchMinutes);
        $tolerance = max(0, min(30, (int) ($entry?->lunch_tolerance_minutes ?? $lunchToleranceMinutes)));
        $lunchThreshold = max(1, $minimumLunch - $tolerance);
        $exitTolerance = max(0, min(60, (int) ($entry?->exit_tolerance_minutes ?? $exitToleranceMinutes)));
        $actualExit = $this->seconds($exit?->effectiveTime());
        $scheduledExit = $this->seconds($entry?->scheduled_end_time);
        $exitDifference = $actualExit !== null && $scheduledExit !== null ? $actualExit - $scheduledExit : null;
        $exitStatus = $exitDifference === null ? 'unknown' : (abs($exitDifference) <= $exitTolerance * 60 ? 'normal' : ($exitDifference < 0 ? 'early' : 'late'));
        $sourceFingerprint = hash('sha256', json_encode($marks->sortBy('mark_type')->map(fn ($mark) => $mark->correction_version ? [$mark->id, $mark->mark_type, $mark->effectiveTime(), $mark->correction_version] : [$mark->id, $mark->mark_type, $mark->mark_time])->values()->all()));
        $adjustments = $entry ? $entry->lunchAdjustments : collect();
        $adjustment = $adjustments->first();
        $adjusted = $adjustment && hash_equals($adjustment->source_fingerprint, $sourceFingerprint);
        $result = [
            'marks' => $byType, 'entry' => $entry, 'exit' => $exit, 'complete' => false,
            'reason' => 'Faltan marcas: ingreso, salida al almuerzo, retorno o salida.',
            'worked' => null, 'lunch' => null, 'scheduled' => null, 'raw_extra' => null,
            'eligible' => 0, 'approved' => 0, 'status' => 'incomplete',
            'minimum' => (int) $minimum, 'reference_lunch' => (int) $referenceLunch,
            'is_unscheduled_day' => (bool) $entry?->is_unscheduled_day,
            'uses_reference_lunch' => false, 'legacy_rules' => $entry && $entry->overtime_minimum_minutes === null,
            'fingerprint' => null, 'source_fingerprint' => $sourceFingerprint,
            'minimum_lunch' => $minimumLunch, 'maximum_lunch' => $maximumLunch,
            'lunch_tolerance' => $tolerance, 'lunch_threshold' => $lunchThreshold,
            'exit_tolerance' => $exitTolerance, 'exit_status' => $exitStatus, 'exit_difference' => $exitDifference,
            'adjustment' => $adjustment, 'adjustments' => $adjustments, 'adjusted' => (bool) $adjusted,
            'can_regularize' => (bool) ($entry && $exit),
        ];
        if (!$entry || !$exit) {
            return $result;
        }
        $result['reason'] = 'Almuerzo por regularizar: faltan marcas o no representan el descanso real.';
        if ($marks->count() !== $byType->count() || $byType->keys()->diff(['entry', 'break_out', 'break_in', 'exit'])->isNotEmpty()) {
            $result['reason'] = 'Marcas duplicadas o inválidas. Requiere revisión.';
            $result['can_regularize'] = false;
            return $result;
        }
        if ($adjustment && !$adjusted) {
            $result['reason'] = 'Las marcas cambiaron después de la regularización. Confirme nuevamente el almuerzo.';
            return $result;
        }
        $times = [
            $this->seconds($entry->effectiveTime()),
            $this->seconds($adjusted ? $adjustment->lunch_start : $byType->get('break_out')?->mark_time),
            $this->seconds($adjusted ? $adjustment->lunch_end : $byType->get('break_in')?->mark_time),
            $this->seconds($exit->effectiveTime()),
        ];
        if (in_array(null, $times, true)) {
            return $result;
        }
        if (!($times[0] < $times[1] && $times[1] < $times[2] && $times[2] < $times[3])) {
            $result['reason'] = 'Almuerzo por regularizar: marcas fuera de orden o registradas junto con la salida.';
            return $result;
        }
        $lunchSeconds = $times[2] - $times[1];
        if ($lunchSeconds < $lunchThreshold * 60) {
            $result['reason'] = 'Almuerzo por regularizar: dura menos de '.$lunchThreshold.' minutos (mínimo '.$minimumLunch.' min, tolerancia '.$tolerance.' min).';
            return $result;
        }
        if (!$adjusted && $lunchSeconds > $maximumLunch * 60) {
            $result['reason'] = 'Almuerzo por confirmar: supera '.$maximumLunch.' minutos. Puede tratarse de un retorno olvidado.';
            return $result;
        }
        // Aunque el intervalo parezca válido, un retorno pegado a la salida requiere confirmación.
        if (!$adjusted && $times[3] - $times[2] <= 5 * 60) {
            $result['reason'] = 'Almuerzo por confirmar: el retorno se marcó dentro de los 5 minutos previos a la salida.';
            return $result;
        }
        $start = $this->seconds($entry->scheduled_start_time);
        $end = $this->seconds($entry->scheduled_end_time);
        $breakStart = $this->seconds($entry->scheduled_break_start);
        $breakEnd = $this->seconds($entry->scheduled_break_end);
        if ($result['is_unscheduled_day']) {
            $scheduledLunch = 0;
        } else {
            $result['uses_reference_lunch'] = !$entry->scheduled_break_start && !$entry->scheduled_break_end;
            if ($result['uses_reference_lunch']) {
                $scheduledLunch = (int) $referenceLunch * 60;
            } elseif ($breakStart !== null && $breakEnd !== null && $start !== null && $end !== null && $start < $breakStart && $breakStart < $breakEnd && $breakEnd < $end) {
                $scheduledLunch = $breakEnd - $breakStart;
            } else {
                $result['reason'] = 'El horario programado de almuerzo no es válido.';
                return $result;
            }
            if ($start === null || $end === null || $end <= $start || $end - $start <= $scheduledLunch) {
                $result['reason'] = 'No hay una jornada programada válida para calcular extras.';
                return $result;
            }
        }
        $result['complete'] = true;
        $result['reason'] = null;
        $result['lunch'] = $times[2] - $times[1];
        $result['worked'] = $times[3] - $times[0] - $result['lunch'];
        $result['scheduled'] = $result['is_unscheduled_day'] ? 0 : $end - $start - $scheduledLunch;
        $result['raw_extra'] = max(0, $result['worked'] - $result['scheduled']);
        $result['eligible'] = $result['raw_extra'] >= (int) $minimum * 60 ? $result['raw_extra'] : 0;
        $fingerprintData = [$entry->employee_id, $entry->mark_date, $times, $start, $end, $scheduledLunch, (int) $minimum];
        if ($marks->contains(fn ($mark) => $mark->correction_version > 0)) {
            $fingerprintData[] = $sourceFingerprint;
        }
        if ($result['is_unscheduled_day']) {
            $fingerprintData[] = 'unscheduled';
        }
        if ($adjusted) {
            $fingerprintData[] = [$sourceFingerprint, $adjustment->id];
        }
        $result['fingerprint'] = hash('sha256', json_encode($fingerprintData));
        $result['status'] = $result['eligible'] > 0 ? 'pending' : 'none';
        if ($result['eligible'] > 0 && $exit->overtime_fingerprint === $result['fingerprint'] && in_array($exit->overtime_status, ['approved', 'rejected'], true)) {
            $result['status'] = $exit->overtime_status;
            if ($result['status'] === 'approved' && (int) $exit->approved_overtime_seconds === $result['eligible']) {
                $result['approved'] = $result['eligible'];
            } elseif ($result['status'] === 'approved') {
                $result['status'] = 'pending';
            }
        }
        return $result;
    }
}
