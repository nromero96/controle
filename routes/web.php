<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\SettingController;


Route::get('/', function () {
    return view('welcome', ['cameraEnabled' => \App\Models\Setting::enabled('camera_enabled')]);
})->middleware('restrict.ip');

Route::get('/get-employee/{document_number}', [EmployeeController::class, 'getEmployee'])->middleware('restrict.ip')->name('get-employee');
Route::post('/submit-marker', [AttendanceController::class, 'submitMarker'])->middleware('restrict.ip')->name('submit-marker');

Auth::routes(['register' => false]);

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

//Solo para authenticated users
Route::group(['middleware' => ['auth']], function () {

    //Ejecutar migración
    Route::get('/ejecutar-migraciones', function () {
        Artisan::call('migrate');
        return 'Migraciones ejecutadas con éxito.';
    });

    //Optimize
    Route::get('/optimize', function () {
        Artisan::call('optimize');
        return 'Optimizado.';
    });

    //Cache todo
    Route::get('/cache-todo', function () {
        Artisan::call('optimize');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        // Artisan::call('livewire:discover');
        return 'Cache todo.';
    });

    //storage link
    Route::get('/storage-link', function () {
        Artisan::call('storage:link');
        return 'Storage link creado correctamente en cpanel.';
    });




    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    
    Route::get('/employees/import', [\App\Http\Controllers\EmployeeImportController::class, 'index'])->name('employees.import');
    Route::get('/employees/import/template', [\App\Http\Controllers\EmployeeImportController::class, 'template'])->name('employees.import.template');
    Route::post('/employees/import/preview', [\App\Http\Controllers\EmployeeImportController::class, 'preview'])->name('employees.import.preview');
    Route::post('/employees/import', [\App\Http\Controllers\EmployeeImportController::class, 'store'])->block(10, 10)->name('employees.import.store');
    Route::resource('/employees', EmployeeController::class)->names('employees');
    Route::resource('/schedules', ScheduleController::class)->names('schedules');
    Route::get('/attendances/{attendance}/photo', [AttendanceController::class, 'photo'])->name('attendances.photo');

    Route::post('/attendances/{attendance}/lunch-regularization', [AttendanceController::class, 'regularizeLunch'])->name('attendances.lunch-regularization');
    Route::post('/attendances/{attendance}/time-correction', [\App\Http\Controllers\AttendanceTimeCorrectionController::class, 'store'])->name('attendances.time-correction');

    Route::post('/attendances/{attendance}/overtime-review', [AttendanceController::class, 'review'])->name('attendances.overtime-review');

    Route::get('/reports/attendances', [\App\Http\Controllers\AttendanceReportController::class, 'index'])->name('reports.attendances');

    Route::resource('/attendances', AttendanceController::class)->names('attendances');
    Route::resource('/branches', BranchController::class)->names('branches');

    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->middleware('throttle:6,1')->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings/update', [SettingController::class, 'update'])->name('settings.update');
});
