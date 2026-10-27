<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function assignedBranchEmployee(Branch $branch) {
    return Employee::create(['document_type' => 'DNI', 'document_number' => '99999999', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez', 'branch_id' => $branch->id]);
}

test('assigned branches cannot be deleted but can be deactivated and reactivated', function () {
    $branch = Branch::create(['name' => 'Central', 'address' => 'Calle 1']);
    $employee = assignedBranchEmployee($branch);
    $this->delete(route('branches.destroy', $branch))->assertRedirect(route('branches.index'))->assertSessionHas('error');
    $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    $this->put(route('branches.update', $branch), ['name' => 'Central', 'address' => 'Calle 1', 'is_active' => '0'])->assertSessionHasNoErrors();
    expect($branch->fresh()->is_active)->toBeFalse();
    expect($employee->fresh()->branch_id)->toBe($branch->id);
    $this->delete(route('branches.destroy', $branch))->assertSessionHas('error');
    $this->put(route('branches.update', $branch), ['name' => 'Central', 'address' => 'Calle 1', 'is_active' => '1'])->assertSessionHasNoErrors();
    expect($branch->fresh()->is_active)->toBeTrue();
    $this->get(route('branches.index'))->assertOk()->assertSee('Central');
});

test('branches without employees can be deleted', function () {
    foreach ([true, false] as $active) {
        $branch = Branch::create(['name' => 'Vacia', 'is_active' => $active]);
        $this->delete(route('branches.destroy', $branch))->assertSessionHas('success');
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }
});

test('database also protects assigned branches from deletion', function () {
    $branch = Branch::create(['name' => 'Protegida']);
    assignedBranchEmployee($branch);
    expect(fn () => $branch->delete())->toThrow(\Illuminate\Database\QueryException::class);
});

test('branch fields are validated on creation and update', function () {
    $branch = Branch::create(['name' => 'Existente']);
    $invalid = ['name' => 'Existente', 'address' => '', 'phone' => 'abc', 'email' => 'incorrecto', 'is_active' => 'otro'];
    $this->post(route('branches.store'), $invalid)->assertSessionHasErrors(['name', 'address', 'phone', 'email', 'is_active']);
    $other = Branch::create(['name' => 'Otra']);
    $this->put(route('branches.update', $other), $invalid)->assertSessionHasErrors(['name', 'address', 'phone', 'email', 'is_active']);
    $valid = ['name' => 'Nueva', 'address' => 'Calle 123', 'phone' => '+51 (01) 234-5678', 'email' => 'sede@example.com', 'is_active' => '1'];
    $this->post(route('branches.store'), $valid)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('branches', ['name' => 'Nueva', 'email' => 'sede@example.com']);
    $this->put(route('branches.update', $branch), ['name' => 'Existente', 'address' => 'Calle 2', 'phone' => '', 'email' => '', 'is_active' => '1'])->assertSessionHasNoErrors();
    expect($branch->fresh()->email)->toBeNull();
});

test('inactive branches are excluded from new assignments but existing assignments can be kept', function () {
    $branch = Branch::create(['name' => 'Inactiva', 'is_active' => false]);
    $employee = assignedBranchEmployee($branch);
    $this->get(route('employees.create'))->assertViewHas('branches', fn ($branches) => !$branches->contains('id', $branch->id));
    $this->get(route('employees.edit', $employee))->assertViewHas('branches', fn ($branches) => $branches->contains('id', $branch->id));
    $payload = $employee->only(['document_type', 'document_number', 'first_name', 'last_name_father', 'last_name_mother', 'branch_id']);
    $this->put(route('employees.update', $employee), $payload)->assertSessionHasNoErrors();
    $payload['document_number'] = '88888888';
    $this->post(route('employees.store'), $payload)->assertSessionHasErrors('branch_id');
});
