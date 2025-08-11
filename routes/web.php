<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AiCommentLogController;
use App\Http\Controllers\Admin\ChannelController;
use App\Http\Controllers\Admin\PostController;



Route::get('/', fn() => view('welcome'));

Route::prefix('admin')->group(function () {
    // ai deja /admin/ai/settings etc.
    Route::resource('channels', ChannelController::class)->names('admin.channels');
});



Route::resource('posts', PostController::class)->names('admin.posts');
Route::post('posts/{post}/publish', [PostController::class,'publish'])->name('admin.posts.publish');



Route::middleware(['web'])
    ->prefix('admin/ai')
    ->group(function () {
        Route::get('/settings', [AiSettingsController::class,'edit'])->name('ai.settings');
        Route::post('/settings', [AiSettingsController::class,'save']);
        Route::get('/comments', [AiCommentLogController::class,'index'])->name('ai.comments');
    });
