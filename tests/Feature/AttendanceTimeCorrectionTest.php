<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-29 19:00:00', config('app.timezone')));
    $this->admin = User::factory()->create();
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Pérez', 'last_name_mother' => 'López']);
});

function correctionDay($test, array $times = []): array
{
    foreach (array_replace(['entry' => '08:00:00', 'break_out' => '12:00:00', 'break_in' => '13:00:00', 'exit' => '18:00:00'], $times) as $type => $time) {
        if (! $time) {
            continue;
        }
        $mark = new Attendance(['employee_id' => $test->employee->id, 'mark_date' => '2026-10-29', 'mark_type' => $type, 'mark_time' => $time]);
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00:00';
            $mark->scheduled_end_time = '17:00:00';
            $mark->scheduled_break_start = '12:00:00';
            $mark->scheduled_break_end = '13:00:00';
        }
        $mark->recorded_branch_name = 'Sede histórica';
        $mark->branch_snapshot_captured = true;
        $mark->save();
    }

    return correctionCalculation($test);
}

function correctionCalculation($test): array
{
    return app(AttendanceDayCalculator::class)->calculate($test->employee->attendances()->get(), 0, 60);
}

function correctionPayload(array $day, array $changes = []): array
{
    return array_replace(['entry_time' => '07:30', 'exit_time' => '18:00', 'reason' => 'Hora confirmada con el responsable de sede.', 'source_fingerprint' => $day['source_fingerprint'], 'current_lunch_adjustment_id' => $day['adjustment']?->id ?? 0], $changes);
}

test('correction preserves original marks and resets overtime approval with audited history', function () {
    $day = correctionDay($this);
    $this->actingAs($this->admin)->post(route('attendances.overtime-review', $day['exit']), ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('success');
    $this->post(route('attendances.time-correction', $day['entry']), correctionPayload($day))->assertSessionHas('success');
    $updated = correctionCalculation($this);
    expect($updated['entry']->mark_time)->toBe('08:00:00')->and($updated['entry']->effectiveTime())->toBe('07:30:00');
    expect($updated['worked'])->toBe(34200)->and($updated['eligible'])->toBe(5400)->and($updated['approved'])->toBe(0)->and($updated['status'])->toBe('pending');
    $history = $updated['entry']->timeCorrections->first();
    expect($history->previous_time)->toBe('08:00:00')->and($history->recorded_by)->toBe($this->admin->id)->and($history->previous_overtime_review['overtime_status'])->toBe('approved');
    $this->get(route('attendances.index'))->assertOk()->assertSee('Corregir ingreso / salida')->assertSee('Original: 08:00')->assertSee('Historial de ingreso y salida');
    $this->post(route('attendances.overtime-review', $updated['exit']), ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('error');
    $this->post(route('attendances.overtime-review', $updated['exit']), ['decision' => 'approved', 'fingerprint' => $updated['fingerprint']])->assertSessionHas('success');
});

test('missing entry requires explicit historical schedule and preserves historical branch', function () {
    $day = correctionDay($this, ['entry' => null]);
    $anchor = $day['marks']->first();
    $this->actingAs($this->admin)->post(route('attendances.time-correction', $anchor), correctionPayload($day))->assertSessionHasErrors('scheduled_start');
    $this->post(route('attendances.time-correction', $anchor), correctionPayload($day, ['scheduled_start' => '08:00', 'scheduled_end' => '17:00']))->assertSessionHas('success');
    $updated = correctionCalculation($this);
    expect($updated['complete'])->toBeTrue()->and($updated['entry']->is_manual)->toBe(1)->and($updated['entry']->recorded_branch_name)->toBe('Sede histórica');
    expect($updated['entry']->timeCorrections->first()->previous_time)->toBeNull();
    $this->assertDatabaseCount('attendances', 4);
});

test('missing exit is added manually', function () {
    $day = correctionDay($this, ['exit' => null]);
    $this->actingAs($this->admin)->post(route('attendances.time-correction', $day['entry']), correctionPayload($day, ['entry_time' => '08:00', 'exit_time' => '17:00']))->assertSessionHas('success');
    expect(correctionCalculation($this)['worked'])->toBe(28800);
    expect($this->employee->attendances()->where('mark_type', 'exit')->first()->is_manual)->toBe(1);
});

test('missing entry can be registered for a day explicitly confirmed as unscheduled', function () {
    $day = correctionDay($this, ['entry' => null]);
    $this->actingAs($this->admin)->post(route('attendances.time-correction', $day['marks']->first()), correctionPayload($day, ['unscheduled' => '1']))->assertSessionHas('success');
    $updated = correctionCalculation($this);
    expect($updated['is_unscheduled_day'])->toBeTrue()->and($updated['scheduled'])->toBe(0)->and($updated['eligible'])->toBe(34200)->and($updated['status'])->toBe('pending');
});

test('unchanged values do not create audit entries or invalidate existing approval', function () {
    $day = correctionDay($this);
    $this->actingAs($this->admin)->post(route('attendances.overtime-review', $day['exit']), ['decision' => 'approved', 'fingerprint' => $day['fingerprint']])->assertSessionHas('success');
    $this->post(route('attendances.time-correction', $day['entry']), correctionPayload($day, ['entry_time' => '08:00']))->assertSessionHas('error');
    $this->assertDatabaseCount('attendance_time_corrections', 0);
    expect(correctionCalculation($this)['status'])->toBe('approved');
});

test('correction validates hours reason future times and lunch boundaries atomically', function ($changes, $error) {
    $day = correctionDay($this);
    $this->actingAs($this->admin)->post(route('attendances.time-correction', $day['entry']), correctionPayload($day, $changes))->assertSessionHasErrors($error);
    $this->assertDatabaseCount('attendance_time_corrections', 0);
    expect($this->employee->attendances()->where('mark_type', 'entry')->first()->corrected_time)->toBeNull();
})->with([
    [['entry_time' => '25:00'], 'entry_time'],
    [['exit_time' => '07:00'], 'exit_time'],
    [['entry_time' => '12:30'], 'entry_time'],
    [['exit_time' => '12:30'], 'entry_time'],
    [['exit_time' => '20:00'], 'exit_time'],
    [['reason' => ''], 'reason'],
]);

test('stale forms unauthenticated requests and duplicate submissions cannot overwrite corrections', function () {
    $day = correctionDay($this);
    $url = route('attendances.time-correction', $day['entry']);
    $this->post($url, correctionPayload($day))->assertRedirect(route('login'));
    $this->actingAs($this->admin)->post($url, correctionPayload($day))->assertSessionHas('success');
    $this->post($url, correctionPayload($day, ['entry_time' => '07:00']))->assertSessionHas('error');
    $this->assertDatabaseCount('attendance_time_corrections', 1);
});

test('confirmed lunch remains valid after ingress correction and reports use corrected work hours', function () {
    $day = correctionDay($this);
    $this->actingAs($this->admin)->post(route('attendances.lunch-regularization', $day['entry']), ['lunch_start' => '12:00', 'lunch_end' => '13:00', 'reason' => 'Refrigerio confirmado por responsable.', 'source_fingerprint' => $day['source_fingerprint'], 'current_adjustment_id' => 0])->assertSessionHas('success');
    $day = correctionCalculation($this);
    $this->post(route('attendances.time-correction', $day['entry']), correctionPayload($day))->assertSessionHas('success');
    $updated = correctionCalculation($this);
    expect($updated['adjusted'])->toBeTrue()->and($updated['complete'])->toBeTrue()->and($updated['worked'])->toBe(34200);
    $this->get(route('reports.attendances', ['employee_id' => $this->employee->id, 'from' => '2026-10-29', 'to' => '2026-10-29']))->assertOk()->assertSee('07:30');
});

test('entry only correction leaves a live workday without a fabricated exit', function () {
    $day = correctionDay($this, ['break_out' => null, 'break_in' => null, 'exit' => null]);
    $this->actingAs($this->admin)->post(route('attendances.time-correction', $day['entry']), correctionPayload($day, ['exit_time' => null]))->assertSessionHas('success');
    $this->assertDatabaseCount('attendances', 1);
    expect(correctionCalculation($this)['complete'])->toBeFalse();
});
