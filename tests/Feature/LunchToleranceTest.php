<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '01234567', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
});

function toleranceMarks($test, string $returnTime, ?int $tolerance = 5) {
    foreach (['entry' => '08:00:00', 'break_out' => '13:00:00', 'break_in' => $returnTime, 'exit' => '18:00:00'] as $type => $time) {
        $mark = new Attendance(['employee_id' => $test->employee->id, 'mark_type' => $type, 'mark_date' => '2026-10-16', 'mark_time' => $time]);
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00:00'; $mark->scheduled_end_time = '17:00:00';
            $mark->scheduled_break_start = '13:00:00'; $mark->scheduled_break_end = '13:45:00';
            $mark->minimum_lunch_minutes = 45; $mark->maximum_lunch_minutes = 180;
            $mark->lunch_tolerance_minutes = $tolerance;
        }
        $mark->save();
    }
    return $test->employee->attendances()->get();
}

test('short lunch tolerance respects the exact second boundary and preserves real time', function ($returnTime, $complete, $lunchSeconds) {
    $marks = toleranceMarks($this, $returnTime);
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 0, 60);
    expect($result['complete'])->toBe($complete)->and($result['lunch_threshold'])->toBe(40);
    if ($complete) {
        expect($result['lunch'])->toBe($lunchSeconds)->and($result['worked'])->toBe(10 * 3600 - $lunchSeconds)->and($result['approved'])->toBe(0)->and($result['status'])->toBe('pending');
    } else {
        expect($result['eligible'])->toBe(0)->and($result['reason'])->toContain('tolerancia');
    }
})->with([
    ['13:45:00', true, 2700], ['13:44:59', true, 2699], ['13:40:00', true, 2400],
    ['13:39:59', false, null], ['13:30:00', false, null], ['13:00:01', false, null],
]);

test('zero tolerance keeps strict minimum and existing snapshot ignores later setting', function () {
    $marks = toleranceMarks($this, '13:44:59', 0);
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 0, 60, 45, 180, 10);
    expect($result['complete'])->toBeFalse()->and($result['lunch_tolerance'])->toBe(0);
});

test('legacy records use current tolerance until review and freeze it on approval', function () {
    $marks = toleranceMarks($this, '13:40:00', null);
    Setting::setValue('lunch_tolerance_minutes', '5');
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 0, 60, 45, 180, 5);
    $this->actingAs(User::factory()->create())->post(route('attendances.overtime-review', $result['exit']), ['decision' => 'approved', 'fingerprint' => $result['fingerprint']])->assertSessionHas('success');
    $result = app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60, 45, 180, 0);
    expect($result['lunch_tolerance'])->toBe(5)->and($result['status'])->toBe('approved');
});

test('regularization uses the same tolerance without automatic overtime approval', function () {
    $marks = toleranceMarks($this, '13:00:01');
    $result = app(AttendanceDayCalculator::class)->calculate($marks, 0, 60);
    $this->actingAs(User::factory()->create())->post(route('attendances.lunch-regularization', $result['entry']), [
        'lunch_start' => '13:00', 'lunch_end' => '13:40', 'reason' => 'Hora real confirmada por el responsable.',
        'source_fingerprint' => $result['source_fingerprint'], 'current_adjustment_id' => 0,
    ])->assertSessionHas('success');
    $updated = app(AttendanceDayCalculator::class)->calculate($this->employee->attendances()->get(), 0, 60);
    expect($updated['lunch'])->toBe(2400)->and($updated['complete'])->toBeTrue()->and($updated['approved'])->toBe(0);
});

test('tolerance is validated saved shown and snapshotted at ingress', function () {
    $this->actingAs(User::factory()->create());
    $base = ['company_name' => 'Empresa', 'restrict_ip_enabled' => '0', 'camera_enabled' => '0'];
    foreach (['-1', '31', '1.5', 'abc'] as $value) {
        $this->put(route('settings.update'), $base + ['lunch_tolerance_minutes' => $value])->assertSessionHasErrors('lunch_tolerance_minutes');
    }
    $this->put(route('settings.update'), $base + ['lunch_tolerance_minutes' => '10'])->assertSessionHasNoErrors();
    expect(Setting::getValue('lunch_tolerance_minutes'))->toBe('10');
    $this->get(route('settings.index'))->assertOk()->assertSee('Tolerancia para almuerzo corto');
    $this->employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 08:00:00', config('app.timezone')));
    $this->postJson('/submit-marker', ['document_number' => $this->employee->document_number, 'type' => 'entry'])->assertOk();
    expect((int) $this->employee->attendances()->first()->lunch_tolerance_minutes)->toBe(10);
});
