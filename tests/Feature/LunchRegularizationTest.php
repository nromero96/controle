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
    $this->admin = User::factory()->create();
});

function lunchDay($test, array $times = []) {
    foreach (array_replace(['entry' => '08:00:00', 'break_out' => '17:59:00', 'break_in' => '17:59:30', 'exit' => '18:00:00'], $times) as $type => $time) {
        if ($time === null) continue;
        $mark = new Attendance(['employee_id' => $test->employee->id, 'mark_date' => '2026-10-16', 'mark_type' => $type, 'mark_time' => $time]);
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00:00'; $mark->scheduled_end_time = '17:00:00';
            $mark->scheduled_break_start = '13:00:00'; $mark->scheduled_break_end = '14:00:00';
            $mark->minimum_lunch_minutes = 60; $mark->maximum_lunch_minutes = 180;
            $mark->overtime_minimum_minutes = 15; $mark->reference_lunch_minutes = 60;
        }
        $mark->save();
    }
    return lunchCalculation($test);
}

function lunchCalculation($test) {
    return app(AttendanceDayCalculator::class)->calculate($test->employee->attendances()->get(), 15, 60);
}

function lunchPayload($day, array $changes = []) {
    return array_replace(['lunch_start' => '13:00', 'lunch_end' => '14:00', 'reason' => 'Olvido de marcación, horario confirmado con el responsable.', 'source_fingerprint' => $day['source_fingerprint'], 'current_adjustment_id' => $day['adjustment']?->id ?? 0], $changes);
}

test('three marks at exit and forgotten lunch return are incidents', function ($times) {
    $day = lunchDay($this, $times);
    expect($day['complete'])->toBeFalse()->and($day['eligible'])->toBe(0)->and($day['approved'])->toBe(0)->and($day['can_regularize'])->toBeTrue();
    $this->actingAs($this->admin)->post(route('attendances.overtime-review', $day['exit']), ['decision' => 'approved', 'fingerprint' => str_repeat('0', 64)])->assertSessionHas('error');
})->with([
    [[]],
    [['break_out' => '13:00:00', 'break_in' => '17:59:00']],
    [['break_out' => '17:59:59', 'break_in' => '17:59:59', 'exit' => '17:59:59']],
    [['break_out' => '13:00:00', 'break_in' => null]],
    [['break_out' => null, 'break_in' => null]],
    [['break_out' => '16:00:00', 'break_in' => '17:59:00']],
]);

test('regularization preserves originals photos and records audited history then recalculates only pending extras', function () {
    $day = lunchDay($this);
    $before = $this->employee->attendances()->get()->map->only(['id', 'mark_time', 'mark_type', 'photo'])->all();
    $this->actingAs($this->admin)->post(route('attendances.lunch-regularization', $day['entry']), lunchPayload($day))->assertSessionHas('success');
    $after = $this->employee->attendances()->get()->map->only(['id', 'mark_time', 'mark_type', 'photo'])->all();
    expect($after)->toBe($before);
    $updated = lunchCalculation($this);
    expect($updated['worked'])->toBe(9 * 3600)->and($updated['eligible'])->toBe(3600)->and($updated['approved'])->toBe(0)->and($updated['status'])->toBe('pending');
    expect($updated['adjustment']->recorded_by)->toBe($this->admin->id)->and($updated['adjustment']->reason)->toContain('confirmado');
    $this->get(route('attendances.index'))->assertOk()->assertSee('Historial de regularizaciones')->assertSee('Almuerzo regularizado');
    foreach (['attendances.index', 'reports.attendances'] as $route) {
        $this->get(route($route, ['from' => '2026-10-16', 'to' => '2026-10-16', 'employee_id' => $this->employee->id]))
            ->assertOk()->assertSee('<span class="fw-semibold">13:00</span>', false)
            ->assertSee('<span class="fw-semibold">14:00</span>', false)
            ->assertSee('Regularizado · Original: 17:59')
            ->assertDontSee('<span class="fw-semibold">17:59</span>', false);
    }
    $this->post(route('attendances.overtime-review', $updated['exit']), ['decision' => 'approved', 'fingerprint' => $updated['fingerprint']])->assertSessionHas('success');
    expect(lunchCalculation($this)['approved'])->toBe(3600);
});

test('missing lunch marks can be regularized without manufacturing original punches', function () {
    $day = lunchDay($this, ['break_out' => null, 'break_in' => null]);
    $this->actingAs($this->admin)->post(route('attendances.lunch-regularization', $day['entry']), lunchPayload($day))->assertSessionHas('success');
    $this->assertDatabaseCount('attendances', 2);
    expect(lunchCalculation($this)['complete'])->toBeTrue()->and(lunchCalculation($this)['eligible'])->toBe(3600);
    foreach (['attendances.index', 'reports.attendances'] as $route) {
        $this->get(route($route, ['from' => '2026-10-16', 'to' => '2026-10-16', 'employee_id' => $this->employee->id]))
            ->assertOk()->assertSee('<span class="fw-semibold">13:00</span>', false)
            ->assertSee('<span class="fw-semibold">14:00</span>', false)->assertSee('Sin marcación original');
    }
});

test('regularization requires authentication reason valid times and minimum duration', function ($changes, $error) {
    $day = lunchDay($this);
    $url = route('attendances.lunch-regularization', $day['entry']);
    $this->post($url, lunchPayload($day))->assertRedirect('/login');
    $this->actingAs($this->admin)->post($url, lunchPayload($day, $changes))->assertSessionHasErrors($error);
    $this->assertDatabaseCount('attendance_lunch_adjustments', 0);
})->with([
    [['reason' => ''], 'reason'], [['lunch_start' => '25:00'], 'lunch_start'],
    [['lunch_end' => '12:00'], 'lunch_end'], [['lunch_end' => '13:30'], 'lunch_end'],
    [['lunch_start' => '07:00'], 'lunch_end'], [['lunch_end' => '18:30'], 'lunch_end'],
]);

test('confirmed long lunch is accepted without silently replacing its duration', function () {
    $day = lunchDay($this, ['break_out' => '12:00:00', 'break_in' => '16:00:00']);
    expect($day['complete'])->toBeFalse();
    $this->actingAs($this->admin)->post(route('attendances.lunch-regularization', $day['entry']), lunchPayload($day, ['lunch_start' => '12:00', 'lunch_end' => '16:00', 'reason' => 'Ausencia larga confirmada por responsable de sede.']))->assertSessionHas('success');
    $updated = lunchCalculation($this);
    expect($updated['lunch'])->toBe(4 * 3600)->and($updated['worked'])->toBe(6 * 3600)->and($updated['eligible'])->toBe(0);
});

test('second adjustment invalidates extra approval and preserves previous correction and review', function () {
    $day = lunchDay($this);
    $this->actingAs($this->admin)->post(route('attendances.lunch-regularization', $day['entry']), lunchPayload($day))->assertSessionHas('success');
    $first = lunchCalculation($this);
    $this->post(route('attendances.overtime-review', $first['exit']), ['decision' => 'approved', 'fingerprint' => $first['fingerprint']])->assertSessionHas('success');
    $this->post(route('attendances.lunch-regularization', $first['entry']), lunchPayload($first, ['lunch_end' => '14:15']))->assertSessionHas('success');
    $updated = lunchCalculation($this);
    expect($updated['status'])->toBe('pending')->and($updated['approved'])->toBe(0)->and($updated['eligible'])->toBe(2700);
    expect($updated['adjustments'])->toHaveCount(2);
    expect($updated['adjustment']->previous_overtime_review['overtime_status'])->toBe('approved');
    $this->post(route('attendances.overtime-review', $first['exit']), ['decision' => 'approved', 'fingerprint' => $first['fingerprint']])->assertSessionHas('error');
});

test('stale regularization forms cannot overwrite a newer correction', function () {
    $day = lunchDay($this);
    $url = route('attendances.lunch-regularization', $day['entry']);
    $this->actingAs($this->admin)->post($url, lunchPayload($day))->assertSessionHas('success');
    $this->post($url, lunchPayload($day, ['lunch_end' => '14:30']))->assertSessionHas('error');
    $this->assertDatabaseCount('attendance_lunch_adjustments', 1);
});

test('changed original marks invalidate correction and require a new confirmation', function () {
    $day = lunchDay($this, ['break_in' => null]);
    $this->actingAs($this->admin)->post(route('attendances.lunch-regularization', $day['entry']), lunchPayload($day))->assertSessionHas('success');
    Attendance::create(['employee_id' => $this->employee->id, 'mark_date' => '2026-10-16', 'mark_type' => 'break_in', 'mark_time' => '17:59:30']);
    expect(lunchCalculation($this)['complete'])->toBeFalse()->and(lunchCalculation($this)['reason'])->toContain('cambiaron');
});

test('lunch settings validate consistent boundaries and freeze on ingress', function () {
    $this->actingAs($this->admin);
    $base = ['company_name' => 'Empresa', 'restrict_ip_enabled' => '0', 'camera_enabled' => '0'];
    $this->put(route('settings.update'), $base + ['minimum_lunch_minutes' => '30'])->assertSessionHasErrors('minimum_lunch_minutes');
    $this->put(route('settings.update'), $base + ['minimum_lunch_minutes' => '60', 'maximum_lunch_minutes' => '45', 'reference_lunch_minutes' => '60'])->assertSessionHasErrors('reference_lunch_minutes');
    $this->put(route('settings.update'), $base + ['minimum_lunch_minutes' => '60', 'maximum_lunch_minutes' => '120', 'reference_lunch_minutes' => '60'])->assertSessionHasNoErrors();
    $this->employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 08:00:00', config('app.timezone')));
    $this->postJson('/submit-marker', ['document_number' => $this->employee->document_number, 'type' => 'entry'])->assertOk();
    $entry = $this->employee->attendances()->first();
    expect((int) $entry->minimum_lunch_minutes)->toBe(60)->and((int) $entry->maximum_lunch_minutes)->toBe(120);
});

test('exit can be marked with missing lunch and suspicious originals never store eligible overtime', function () {
    Setting::setValue('restrict_ip_enabled', '0');
    $day = lunchDay($this, ['break_out' => null, 'break_in' => null, 'exit' => null]);
    $this->postJson('/submit-marker', ['document_number' => $this->employee->document_number, 'type' => 'exit'])->assertOk();
    expect(lunchCalculation($this)['complete'])->toBeFalse();
    expect($this->employee->attendances()->where('mark_type', 'exit')->first()->overtime_time)->toBeNull();
    $this->postJson('/submit-marker', ['document_number' => $this->employee->document_number, 'type' => 'break_out'])->assertUnprocessable();
    $this->assertDatabaseCount('attendances', 2);
});
