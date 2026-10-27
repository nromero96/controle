<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function exitToleranceDay(string $exitTime, ?int $tolerance = 5): array {
    $employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    foreach (['entry' => '08:00:00', 'break_out' => '12:00:00', 'break_in' => '13:00:00', 'exit' => $exitTime] as $type => $time) {
        $mark = new Attendance(['employee_id' => $employee->id, 'mark_date' => '2026-10-16', 'mark_time' => $time, 'mark_type' => $type]);
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00:00'; $mark->scheduled_end_time = '17:00:00';
            $mark->scheduled_break_start = '12:00:00'; $mark->scheduled_break_end = '13:00:00';
            $mark->exit_tolerance_minutes = $tolerance;
        }
        $mark->save();
    }
    return [$employee, $employee->attendances()->get()];
}

test('departure tolerance includes boundaries and classifies differences without blocking calculation', function ($time, $status) {
    [$employee, $marks] = exitToleranceDay($time);
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 15, 60);
    expect($result['exit_status'])->toBe($status)->and($result['complete'])->toBeTrue()->and($result['exit_tolerance'])->toBe(5);
})->with([
    ['16:55:00', 'normal'], ['16:54:59', 'early'], ['16:59:59', 'normal'],
    ['17:00:00', 'normal'], ['17:05:00', 'normal'], ['17:05:01', 'late'],
]);

test('departure tolerance preserves actual worked and extra time and existing snapshot', function () {
    [$employee, $marks] = exitToleranceDay('17:03:00');
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 0, 60, 45, 180, 5, 0);
    expect($result['exit_status'])->toBe('normal')->and($result['worked'])->toBe(8 * 3600 + 180)
        ->and($result['eligible'])->toBe(180)->and($result['approved'])->toBe(0);
    $withMinimum = app(AttendanceDayCalculator::class)->calculate($marks, 15, 60);
    expect($withMinimum['eligible'])->toBe(0);
    expect($employee->attendances()->where('mark_type', 'exit')->first()->mark_time)->toBe('17:03:00');
});

test('zero tolerance requires exact time only for the informational departure label', function () {
    [$employee, $marks] = exitToleranceDay('16:59:59', 0);
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 0, 60);
    expect($result['exit_status'])->toBe('early')->and($result['complete'])->toBeTrue()->and($result['worked'])->toBe(8 * 3600 - 1);
});

test('exit tolerance is validated persisted displayed and frozen for new ingresses', function () {
    $this->actingAs(User::factory()->create());
    $base = ['company_name' => 'Empresa', 'restrict_ip_enabled' => '0', 'camera_enabled' => '0'];
    foreach (['-1', '61', '1.5', 'abc'] as $value) {
        $this->put(route('settings.update'), $base + ['exit_tolerance_minutes' => $value])->assertSessionHasErrors('exit_tolerance_minutes');
    }
    $this->put(route('settings.update'), $base + ['exit_tolerance_minutes' => '10'])->assertSessionHasNoErrors();
    expect(Setting::getValue('exit_tolerance_minutes'))->toBe('10');
    $this->get(route('settings.index'))->assertOk()->assertSee('Tolerancia de salida');
    $employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    $employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 08:00:00', config('app.timezone')));
    $this->postJson('/submit-marker', ['document_number' => $employee->document_number, 'type' => 'entry'])->assertOk();
    expect((int) $employee->attendances()->first()->exit_tolerance_minutes)->toBe(10);
});

test('attendance list shows departure tolerance independently of overtime status', function () {
    [$employee, $marks] = exitToleranceDay('17:03:00');
    $this->actingAs(User::factory()->create())->get(route('reports.attendances', ['from' => '2026-10-16', 'to' => '2026-10-16']))
        ->assertOk()->assertSee('Salida dentro de tolerancia')->assertSee('Pendiente de aprobar extras');
});
