<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentImportController;
use App\Http\Controllers\FaultCauseController;
use App\Http\Controllers\FaultTypeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PreventiveMaintenanceController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\QuickRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/language', LanguageController::class)->name('language');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats')->middleware('role:Admin,Coordinator,DepartmentManager,FoodSafety');

    Route::controller(PushController::class)->prefix('push')->name('push.')->group(function () {
        Route::get('/public-key', 'publicKey')->name('public-key');
        Route::post('/subscribe', 'subscribe')->name('subscribe');
        Route::post('/unsubscribe', 'unsubscribe')->name('unsubscribe');
        Route::post('/test', 'test')->name('test');
    });

    // QR quick request: opened by scanning the label on the equipment.
    Route::controller(QuickRequestController::class)->prefix('r')->name('quick.')->group(function () {
        Route::get('/', 'find')->name('find');
        Route::get('/mine', 'mine')->name('mine');
        Route::get('/done/{maintenanceRequest}', 'done')->name('done');
        Route::get('/{code}', 'show')->name('show');
        Route::post('/{code}', 'store')->name('store');
    });

    Route::controller(MaintenanceRequestController::class)->prefix('requests')->name('requests.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/mine', 'mine')->name('mine')->middleware('role:Technician');
        Route::post('/bulk', 'bulk')->name('bulk')->middleware('role:Admin,Coordinator');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{maintenanceRequest}', 'show')->name('show');
        Route::post('/{maintenanceRequest}/review', 'review')->name('review')->middleware('role:Admin,Coordinator');
        Route::post('/{maintenanceRequest}/assign', 'assign')->name('assign')->middleware('role:Admin,Coordinator');
        Route::post('/{maintenanceRequest}/accept', 'accept')->name('accept');
        Route::post('/{maintenanceRequest}/start', 'start')->name('start');
        Route::post('/{maintenanceRequest}/accept-start', 'acceptStart')->name('accept-start');
        Route::post('/{maintenanceRequest}/comment', 'comment')->name('comment');
        Route::post('/{maintenanceRequest}/note', 'addNote')->name('note');
        Route::post('/{maintenanceRequest}/wait-parts', 'waitParts')->name('wait-parts');
        Route::post('/{maintenanceRequest}/resume', 'resume')->name('resume');
        Route::post('/{maintenanceRequest}/complete', 'complete')->name('complete');
        Route::post('/{maintenanceRequest}/confirm', 'confirm')->name('confirm');
        Route::post('/{maintenanceRequest}/close', 'close')->name('close')->middleware('role:Admin,Coordinator');
        Route::post('/{maintenanceRequest}/release', 'release')->name('release')->middleware('role:Admin,DepartmentManager,FoodSafety');
        Route::post('/{maintenanceRequest}/food-safety', 'foodSafety')->name('food-safety')->middleware('role:Admin,FoodSafety');
        Route::post('/{maintenanceRequest}/reopen', 'reopen')->name('reopen');
        Route::post('/{maintenanceRequest}/cancel', 'cancel')->name('cancel');
    });

    Route::controller(EquipmentController::class)->prefix('equipment')->name('equipment.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('role:Admin,Coordinator,DepartmentManager,FoodSafety');
        Route::get('/labels', 'labels')->name('labels')->middleware('role:Admin,Coordinator');
        Route::post('/{equipment}/calibrations', 'calibrate')->name('calibrate')->middleware('role:Admin,Coordinator,FoodSafety');
        Route::post('/{equipment}/commission', 'commission')->name('commission')->middleware('role:Admin,FoodSafety');
        Route::controller(EquipmentImportController::class)->prefix('import')->name('import.')->middleware('role:Admin,Coordinator')->group(function () {
            Route::get('/template', 'template')->name('template');
            Route::get('/create', 'create')->name('create');
            Route::post('/preview', 'preview')->name('preview');
            Route::post('/', 'store')->name('store');
        });
        Route::middleware('role:Admin,Coordinator')->group(function () {
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{equipment}/edit', 'edit')->name('edit');
            Route::put('/{equipment}', 'update')->name('update');
        });
        Route::delete('/{equipment}', 'destroy')->name('destroy')->middleware('role:Admin');
        Route::get('/{equipment}/qr.svg', 'qr')->name('qr');
        Route::get('/{equipment}', 'show')->name('show');
    });

    Route::middleware('role:Admin,Coordinator,FoodSafety')->group(function () {
        Route::get('/reports', [ReportController::class, 'show'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show')->where('report', '[a-z-]+');
        Route::get('/reports/{report}/export/{format}', [ReportController::class, 'export'])->name('reports.export')->where('report', '[a-z-]+')->whereIn('format', ['xlsx', 'pdf']);
    });

    Route::middleware('role:Admin,Coordinator')->group(function () {
        Route::controller(PreventiveMaintenanceController::class)->prefix('pm')->name('pm.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/generate', 'generate')->name('generate');
            Route::get('/{plan}/edit', 'edit')->name('edit');
            Route::put('/{plan}', 'update')->name('update');
            Route::delete('/{plan}', 'destroy')->name('destroy');
            Route::post('/{plan}/done', 'markDone')->name('done');
        });

        Route::controller(ChecklistController::class)->prefix('checklists')->name('checklists.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{checklist}/edit', 'edit')->name('edit');
            Route::put('/{checklist}', 'update')->name('update');
            Route::delete('/{checklist}', 'destroy')->name('destroy');
            Route::post('/{checklist}/items', 'addItem')->name('items.store');
            Route::delete('/{checklist}/items/{item}', 'deleteItem')->name('items.destroy')->scopeBindings();
        });

        Route::controller(PurchaseRequestController::class)->group(function () {
            Route::prefix('purchases')->name('purchases.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{purchase}', 'show')->name('show');
                Route::post('/{purchase}/approve', 'approve')->name('approve')->middleware('role:Admin');
                Route::post('/{purchase}/reject', 'reject')->name('reject')->middleware('role:Admin');
                Route::post('/{purchase}/cancel', 'cancel')->name('cancel');
                Route::get('/{purchase}/receive', 'receiveForm')->name('receive');
                Route::post('/{purchase}/receive', 'receive')->name('receive.store');
            });
            Route::get('/receipts/{receipt}', 'receipt')->name('receipts.show');
        });

        Route::get('/parts/movements', [SparePartController::class, 'movements'])->name('parts.movements');
    });

    Route::controller(SparePartController::class)->prefix('parts')->name('parts.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('role:Admin,Coordinator,FoodSafety');
        Route::middleware('role:Admin,Coordinator')->group(function () {
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{part}/edit', 'edit')->name('edit');
            Route::put('/{part}', 'update')->name('update');
            Route::post('/{part}/adjust', 'adjust')->name('adjust');
        });
        Route::delete('/{part}', 'destroy')->name('destroy')->middleware('role:Admin');
    });

    Route::middleware('role:Admin')->group(function () {
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('priorities', PriorityController::class)->except('show');
        Route::resource('fault-types', FaultTypeController::class)->except('show');
        Route::resource('fault-causes', FaultCauseController::class)->except('show');
        Route::controller(SettingController::class)->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/', 'update')->name('update');
            Route::post('/backup', 'backup')->name('backup');
            Route::get('/activity', 'activity')->name('activity');
        });
    });
});
