<?php

use App\Http\Controllers\LookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LookupController::class, 'index'])->name('home');
Route::post('/', [LookupController::class, 'lookup'])->name('lookup');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
