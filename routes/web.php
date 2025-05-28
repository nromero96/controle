<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AttendanceController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/get-employee/{document_number}', [EmployeeController::class, 'getEmployee'])->name('get-employee');
Route::post('/submit-marker', [AttendanceController::class, 'submitMarker'])->name('submit-marker');

Auth::routes(['register' => false]);

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

//Solo para authenticated users
Route::group(['middleware' => ['auth']], function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    
    Route::resource('/employees', EmployeeController::class)->names('employees');
    Route::resource('/schedules', ScheduleController::class)->names('schedules');
    Route::resource('/attendances', AttendanceController::class)->names('attendances');
});
