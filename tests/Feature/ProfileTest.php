<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('profile requires authentication on every endpoint', function () {
    $this->get(route('profile.edit'))->assertRedirect('/login');
    $this->put(route('profile.update'), [])->assertRedirect('/login');
    $this->put(route('profile.password'), [])->assertRedirect('/login');
});

test('administrator can update own name without modifying another account', function () {
    $user = User::factory()->create(); $other = User::factory()->create();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Datos personales')->assertSee('Cambiar contraseña')->assertSee('Mi perfil');
    $this->put(route('profile.update'), ['name' => 'Nombre actualizado', 'email' => $user->email, 'id' => $other->id, 'password' => 'ignored-password'])->assertRedirect(route('profile.edit'))->assertSessionHas('profile_success');
    expect($user->fresh()->name)->toBe('Nombre actualizado')->and($other->fresh()->name)->toBe($other->name);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('profile validates name email uniqueness and password when changing email', function () {
    $user = User::factory()->create(); $other = User::factory()->create();
    $this->actingAs($user)->put(route('profile.update'), ['name' => '', 'email' => 'invalido'])->assertSessionHasErrorsIn('profile', ['name', 'email']);
    $this->put(route('profile.update'), ['name' => 'Admin', 'email' => $other->email, 'current_password' => 'password'])->assertSessionHasErrorsIn('profile', ['email']);
    $this->put(route('profile.update'), ['name' => 'Admin', 'email' => 'nuevo@example.com', 'current_password' => 'incorrecta'])->assertSessionHasErrorsIn('profile', ['current_password'])->assertSessionMissing('_old_input.current_password');
    $this->put(route('profile.update'), ['name' => 'Admin', 'email' => 'nuevo@example.com', 'current_password' => 'password'])->assertSessionHas('profile_success');
    expect($user->fresh()->email)->toBe('nuevo@example.com')->and($user->fresh()->email_verified_at)->toBeNull();
});

test('password change requires current password confirmation and minimum length', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'mal', 'password' => '123', 'password_confirmation' => 'otro'])->assertSessionHasErrorsIn('password', ['current_password', 'password']);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrorsIn('password', ['password']);
});

test('password is hashed rotated and affects only the signed in account', function () {
    $user = User::factory()->create(); $other = User::factory()->create(); $oldToken = $user->remember_token;
    $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026', 'id' => $other->id])->assertRedirect(route('profile.edit'))->assertSessionHas('password_success');
    expect(Hash::check('NuevaClave2026', $user->fresh()->password))->toBeTrue()->and($user->fresh()->remember_token)->not->toBe($oldToken);
    expect(Hash::check('password', $other->fresh()->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $this->get(route('profile.edit'))->assertOk()->assertDontSee('NuevaClave2026');
});
