<?php

namespace App\Events;

use App\Models\MaintenanceRequest;
use App\Support\Localized;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on every create / status change / note of a maintenance request so open screens update without a reload.
 * Labels are sent in both languages; the browser picks the one matching the page.
 */
class RequestChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array<string, mixed> */
    public array $payload;

    public function __construct(MaintenanceRequest $request, public bool $isNew = false)
    {
        $this->payload = self::payload($request, $isNew);
    }

    public function broadcastAs(): string
    {
        return 'request.changed';
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        $p = $this->payload;
        $channels = [
            new PrivateChannel('staff'),
            new PrivateChannel('department.'.$p['departmentId']),
            new PrivateChannel('request.'.$p['id']),
        ];
        if ($p['technicianId'] !== null) {
            $channels[] = new PrivateChannel('technician.'.$p['technicianId']);
        }
        if ($p['createdById'] !== null) {
            $channels[] = new PrivateChannel('user.'.$p['createdById']);
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    /**
     * Shared shape for the WebSocket event and the dashboard polling endpoint.
     *
     * @return array<string, mixed>
     */
    public static function payload(MaintenanceRequest $r, bool $isNew, ?CarbonInterface $since = null): array
    {
        $r->loadMissing(['equipment', 'department', 'assignedTechnician', 'priority']);

        return [
            'id' => $r->id,
            'isNew' => $isNew || ($since !== null && $r->created_at->gt($since)),
            'requestNumber' => $r->request_number,
            'description' => str($r->description)->limit(100)->toString(),
            'equipment' => $r->equipment?->name,
            'department' => Localized::all(fn () => $r->department?->localized_name),
            'departmentId' => $r->department_id,
            'technician' => $r->assignedTechnician?->full_name,
            'technicianId' => $r->assigned_technician_id,
            'createdById' => $r->created_by_id,
            'priorityCritical' => $r->priority->is_critical,
            'priorityLabel' => Localized::all(fn () => $r->priority->label()),
            'priorityBadge' => $r->priority->badge(),
            'priorityStyle' => $r->priority->badgeStyle(),
            'status' => $r->status->value,
            'statusLabel' => Localized::all(fn () => $r->status->label()),
            'statusBadge' => $r->status->badge(),
            'createdAt' => $r->created_at->format('Y-m-d H:i'),
            'updatedAt' => $r->updated_at?->toIso8601String(),
            'detailsUrl' => route('requests.show', $r),
        ];
    }
}
