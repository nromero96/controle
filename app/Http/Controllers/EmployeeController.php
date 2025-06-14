<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Schedule;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;


class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $data = [
            'category_name' => 'employees',
            'page_name' => 'employees.index',
            'page_title' => 'Empleados',
        ];

        $employees = Employee::all();

        

        return view('pages.employees.index')
            ->with('employees', $employees)
            ->with('data', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $data = [
            'category_name' => 'employees',
            'page_name' => 'employees.create',
            'page_title' => 'Crear empleado',
        ];

        $branches = Branch::all();

        return view('pages.employees.create')
            ->with('data', $data)
            ->with('branches', $branches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string|max:255',
            'document_number' => 'required|string|max:255|unique:employees',
            'first_name' => 'required|string|max:255',
            'last_name_father' => 'required|string|max:255',
            'last_name_mother' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:employees',
            'branch_id' => 'required|exists:branches,id',
        ]);

        // Crea el empleado solo con los campos permitidos
        $employee = Employee::create($request->only([
            'document_type',
            'document_number',
            'first_name',
            'last_name_father',
            'last_name_mother',
            'email',
            'branch_id',
        ]));

        // Procesar horarios si los hay
        if ($request->has('schedule')) {
            foreach ($request->schedule as $day => $data) {
                if (!empty($data['active'])) {
                    $employee->schedules()->create([
                        'day' => $data['day'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'break_start' => $data['break_start'] ?? null,
                        'break_end' => $data['break_end'] ?? null,
                    ]);
                }
            }
        }

        return redirect()->route('employees.index')
            ->with('success', 'Empleado creado correctamente.');
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

        $data = [
            'category_name' => 'employees',
            'page_name' => 'employees.edit',
            'page_title' => 'Editar empleado',
        ];

        $employee = Employee::findOrFail($id);
        $schedules = $employee->schedules;

        $branches = Branch::all();

        return view('pages.employees.edit')
            ->with('employee', $employee)
            ->with('schedules', $schedules)
            ->with('data', $data)
            ->with('branches', $branches);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'document_type' => 'required|string|max:255',
            'document_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('employees')->ignore($employee->id),
            ],
            'first_name' => 'required|string|max:255',
            'last_name_father' => 'required|string|max:255',
            'last_name_mother' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees')->ignore($employee->id),
            ],
            'branch_id' => 'required|exists:branches,id',
        ]);



        // Actualizar datos del empleado
        $employee->update($request->only([
            'document_type',
            'document_number',
            'first_name',
            'last_name_father',
            'last_name_mother',
            'email',
            'branch_id',
        ]));

        // Actualizar horarios si los hay
        if ($request->has('schedule')) {
            //Actualizar horarios existentes
            foreach ($request->schedule as $day => $data) {
                if (!empty($data['active'])) {
                    // Verificar si el horario ya existe
                    $schedule = $employee->schedules()->where('day', $data['day'])->first();
                    if ($schedule) {
                        // Actualizar horario existente
                        $schedule->update([
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time'],
                            'break_start' => $data['break_start'] ?? null,
                            'break_end' => $data['break_end'] ?? null,
                        ]);
                    } else {
                        // Crear nuevo horario
                        $employee->schedules()->create([
                            'day' => $data['day'],
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time'],
                            'break_start' => $data['break_start'] ?? null,
                            'break_end' => $data['break_end'] ?? null,
                        ]);
                    }
                } else {
                    // Eliminar horario si no está activo
                    $employee->schedules()->where('day', $data['day'])->delete();
                }
            }
        }

        return redirect()->route('employees.index')
            ->with('success', 'Empleado actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    public function getEmployee($document_number)
    {
        $employee = Employee::where('document_number', $document_number)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Empleado no encontrado'
            ]);
        }

        // Obtener el día de la semana actual
        $dayOfWeek = now()->format('l'); // 'l' devuelve el nombre completo del día (ej. 'Monday')
        $dayName = match ($dayOfWeek) {
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo',
        };
        // Buscar el horario del empleado para el día actual
        $schedule = $employee->schedules()->where('day', $dayName)->first();

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Horario no encontrado para el día actual'
            ]);
        }


        

            //revisar si el empleado tiene asistencia de la fecha actual
            $first_attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('mark_date', today())
                ->whereTime('mark_time', '>=', '00:00:00')
                ->whereTime('mark_time', '<=', '23:59:59')
                ->first();

            if ($first_attendance) {
                $attendances = Attendance::where('employee_id', $employee->id)
                    ->whereDate('mark_date', today())
                    ->whereTime('mark_time', '>=', '00:00:00')
                    ->whereTime('mark_time', '<=', '23:59:59')
                    ->get();
            } else {
                $attendances = false;
            }


            return response()->json([
                'success' => true,
                'data' => $employee,
                'attendances' => $attendances,
            ]);

        
    }


}
