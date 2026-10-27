<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EmployeeRequest;


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

        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('pages.employees.create')
            ->with('data', $data)
            ->with('branches', $branches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeRequest $request)
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();
            $employee = Employee::create(collect($data)->except('schedule')->all());
            $this->saveSchedules($employee, $data['schedule'] ?? []);
        });

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

        $branches = Branch::where('is_active', true)->orWhere('id', $employee->branch_id)->orderBy('name')->get();

        return view('pages.employees.edit')
            ->with('employee', $employee)
            ->with('schedules', $schedules)
            ->with('data', $data)
            ->with('branches', $branches);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeRequest $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        DB::transaction(function () use ($employee, $request) {
            $data = $request->validated();
            $employee->update(collect($data)->except('schedule')->all());
            if (array_key_exists('schedule', $data)) {
                $this->saveSchedules($employee, $data['schedule']);
            }
        });

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


    private function saveSchedules(Employee $employee, array $schedules): void
    {
        foreach ($schedules as $day => $schedule) {
            if (empty($schedule['active'])) {
                $employee->schedules()->where('day', $day)->delete();
                continue;
            }
            $employee->schedules()->updateOrCreate(['day' => $day], [
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'break_start' => $schedule['break_start'] ?? null,
                'break_end' => $schedule['break_end'] ?? null,
            ]);
        }
    }

    public function getEmployee($document_number)
    {
        $employee = Employee::where('document_number', $document_number)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'No encontramos ese documento. Revisa el número e intenta nuevamente.']);
        }
        if ($employee->status !== 'activo') {
            return response()->json(['success' => false, 'message' => 'El empleado está inactivo. Comunícate con el administrador.'], 403);
        }
        $now = now();
        $dayName = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'][$now->format('l')];
        $marks = $employee->attendances()->where('mark_date', $now->toDateString())->orderBy('mark_time')->get();
        $entry = $marks->firstWhere('mark_type', 'entry');
        $schedule = $employee->schedules()->where('day', $dayName)->first();
        return response()->json([
            'success' => true,
            'data' => $employee->only(['first_name', 'last_name_father']),
            'attendances' => $marks->map->only(['mark_type', 'mark_time'])->values(),
            'allowed_types' => \App\Services\MarkerFlow::allowedTypes($marks),
            'is_unscheduled_day' => $entry ? (bool) $entry->is_unscheduled_day : !$schedule,
            'schedule' => [
                'start_time' => $entry ? $entry->scheduled_start_time : $schedule?->start_time,
                'end_time' => $entry ? $entry->scheduled_end_time : $schedule?->end_time,
            ],
        ]);
    }
}
