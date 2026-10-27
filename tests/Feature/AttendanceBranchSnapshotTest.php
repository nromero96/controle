<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceBranchOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::setValue('restrict_ip_enabled', '0');
    $this->a = Branch::create(['name' => 'Sede Norte']);
    $this->b = Branch::create(['name' => 'Sede Sur']);
    $this->employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez', 'branch_id' => $this->a->id]);
    $this->employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00', 'end_time' => '17:00']);
});

function branchSnapshotSubmit($test, string $type, string $datetime) {
    $test->travelTo(\Carbon\Carbon::parse($datetime, config('app.timezone')));
    return $test->postJson('/submit-marker', ['document_number' => $test->employee->document_number, 'type' => $type]);
}

test('branch identity and name are frozen for the day even after transfer or rename', function () {
    branchSnapshotSubmit($this, 'entry', '2026-10-16 08:00:00')->assertOk();
    $this->employee->update(['branch_id' => $this->b->id]);
    $this->a->update(['name' => 'Norte renombrada']);
    foreach (['break_out' => '12:00:00', 'break_in' => '13:00:00', 'exit' => '18:00:00'] as $type => $time) {
        branchSnapshotSubmit($this, $type, '2026-10-16 '.$time)->assertOk();
    }
    foreach ($this->employee->attendances()->get() as $mark) {
        expect($mark->branch_snapshot_captured)->toBeTrue()->and((int) $mark->recorded_branch_id)->toBe($this->a->id)->and($mark->recorded_branch_name)->toBe('Sede Norte');
    }
    branchSnapshotSubmit($this, 'entry', '2026-10-17 08:00:00')->assertOk();
    $newEntry = $this->employee->attendances()->where('mark_date', '2026-10-17')->first();
    expect((int) $newEntry->recorded_branch_id)->toBe($this->b->id)->and($newEntry->recorded_branch_name)->toBe('Sede Sur');
    $this->actingAs(User::factory()->create());
    $filters = ['from' => '2026-10-16', 'to' => '2026-10-17', 'branch_id' => $this->a->id];
    $this->get(route('attendances.index', $filters))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->marks->count() === 4)->assertSee('Sede Norte');
    $this->get(route('reports.attendances', $filters))->assertOk()->assertViewHas('summaries', fn ($summaries) => $summaries->sum('worked') === 9 * 3600)->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()['branch_label'] === 'Sede Norte');
    $filters['branch_id'] = $this->b->id;
    $this->get(route('attendances.index', $filters))->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->mark_date === '2026-10-17');
});

test('deleting unused branch does not remove its name or historical filter', function () {
    branchSnapshotSubmit($this, 'entry', '2026-10-16 08:00:00')->assertOk();
    $this->employee->update(['branch_id' => $this->b->id]);
    $id = $this->a->id;
    $this->a->delete();
    expect(Attendance::first()->recordedBranchLabel())->toBe('Sede Norte');
    expect(AttendanceBranchOptions::all()->contains('id', $id))->toBeTrue();
    $this->actingAs(User::factory()->create())->get(route('attendances.index', ['branch_id' => $id]))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
});

test('legacy unknown branch is not inferred from the current employee branch', function () {
    Attendance::create(['employee_id' => $this->employee->id, 'mark_type' => 'entry', 'mark_date' => '2026-10-16', 'mark_time' => '08:00']);
    expect(Attendance::first()->recordedBranchLabel())->toBe('Sin sede histórica registrada');
    $this->actingAs(User::factory()->create())->get(route('attendances.index', ['from' => '2026-10-16', 'to' => '2026-10-16', 'branch_id' => $this->a->id]))->assertViewHas('records', fn ($records) => $records->total() === 0);
});

test('recorded absence of assigned branch remains distinct from unknown legacy data', function () {
    $this->employee->update(['branch_id' => null]);
    branchSnapshotSubmit($this, 'entry', '2026-10-16 08:00:00')->assertOk();
    $this->employee->update(['branch_id' => $this->b->id]);
    branchSnapshotSubmit($this, 'break_out', '2026-10-16 12:00:00')->assertOk();
    foreach (Attendance::all() as $mark) {
        expect($mark->branch_snapshot_captured)->toBeTrue()->and($mark->recorded_branch_id)->toBeNull()->and($mark->recordedBranchLabel())->toBe('Sin sede asignada');
    }
});
