<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 18:00:00', config('app.timezone')));
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Pérez', 'last_name_mother' => 'López']);
    $this->calculator = new AttendanceDayCalculator();
});

function overtimeMarks(Employee $employee, array $times = [], bool $snapshot = true, bool $scheduledLunch = true) {
    $times = array_replace(['entry' => '07:30:00', 'break_out' => '12:00:00', 'break_in' => '13:00:00', 'exit' => '17:30:00'], $times);
    foreach ($times as $type => $time) {
        if ($time === null) continue;
        $mark = new Attendance(['employee_id' => $employee->id, 'mark_date' => '2026-10-16', 'mark_time' => $time, 'mark_type' => $type]);
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00:00';
            $mark->scheduled_end_time = '17:00:00';
            $mark->scheduled_break_start = $scheduledLunch ? '12:00:00' : null;
            $mark->scheduled_break_end = $scheduledLunch ? '13:00:00' : null;
            $mark->overtime_minimum_minutes = $snapshot ? 15 : null;
            $mark->reference_lunch_minutes = $snapshot ? 60 : null;
        }
        $mark->save();
    }
    return $employee->attendances()->get();
}

function calculateOvertime($test) {
    return $test->calculator->calculate($test->employee->attendances()->get(), 15, 60);
}

test('net work counts early arrival and subtracts actual lunch', function () {
    overtimeMarks($this->employee);
    $day = calculateOvertime($this);
    expect($day['worked'])->toBe(9 * 3600)->and($day['scheduled'])->toBe(8 * 3600)
        ->and($day['eligible'])->toBe(3600)->and($day['approved'])->toBe(0)->and($day['status'])->toBe('pending');
});

test('late arrival and extended lunch offset extra time after scheduled exit', function () {
    overtimeMarks($this->employee, ['entry' => '08:30:00', 'break_in' => '13:30:00', 'exit' => '18:00:00']);
    $day = calculateOvertime($this);
    expect($day['worked'])->toBe(8 * 3600)->and($day['raw_extra'])->toBe(0)->and($day['status'])->toBe('none');
});

test('minimum is a threshold and qualifying time is not reduced', function ($exit, $eligible) {
    overtimeMarks($this->employee, ['entry' => '08:00:00', 'exit' => $exit]);
    expect(calculateOvertime($this)['eligible'])->toBe($eligible);
})->with([['17:14:59', 0], ['17:15:00', 900], ['17:20:00', 1200], ['16:00:00', 0]]);

test('zero minimum preserves seconds and no rounding creates extra time', function () {
    $marks = overtimeMarks($this->employee, ['entry' => '08:00:00', 'exit' => '17:00:01']);
    $entry = $marks->firstWhere('mark_type', 'entry');
    $entry->overtime_minimum_minutes = 0; $entry->save();
    expect(calculateOvertime($this)['eligible'])->toBe(1);
});

test('missing and disordered marks never permit approval', function ($times) {
    overtimeMarks($this->employee, $times);
    $day = calculateOvertime($this);
    expect($day['complete'])->toBeFalse()->and($day['status'])->toBe('incomplete')->and($day['eligible'])->toBe(0);
})->with([
    [['break_out' => null]], [['break_in' => null]], [['entry' => null]], [['exit' => null]],
    [['break_out' => '14:00:00', 'break_in' => '13:00:00']],
    [['entry' => '18:00:00']],
]);

test('unscheduled lunch uses configured reference and snapshots are preserved', function () {
    overtimeMarks($this->employee, [], true, false);
    $day = $this->calculator->calculate($this->employee->attendances()->get(), 120, 30);
    expect($day['uses_reference_lunch'])->toBeTrue()->and($day['scheduled'])->toBe(8 * 3600)
        ->and($day['eligible'])->toBe(3600)->and($day['minimum'])->toBe(15);
});

test('new ingress snapshots settings and exit stores net eligible extra using original schedule', function () {
    Setting::setValue('restrict_ip_enabled', '0');
    Setting::setValue('overtime_minimum_minutes', '15');
    Setting::setValue('reference_lunch_minutes', '60');
    $schedule = $this->employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
    foreach (['entry' => '07:30:00', 'break_out' => '12:00:00', 'break_in' => '13:00:00', 'exit' => '17:30:00'] as $type => $time) {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-16 '.$time, config('app.timezone')));
        if ($type === 'exit') {
            $schedule->delete();
            Setting::setValue('overtime_minimum_minutes', '120');
        }
        $this->postJson('/submit-marker', ['document_number' => $this->employee->document_number, 'type' => $type])->assertOk();
    }
    $exit = $this->employee->attendances()->where('mark_type', 'exit')->first();
    expect($exit->overtime_time)->toBe('01:00:00');
    expect(calculateOvertime($this)['status'])->toBe('pending');
});

test('approval records reviewer time and approved amount and cannot be repeated', function () {
    overtimeMarks($this->employee);
    $day = calculateOvertime($this);
    $user = User::factory()->create();
    $url = route('attendances.overtime-review', $day['exit']);
    $this->post($url, ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertRedirect('/login');
    $this->actingAs($user)->post($url, ['decision' => 'approved', 'fingerprint' => $day['fingerprint'], 'note' => 'Trabajo adicional'])->assertSessionHas('success');
    $exit = $day['exit']->fresh();
    expect($exit->overtime_reviewed_by)->toBe($user->id)->and($exit->overtime_reviewed_at)->not->toBeNull();
    expect(calculateOvertime($this)['approved'])->toBe(3600);
    $this->post($url, ['decision' => 'rejected', 'fingerprint' => $day['fingerprint'], 'note' => 'Otra revisión'])->assertSessionHas('error');
    expect($exit->fresh()->overtime_status)->toBe('approved');
});

test('rejection requires reason and counts zero approved extra', function () {
    overtimeMarks($this->employee);
    $day = calculateOvertime($this);
    $url = route('attendances.overtime-review', $day['exit']);
    $this->actingAs(User::factory()->create())->post($url, ['decision' => 'rejected', 'fingerprint' => $day['fingerprint']])->assertSessionHasErrors('note');
    $this->post($url, ['decision' => 'rejected', 'fingerprint' => $day['fingerprint'], 'note' => 'Tiempo no autorizado'])->assertSessionHas('success');
    expect(calculateOvertime($this)['status'])->toBe('rejected')->and(calculateOvertime($this)['approved'])->toBe(0);
});

test('incomplete below minimum and stale calculations cannot be approved', function () {
    overtimeMarks($this->employee);
    $day = calculateOvertime($this);
    $this->actingAs(User::factory()->create());
    $url = route('attendances.overtime-review', $day['exit']);
    $this->post($url, ['decision' => 'approved', 'fingerprint' => str_repeat('0', 64)])->assertSessionHas('error');
    $exit = $day['exit']; $exit->mark_time = '16:00:00'; $exit->save();
    $this->post($url, ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('error');
    $exit->mark_time = '17:30:00'; $exit->save();
    $this->employee->attendances()->where('mark_type', 'break_in')->delete();
    $this->post($url, ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('error');
});

test('changing underlying marks invalidates an existing approval', function () {
    overtimeMarks($this->employee);
    $day = calculateOvertime($this);
    $this->actingAs(User::factory()->create())->post(route('attendances.overtime-review', $day['exit']), ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('success');
    $exit = $day['exit']; $exit->mark_time = '18:00:00'; $exit->save();
    expect(calculateOvertime($this)['status'])->toBe('pending')->and(calculateOvertime($this)['approved'])->toBe(0);
});

test('legacy calculations use current rules until reviewed then freeze them', function () {
    overtimeMarks($this->employee, [], false, false);
    Setting::setValue('reference_lunch_minutes', '60');
    Setting::setValue('overtime_minimum_minutes', '15');
    $day = calculateOvertime($this);
    $this->actingAs(User::factory()->create())->post(route('attendances.overtime-review', $day['exit']), ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('success');
    $result = $this->calculator->calculate($this->employee->attendances()->get(), 120, 30);
    expect($result['status'])->toBe('approved')->and($result['approved'])->toBe(3600)->and($result['legacy_rules'])->toBeFalse();
});

test('attendance list renders calculations filters and empty results', function () {
    overtimeMarks($this->employee);
    $this->actingAs(User::factory()->create())->get(route('reports.attendances'))->assertOk()->assertSee('09:00:00')->assertSee('01:00:00')->assertSee('Pendiente');
    $this->get(route('reports.attendances', ['from' => '2026-10-15', 'to' => '2026-10-15']))->assertOk()->assertSee('No hay jornadas');
    $this->get(route('reports.attendances', ['from' => '2026-10-17', 'to' => '2026-10-16']))->assertSessionHasErrors('to');
    $this->get(route('reports.attendances', ['to' => '2026-09-30']))->assertOk();
});

test('overtime and lunch settings validate nonnegative whole minutes', function () {
    $this->actingAs(User::factory()->create());
    $base = ['company_name' => 'Empresa', 'restrict_ip_enabled' => '0', 'camera_enabled' => '0'];
    $this->put(route('settings.update'), $base + ['overtime_minimum_minutes' => '-1', 'reference_lunch_minutes' => '1.5'])->assertSessionHasErrors(['overtime_minimum_minutes', 'reference_lunch_minutes']);
    $this->put(route('settings.update'), $base + ['overtime_minimum_minutes' => '30', 'reference_lunch_minutes' => '45'])->assertSessionHasNoErrors();
    expect(Setting::getValue('overtime_minimum_minutes'))->toBe('30')->and(Setting::getValue('reference_lunch_minutes'))->toBe('45');
    $this->get(route('settings.index'))->assertOk()->assertSee('Mínimo de minutos extra');
});
