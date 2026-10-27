<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->branch = Branch::create(['name' => 'Central']);
});

function employeePayload($branch): array {
    return ['document_type' => 'DNI', 'document_number' => '01234567', 'first_name' => 'María José', 'last_name_father' => 'Pérez', 'last_name_mother' => 'López', 'email' => null, 'branch_id' => $branch->id];
}

function mondaySchedule(array $changes = []): array {
    return ['Lunes' => array_replace(['day' => 'Lunes', 'active' => '1', 'start_time' => '08:00', 'end_time' => '17:00', 'break_start' => null, 'break_end' => null], $changes)];
}

test('employee validation rejects invalid personal fields for create and edit', function () {
    $employee = Employee::create(employeePayload($this->branch));
    $invalid = ['document_type' => 'otro', 'document_number' => '?', 'first_name' => '', 'last_name_father' => '', 'last_name_mother' => '', 'email' => 'incorrecto', 'branch_id' => 99999];
    $errors = array_keys($invalid);
    $this->post(route('employees.store'), $invalid)->assertSessionHasErrors($errors);
    $this->put(route('employees.update', $employee), $invalid)->assertSessionHasErrors($errors);
    expect($employee->fresh()->first_name)->toBe('María José');
});

test('documents follow type and unique constraints while edit permits own values', function () {
    $payload = employeePayload($this->branch);
    $payload['email'] = 'ana@example.com';
    $employee = Employee::create($payload);
    $this->post(route('employees.store'), $payload)->assertSessionHasErrors(['document_number', 'email']);
    $this->put(route('employees.update', $employee), $payload)->assertSessionHasNoErrors();
    foreach ([['DNI', '1234567'], ['RUC', '12345678'], ['Pasaporte', '!123456']] as [$type, $number]) {
        $this->post(route('employees.store'), array_replace($payload, ['document_type' => $type, 'document_number' => $number, 'email' => null]))->assertSessionHasErrors('document_number');
    }
});

test('invalid schedules do not create or partially update employees', function ($changes, $field) {
    $payload = employeePayload($this->branch);
    $invalid = mondaySchedule($changes);
    $this->post(route('employees.store'), $payload + ['schedule' => $invalid])->assertSessionHasErrors('schedule.Lunes.'.$field);
    $this->assertDatabaseCount('employees', 0);
    $employee = Employee::create($payload);
    $employee->schedules()->create(['day' => 'Lunes', 'start_time' => '08:00', 'end_time' => '17:00']);
    $payload['first_name'] = 'Cambio';
    $this->put(route('employees.update', $employee), $payload + ['schedule' => $invalid])->assertSessionHasErrors('schedule.Lunes.'.$field);
    expect($employee->fresh()->first_name)->toBe('María José');
    expect(substr($employee->schedules()->first()->end_time, 0, 5))->toBe('17:00');
})->with([
    [['start_time' => ''], 'start_time'],
    [['end_time' => '07:00'], 'end_time'],
    [['start_time' => '25:00'], 'start_time'],
    [['break_start' => '12:00'], 'break_end'],
    [['break_end' => '13:00'], 'break_start'],
    [['break_start' => '07:00', 'break_end' => '13:00'], 'break_start'],
    [['break_start' => '12:00', 'break_end' => '18:00'], 'break_end'],
    [['break_start' => '13:00', 'break_end' => '12:00'], 'break_end'],
    [['day' => 'Martes'], 'day'],
    [['active' => 'otro'], 'active'],
]);

test('valid optional breaks persist and disabled days remove schedules', function () {
    $payload = employeePayload($this->branch);
    $this->post(route('employees.store'), $payload + ['schedule' => mondaySchedule()])->assertSessionHasNoErrors();
    $employee = Employee::firstOrFail();
    expect($employee->document_number)->toBe('01234567');
    expect($employee->schedules()->first()->break_start)->toBeNull();
    $this->put(route('employees.update', $employee), $payload + ['schedule' => mondaySchedule(['break_start' => '12:00', 'break_end' => '13:00'])])->assertSessionHasNoErrors();
    expect(substr($employee->schedules()->first()->break_end, 0, 5))->toBe('13:00');
    $this->put(route('employees.update', $employee), $payload + ['schedule' => ['Lunes' => ['day' => 'Lunes', 'active' => '0']]])->assertSessionHasNoErrors();
    expect($employee->schedules()->count())->toBe(0);
});

test('unknown schedule keys are rejected', function () {
    $this->post(route('employees.store'), employeePayload($this->branch) + ['schedule' => ['Falso' => ['day' => 'Lunes']]])->assertSessionHasErrors('schedule');
});

test('transaction rolls back employee when saving schedule fails', function () {
    Schedule::creating(fn () => throw new RuntimeException('Simulated failure'));
    try {
        $this->post(route('employees.store'), employeePayload($this->branch) + ['schedule' => mondaySchedule()])->assertStatus(500);
        $this->assertDatabaseCount('employees', 0);
    } finally {
        Schedule::flushEventListeners();
    }
});

test('edit form restores submitted values and inactive days after validation failure', function () {
    $employee = Employee::create(employeePayload($this->branch));
    $employee->schedules()->create(['day' => 'Lunes', 'start_time' => '08:00', 'end_time' => '17:00']);
    $this->withSession(['_old_input' => ['first_name' => 'Valor enviado', 'document_type' => 'Pasaporte', 'schedule' => ['Lunes' => ['day' => 'Lunes', 'active' => '0']]]])
        ->get(route('employees.edit', $employee))->assertOk()->assertSee('Valor enviado')->assertSee('row horario-fields d-none', false);
});
