<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $data = [
            'category_name' => 'settings',
            'page_name' => 'settings.index',
            'page_title' => 'Configuración',
        ];

        // Obtiene las configuraciones del sistema
        $companyName = Setting::getValue('company_name');
        $allowedIps = Setting::getValue('allowed_ips');

        return view('pages.settings.index')
            ->with('data', $data)
            ->with('companyName', $companyName)
            ->with('allowedIps', $allowedIps);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'allowed_ips' => 'nullable|string|max:255',
        ]);

        // Actualiza las configuraciones del sistema
        Setting::setValue('company_name', $request->input('company_name'));
        Setting::setValue('allowed_ips', $request->input('allowed_ips'));

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }

}
