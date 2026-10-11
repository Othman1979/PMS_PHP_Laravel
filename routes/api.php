<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\RequestsController;
use App\Http\Controllers\Api\TasksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware('auth.token')->group(function () {
    Route::get('/mobile-config', [RequestsController::class, 'configuration']);
    Route::get('/equipment', [RequestsController::class, 'equipment']);
    Route::get('/request-options', [RequestsController::class, 'options']);
    Route::post('/requests', [RequestsController::class, 'store']);
    Route::get('/requests/{maintenanceRequest}', [RequestsController::class, 'show']);
    Route::post('/requests/{maintenanceRequest}/actions/{action}', [RequestsController::class, 'action'])
        ->whereIn('action', ['accept', 'accept-start', 'start', 'wait-parts', 'resume']);
    Route::post('/requests/{maintenanceRequest}/complete', [RequestsController::class, 'complete']);
    Route::post('/requests/{maintenanceRequest}/comments', [RequestsController::class, 'comment']);
    Route::post('/broadcasting/auth', function (Request $request) {
        $request->setUserResolver(static fn () => Auth::user());

        return Broadcast::auth($request);
    });
    Route::get('/tasks', [TasksController::class, 'index']);
    Route::get('/my-requests', [TasksController::class, 'mine']);
    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationsController::class, 'readAll']);
});
