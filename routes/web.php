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
    return view('welcome');
})->middleware('restrict.ip');

Route::get('/get-employee/{document_number}', [EmployeeController::class, 'getEmployee'])->name('get-employee');
Route::post('/submit-marker', [AttendanceController::class, 'submitMarker'])->name('submit-marker');

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
    
    Route::resource('/employees', EmployeeController::class)->names('employees');
    Route::resource('/schedules', ScheduleController::class)->names('schedules');
    Route::resource('/attendances', AttendanceController::class)->names('attendances');
    Route::resource('/branches', BranchController::class)->names('branches');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings/update', [SettingController::class, 'update'])->name('settings.update');
});
