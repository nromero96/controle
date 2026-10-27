<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::setValue('restrict_ip_enabled', '0');
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 08:00:00', config('app.timezone')));
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    $this->schedule = $this->employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
});

function markerSubmit($test, string $type) {
    return $test->postJson('/submit-marker', ['document_number' => $test->employee->document_number, 'type' => $type]);
}

test('lookup and server follow the same steps with accurate receipt times', function () {
    $this->getJson('/get-employee/12345678')->assertJsonPath('allowed_types', ['entry']);
    markerSubmit($this, 'break_in')->assertUnprocessable();
    markerSubmit($this, 'exit')->assertUnprocessable();
    markerSubmit($this, 'entry')->assertOk()->assertJsonPath('mark_time', '08:00:00')->assertJsonPath('mark_type', 'entry')->assertJsonPath('employee_name', 'Ana Perez');
    $this->getJson('/get-employee/12345678')->assertJsonPath('allowed_types', ['break_out', 'exit'])->assertJsonPath('attendances.0.mark_time', '08:00:00');
    markerSubmit($this, 'break_in')->assertUnprocessable();
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 12:00:00', config('app.timezone')));
    markerSubmit($this, 'break_out')->assertOk();
    $this->getJson('/get-employee/12345678')->assertJsonPath('allowed_types', ['break_in', 'exit']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 13:00:00', config('app.timezone')));
    markerSubmit($this, 'break_in')->assertOk();
    $this->getJson('/get-employee/12345678')->assertJsonPath('allowed_types', ['exit']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 17:00:00', config('app.timezone')));
    markerSubmit($this, 'exit')->assertOk()->assertJsonPath('mark_time', '17:00:00');
    $this->getJson('/get-employee/12345678')->assertJsonPath('allowed_types', []);
    markerSubmit($this, 'exit')->assertConflict();
    $this->assertDatabaseCount('attendances', 4);
});

test('original entry schedule supports remaining marks after admin changes schedule', function () {
    markerSubmit($this, 'entry')->assertOk();
    $this->schedule->delete();
    $this->getJson('/get-employee/12345678')->assertOk()->assertJsonPath('success', true)->assertJsonPath('schedule.end_time', '17:00');
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 17:00:00', config('app.timezone')));
    markerSubmit($this, 'exit')->assertOk();
    markerSubmit($this, 'break_out')->assertUnprocessable();
});

test('inactive employees cannot query or submit markers', function () {
    $this->employee->update(['status' => 'inactivo']);
    $this->getJson('/get-employee/12345678')->assertForbidden()->assertJsonPath('success', false);
    markerSubmit($this, 'entry')->assertForbidden();
    $this->assertDatabaseCount('attendances', 0);
});

test('lookup supports days without schedule and discloses no extra personal data', function () {
    $this->getJson('/get-employee/99999999')->assertJsonPath('success', false);
    $this->getJson('/get-employee/12345678')->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.document_number');
    $this->schedule->delete();
    $this->getJson('/get-employee/12345678')->assertJsonPath('success', true)->assertJsonPath('is_unscheduled_day', true)->assertJsonPath('allowed_types', ['entry']);
});
