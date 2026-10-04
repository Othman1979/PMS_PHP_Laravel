<?php

use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('staff', fn (User $user) => $user->canManage());

Broadcast::channel('department.{id}', fn (User $user, int $id) => $user->canManage()
    || ($user->role === Role::DepartmentManager && (int) $user->department_id === $id));

Broadcast::channel('technician.{id}', fn (User $user, int $id) => $user->id === $id || $user->canManage());

Broadcast::channel('user.{id}', fn (User $user, int $id) => $user->id === $id);

Broadcast::channel('request.{id}', function (User $user, int $id) {
    $request = MaintenanceRequest::query()->find($id);

    return $request !== null && $request->isVisibleTo($user);
});
