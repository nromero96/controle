<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::setValue('restrict_ip_enabled', '0');
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-17 08:00:00', config('app.timezone')));
});

function unscheduledSubmit($test, string $type, string $time) {
    $test->travelTo(\Carbon\Carbon::parse('2026-10-17 '.$time, config('app.timezone')));
    return $test->postJson('/submit-marker', ['document_number' => $test->employee->document_number, 'type' => $type]);
}

test('unscheduled day allows marking net work becomes only pending extra and requires approval', function () {
    $this->getJson('/get-employee/12345678')->assertJsonPath('success', true)->assertJsonPath('is_unscheduled_day', true)->assertJsonPath('allowed_types', ['entry']);
    foreach (['entry' => '08:00:00', 'break_out' => '12:00:00', 'break_in' => '13:00:00', 'exit' => '17:00:00'] as $type => $time) {
        unscheduledSubmit($this, $type, $time)->assertOk();
    }
    $result = app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60);
    expect($result['complete'])->toBeTrue()->and($result['scheduled'])->toBe(0)->and($result['worked'])->toBe(8 * 3600)
        ->and($result['eligible'])->toBe(8 * 3600)->and($result['approved'])->toBe(0)->and($result['status'])->toBe('pending')
        ->and($result['exit_status'])->toBe('unknown');
    $this->actingAs(User::factory()->create())->post(route('attendances.overtime-review', $result['exit']), ['decision' => 'approved', 'fingerprint' => $result['fingerprint']])->assertSessionHas('success');
    $approved = app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60);
    expect($approved['approved'])->toBe(8 * 3600);
    $this->get(route('reports.attendances', ['from' => '2026-10-17', 'to' => '2026-10-17']))->assertOk()->assertSee('Día sin horario programado');
});

test('later schedule creation does not change the recorded unscheduled day', function () {
    unscheduledSubmit($this, 'entry', '08:00:00')->assertOk();
    $this->employee->schedules()->create(['day' => 'Sábado', 'start_time' => '08:00', 'end_time' => '17:00']);
    $this->getJson('/get-employee/12345678')->assertJsonPath('is_unscheduled_day', true)->assertJsonPath('schedule.start_time', null);
    $entry = $this->employee->attendances()->first();
    expect($entry->is_unscheduled_day)->toBeTrue()->and($entry->scheduled_start_time)->toBeNull()->and($entry->lateness_time)->toBeNull();
});

test('missing lunch on unscheduled day still requires regularization before extras calculation', function () {
    unscheduledSubmit($this, 'entry', '08:00:00')->assertOk();
    unscheduledSubmit($this, 'exit', '17:00:00')->assertOk();
    $calculator = app(AttendanceDayCalculator::class);
    $result = $calculator->calculate($this->employee->attendances()->get(), 0, 60);
    expect($result['complete'])->toBeFalse()->and($result['eligible'])->toBe(0);
    $this->actingAs(User::factory()->create())->post(route('attendances.lunch-regularization', $result['entry']), [
        'lunch_start' => '12:00', 'lunch_end' => '13:00', 'reason' => 'Almuerzo confirmado con el responsable.', 'source_fingerprint' => $result['source_fingerprint'], 'current_adjustment_id' => 0,
    ])->assertSessionHas('success');
    expect($calculator->calculate($this->employee->attendances()->get(), 0, 60)['eligible'])->toBe(8 * 3600);
});

test('legacy missing schedule is not silently treated as a full overtime day', function () {
    foreach (['entry' => '08:00', 'break_out' => '12:00', 'break_in' => '13:00', 'exit' => '17:00'] as $type => $time) {
        Attendance::create(['employee_id' => $this->employee->id, 'mark_date' => '2026-10-17', 'mark_type' => $type, 'mark_time' => $time]);
    }
    $result = app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60);
    expect($result['is_unscheduled_day'])->toBeFalse()->and($result['complete'])->toBeFalse()->and($result['eligible'])->toBe(0);
});
