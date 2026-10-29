<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceReport;
use App\Services\AttendanceDayCalculator;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(\Carbon\Carbon::parse('2026-10-17 10:00:00', config('app.timezone')));
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    foreach (['Jueves', 'Viernes'] as $day) $this->employee->schedules()->create(['day' => $day, 'start_time' => '08:00', 'end_time' => '17:00']);
});

function reportMarks($employee) {
    foreach (['entry' => '08:00', 'break_out' => '12:00', 'break_in' => '13:00', 'exit' => '18:00'] as $type => $time) {
        $mark = new Attendance(['employee_id' => $employee->id, 'mark_type' => $type, 'mark_date' => '2026-10-16', 'mark_time' => $time, 'ip_address' => '127.0.0.1']);
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00'; $mark->scheduled_end_time = '17:00';
            $mark->reference_lunch_minutes = 60;
        }
        $mark->save();
    }
}

test('attendance manages daily records and report is a separate protected page', function () {
    reportMarks($this->employee);
    $this->get(route('attendances.index'))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->marks->count() === 4)
        ->assertSee('127.0.0.1')->assertSee('Ver reportes')->assertDontSee('Horas trabajadas computables')->assertSee('Aprobar extras');
    $this->get(route('attendances.index'))->assertSee('Ingreso laboral')->assertSee('Salida al almuerzo')->assertSee('Retorno del almuerzo')->assertSee('Salida laboral');
    $this->get(route('reports.attendances'))->assertOk()->assertSee('Resumen por empleado')->assertSee('Horas programadas netas')->assertDontSee('Aprobar extras')->assertDontSee('Guardar almuerzo confirmado');
    auth()->logout();
    $this->get(route('reports.attendances'))->assertRedirect('/login');
});

test('report totals include unmarked scheduled days but actual work counts only complete valid days', function () {
    reportMarks($this->employee);
    $response = $this->get(route('reports.attendances', ['from' => '2026-10-15', 'to' => '2026-10-16']));
    $response->assertOk()->assertViewHas('summaries', function ($summaries) {
        $summary = $summaries->first();
        return $summary['scheduled'] === 16 * 3600 && $summary['worked'] === 9 * 3600 && $summary['lunch'] === 3600 && $summary['pending'] === 3600 && $summary['approved'] === 0 && $summary['unmarked_days'] === 1 && $summary['estimated_days'] === 1;
    })->assertSee('Sin marcaciones')->assertSee('Estimado con horario actual');
});

test('saved schedule wins over modified current schedule even for an incomplete day', function () {
    reportMarks($this->employee);
    $this->employee->schedules()->update(['start_time' => '07:00', 'end_time' => '20:00']);
    $this->employee->attendances()->where('mark_type', 'break_in')->delete();
    $result = app(AttendanceReport::class)->build(Employee::with(['schedules', 'branch'])->get(), '2026-10-16', '2026-10-16');
    expect($result['summaries']->first()['scheduled'])->toBe(8 * 3600)->and($result['summaries']->first()['worked'])->toBe(0)
        ->and($result['summaries']->first()['incomplete_days'])->toBe(1)->and($result['rows']->first()['planned'])->toBe(8 * 3600);
});

test('period totals do not depend on daily pagination', function () {
    $this->employee->schedules()->delete();
    foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $day) $this->employee->schedules()->create(['day' => $day, 'start_time' => '08:00', 'end_time' => '17:00']);
    $filters = ['from' => '2026-10-01', 'to' => '2026-10-31'];
    foreach ([1, 2] as $page) {
        $this->get(route('reports.attendances', $filters + ['page' => $page]))->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries->sum('scheduled') === 31 * 8 * 3600)
            ->assertViewHas('days', fn ($days) => $days->total() === 31 && $days->count() === ($page === 1 ? 25 : 6));
    }
});

test('employee and branch report filters limit the summaries and date ranges are validated', function () {
    $branch = Branch::create(['name' => 'Central']);
    $this->employee->update(['branch_id' => $branch->id]);
    $other = Employee::create(['document_type' => 'DNI', 'document_number' => '87654321', 'first_name' => 'Luis', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    $this->get(route('reports.attendances', ['branch_id' => $branch->id]))->assertViewHas('summaries', fn ($summaries) => $summaries->count() === 1 && $summaries->first()['employee']->id === $this->employee->id);
    $this->get(route('reports.attendances', ['employee_id' => $other->id]))->assertViewHas('summaries', fn ($summaries) => $summaries->count() === 1 && $summaries->first()['employee']->id === $other->id);
    $this->get(route('reports.attendances', ['from' => '2025-01-01', 'to' => '2026-10-17']))->assertSessionHasErrors('to');
});

test('daily attendance pagination keeps all marks together and missing marks remain empty', function () {
    for ($day = 1; $day <= 26; $day++) {
        foreach (['entry' => '08:00:00', 'exit' => '17:00:00'] as $type => $time) {
            Attendance::create(['employee_id' => $this->employee->id, 'mark_date' => sprintf('2026-10-%02d', $day), 'mark_type' => $type, 'mark_time' => $time]);
        }
    }
    foreach ([1 => 25, 2 => 1] as $page => $count) {
        $this->get(route('attendances.index', ['from' => '2026-10-01', 'to' => '2026-10-26', 'page' => $page]))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 26 && $records->count() === $count && $records->every(fn ($day) => $day->marks->count() === 2 && $day->marks->has('entry') && $day->marks->has('exit') && !$day->marks->has('break_in')));
    }
});

test('overtime can be approved from attendance and reports only show the result', function () {
    reportMarks($this->employee);
    $url = route('attendances.index', ['from' => '2026-10-16', 'to' => '2026-10-16', 'employee_id' => $this->employee->id]);
    $response = $this->get($url)->assertOk()->assertSee('Aprobar extras')->assertSee('Rechazar extras');
    $row = $response->viewData('records')->first()->day_result;
    $this->from($url)->post(route('attendances.overtime-review', $row['exit']), ['decision' => 'approved', 'fingerprint' => $row['fingerprint']])->assertRedirect($url)->assertSessionHas('success');
    $this->get($url)->assertSee('Extras aprobadas')->assertDontSee('Aprobar extras');
    $this->get(route('reports.attendances', ['from' => '2026-10-16', 'to' => '2026-10-16']))
        ->assertOk()->assertSee('Extras aprobadas')->assertSee('Gestionar en Asistencias')
        ->assertDontSee(route('attendances.overtime-review', $row['exit']))
        ->assertViewHas('summaries', fn ($summaries) => $summaries->sum('approved') === 3600);
});

test('lunch regularization form belongs to attendance while reports stay read only', function () {
    reportMarks($this->employee);
    $mark = $this->employee->attendances()->where('mark_type', 'break_in')->first();
    $mark->mark_time = '17:59:00'; $mark->save();
    $filters = ['from' => '2026-10-16', 'to' => '2026-10-16', 'employee_id' => $this->employee->id];
    $response = $this->get(route('attendances.index', $filters))->assertOk()->assertSee('Guardar almuerzo confirmado');
    $row = $response->viewData('records')->first()->day_result;
    $this->get(route('reports.attendances', $filters))->assertOk()->assertDontSee('Guardar almuerzo confirmado')->assertDontSee(route('attendances.lunch-regularization', $row['entry']));
    $this->from(route('attendances.index', $filters))->post(route('attendances.lunch-regularization', $row['entry']), [
        'lunch_start' => '12:00', 'lunch_end' => '13:00', 'reason' => 'Retorno olvidado, horario confirmado con el responsable.',
        'source_fingerprint' => $row['source_fingerprint'], 'current_adjustment_id' => 0,
    ])->assertSessionHas('success')->assertRedirect(route('attendances.index', $filters));
    $this->get(route('attendances.index', $filters))->assertSee('Historial de regularizaciones')->assertSee('Aprobar extras');
});

test('report graphs compare employees and selecting one gives daily values with incomplete days as null', function () {
    reportMarks($this->employee);
    $other = Employee::create(['document_type' => 'DNI', 'document_number' => '87654321', 'first_name' => 'Luis', 'last_name_father' => 'Ramos', 'last_name_mother' => 'Lopez']);
    $filters = ['from' => '2026-10-15', 'to' => '2026-10-16'];
    $this->get(route('reports.attendances', $filters))->assertOk()
        ->assertViewHas('chartData', fn ($data) => $data['mode'] === 'employees' && $data['points']->count() === 2 && $data['points']->first()['worked'] === 9 * 3600)
        ->assertSee('attendance-work-chart')->assertSee('attendance-extra-chart')->assertSee('Generar reporte');
    $this->get(route('reports.attendances', $filters + ['employee_id' => $this->employee->id]))->assertOk()
        ->assertViewHas('selectedEmployee', fn ($employee) => $employee->id === $this->employee->id)
        ->assertViewHas('chartData', function ($data) {
            return $data['mode'] === 'daily' && $data['points']->count() === 2
                && $data['points'][0]['label'] === '15/10/2026' && $data['points'][0]['scheduled'] === 8 * 3600 && $data['points'][0]['worked'] === null
                && $data['points'][1]['label'] === '16/10/2026' && $data['points'][1]['worked'] === 9 * 3600 && $data['points'][1]['pending'] === 3600 && $data['points'][1]['approved'] === 0;
        });
    $this->get(route('reports.attendances', $filters + ['employee_id' => $other->id]))->assertOk()->assertSee('No hay datos para graficar')->assertViewHas('chartData', fn ($data) => $data['points']->isEmpty());
});

test('employee daily graphs cover the full period regardless of detail pagination', function () {
    $this->employee->schedules()->delete();
    foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $day) $this->employee->schedules()->create(['day' => $day, 'start_time' => '08:00', 'end_time' => '17:00']);
    $filters = ['from' => '2026-10-01', 'to' => '2026-10-31', 'employee_id' => $this->employee->id, 'page' => 2];
    $this->get(route('reports.attendances', $filters))->assertOk()
        ->assertViewHas('days', fn ($days) => $days->count() === 6)
        ->assertViewHas('chartData', fn ($data) => $data['points']->count() === 31 && $data['points']->sum('scheduled') === 31 * 8 * 3600);
});

test('report detail totals and both graph modes agree after lunch entry exit corrections and extra approval', function () {
    reportMarks($this->employee);
    Setting::setValue('lunch_tolerance_minutes', '25');
    $calculate = fn () => app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60);
    $day = $calculate();
    $this->post(route('attendances.lunch-regularization', $day['entry']), [
        'lunch_start' => '12:00', 'lunch_end' => '12:20', 'reason' => 'Refrigerio real confirmado por responsable de sede.',
        'source_fingerprint' => $day['source_fingerprint'], 'current_adjustment_id' => 0,
    ])->assertSessionHas('success');
    $day = $calculate();
    $correct = function ($day, $exit) {
        return $this->post(route('attendances.time-correction', $day['entry']), [
            'entry_time' => '07:30', 'exit_time' => $exit, 'reason' => 'Horas reales confirmadas por responsable de sede.',
            'source_fingerprint' => $day['source_fingerprint'], 'current_lunch_adjustment_id' => $day['adjustment']->id,
        ])->assertSessionHas('success');
    };
    $correct($day, '18:30');
    $filters = ['from' => '2026-10-16', 'to' => '2026-10-16'];
    $checkReport = function ($worked, $pending, $approved, $exit) use ($filters) {
        foreach ([[], ['employee_id' => $this->employee->id]] as $employeeFilter) {
            $this->get(route('reports.attendances', $filters + $employeeFilter))->assertOk()
                ->assertViewHas('rows', fn ($rows) => $rows->first()['complete'] && $rows->first()['adjusted'] && $rows->first()['lunch'] === 1200 && $rows->first()['worked'] === $worked && $rows->first()['planned'] === 28800)
                ->assertViewHas('summaries', fn ($summaries) => $summaries->sum('worked') === $worked && $summaries->sum('lunch') === 1200 && $summaries->sum('scheduled') === 28800 && $summaries->sum('pending') === $pending && $summaries->sum('approved') === $approved)
                ->assertViewHas('chartData', fn ($data) => $data['points']->first()['worked'] === $worked && $data['points']->first()['scheduled'] === 28800 && $data['points']->first()['pending'] === $pending && $data['points']->first()['approved'] === $approved)
                ->assertSee('<span class="fw-semibold">07:30</span>', false)
                ->assertSee('<span class="fw-semibold">12:20</span>', false)
                ->assertSee('<span class="fw-semibold">'.$exit.'</span>', false)
                ->assertSee('Regularizado · Original: 13:00')->assertSee('Original: 08:00')
                ->assertDontSee('Guardar almuerzo confirmado')->assertDontSee('Guardar ingreso / salida');
        }
    };
    $checkReport(38400, 9600, 0, '18:30');
    $day = $calculate();
    $this->post(route('attendances.overtime-review', $day['exit']), ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('success');
    $checkReport(38400, 0, 9600, '18:30');
    $correct($calculate(), '18:00');
    $checkReport(36600, 7800, 0, '18:00');
});

test('report does not display a stale lunch correction or count its work after original marks change', function () {
    reportMarks($this->employee);
    $day = app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60);
    $this->post(route('attendances.lunch-regularization', $day['entry']), [
        'lunch_start' => '12:05', 'lunch_end' => '13:05', 'reason' => 'Refrigerio real confirmado por responsable.',
        'source_fingerprint' => $day['source_fingerprint'], 'current_adjustment_id' => 0,
    ])->assertSessionHas('success');
    $this->employee->attendances()->where('mark_type', 'break_in')->update(['mark_time' => '13:10:00']);
    $this->get(route('reports.attendances', ['from' => '2026-10-16', 'to' => '2026-10-16', 'employee_id' => $this->employee->id]))->assertOk()
        ->assertViewHas('summaries', fn ($summaries) => $summaries->sum('worked') === 0 && $summaries->sum('approved') === 0 && $summaries->sum('incomplete_days') === 1)
        ->assertViewHas('chartData', fn ($data) => $data['points']->first()['worked'] === null)
        ->assertSee('<span class="fw-semibold">13:10</span>', false)
        ->assertDontSee('<span class="fw-semibold">13:05</span>', false)->assertSee('Confirme nuevamente el almuerzo');
});
