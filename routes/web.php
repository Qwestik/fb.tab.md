<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AiCommentLogController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web'])
    ->prefix('admin/ai')
    ->group(function () {
        Route::get('/settings', [AiSettingsController::class,'edit'])->name('ai.settings');
        Route::post('/settings', [AiSettingsController::class,'save']);
        Route::get('/comments', [AiCommentLogController::class,'index'])->name('ai.comments');
    });
