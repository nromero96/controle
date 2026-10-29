<?php

namespace App\Services;

use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class EmployeeImport
{
    public const EMPLOYEE_HEADERS = ['tipo_documento', 'numero_documento', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'sede_id'];

    public const SCHEDULE_HEADERS = ['numero_documento', 'dias', 'entrada', 'salida', 'salida_almuerzo', 'retorno_almuerzo'];

    public function parse(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'El archivo no es un Excel .xlsx válido.']);
        }
        try {
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
            }
            if ($size > 30 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'El contenido del Excel es demasiado grande. Divida la importación en archivos más pequeños.']);
            }
        } finally {
            $zip->close();
        }
        $reader = new Xlsx;
        $info = $reader->listWorksheetInfo($path);
        foreach ($info as $sheet) {
            if ($sheet['totalRows'] > 2001 || $sheet['totalColumns'] > 20) {
                throw ValidationException::withMessages(['file' => 'Cada hoja admite hasta 2000 filas y 20 columnas.']);
            }
        }
        $reader->setLoadSheetsOnly(['Empleados', 'Horarios']);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        try {
            $employees = $this->rows($book->getSheetByName('Empleados'), self::EMPLOYEE_HEADERS, 'Empleados');
            $schedules = $this->rows($book->getSheetByName('Horarios'), self::SCHEDULE_HEADERS, 'Horarios');
            $errors = [];
            $payloads = [];
            $emails = [];
            foreach ($employees as $row => $values) {
                [$type, $document, $name, $father, $mother, $email, $branch] = $values;
                if (isset($payloads[$document])) {
                    $errors[] = "Empleados, fila $row: documento repetido ($document).";

                    continue;
                }
                if ($email !== '' && isset($emails[mb_strtolower($email)])) {
                    $errors[] = "Empleados, fila $row: correo repetido ($email).";
                }
                $emails[mb_strtolower($email)] = true;
                $payloads[$document] = ['row' => $row, 'data' => ['document_type' => $type, 'document_number' => $document, 'first_name' => $name, 'last_name_father' => $father, 'last_name_mother' => $mother, 'email' => $email ?: null, 'branch_id' => $branch, 'schedule' => []]];
            }
            foreach ($schedules as $row => $values) {
                [$document, $days, $start, $end, $breakStart, $breakEnd] = $values;
                if (! isset($payloads[$document])) {
                    $errors[] = "Horarios, fila $row: el documento $document no figura en la hoja Empleados.";

                    continue;
                }
                foreach (preg_split('/[,;]/u', $days) as $day) {
                    $day = trim($day);
                    if (! in_array($day, EmployeeRequest::DAYS, true)) {
                        $errors[] = "Horarios, fila $row: día inválido ($day). Use los nombres de la plantilla.";

                        continue;
                    }
                    if (isset($payloads[$document]['data']['schedule'][$day])) {
                        $errors[] = "Horarios, fila $row: $day está repetido para $document.";

                        continue;
                    }
                    $payloads[$document]['data']['schedule'][$day] = ['day' => $day, 'active' => '1', 'start_time' => $this->time($start), 'end_time' => $this->time($end), 'break_start' => $this->time($breakStart), 'break_end' => $this->time($breakEnd)];
                }
            }
            foreach ($payloads as $record) {
                $validator = $this->validator($record['data']);
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = "Empleados, fila {$record['row']} ({$record['data']['document_number']}): $error";
                }
            }
            if (! $payloads) {
                $errors[] = 'La hoja Empleados debe contener al menos un empleado.';
            }
            if ($errors) {
                throw ValidationException::withMessages(['file' => array_slice($errors, 0, 100)]);
            }

            return array_values(array_column($payloads, 'data'));
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function save(array $employees): void
    {
        DB::transaction(function () use ($employees) {
            // Revalidate at confirmation: the database may have changed since preview.
            foreach ($employees as $data) {
                $this->validator($data)->validate();
                $employee = Employee::create(collect($data)->except('schedule')->all());
                foreach ($data['schedule'] as $schedule) {
                    $employee->schedules()->create(collect($schedule)->except('active')->all());
                }
            }
        });
    }

    private function validator(array $data): \Illuminate\Validation\Validator
    {
        $request = EmployeeRequest::create('/employees', 'POST', $data);

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
    }

    private function time(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $minutes = (int) round((float) $value * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return preg_match('/^\d{1,2}:\d{2}(:00)?$/', $value) ? sprintf('%02d:%02d', ...array_map('intval', array_slice(explode(':', $value), 0, 2))) : $value;
    }

    private function rows(?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers, string $name): array
    {
        if (! $sheet) {
            throw ValidationException::withMessages(['file' => "Falta la hoja $name. Descargue y use la plantilla."]);
        }
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $values = [];
            foreach (range(1, count($headers)) as $column) {
                $cell = $sheet->getCell([$column, $row->getRowIndex()]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw ValidationException::withMessages(['file' => "$name, fila {$row->getRowIndex()}: use valores, no fórmulas."]);
                }
                $values[] = trim((string) $cell->getValue());
            }
            if ($row->getRowIndex() === 1) {
                if ($values !== $headers) {
                    throw ValidationException::withMessages(['file' => "Los encabezados de $name no coinciden con la plantilla."]);
                }
            } elseif (count(array_filter($values, fn ($value) => $value !== ''))) {
                $rows[$row->getRowIndex()] = $values;
            }
        }

        return $rows;
    }
}
