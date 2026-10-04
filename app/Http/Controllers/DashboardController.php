<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\PreventiveMaintenancePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const OPEN = [RequestStatus::New, RequestStatus::UnderReview, RequestStatus::Assigned, RequestStatus::Accepted, RequestStatus::Reopened];

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isTechnician()) {
            $request->session()->reflash();

            return redirect()->route('requests.mine');
        }
        if ($request->user()->isEmployee()) {
            $request->session()->reflash();

            return redirect()->route('quick.find');
        }

        $with = ['equipment', 'department', 'assignedTechnician', 'priority'];

        return view('dashboard.index', [
            'stats' => $this->counters(),
            'recent' => MaintenanceRequest::with($with)->latest()->latest('id')->take(8)->get(),
            'critical' => MaintenanceRequest::with($with)
                ->whereHas('priority', fn ($q) => $q->where('is_critical', true))
                ->whereNotIn('status', [RequestStatus::Closed, RequestStatus::Cancelled])
                ->latest()->take(5)->get(),
            'warrantyExpiring' => Equipment::query()->where('has_warranty', true)
                ->whereBetween('warranty_end', [today(), today()->addDays(60)])
                ->orderBy('warranty_end')->get(),
            'pmDueSoon' => PreventiveMaintenancePlan::with('equipment')->where('is_active', true)
                ->where('next_due_date', '<=', today()->addDays(14))
                ->orderBy('next_due_date')->take(10)->get(),
            'now' => now()->toIso8601String(),
        ]);
    }

    /** Polled by the dashboard: counters plus requests created/changed since `since`. */
    public function stats(Request $request): JsonResponse
    {
        $since = $request->date('since') ?? now()->subMinute();
        $changes = MaintenanceRequest::with(['equipment', 'department', 'assignedTechnician', 'priority'])
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')->take(20)->get()
            ->map(fn (MaintenanceRequest $r) => [
                'id' => $r->id,
                'isNew' => $r->created_at->gt($since),
                'requestNumber' => $r->request_number,
                'description' => str($r->description)->limit(100)->toString(),
                'equipment' => $r->equipment?->name,
                'department' => $r->department?->localized_name,
                'technician' => $r->assignedTechnician?->full_name,
                'priorityCritical' => $r->priority->is_critical,
                'priorityLabel' => $r->priority->label(),
                'priorityBadge' => $r->priority->badge(),
                'priorityStyle' => $r->priority->badgeStyle(),
                'status' => $r->status->value,
                'statusLabel' => $r->status->label(),
                'statusBadge' => $r->status->badge(),
                'createdAt' => $r->created_at->format('Y-m-d H:i'),
                'detailsUrl' => route('requests.show', $r),
            ]);

        return response()->json([...$this->counters(), 'changes' => $changes, 'now' => now()->toIso8601String()]);
    }

    /** @return array<string, mixed> */
    private function counters(): array
    {
        $byStatus = MaintenanceRequest::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $count = fn (array $statuses) => collect($statuses)->sum(fn (RequestStatus $s) => (int) ($byStatus[$s->value] ?? 0));

        return [
            'open' => $count(self::OPEN),
            'inProgress' => $count([RequestStatus::InProgress]),
            'waitingParts' => $count([RequestStatus::WaitingParts]),
            'completedThisMonth' => MaintenanceRequest::query()->where('completed_at', '>=', Carbon::now()->startOfMonth())->count(),
            'totalEquipment' => Equipment::query()->count(),
            'downEquipment' => Equipment::query()->where('status', EquipmentStatus::Down)->count(),
            'byStatus' => collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)]),
        ];
    }
}
