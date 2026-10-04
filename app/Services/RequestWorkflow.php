<?php

namespace App\Services;

use App\Enums\DepartmentConfirmation;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\ChecklistResult;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Maintenance request lifecycle: creation, status transitions (with timeline) and notifications. */
class RequestWorkflow
{
    public function __construct(private FileUploadService $files, private WebPushService $push) {}

    /** @param list<UploadedFile> $files */
    public function create(?User $user, ?Equipment $equipment, int $departmentId, string $description,
        Priority $priority, array $files = [], bool $preventive = false, ?int $planId = null, ?int $faultTypeId = null): MaintenanceRequest
    {
        $request = DB::transaction(function () use ($user, $equipment, $departmentId, $description, $priority, $preventive, $planId, $faultTypeId) {
            $request = MaintenanceRequest::create([
                'equipment_id' => $equipment?->id,
                'department_id' => $departmentId,
                'created_by_id' => $user?->id ?? User::query()->where('role', Role::Admin)->value('id'),
                'description' => $description,
                'priority_id' => $priority->id,
                'fault_type_id' => $faultTypeId,
                'status' => RequestStatus::New,
                'is_under_warranty' => $equipment?->isUnderWarranty() ?? false,
                'is_preventive' => $preventive,
                'preventive_maintenance_plan_id' => $planId,
            ]);
            $request->update(['request_number' => sprintf('MR-%s-%05d', $request->created_at->format('Y'), $request->id)]);
            $request->timeline()->create([
                'status_from' => null,
                'status_to' => RequestStatus::New,
                'changed_by_id' => $user?->id,
                'changed_at' => now(),
            ]);

            return $request;
        });

        foreach (array_slice($files, 0, 5) as $file) {
            $saved = $this->files->save($file, 'requests/'.$request->id);
            if ($saved !== null) {
                $request->attachments()->create([
                    'file_url' => $saved['url'],
                    'file_name' => $saved['name'],
                    'uploaded_by_id' => $user?->id,
                ]);
            }
        }

        $request->load(['equipment', 'department', 'priority']);
        $this->notifyNewRequest($request);

        return $request;
    }

    public function transition(MaintenanceRequest $request, RequestStatus $to, ?User $by, ?string $note = null): void
    {
        $from = $request->status;
        $request->status = $to;
        if ($to === RequestStatus::Closed) {
            $request->closed_at ??= now();
        }

        DB::transaction(function () use ($request, $from, $to, $by, $note) {
            $current = MaintenanceRequest::query()->lockForUpdate()->find($request->id, ['status']);
            if ($current !== null && $current->status !== $from) {
                $request->status = $current->status;
                throw ValidationException::withMessages(['status' => __('Error_StateChanged')]);
            }
            $request->save();
            $request->touch();
            $request->timeline()->create([
                'status_from' => $from,
                'status_to' => $to,
                'changed_by_id' => $by?->id,
                'changed_at' => now(),
                'note' => filled($note) ? trim($note) : null,
            ]);
        });
    }

    public function assign(MaintenanceRequest $request, User $technician, User $by, ?string $note): void
    {
        $request->assigned_technician_id = $technician->id;
        $request->assigned_at = now();
        $this->transition($request, RequestStatus::Assigned, $by, $note);
        $request->setRelation('assignedTechnician', $technician);

        $what = $request->equipment?->name ?? $request->department?->localized_name;
        $desc = Str::limit($request->description, 120);
        $body = $what ? "{$what} — {$desc}" : $desc;
        if (filled($note)) {
            $body .= "\n".$note;
        }
        $this->push->sendToUsers($technician->id, __('Push_AssignedTitle').' '.$request->request_number,
            $body, route('requests.show', $request, false), 'request-'.$request->id);
    }

    /**
     * Completes the request: issues spare parts from stock (rejects quantities above the balance), records checklist results,
     * and moves the PM plan forward.
     *
     * @param  array<int, int>  $parts  spare_part_id => quantity
     * @param  array<int, array{compliant: bool, note: ?string}>  $checklist  checklist_item_id => result
     */
    public function complete(MaintenanceRequest $request, User $by, string $resolution, ?string $technicianNotes,
        float $laborCost, array $parts, array $checklist, ?int $faultTypeId = null, ?int $faultCauseId = null): void
    {
        DB::transaction(function () use ($request, $by, $resolution, $technicianNotes, $laborCost, $parts, $checklist, $faultTypeId, $faultCauseId) {
            foreach ($parts as $partId => $qty) {
                $qty = (int) $qty;
                $spare = SparePart::query()->lockForUpdate()->find($partId);
                if ($spare === null || $qty <= 0) {
                    continue;
                }
                if ($qty > $spare->quantity) {
                    throw ValidationException::withMessages([
                        'parts' => str_replace(['{0}', '{1}'], [$spare->name, $spare->quantity], __('Error_InsufficientStock')),
                    ]);
                }
                $spare->decrement('quantity', $qty);
                StockMovement::create([
                    'spare_part_id' => $spare->id,
                    'date' => now(),
                    'type' => StockMovementType::Issue,
                    'quantity' => -$qty,
                    'balance_after' => $spare->quantity,
                    'unit_cost' => $spare->unit_cost,
                    'maintenance_request_id' => $request->id,
                    'user_id' => $by->id,
                ]);
                $request->partsUsed()->create([
                    'spare_part_id' => $spare->id,
                    'quantity' => $qty,
                    'unit_cost_at_use' => $spare->unit_cost,
                ]);
            }

            foreach ($checklist as $itemId => $result) {
                ChecklistResult::create([
                    'request_id' => $request->id,
                    'checklist_item_id' => $itemId,
                    'is_compliant' => $result['compliant'],
                    'note' => $result['note'],
                ]);
            }

            $request->fill([
                'resolution_notes' => $resolution,
                'technician_notes' => $technicianNotes,
                'fault_type_id' => $faultTypeId ?? $request->fault_type_id,
                'fault_cause_id' => $faultCauseId,
                'cost_labor' => (float) $request->cost_labor + $laborCost,
                'cost_parts' => (float) $request->partsUsed()->selectRaw('coalesce(sum(quantity * unit_cost_at_use), 0) as total')->value('total'),
                'completed_at' => now(),
                'department_confirmation' => DepartmentConfirmation::Pending,
            ]);

            $equipment = $request->equipment;
            $equipment?->update(['last_maintenance_date' => today()]);

            $plan = $request->plan;
            if ($request->is_preventive && $plan !== null) {
                $plan->last_executed_date = today();
                if ($plan->next_due_date->lte(today())) {
                    $plan->next_due_date = today()->addDays($plan->frequency_days);
                    $equipment?->update(['next_maintenance_date' => $plan->next_due_date]);
                }
                $plan->save();
            }

            $this->transition($request, RequestStatus::Completed, $by, $resolution);
        });

        $this->notifyStaff($request, 'Push_CompletedTitle', $resolution, $by);
    }

    public function notifyStaff(MaintenanceRequest $request, string $titleKey, ?string $note, ?User $except): void
    {
        $text = Str::limit(filled($note) ? $note : $request->description, 120);
        $this->push->sendToUsers(
            $this->staffIds($except?->id),
            __($titleKey).' '.$request->request_number,
            ($request->assignedTechnician?->full_name ?? '').' — '.$text,
            route('requests.show', $request, false),
            'request-'.$request->id,
        );
    }

    private function notifyNewRequest(MaintenanceRequest $request): void
    {
        $what = $request->equipment?->name ?? $request->department?->localized_name;
        $desc = Str::limit($request->description, 120);
        $title = __('Push_NewRequestTitle').' '.$request->request_number;
        if (! $request->priority->is_default) {
            $title = $request->priority->label().' — '.$title;
        }
        $this->push->sendToUsers($this->staffIds($request->created_by_id), $title,
            $what ? "{$what} — {$desc}" : $desc, route('requests.show', $request, false), 'request-'.$request->id);
    }

    /** @return list<int> active admins + coordinators */
    public function staffIds(?int $except = null): array
    {
        return User::query()
            ->whereIn('role', [Role::Admin, Role::Coordinator])
            ->where('is_active', true)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->pluck('id')->all();
    }
}
