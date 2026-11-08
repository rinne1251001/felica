<?php

use App\Http\Controllers\EntryPassController;
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

// 入校証の照合・登録（POST は web ミドルウェアグループ内なので CSRF 検証が効く）
Route::get('entry-pass', [EntryPassController::class, 'index'])->name('entry-pass.index');
Route::post('entry-pass/touch', [EntryPassController::class, 'touch'])
    ->middleware('throttle:60,1')
    ->name('entry-pass.touch');
Route::post('entry-pass/students/search', [EntryPassController::class, 'searchStudents'])
    ->middleware('throttle:120,1')
    ->name('entry-pass.students.search');
Route::post('entry-pass/link', [EntryPassController::class, 'link'])
    ->middleware('throttle:30,1')
    ->name('entry-pass.link');