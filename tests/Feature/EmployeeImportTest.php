<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->branch = Branch::create(['name' => 'Central']);
});

function importWorkbook(array $employees, array $schedules = [], bool $formula = false): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet()->setTitle('Empleados');
    $sheet->fromArray([EmployeeImport::EMPLOYEE_HEADERS, ...$employees]);
    if ($formula) {
        $sheet->setCellValue('C2', '=1+1');
    }
    $book->createSheet()->setTitle('Horarios')->fromArray([EmployeeImport::SCHEDULE_HEADERS, ...$schedules]);
    $path = tempnam(sys_get_temp_dir(), 'employee-import-');
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();
    $content = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('empleados.xlsx', $content);
}

function importEmployeeRow($branch, string $document = '01234567'): array
{
    return ['DNI', $document, 'María', 'Pérez', 'López', '', $branch->id];
}

test('excel import previews then persists employees and different weekday schedules only on confirmation', function () {
    $file = importWorkbook([importEmployeeRow($this->branch)], [
        ['01234567', 'Lunes,Martes,Miércoles,Jueves,Viernes', '08:00', '17:00', '12:00', '13:00'],
        ['01234567', 'Sábado', 0.375, 0.5, '', ''],
    ]);
    $this->post(route('employees.import.preview'), ['file' => $file])->assertRedirect(route('employees.import'))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('employees', 0);
    $this->get(route('employees.import'))->assertOk()->assertSee('María')->assertSee('Confirmar importación');
    $token = session('employee_import.token');
    $this->post(route('employees.import.store'), ['token' => $token])->assertRedirect(route('employees.index'))->assertSessionHasNoErrors();
    $employee = Employee::firstOrFail();
    expect($employee->document_number)->toBe('01234567');
    expect($employee->schedules()->count())->toBe(6);
    expect(substr($employee->schedules()->where('day', 'Sábado')->first()->start_time, 0, 5))->toBe('09:00');
    expect($employee->schedules()->where('day', 'Sábado')->first()->break_start)->toBeNull();
    $this->post(route('employees.import.store'), ['token' => $token])->assertSessionHasErrors();
    $this->assertDatabaseCount('employees', 1);
});

test('invalid rows reject entire workbook with row numbers and clear a previous preview', function () {
    $this->withSession(['employee_import' => ['token' => 'old']]);
    $file = importWorkbook([importEmployeeRow($this->branch), importEmployeeRow($this->branch, '123')]);
    $response = $this->post(route('employees.import.preview'), ['file' => $file])->assertSessionHasErrors('file')->assertSessionMissing('employee_import');
    expect(session('errors')->first('file'))->toContain('fila 3');
    $this->assertDatabaseCount('employees', 0);
});

test('duplicate documents days unknown employees and invalid breaks are rejected', function ($rows, $schedules) {
    $employees = array_map(fn ($document) => importEmployeeRow($this->branch, $document), $rows);
    $this->post(route('employees.import.preview'), ['file' => importWorkbook($employees, $schedules)])
        ->assertSessionHasErrors('file');
    $this->assertDatabaseCount('employees', 0);
})->with([
    [['01234567', '01234567'], []],
    [['01234567'], [['01234567', 'Lunes,Lunes', '08:00', '17:00']]],
    [['01234567'], [['99999999', 'Lunes', '08:00', '17:00']]],
    [['01234567'], [['01234567', 'Lunes', '08:00', '17:00', '12:00', '']]],
    [['01234567'], [['01234567', 'Lunes', '18:00', '17:00']]],
    [['01234567'], [['01234567', 'Feriado', '08:00', '17:00']]],
]);

test('existing employees inactive branches and formulas are rejected', function () {
    $row = importEmployeeRow($this->branch);
    Employee::create(['document_type' => 'DNI', 'document_number' => '01234567', 'first_name' => 'Original', 'last_name_father' => 'Pérez', 'last_name_mother' => 'López', 'branch_id' => $this->branch->id]);
    $this->post(route('employees.import.preview'), ['file' => importWorkbook([$row])])->assertSessionHasErrors('file');
    $this->branch->update(['is_active' => false]);
    $this->post(route('employees.import.preview'), ['file' => importWorkbook([importEmployeeRow($this->branch, '11111111')])])->assertSessionHasErrors('file');
    $this->post(route('employees.import.preview'), ['file' => importWorkbook([$row], [], true)])->assertSessionHasErrors('file');
    expect(Employee::first()->first_name)->toBe('Original');
});

test('confirmation revalidates all data and rolls back if a branch is no longer active', function () {
    $otherBranch = Branch::create(['name' => 'Otra sede']);
    $this->post(route('employees.import.preview'), ['file' => importWorkbook([importEmployeeRow($otherBranch, '22222222'), importEmployeeRow($this->branch)])])->assertSessionHasNoErrors();
    $this->branch->update(['is_active' => false]);
    $this->post(route('employees.import.store'), ['token' => session('employee_import.token')])->assertSessionHasErrors();
    $this->assertDatabaseCount('employees', 0);
});

test('duplicate emails and malformed workbooks do not create records', function () {
    $first = importEmployeeRow($this->branch);
    $second = importEmployeeRow($this->branch, '22222222');
    $first[5] = $second[5] = 'ana@example.com';
    $this->post(route('employees.import.preview'), ['file' => importWorkbook([$first, $second])])->assertSessionHasErrors('file');
    $this->post(route('employees.import.preview'), ['file' => UploadedFile::fake()->createWithContent('invalid.xlsx', 'invalid')])->assertSessionHasErrors('file');
    $this->assertDatabaseCount('employees', 0);
});

test('template contains import headers instructions and active branch ids', function () {
    $response = $this->get(route('employees.import.template'))->assertOk()->assertDownload('plantilla-empleados.xlsx');
    $path = tempnam(sys_get_temp_dir(), 'template-');
    file_put_contents($path, $response->streamedContent());
    try {
        $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($path);
        expect($book->getSheetNames())->toBe(['Empleados', 'Horarios', 'Sedes', 'Instrucciones']);
        expect($book->getSheetByName('Sedes')->getCell('B2')->getValue())->toBe('Central');
        expect($book->getSheetByName('Empleados')->getCell('B2')->getStyle()->getNumberFormat()->getFormatCode())->toBe('@');
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

test('import routes require authentication and expired previews cannot persist', function () {
    $this->withSession(['employee_import' => ['token' => 'expired', 'expires' => now()->subMinute()->timestamp, 'employees' => []]])
        ->post(route('employees.import.store'), ['token' => 'expired'])->assertSessionHasErrors();
    auth()->logout();
    $this->get(route('employees.import'))->assertRedirect(route('login'));
    $this->get(route('employees.import.template'))->assertRedirect(route('login'));
    $this->post(route('employees.import.preview'))->assertRedirect(route('login'));
    $this->post(route('employees.import.store'))->assertRedirect(route('login'));
});
