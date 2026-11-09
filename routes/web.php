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
Route::resource('students', StudentController::class);

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