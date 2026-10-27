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
            ->with('allowedIps', $allowedIps)
            ->with('restrictIpEnabled', Setting::enabled('restrict_ip_enabled', true))
            ->with('cameraEnabled', Setting::enabled('camera_enabled'))
            ->with('overtimeMinimum', Setting::getValue('overtime_minimum_minutes', 0))
            ->with('referenceLunch', Setting::getValue('reference_lunch_minutes', 60))
            ->with('minimumLunch', Setting::getValue('minimum_lunch_minutes', 45))
            ->with('maximumLunch', Setting::getValue('maximum_lunch_minutes', 180))
            ->with('lunchTolerance', Setting::getValue('lunch_tolerance_minutes', 5))
            ->with('exitTolerance', Setting::getValue('exit_tolerance_minutes', 5));
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'overtime_minimum_minutes' => 'sometimes|required|integer|min:0|max:1440',
            'reference_lunch_minutes' => 'sometimes|required|integer|min:45|max:720',
            'minimum_lunch_minutes' => 'sometimes|required|integer|min:45|max:720',
            'lunch_tolerance_minutes' => 'sometimes|required|integer|min:0|max:30',
            'exit_tolerance_minutes' => 'sometimes|required|integer|min:0|max:60',
            'maximum_lunch_minutes' => 'sometimes|required|integer|min:45|max:720',
            'restrict_ip_enabled' => 'required|boolean',
            'camera_enabled' => 'required|boolean',
            'allowed_ips' => ['nullable', 'required_if:restrict_ip_enabled,1', 'string', 'max:255', function ($attribute, $value, $fail) {
                foreach (explode(',', $value) as $ip) {
                    if (!filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                        $fail('Ingrese direcciones IP válidas separadas por comas.');
                        break;
                    }
                }
            }],
        ]);

        $minimumLunch = $request->integer('minimum_lunch_minutes', (int) Setting::getValue('minimum_lunch_minutes', 45));
        $maximumLunch = $request->integer('maximum_lunch_minutes', (int) Setting::getValue('maximum_lunch_minutes', 180));
        $referenceLunch = $request->integer('reference_lunch_minutes', (int) Setting::getValue('reference_lunch_minutes', 60));
        if ($maximumLunch < $minimumLunch || $referenceLunch < $minimumLunch || $referenceLunch > $maximumLunch) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reference_lunch_minutes' => 'El almuerzo de referencia debe estar entre el mínimo y el límite de alerta.']);
        }

        foreach (['overtime_minimum_minutes', 'reference_lunch_minutes', 'minimum_lunch_minutes', 'maximum_lunch_minutes', 'lunch_tolerance_minutes', 'exit_tolerance_minutes'] as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, (string) $request->integer($key));
            }
        }

        Setting::setValue('restrict_ip_enabled', $request->boolean('restrict_ip_enabled') ? '1' : '0');
        Setting::setValue('camera_enabled', $request->boolean('camera_enabled') ? '1' : '0');

        // Actualiza las configuraciones del sistema
        Setting::setValue('company_name', $request->input('company_name'));
        Setting::setValue('allowed_ips', $request->input('allowed_ips'));

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }

}
