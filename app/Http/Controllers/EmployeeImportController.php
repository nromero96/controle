<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\EmployeeImport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmployeeImportController extends Controller
{
    public function index(Request $request)
    {
        return view('pages.employees.import', [
            'data' => ['page_title' => 'Importar empleados'],
            'preview' => $request->session()->get('employee_import'),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function preview(Request $request, EmployeeImport $import)
    {
        $request->session()->forget('employee_import');
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        try {
            $employees = $import->parse($request->file('file')->getRealPath());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $exception) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el archivo. Use un Excel .xlsx válido y sin contraseña.']);
        }
        $request->session()->put('employee_import', ['token' => bin2hex(random_bytes(24)), 'expires' => now()->addMinutes(30)->timestamp, 'employees' => $employees]);

        return to_route('employees.import');
    }

    public function store(Request $request, EmployeeImport $import)
    {
        $request->validate(['token' => ['required', 'string']]);
        $preview = $request->session()->get('employee_import');
        if (! $preview || $preview['expires'] < now()->timestamp || ! hash_equals($preview['token'], $request->string('token')->toString())) {
            return to_route('employees.import')->withErrors(['file' => 'La vista previa venció o ya fue utilizada. Vuelva a cargar el archivo.']);
        }
        $import->save($preview['employees']);
        $request->session()->forget('employee_import');

        return to_route('employees.index')->with('success', count($preview['employees']).' empleados importados con sus horarios.');
    }

    public function template()
    {
        // This is the application's downloadable template, generated with current branch data.
        $book = new Spreadsheet;
        $employees = $book->getActiveSheet()->setTitle('Empleados');
        $employees->fromArray(EmployeeImport::EMPLOYEE_HEADERS);
        $employees->getStyle('B2:B2001')->getNumberFormat()->setFormatCode('@');
        $schedules = $book->createSheet()->setTitle('Horarios');
        $schedules->fromArray(EmployeeImport::SCHEDULE_HEADERS);
        $schedules->getStyle('A2:F2001')->getNumberFormat()->setFormatCode('@');
        $branches = $book->createSheet()->setTitle('Sedes');
        $branches->fromArray(['sede_id', 'nombre']);
        foreach (Branch::where('is_active', true)->orderBy('name')->get() as $index => $branch) {
            $branches->setCellValue('A'.($index + 2), $branch->id);
            $branches->setCellValueExplicit('B'.($index + 2), $branch->name, DataType::TYPE_STRING);
        }
        $help = $book->createSheet()->setTitle('Instrucciones');
        $help->fromArray(array_map(fn ($line) => [$line], [
            'Complete Empleados: una fila por empleado nuevo. No cambie los encabezados.',
            'numero_documento debe ser texto para conservar ceros iniciales. Tipo: DNI, RUC, Pasaporte o Carnet de Extranjería.',
            'Use sede_id de la hoja Sedes. El correo es opcional; nombres y ambos apellidos son obligatorios.',
            'Complete Horarios: vincule cada fila con numero_documento de Empleados.',
            'dias admite nombres separados por coma: Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo.',
            'Ejemplo: 01234567 | Lunes,Martes,Miércoles,Jueves,Viernes | 08:00 | 17:00 | 12:00 | 13:00.',
            'Agregue otra fila para un horario distinto. No repita el mismo día del empleado.',
            'Use horas HH:mm. Almuerzo opcional: complete ambas horas o deje ambas vacías.',
            'Los turnos deben comenzar y terminar en el mismo día. Sin filas de horario, el empleado queda sin horario asignado.',
            'Solo se crean empleados nuevos activos; no se actualizan empleados existentes ni sus marcaciones.',
            'Máximo 2000 filas por hoja y 5 MB. Use valores, no fórmulas. Revise y confirme antes de guardar.',
        ]));
        foreach ($book->getAllSheets() as $sheet) {
            $sheet->freezePane('A2');
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF3154A5');
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setWidth($sheet === $help ? 110 : 25);
            }
        }
        $help->getStyle('A1:A11')->getAlignment()->setWrapText(true);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'plantilla-empleados.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
