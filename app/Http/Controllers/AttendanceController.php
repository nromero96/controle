<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Branch;
use App\Services\AttendanceDayCalculator;
use App\Services\MarkerFlow;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AttendanceDayCalculator $calculator)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $to = $filters['to'] ?? now()->toDateString();
        $from = $filters['from'] ?? Carbon::parse($to)->startOfMonth()->toDateString();
        if ($from > $to) throw ValidationException::withMessages(['to' => 'La fecha final debe ser igual o posterior a la fecha inicial.']);
        $query = Attendance::query()->whereBetween('mark_date', [$from, $to])
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_snapshot_captured', true)->where('recorded_branch_id', $id));
        $records = (clone $query)->select('employee_id', 'mark_date')->groupBy('employee_id', 'mark_date')
            ->orderByDesc('mark_date')->orderBy('employee_id')->paginate(25)->withQueryString();
        if ($records->isNotEmpty()) {
            $marks = Attendance::with(['employee.branch', 'reviewer', 'lunchAdjustments.recorder', 'timeCorrections.recorder'])->where(function ($q) use ($records) {
                foreach ($records as $record) {
                    $q->orWhere(fn ($pair) => $pair->where('employee_id', $record->employee_id)->where('mark_date', $record->mark_date));
                }
            })->get()->groupBy(fn ($mark) => $mark->employee_id.'|'.$mark->mark_date);
            $rules = array_map(fn ($key, $default) => (int) Setting::getValue($key, $default),
                ['overtime_minimum_minutes', 'reference_lunch_minutes', 'minimum_lunch_minutes', 'maximum_lunch_minutes', 'lunch_tolerance_minutes', 'exit_tolerance_minutes'],
                [0, 60, 45, 180, 5, 5]);
            foreach ($records as $record) {
                $group = $marks[$record->employee_id.'|'.$record->mark_date];
                $record->setRelation('employee', $group->first()->employee);
                $record->setRelation('marks', $group->keyBy('mark_type'));
                $record->setAttribute('day_result', $calculator->calculate($group, ...$rules));
            }
        }
        return view('pages.attendances.index', [
            'data' => ['page_title' => 'Asistencias'], 'records' => $records, 'from' => $from, 'to' => $to,
            'employees' => Employee::orderBy('first_name')->get(), 'branches' => \App\Services\AttendanceBranchOptions::all(),
            'regularizationMinimumLunch' => (int) Setting::getValue('minimum_lunch_minutes', 45),
            'regularizationLunchTolerance' => max(0, min(30, (int) Setting::getValue('lunch_tolerance_minutes', 5))),
        ]);
    }

    public function regularizeLunch(Request $request, Attendance $attendance, AttendanceDayCalculator $calculator)
    {
        abort_unless($attendance->mark_type === 'entry', 422);
        $validated = $request->validate([
            'lunch_start' => ['required', 'date_format:H:i,H:i:s'],
            'lunch_end' => ['required', 'date_format:H:i,H:i:s', 'after:lunch_start'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'source_fingerprint' => ['required', 'string', 'size:64'],
            'current_adjustment_id' => ['required', 'integer', 'min:0'],
        ]);
        return DB::transaction(function () use ($request, $attendance, $validated, $calculator) {
            Employee::whereKey($attendance->employee_id)->lockForUpdate()->firstOrFail();
            $marks = Attendance::where('employee_id', $attendance->employee_id)->where('mark_date', $attendance->mark_date)->lockForUpdate()->get();
            $result = $calculator->calculate($marks, (int) Setting::getValue('overtime_minimum_minutes', 0), (int) Setting::getValue('reference_lunch_minutes', 60), (int) Setting::getValue('minimum_lunch_minutes', 45), (int) Setting::getValue('maximum_lunch_minutes', 180), (int) Setting::getValue('lunch_tolerance_minutes', 5), (int) Setting::getValue('exit_tolerance_minutes', 5));
            if (!$result['can_regularize'] || (int) ($result['adjustment']?->id ?? 0) !== (int) $validated['current_adjustment_id'] || !hash_equals($result['source_fingerprint'], $validated['source_fingerprint'])) {
                return back()->with('error', 'Las marcas cambiaron o falta ingreso/salida. Actualice el listado antes de regularizar.');
            }
            $start = Carbon::parse($attendance->mark_date.' '.$validated['lunch_start']);
            $end = Carbon::parse($attendance->mark_date.' '.$validated['lunch_end']);
            $entryTime = Carbon::parse($attendance->mark_date.' '.$result['entry']->effectiveTime());
            $exitTime = Carbon::parse($attendance->mark_date.' '.$result['exit']->effectiveTime());
            // Explicit regularization adopts today's short-lunch policy, without rewriting other days.
            $previousLunchRules = ['minimum_lunch_minutes' => $result['minimum_lunch'], 'lunch_tolerance_minutes' => $result['lunch_tolerance']];
            $result['minimum_lunch'] = (int) Setting::getValue('minimum_lunch_minutes', 45);
            $result['lunch_tolerance'] = max(0, min(30, (int) Setting::getValue('lunch_tolerance_minutes', 5)));
            $result['lunch_threshold'] = max(1, $result['minimum_lunch'] - $result['lunch_tolerance']);
            if ($start->lessThanOrEqualTo($entryTime) || $end->greaterThanOrEqualTo($exitTime) || $start->diffInSeconds($end) < $result['lunch_threshold'] * 60) {
                throw ValidationException::withMessages(['lunch_end' => 'El almuerzo debe estar dentro de la jornada y durar al menos '.$result['lunch_threshold'].' minutos considerando la tolerancia de '.$result['lunch_tolerance'].' minutos. Registre únicamente horas confirmadas.']);
            }
            $entry = $result['entry'];
            $entry->overtime_minimum_minutes = $result['minimum'];
            $entry->reference_lunch_minutes = $result['reference_lunch'];
            $entry->minimum_lunch_minutes = $result['minimum_lunch'];
            $entry->maximum_lunch_minutes = $result['maximum_lunch'];
            $entry->lunch_tolerance_minutes = $result['lunch_tolerance'];
            $entry->exit_tolerance_minutes = $result['exit_tolerance'];
            $entry->save();
            $entry->lunchAdjustments()->create([
                'lunch_start' => $start->format('H:i:s'), 'lunch_end' => $end->format('H:i:s'),
                'reason' => $validated['reason'], 'recorded_by' => $request->user()->id,
                'source_fingerprint' => $result['source_fingerprint'],
                'previous_lunch_rules' => $previousLunchRules,
                'previous_overtime_review' => $result['exit']->overtime_status ? $result['exit']->only(['overtime_status', 'approved_overtime_seconds', 'overtime_reviewed_by', 'overtime_reviewed_at', 'overtime_review_note', 'overtime_fingerprint']) : null,
            ]);
            $entry->unsetRelation('lunchAdjustments');
            $updated = $calculator->calculate($marks, $result['minimum'], $result['reference_lunch'], $result['minimum_lunch'], $result['maximum_lunch'], $result['lunch_tolerance'], $result['exit_tolerance']);
            $exit = $result['exit'];
            // Regularizar el almuerzo no aprueba las extras y anula una revisión previa.
            $exit->overtime_status = null;
            $exit->approved_overtime_seconds = null;
            $exit->overtime_fingerprint = null;
            $exit->overtime_reviewed_by = null;
            $exit->overtime_reviewed_at = null;
            $exit->overtime_review_note = null;
            $exit->overtime_time = $updated['complete'] ? AttendanceDayCalculator::duration($updated['eligible']) : null;
            $exit->save();
            return back()->with('success', 'Almuerzo regularizado. Las marcas originales se conservaron y las extras requieren aprobación por separado.');
        });
    }

    public function review(Request $request, Attendance $attendance, AttendanceDayCalculator $calculator)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:500'],
            'fingerprint' => ['required', 'string', 'size:64'],
        ]);
        abort_unless($attendance->mark_type === 'exit', 422);
        return DB::transaction(function () use ($attendance, $calculator, $validated, $request) {
            Employee::whereKey($attendance->employee_id)->lockForUpdate()->firstOrFail();
            $marks = Attendance::where('employee_id', $attendance->employee_id)->where('mark_date', $attendance->mark_date)->lockForUpdate()->get();
            $result = $calculator->calculate($marks, (int) Setting::getValue('overtime_minimum_minutes', 0), (int) Setting::getValue('reference_lunch_minutes', 60), (int) Setting::getValue('minimum_lunch_minutes', 45), (int) Setting::getValue('maximum_lunch_minutes', 180), (int) Setting::getValue('lunch_tolerance_minutes', 5), (int) Setting::getValue('exit_tolerance_minutes', 5));
            if (!$result['complete'] || !$result['eligible'] || $result['status'] !== 'pending' || !hash_equals($result['fingerprint'], $validated['fingerprint'])) {
                return back()->with('error', 'La jornada cambió, ya fue revisada o no tiene extras elegibles. Actualice el listado.');
            }
            // Congelar las reglas también en registros históricos antes de aprobarlos.
            $entry = $result['entry'];
            $entry->overtime_minimum_minutes = $result['minimum'];
            $entry->reference_lunch_minutes = $result['reference_lunch'];
            $entry->minimum_lunch_minutes = $result['minimum_lunch'];
            $entry->maximum_lunch_minutes = $result['maximum_lunch'];
            $entry->lunch_tolerance_minutes = $result['lunch_tolerance'];
            $entry->exit_tolerance_minutes = $result['exit_tolerance'];
            $entry->save();
            $exit = $result['exit'];
            $exit->overtime_status = $validated['decision'];
            $exit->approved_overtime_seconds = $validated['decision'] === 'approved' ? $result['eligible'] : 0;
            $exit->overtime_reviewed_by = $request->user()->id;
            $exit->overtime_reviewed_at = now();
            $exit->overtime_review_note = $validated['note'] ?? null;
            $exit->overtime_fingerprint = $result['fingerprint'];
            $exit->save();
            return back()->with('success', $validated['decision'] === 'approved' ? 'Horas extra aprobadas.' : 'Horas extra rechazadas.');
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    public function photo(Attendance $attendance)
    {
        abort_unless($attendance->photo && Storage::disk('local')->exists($attendance->photo), 404);

        return response()->file(Storage::disk('local')->path($attendance->photo), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function submitMarker(Request $request)
    {
        $photoPath = null;
        $cameraEnabled = Setting::enabled('camera_enabled');
        try {
            $validated = $request->validate([
                'document_number' => 'required|string|max:255',
                'type' => 'required|string|in:entry,break_out,break_in,exit',
                'photo' => [$cameraEnabled ? 'required' : 'nullable', 'file', 'image', 'mimes:jpeg', 'max:2048', 'dimensions:max_width=1920,max_height=1920'],
            ]);

            // Buscar al empleado por su número de documento
            $employee = Employee::where('document_number', $validated['document_number'])->first();

            if (!$employee) {
                return response()->json(['message' => 'Empleado no encontrado'], 404);
            }

            if ($employee->status !== 'activo') {
                return response()->json(['message' => 'El empleado está inactivo. Comunícate con el administrador.'], 403);
            }

            DB::beginTransaction();
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $latenessTime = null;

            // Mapeo de día en inglés a español para buscar en el horario
            $daysMap = [
                'Monday' => 'Lunes',
                'Tuesday' => 'Martes',
                'Wednesday' => 'Miércoles',
                'Thursday' => 'Jueves',
                'Friday' => 'Viernes',
                'Saturday' => 'Sábado',
                'Sunday' => 'Domingo',
            ];

            $dayName = $daysMap[$now->format('l')]; // Día actual en español

            // Obtener el horario del día actual
            $schedule = $employee->schedules()->where('day', $dayName)->first();


            // Calcular tardanza si es entrada
            if ($validated['type'] === 'entry' && $schedule && $schedule->start_time) {
                $scheduledTime = Carbon::parse($now->toDateString().' '.$schedule->start_time, config('app.timezone'));
                $actualTime = $now;

                if ($actualTime->greaterThan($scheduledTime)) {
                    $diff = $actualTime->diff($scheduledTime);
                    $latenessTime = $diff->format('%H:%I:%S');
                }
            }

            // Verificar que no haya duplicados para el mismo día y tipo
            $alreadyMarked = Attendance::where('employee_id', $employee->id)
                ->where('mark_date', $now->format('Y-m-d'))
                ->where('mark_type', $validated['type'])
                ->exists();

            if ($alreadyMarked) {
                DB::rollBack();
                return response()->json(['message' => 'Ya se registró esta marcación hoy'], 409);
            }

            if (Attendance::where('employee_id', $employee->id)->where('mark_date', $now->toDateString())->where('mark_type', 'exit')->exists()) {
                DB::rollBack();
                return response()->json(['message' => 'La salida laboral ya está registrada. Solicite al administrador regularizar las marcas olvidadas.'], 422);
            }

            $existingMarks = Attendance::where('employee_id', $employee->id)->where('mark_date', $now->toDateString())->get();
            if (!in_array($validated['type'], MarkerFlow::allowedTypes($existingMarks), true)) {
                DB::rollBack();
                return response()->json(['message' => 'Esta marcación no corresponde al paso actual. Consulta nuevamente tu documento.'], 422);
            }

            // Crear registro de asistencia
            $attendance = new Attendance();
            $attendance->employee_id = $employee->id;
            $dayEntry = $existingMarks->firstWhere('mark_type', 'entry');
            if ($dayEntry) {
                $attendance->recorded_branch_id = $dayEntry->recorded_branch_id;
                $attendance->recorded_branch_name = $dayEntry->recorded_branch_name;
                $attendance->branch_snapshot_captured = $dayEntry->branch_snapshot_captured;
            } else {
                $attendance->recorded_branch_id = $employee->branch_id;
                $attendance->recorded_branch_name = $employee->branch?->name;
                $attendance->branch_snapshot_captured = true;
            }

            $attendance->mark_type = $validated['type'];
            
            if($validated['type'] === 'entry'){
                $attendance->overtime_minimum_minutes = (int) Setting::getValue('overtime_minimum_minutes', 0);
                $attendance->reference_lunch_minutes = (int) Setting::getValue('reference_lunch_minutes', 60);
                $attendance->minimum_lunch_minutes = (int) Setting::getValue('minimum_lunch_minutes', 45);
                $attendance->maximum_lunch_minutes = (int) Setting::getValue('maximum_lunch_minutes', 180);
                $attendance->lunch_tolerance_minutes = (int) Setting::getValue('lunch_tolerance_minutes', 5);
                $attendance->exit_tolerance_minutes = (int) Setting::getValue('exit_tolerance_minutes', 5);
                $attendance->is_unscheduled_day = !$schedule;
                $attendance->day_name = $dayName;
                $attendance->scheduled_start_time = $schedule->start_time ?? null;
                $attendance->scheduled_end_time = $schedule->end_time ?? null;
                $attendance->scheduled_break_start = $schedule->break_start ?? null;
                $attendance->scheduled_break_end = $schedule->break_end ?? null;
            } else {
                // Si no es entrada, no necesitamos los horarios programados
                $attendance->day_name = null;
                $attendance->scheduled_start_time = null;
                $attendance->scheduled_end_time = null;
                $attendance->scheduled_break_start = null;
                $attendance->scheduled_break_end = null;
            }

            $attendance->mark_date = $now->format('Y-m-d');
            $attendance->mark_time = $now->format('H:i:s');
            $attendance->ip_address = $request->ip();
            $attendance->device = $request->header('User-Agent');
            $attendance->lateness_time = $latenessTime;
            $attendance->overtime_time = null;
            if ($cameraEnabled) {
                $photoPath = $request->file('photo')->store('attendance-photos/'.$now->format('Y/m'), 'local');
                if (!$photoPath) {
                    throw new \RuntimeException('No se pudo guardar la foto.');
                }
                $attendance->photo = $photoPath;
            }
            $attendance->save();
            $marks = Attendance::where('employee_id', $employee->id)->where('mark_date', $now->toDateString())->get();
            $calculation = app(AttendanceDayCalculator::class)->calculate($marks, (int) Setting::getValue('overtime_minimum_minutes', 0), (int) Setting::getValue('reference_lunch_minutes', 60), (int) Setting::getValue('minimum_lunch_minutes', 45), (int) Setting::getValue('maximum_lunch_minutes', 180), (int) Setting::getValue('lunch_tolerance_minutes', 5), (int) Setting::getValue('exit_tolerance_minutes', 5));
            if ($exit = $marks->firstWhere('mark_type', 'exit')) {
                $exit->overtime_time = $calculation['complete'] ? AttendanceDayCalculator::duration($calculation['eligible']) : null;
                $exit->save();
            }
            DB::commit();

            return response()->json([
                'message' => 'Marcación registrada correctamente.',
                'mark_type' => $attendance->mark_type,
                'mark_date' => $attendance->mark_date,
                'mark_time' => $attendance->mark_time,
                'employee_name' => $employee->first_name.' '.$employee->last_name_father,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }
            report($e);
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => 'No se pudo guardar la marcación. Intente nuevamente.'
            ], 500);
        }
    }

}
