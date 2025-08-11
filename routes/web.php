<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AiCommentLogController;
use App\Http\Controllers\Admin\ChannelController;



Route::get('/', fn() => view('welcome'));

Route::prefix('admin')->group(function () {
    // ai deja /admin/ai/settings etc.
    Route::resource('channels', ChannelController::class)->names('admin.channels');
});


Route::middleware(['web'])
    ->prefix('admin/ai')
    ->group(function () {
        Route::get('/settings', [AiSettingsController::class,'edit'])->name('ai.settings');
        Route::post('/settings', [AiSettingsController::class,'save']);
        Route::get('/comments', [AiCommentLogController::class,'index'])->name('ai.comments');
    });
