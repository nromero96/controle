<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Schedule;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $employees = Employee::all();

        return view('pages.employees.index')
            ->with('employees', $employees)
            ->with('title', 'Empleados')
            ->with('subtitle', 'Lista de empleados');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.employees.create')
            ->with('title', 'Empleados')
            ->with('subtitle', 'Crear empleado');
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
            'email' => 'nullable|email|max:255|unique:employees'
        ]);

        Employee::create($request->all());

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


    public function getEmployee($document_number)
    {
        $employee = Employee::where('document_number', $document_number)->first();

        if ($employee) {

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

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Empleado no encontrado'
            ]);
        }
    }


}
