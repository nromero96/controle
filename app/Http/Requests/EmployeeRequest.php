<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public const DAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee') ? Employee::findOrFail($this->route('employee')) : null;
        $documentRule = match ($this->input('document_type')) {
            'DNI' => 'regex:/^[0-9]{8}$/',
            'RUC' => 'regex:/^[0-9]{11}$/',
            default => 'regex:/^[a-zA-Z0-9-]{6,20}$/',
        };
        $rules = [
            'document_type' => ['required', Rule::in(['DNI', 'RUC', 'Pasaporte', 'Carnet de Extranjería'])],
            'document_number' => ['required', 'string', $documentRule, Rule::unique('employees')->ignore($employee?->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name_father' => ['required', 'string', 'max:255'],
            'last_name_mother' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees')->ignore($employee?->id)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(function ($query) use ($employee) {
                $query->where('is_active', true);
                if ($employee?->branch_id) {
                    $query->orWhere('id', $employee->branch_id);
                }
            })],
            'schedule' => ['sometimes', 'array:'.implode(',', self::DAYS)],
        ];
        foreach (self::DAYS as $day) {
            $prefix = "schedule.$day";
            $rules[$prefix] = ['sometimes', 'array:active,day,start_time,end_time,break_start,break_end'];
            $rules["$prefix.day"] = ['required_with:'.$prefix, Rule::in([$day])];
            $rules["$prefix.active"] = ['sometimes', 'boolean'];
            if (!$this->boolean("$prefix.active")) {
                continue;
            }
            $rules["$prefix.start_time"] = ['required', 'date_format:H:i'];
            $rules["$prefix.end_time"] = ['required', 'date_format:H:i', "after:$prefix.start_time"];
            $rules["$prefix.break_start"] = ['nullable', "required_with:$prefix.break_end", 'date_format:H:i', "after:$prefix.start_time", "before:$prefix.end_time"];
            $rules["$prefix.break_end"] = ['nullable', "required_with:$prefix.break_start", 'date_format:H:i', "after:$prefix.break_start", "before:$prefix.end_time"];
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'document_number.regex' => 'Use 8 dígitos para DNI, 11 para RUC o entre 6 y 20 letras, números o guiones para los otros documentos.',
            'branch_id.exists' => 'Seleccione una sede activa. Puede conservar la sede actual aunque esté inactiva.',
            'schedule.*.active.boolean' => 'El estado del día no es válido.',
            'schedule.*.day.in' => 'El día del horario no es válido.',
            'schedule.*.start_time.required' => 'Ingrese la hora de entrada.',
            'schedule.*.end_time.required' => 'Ingrese la hora de salida.',
            'schedule.*.*.date_format' => 'Ingrese una hora válida (HH:mm).',
            'schedule.*.end_time.after' => 'La salida debe ser posterior a la entrada, dentro del mismo día.',
            'schedule.*.break_start.required_with' => 'Complete también el inicio del almuerzo.',
            'schedule.*.break_end.required_with' => 'Complete también el retorno del almuerzo.',
            'schedule.*.break_start.after' => 'La salida al almuerzo debe ser después de la entrada.',
            'schedule.*.break_start.before' => 'La salida al almuerzo debe ser antes de la salida.',
            'schedule.*.break_end.after' => 'El retorno del almuerzo debe ser posterior a su inicio.',
            'schedule.*.break_end.before' => 'El almuerzo debe finalizar antes de la salida.',
        ];
    }

    public function attributes(): array
    {
        return ['document_type' => 'tipo de documento', 'document_number' => 'número de documento', 'first_name' => 'nombre', 'last_name_father' => 'apellido paterno', 'last_name_mother' => 'apellido materno', 'email' => 'correo', 'branch_id' => 'sede'];
    }
}
