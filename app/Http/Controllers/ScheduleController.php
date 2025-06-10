<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{

    $data = [
            'category_name' => 'schedules',
            'page_name' => 'schedules.index',
            'page_title' => 'Horarios',
    ];

    $rawSchedules = Schedule::join('employees', 'schedules.employee_id', '=', 'employees.id')
        ->select(
            'schedules.*',
            'employees.id as employee_id',
            'employees.first_name as employee_first_name',
            'employees.last_name_father as employee_last_name_father',
            'employees.last_name_mother as employee_last_name_mother',
            'employees.document_number as employee_document_number'
        )
        ->orderBy('employees.id')
        ->orderByRaw("FIELD(day, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo')")
        ->get();

    // Agrupar por empleado
    $schedules = [];

    foreach ($rawSchedules as $schedule) {
        $id = $schedule->employee_id;

        if (!isset($schedules[$id])) {
            $schedules[$id] = [
                'employee_id' => $id,
                'name' => $schedule->employee_first_name . ' ' . $schedule->employee_last_name_father . ' ' . $schedule->employee_last_name_mother,
                'document' => $schedule->employee_document_number,
                'days' => [
                    'Lunes' => null,
                    'Martes' => null,
                    'Miércoles' => null,
                    'Jueves' => null,
                    'Viernes' => null,
                    'Sábado' => null,
                    'Domingo' => null,
                ],
            ];
        }

        $schedules[$id]['days'][$schedule->day] = $schedule;
    }

    return view('pages.schedules.index', ['schedules' => $schedules, 'data' => $data]);
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
}
