<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-16 09:00:00', config('app.timezone')));
    Storage::fake('local');
});

function markerEmployee() {
    $employee = Employee::create(['document_type' => 'DNI', 'document_number' => '12345678', 'first_name' => 'Ana', 'last_name_father' => 'Perez', 'last_name_mother' => 'Lopez']);
    $employee->schedules()->create(['day' => 'Viernes', 'start_time' => '08:00:00', 'end_time' => '17:00:00']);
    return $employee;
}

test('IP restriction protects page lookup and submission and can be disabled', function () {
    Setting::setValue('allowed_ips', '10.0.0.1');
    $this->get('/')->assertForbidden();
    $this->getJson('/get-employee/12345678')->assertForbidden();
    $this->postJson('/submit-marker', [])->assertForbidden();
    Setting::setValue('restrict_ip_enabled', '0');
    $this->get('/')->assertOk();
    $this->getJson('/get-employee/12345678')->assertOk();
    $this->postJson('/submit-marker', [])->assertUnprocessable();
    Setting::setValue('restrict_ip_enabled', '1');
    Setting::setValue('allowed_ips', '127.0.0.1');
    $this->get('/')->assertOk();
});

test('camera requirement rejects missing or invalid evidence', function () {
    Setting::setValue('restrict_ip_enabled', '0');
    Setting::setValue('camera_enabled', '1');
    markerEmployee();
    $payload = ['document_number' => '12345678', 'type' => 'entry'];
    $this->postJson('/submit-marker', $payload)->assertUnprocessable()->assertJsonValidationErrors('photo');
    $this->postJson('/submit-marker', $payload + ['photo' => UploadedFile::fake()->create('fake.jpg', 10, 'text/plain')])->assertUnprocessable();
    $this->assertDatabaseCount('attendances', 0);
});

test('photo evidence is private linked to the mark and duplicate does not store extra files', function () {
    Setting::setValue('restrict_ip_enabled', '0');
    Setting::setValue('camera_enabled', '1');
    markerEmployee();
    $payload = ['document_number' => '12345678', 'type' => 'entry', 'photo' => UploadedFile::fake()->image('capture.jpg', 640, 480)];
    $this->postJson('/submit-marker', $payload)->assertOk();
    $mark = Attendance::firstOrFail();
    Storage::disk('local')->assertExists($mark->photo);
    $this->postJson('/submit-marker', $payload)->assertConflict();
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    $this->get(route('attendances.photo', $mark))->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get(route('attendances.photo', $mark))->assertOk();
    $this->getJson('/get-employee/12345678')->assertJsonMissingPath('attendances.0.photo')->assertJsonMissingPath('data.email');
});

test('camera disabled permits marks without photo and ignores unsolicited files', function () {
    Setting::setValue('restrict_ip_enabled', '0');
    markerEmployee();
    $this->postJson('/submit-marker', ['document_number' => '12345678', 'type' => 'entry'])->assertOk();
    expect(Attendance::first()->photo)->toBeNull();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('settings persist switches and validate IP addresses', function () {
    $this->actingAs(User::factory()->create());
    $this->put('/settings/update', ['company_name' => 'Empresa', 'restrict_ip_enabled' => '1', 'camera_enabled' => '1', 'allowed_ips' => 'incorrecta'])->assertSessionHasErrors('allowed_ips');
    $this->put('/settings/update', ['company_name' => 'Empresa', 'restrict_ip_enabled' => '1', 'camera_enabled' => '1', 'allowed_ips' => '127.0.0.1, ::1'])->assertSessionHasNoErrors();
    expect(Setting::enabled('restrict_ip_enabled'))->toBeTrue();
    expect(Setting::enabled('camera_enabled'))->toBeTrue();
    $this->put('/settings/update', ['company_name' => 'Empresa', 'restrict_ip_enabled' => '0', 'camera_enabled' => '0', 'allowed_ips' => ''])->assertSessionHasNoErrors();
    expect(Setting::enabled('restrict_ip_enabled'))->toBeFalse();
    expect(Setting::enabled('camera_enabled'))->toBeFalse();
});
