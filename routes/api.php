<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\TasksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware('auth.token')->group(function () {
    Route::post('/broadcasting/auth', function (Request $request) {
        $request->setUserResolver(static fn () => Auth::user());

        return Broadcast::auth($request);
    });
    Route::get('/tasks', [TasksController::class, 'index']);
    Route::get('/my-requests', [TasksController::class, 'mine']);
    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationsController::class, 'readAll']);
});
