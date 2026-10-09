<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Events\RequestChanged;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TasksController extends Controller
{
    /** Technician: open tasks assigned to them (same scope as requests.mine) + recently completed. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = [RequestStatus::Assigned, RequestStatus::Accepted, RequestStatus::InProgress, RequestStatus::WaitingParts];

        $tasks = MaintenanceRequest::with(['equipment', 'department', 'priority', 'assignedTechnician', 'createdBy'])
            ->where('assigned_technician_id', $user->id)
            ->whereIn('status', $active)
            ->orderByRaw('case when due_at is not null and due_at < ? then 0 else 1 end', [now()])
            ->orderByRaw(Priority::rankSql().' desc')->orderBy('due_at')->orderBy('assigned_at')
            ->get();

        $done = MaintenanceRequest::with(['equipment', 'department', 'priority'])
            ->where('assigned_technician_id', $user->id)
            ->whereIn('status', [RequestStatus::Completed, RequestStatus::Closed])
            ->latest('completed_at')->take(5)->get();

        return response()->json([
            'tasks' => $tasks->map(fn (MaintenanceRequest $r) => self::taskPayload($r)),
            'done' => $done->map(fn (MaintenanceRequest $r) => self::taskPayload($r)),
        ]);
    }

    /** Employee: requests they created, newest first. */
    public function mine(Request $request): JsonResponse
    {
        $items = MaintenanceRequest::with(['equipment', 'department', 'priority', 'assignedTechnician'])
            ->where('created_by_id', $request->user()->id)
            ->latest('id')->take(25)->get();

        return response()->json(['items' => $items->map(fn (MaintenanceRequest $r) => self::taskPayload($r))]);
    }

    /** RequestChanged payload + the extra fields the native cards need. */
    private static function taskPayload(MaintenanceRequest $r): array
    {
        return RequestChanged::payload($r, false) + [
            'priorityCode' => $r->priority?->code,
            'descriptionFull' => $r->description,
            'equipmentCode' => $r->equipment?->code,
            'equipmentCategory' => $r->equipment?->category->value,
            'equipmentLocation' => $r->equipment?->location,
            'equipmentSerial' => $r->equipment?->serial_number,
            'assignedAgo' => $r->assigned_at?->diffForHumans(),
            'createdAgo' => $r->created_at?->diffForHumans(),
            'completedAt' => $r->completed_at?->format('Y-m-d H:i'),
        ];
    }
}
