<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceDashboard;
use App\Services\AttendanceDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 18:30:00', config('app.timezone')));
});

function dashboardEmployee($number, bool $scheduled = true, string $status = 'activo', $branchId = null) {
    $employee = Employee::create(['document_type' => 'DNI', 'document_number' => $number, 'first_name' => 'Empleado '.$number, 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez', 'status' => $status, 'branch_id' => $branchId]);
    if ($scheduled) $employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
    return $employee;
}

function dashboardMarks($employee, array $times, string $date = '2026-10-16', $branch = null) {
    foreach ($times as $type => $time) {
        $mark = new Attendance(['employee_id' => $employee->id, 'mark_date' => $date, 'mark_type' => $type, 'mark_time' => $time]);
        $mark->branch_snapshot_captured = true; $mark->recorded_branch_id = $branch?->id; $mark->recorded_branch_name = $branch?->name;
        if ($type === 'entry') {
            $mark->scheduled_start_time = '08:00'; $mark->scheduled_end_time = '17:00';
            $mark->scheduled_break_start = '12:00'; $mark->scheduled_break_end = '13:00';
        }
        $mark->save();
    }
}

test('home is protected and renders useful empty states', function () {
    $this->get(route('home'))->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertSee('Hoy')->assertSee('Necesitan atención')->assertSee('Accesos rápidos')
        ->assertSee('Todavía no hay marcaciones hoy.')->assertSee('No hay jornadas cerradas pendientes de revisión.');
});

test('dashboard counts employees once and distinguishes today from previous pending days', function () {
    $lunch = dashboardEmployee('11111111'); $waiting = dashboardEmployee('22222222');
    $finished = dashboardEmployee('33333333'); $unscheduled = dashboardEmployee('44444444', false);
    dashboardEmployee('55555555', true, 'inactivo');
    dashboardMarks($lunch, ['entry' => '08:00', 'break_out' => '12:00']);
    dashboardMarks($finished, ['entry' => '08:00', 'break_out' => '12:00', 'break_in' => '13:00', 'exit' => '18:00']);
    dashboardMarks($unscheduled, ['entry' => '09:00']);
    dashboardMarks($lunch, ['entry' => '08:00', 'exit' => '18:00'], '2026-10-15');
    $data = app(AttendanceDashboard::class)->build();
    expect($data['stats'])->toBe(['scheduled' => 3, 'without_entry' => 1, 'entered' => 3, 'at_lunch' => 1, 'finished' => 1]);
    expect($data['pending']['review_days'])->toBe(1)->and($data['pending']['overtime_days'])->toBe(1)->and($data['pending']['overtime_seconds'])->toBe(3600);
    expect($data['latestMarks'])->toHaveCount(7);
});

test('approved overtime is no longer pending on dashboard', function () {
    $employee = dashboardEmployee('11111111');
    dashboardMarks($employee, ['entry' => '08:00', 'break_out' => '12:00', 'break_in' => '13:00', 'exit' => '18:00']);
    $result = app(AttendanceDayCalculator::class)->calculate($employee->attendances()->get(), 0, 60);
    $exit = $result['exit']; $exit->overtime_status = 'approved'; $exit->approved_overtime_seconds = 3600; $exit->overtime_fingerprint = $result['fingerprint']; $exit->save();
    $data = app(AttendanceDashboard::class)->build();
    expect($data['pending']['overtime_days'])->toBe(0)->and($data['pendingItems'])->toBeEmpty();
});

test('branch filter respects historical marks and current employee programming', function () {
    $a = Branch::create(['name' => 'Norte']); $b = Branch::create(['name' => 'Sur']);
    $employee = dashboardEmployee('11111111', true, 'activo', $a->id);
    dashboardMarks($employee, ['entry' => '08:00', 'exit' => '18:00'], '2026-10-16', $a);
    $employee->update(['branch_id' => $b->id]);
    $aData = app(AttendanceDashboard::class)->build($a->id);
    expect($aData['stats']['entered'])->toBe(1)->and($aData['stats']['scheduled'])->toBe(0)->and($aData['pending']['review_days'])->toBe(1);
    $bData = app(AttendanceDashboard::class)->build($b->id);
    expect($bData['stats']['entered'])->toBe(0)->and($bData['stats']['scheduled'])->toBe(1)->and($bData['stats']['without_entry'])->toBe(0);
    $this->actingAs(User::factory()->create())->get(route('home', ['branch_id' => $a->id]))->assertOk()->assertSee('Norte')->assertViewHas('branchId', $a->id);
    $this->get(route('home', ['branch_id' => 'incorrecta']))->assertSessionHasErrors('branch_id');
});

test('dashboard caps visible pending list without truncating totals', function () {
    $employee = dashboardEmployee('11111111');
    for ($day = 1; $day <= 11; $day++) dashboardMarks($employee, ['entry' => '08:00', 'exit' => '18:00'], sprintf('2026-10-%02d', $day));
    $data = app(AttendanceDashboard::class)->build();
    expect($data['pending']['review_days'])->toBe(11)->and($data['pendingItems'])->toHaveCount(8)->and($data['pendingItems']->first()['date'])->toBe('2026-10-11');
});
