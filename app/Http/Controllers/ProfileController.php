<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('pages.profile.edit', ['data' => ['page_title' => 'Mi perfil'], 'user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];
        if ($request->input('email') !== $user->email) {
            $rules['current_password'] = ['required', 'current_password:web'];
        }
        $validated = $request->validateWithBag('profile', $rules, [
            'name.required' => 'Ingrese su nombre.', 'name.max' => 'El nombre no debe superar 255 caracteres.',
            'email.required' => 'Ingrese su correo.', 'email.email' => 'Ingrese un correo válido.', 'email.unique' => 'Este correo ya pertenece a otra cuenta.',
            'current_password.required' => 'Confirme su contraseña actual para cambiar el correo.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ]);
        $user->name = $validated['name'];
        if ($user->email !== $validated['email']) {
            $user->email = $validated['email'];
            $user->email_verified_at = null;
        }
        $user->save();
        return to_route('profile.edit')->with('profile_success', 'Sus datos se actualizaron correctamente.');
    }

    public function password(Request $request)
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Ingrese su contraseña actual.', 'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingrese la nueva contraseña.', 'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La nueva contraseña no debe superar 255 caracteres.', 'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ]);
        $request->user()->forceFill(['password' => Hash::make($validated['password']), 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();
        return to_route('profile.edit')->with('password_success', 'Su contraseña se actualizó correctamente.');
    }
}
