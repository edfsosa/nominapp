<?php

use App\Http\Controllers\CheckInController;
use Illuminate\Support\Facades\Route;

Route::get('/marcar', [CheckInController::class, 'showForm']);
Route::post('/marcar', [CheckInController::class, 'store']);
