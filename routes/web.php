<?php

use App\Http\Controllers\FelicaController;
use Illuminate\Support\Facades\Route;


// FeliCa IDm 読み取り画面
Route::get('/', [FelicaController::class, 'index'])
    ->name('felica.index');

// IDm 受信 API（Fetch から POST。web ミドルウェアグループ内なので CSRF 検証が効く）
Route::post('/felica/idm', [FelicaController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('felica.store');