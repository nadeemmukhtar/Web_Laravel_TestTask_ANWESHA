<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::controller(ImportController::class)->group(function () {
    Route::get('/dashboard', 'index');
    Route::post('/imports/trigger', 'triggerImport');
    Route::post('/uploads/image', 'uploadImage');
});
