<?php

use App\Http\Controllers\FelicaController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;


// FeliCa IDm 読み取り画面
Route::get('/', [FelicaController::class, 'index'])
    ->name('felica.index');

// IDm 受信 API（Fetch から POST。web ミドルウェアグループ内なので CSRF 検証が効く）
Route::post('/felica/idm', [FelicaController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('felica.store');

// students
Route::get('students', [StudentController::class, 'index'])->name('students.index');
Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
Route::post('students/store', [StudentController::class, 'store'])->name('students.store');
Route::get('students/show/{student}', [StudentController::class, 'show'])->name('students.show');
Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');