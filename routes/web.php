<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [ImportController::class, 'index']);
Route::post('/imports/trigger', [ImportController::class, 'triggerImport']);
Route::post('/uploads/image', [ImportController::class, 'uploadImage']);