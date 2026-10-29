<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AttendanceDayCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceTimeCorrectionController extends Controller
{
    public function store(Request $request, Attendance $attendance, AttendanceDayCalculator $calculator)
    {
        $data = $request->validate([
            'entry_time' => ['nullable', 'required_without:exit_time', 'date_format:H:i,H:i:s'],
            'exit_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'source_fingerprint' => ['required', 'string', 'size:64'],
            'current_lunch_adjustment_id' => ['required', 'integer', 'min:0'],
            'unscheduled' => ['sometimes', 'boolean'],
            'scheduled_start' => ['nullable', 'date_format:H:i,H:i:s'],
            'scheduled_end' => ['nullable', 'date_format:H:i,H:i:s', 'after:scheduled_start'],
            'scheduled_break_start' => ['nullable', 'required_with:scheduled_break_end', 'date_format:H:i,H:i:s', 'after:scheduled_start', 'before:scheduled_end'],
            'scheduled_break_end' => ['nullable', 'required_with:scheduled_break_start', 'date_format:H:i,H:i:s', 'after:scheduled_break_start', 'before:scheduled_end'],
        ]);

        return DB::transaction(function () use ($request, $attendance, $calculator, $data) {
            Employee::whereKey($attendance->employee_id)->lockForUpdate()->firstOrFail();
            $marks = Attendance::where('employee_id', $attendance->employee_id)->where('mark_date', $attendance->mark_date)->lockForUpdate()->get();
            $rules = array_map(fn ($key, $default) => (int) Setting::getValue($key, $default),
                ['overtime_minimum_minutes', 'reference_lunch_minutes', 'minimum_lunch_minutes', 'maximum_lunch_minutes', 'lunch_tolerance_minutes', 'exit_tolerance_minutes'], [0, 60, 45, 180, 5, 5]);
            $before = $calculator->calculate($marks, ...$rules);
            if (! hash_equals($before['source_fingerprint'], $data['source_fingerprint']) || (int) ($before['adjustment']?->id ?? 0) !== (int) $data['current_lunch_adjustment_id']) {
                return back()->with('error', 'Las marcaciones o el almuerzo cambiaron. Actualice el listado antes de corregir.');
            }
            $byType = $marks->keyBy('mark_type');
            if ($marks->count() !== $byType->count()) {
                throw ValidationException::withMessages(['entry_time' => 'Hay marcaciones duplicadas. Revise los registros antes de corregir.']);
            }
            $normal = fn ($time) => strlen($time) === 5 ? $time.':00' : $time;
            $entryTime = ! empty($data['entry_time']) ? $normal($data['entry_time']) : $byType->get('entry')?->effectiveTime();
            $exitTime = ! empty($data['exit_time']) ? $normal($data['exit_time']) : $byType->get('exit')?->effectiveTime();
            $date = $attendance->mark_date;
            foreach (['entry_time' => $entryTime, 'exit_time' => $exitTime] as $field => $time) {
                if ($time && Carbon::parse($date.' '.$time)->isFuture()) {
                    throw ValidationException::withMessages([$field => 'No puede registrar una hora futura.']);
                }
            }
            if ($entryTime && $exitTime && $entryTime >= $exitTime) {
                throw ValidationException::withMessages(['exit_time' => 'La salida debe ser posterior al ingreso dentro del mismo día.']);
            }
            $lunchStart = $before['adjusted'] ? $before['adjustment']->lunch_start : $byType->get('break_out')?->mark_time;
            $lunchEnd = $before['adjusted'] ? $before['adjustment']->lunch_end : $byType->get('break_in')?->mark_time;
            if (($entryTime && $lunchStart && $entryTime >= $lunchStart) || ($exitTime && $lunchEnd && $exitTime <= $lunchEnd)) {
                throw ValidationException::withMessages(['entry_time' => 'El ingreso debe ser anterior a la salida al almuerzo y la salida posterior al retorno. Regularice primero un refrigerio incorrecto si es necesario.']);
            }
            if (! $byType->has('entry') && $entryTime && ! $request->boolean('unscheduled') && (empty($data['scheduled_start']) || empty($data['scheduled_end']))) {
                throw ValidationException::withMessages(['scheduled_start' => 'Confirme el ingreso y la salida programados de ese día, o indique que fue un día sin horario.']);
            }
            $review = $byType->get('exit')?->overtime_status ? $byType->get('exit')->only(['overtime_status', 'approved_overtime_seconds', 'overtime_reviewed_by', 'overtime_reviewed_at', 'overtime_review_note', 'overtime_fingerprint']) : null;
            $changed = false;
            foreach (['entry' => $entryTime, 'exit' => $exitTime] as $type => $time) {
                if (! $time) {
                    continue;
                }
                $mark = $byType->get($type);
                if ($mark && $normal($mark->effectiveTime()) === $time) {
                    continue;
                }
                $previous = $mark?->effectiveTime();
                if (! $mark) {
                    $mark = new Attendance(['employee_id' => $attendance->employee_id, 'mark_date' => $date, 'mark_type' => $type, 'mark_time' => $time]);
                    $mark->is_manual = true;
                    $source = $byType->get('entry') ?? $marks->first();
                    foreach (['recorded_branch_id', 'recorded_branch_name', 'branch_snapshot_captured'] as $field) {
                        $mark->$field = $source->$field;
                    }
                    // Do not infer historical schedules or branches from the employee's current assignment.
                    if ($type === 'entry') {
                        $mark->is_unscheduled_day = $request->boolean('unscheduled');
                        if (! $mark->is_unscheduled_day) {
                            $mark->scheduled_start_time = $data['scheduled_start'];
                            $mark->scheduled_end_time = $data['scheduled_end'];
                            $mark->scheduled_break_start = $data['scheduled_break_start'] ?? null;
                            $mark->scheduled_break_end = $data['scheduled_break_end'] ?? null;
                        }
                        foreach (['overtime_minimum_minutes', 'reference_lunch_minutes', 'minimum_lunch_minutes', 'maximum_lunch_minutes', 'lunch_tolerance_minutes', 'exit_tolerance_minutes'] as $index => $field) {
                            $mark->$field = $rules[$index];
                        }
                    }
                    $mark->save();
                    $marks->push($mark);
                    $byType->put($type, $mark);
                } else {
                    $mark->corrected_time = $time;
                }
                $mark->correction_version++;
                if ($type === 'entry') {
                    $lateness = $mark->scheduled_start_time ? max(0, Carbon::parse($date.' '.$mark->scheduled_start_time)->diffInSeconds(Carbon::parse($date.' '.$time), false)) : 0;
                    $mark->lateness_time = AttendanceDayCalculator::duration((int) $lateness);
                }
                $mark->save();
                $mark->timeCorrections()->create(['previous_time' => $previous, 'corrected_time' => $time, 'reason' => $data['reason'], 'recorded_by' => $request->user()->id, 'previous_overtime_review' => $review]);
                $changed = true;
            }
            if (! $changed) {
                return back()->with('error', 'No se guardaron cambios: las horas son iguales a las registradas.');
            }
            if ($entry = $byType->get('entry')) {
                foreach (['overtime_minimum_minutes' => 'minimum', 'reference_lunch_minutes' => 'reference_lunch', 'minimum_lunch_minutes' => 'minimum_lunch', 'maximum_lunch_minutes' => 'maximum_lunch', 'lunch_tolerance_minutes' => 'lunch_tolerance', 'exit_tolerance_minutes' => 'exit_tolerance'] as $field => $key) {
                    $entry->$field = $before[$key];
                }
                $entry->save();
            }
            // A confirmed lunch remains valid when it still fits inside the corrected day.
            if ($before['adjusted']) {
                $entry = $byType->get('entry');
                $fingerprint = $calculator->calculate($marks, ...$rules)['source_fingerprint'];
                $entry->lunchAdjustments()->create(['lunch_start' => $lunchStart, 'lunch_end' => $lunchEnd, 'reason' => mb_substr('Revalidación por corrección de ingreso/salida: '.$data['reason'], 0, 1000), 'recorded_by' => $request->user()->id, 'source_fingerprint' => $fingerprint, 'previous_overtime_review' => $review]);
                $entry->unsetRelation('lunchAdjustments');
            }
            $updated = $calculator->calculate($marks, ...$rules);
            if ($exit = $byType->get('exit')) {
                foreach (['overtime_status', 'approved_overtime_seconds', 'overtime_fingerprint', 'overtime_reviewed_by', 'overtime_reviewed_at', 'overtime_review_note'] as $field) {
                    $exit->$field = null;
                }
                $exit->overtime_time = $updated['complete'] ? AttendanceDayCalculator::duration($updated['eligible']) : null;
                $exit->save();
            }

            return back()->with('success', 'Ingreso/salida regularizados. Las horas originales se conservaron; las horas extra requieren aprobación por separado.');
        });
    }
}
