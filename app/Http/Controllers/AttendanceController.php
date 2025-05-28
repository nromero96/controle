<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

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
        DB::raw("MAX(CASE WHEN mark_type = 'exit' THEN mark_time END) as exit_time")
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

            // Crear registro de asistencia
            $attendance = new Attendance();
            $attendance->employee_id = $employee->id;
            $attendance->mark_type = $validated['type'];
            $attendance->mark_date = now()->format('Y-m-d');
            $attendance->mark_time = now()->format('H:i:s');
            $attendance->ip_address = $request->ip();
            $attendance->device = $request->header('User-Agent');
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
