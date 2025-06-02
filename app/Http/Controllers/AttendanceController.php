<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attendances = DB::table('attendances')
        ->join('employees', 'attendances.employee_id', '=', 'employees.id')
        ->select(
            'employees.id as employee_id',
            DB::raw("CONCAT(employees.first_name, ' ', employees.last_name_father, ' ', employees.last_name_mother) as full_name"),
            'attendances.mark_date',
            DB::raw("MAX(CASE WHEN mark_type = 'entry' THEN mark_time END) as entry_time"),
            DB::raw("MAX(CASE WHEN mark_type = 'break_out' THEN mark_time END) as break_out_time"),
            DB::raw("MAX(CASE WHEN mark_type = 'break_in' THEN mark_time END) as break_in_time"),
            DB::raw("MAX(CASE WHEN mark_type = 'exit' THEN mark_time END) as exit_time"),
            //lateness_time
            DB::raw("MAX(CASE WHEN mark_type = 'entry' THEN lateness_time END) as lateness_time"),
            //overtime_time
            DB::raw("MAX(CASE WHEN mark_type = 'exit' THEN overtime_time END) as overtime_time")
        )
        ->groupBy(
            'attendances.employee_id',
            'employees.id',
            DB::raw("CONCAT(employees.first_name, ' ', employees.last_name_father, ' ', employees.last_name_mother)"),
            'attendances.mark_date'
        )
        ->orderBy('attendances.mark_date', 'desc')
        ->orderBy('employees.first_name')
        ->get();


        return view('pages.attendances.index')
            ->with('attendances', $attendances)
            ->with('title', 'Asistencias')
            ->with('subtitle', 'Lista de asistencias');
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


    public function submitMarker(Request $request)
    {
        try {
            $validated = $request->validate([
                'document_number' => 'required|string|max:255',
                'type' => 'required|string|in:entry,break_out,break_in,exit',
            ]);

            // Buscar al empleado por su número de documento
            $employee = Employee::where('document_number', $validated['document_number'])->first();

            if (!$employee) {
                return response()->json(['message' => 'Empleado no encontrado'], 404);
            }

            $now = now();
            $latenessTime = null;
            $overtimeTime = null;

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

            // Solo calcular tardanza si es una marca de entrada
            if ($validated['type'] === 'entry') {
                // Suponiendo que el empleado tiene un horario con campo `start_time`
                $schedule = $employee->schedule; // o como tengas la relación

                if ($schedule && $schedule->start_time) {
                    $scheduledTime = Carbon::createFromFormat('H:i:s', $schedule->start_time);
                    $actualTime = Carbon::createFromFormat('H:i:s', $now->format('H:i:s'));

                    if ($actualTime->greaterThan($scheduledTime)) {
                        $diff = $actualTime->diff($scheduledTime);
                        $latenessTime = $diff->format('%H:%I:%S');
                    }
                }
            }

            // Obtener el horario del día actual
            $schedule = $employee->schedules()->where('day', $dayName)->first();

            if (!$schedule) {
                return response()->json(['message' => 'Horario no encontrado para el empleado'], 404);
            }

            // Calcular tardanza si es entrada
            if ($validated['type'] === 'entry' && $schedule && $schedule->start_time) {
                $scheduledTime = Carbon::createFromFormat('H:i:s', $schedule->start_time);
                $actualTime = Carbon::createFromFormat('H:i:s', $now->format('H:i:s'));

                if ($actualTime->greaterThan($scheduledTime)) {
                    $diff = $actualTime->diff($scheduledTime);
                    $latenessTime = $diff->format('%H:%I:%S');
                }
            }

            // Calcular horas extra si es salida
            if ($validated['type'] === 'exit' && $schedule && $schedule->end_time) {
                $scheduledEnd = Carbon::createFromFormat('H:i:s', $schedule->end_time);
                $actualExit = Carbon::createFromFormat('H:i:s', $now->format('H:i:s'));

                if ($actualExit->greaterThan($scheduledEnd)) {
                    $diff = $actualExit->diff($scheduledEnd);
                    $overtimeTime = $diff->format('%H:%I:%S');
                }
            }

            // Verificar que no haya duplicados para el mismo día y tipo
            $alreadyMarked = Attendance::where('employee_id', $employee->id)
                ->where('mark_date', $now->format('Y-m-d'))
                ->where('mark_type', $validated['type'])
                ->exists();

            if ($alreadyMarked) {
                return response()->json(['message' => 'Ya se registró esta marcación hoy'], 409);
            }

            // Crear registro de asistencia
            $attendance = new Attendance();
            $attendance->employee_id = $employee->id;
            $attendance->mark_type = $validated['type'];
            $attendance->mark_date = now()->format('Y-m-d');
            $attendance->mark_time = now()->format('H:i:s');
            $attendance->ip_address = $request->ip();
            $attendance->device = $request->header('User-Agent');
            $attendance->lateness_time = $latenessTime;
            $attendance->overtime_time = $overtimeTime;
            $attendance->save();

            return response()->json(['message' => 'Marcador registrado correctamente']);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
