<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\PreventiveMaintenancePlan;
use App\Models\Priority;
use App\Models\SparePart;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\RequestWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MobileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function tokenFor(string $username): void
    {
        User::where('username', $username)->firstOrFail()->forceFill(['api_token' => hash('sha256', $username)])->save();
        $this->withToken($username);
    }

    private function newRequest(string $code = 'EQ-FRZ-001'): MaintenanceRequest
    {
        $this->tokenFor('employee');
        $id = $this->postJson('/api/requests', [
            'equipment_id' => Equipment::where('code', $code)->value('id'),
            'description' => 'Compressor is leaking',
        ])->assertCreated()->json('request.id');

        return MaintenanceRequest::findOrFail($id);
    }

    private function assignedRequest(): MaintenanceRequest
    {
        $mr = $this->newRequest();
        app(RequestWorkflow::class)->assign($mr, User::where('username', 'tech1')->firstOrFail(), User::where('username', 'coord')->firstOrFail(), null);
        $this->tokenFor('tech1');

        return $mr->fresh();
    }

    private function completion(): array
    {
        return [
            'fault_type_id' => FaultType::active()->firstOrFail()->id,
            'fault_cause_id' => FaultCause::active()->firstOrFail()->id,
            'resolution_notes' => 'Replaced damaged seal',
            'cost_labor' => 12.5,
        ];
    }

    public function test_mobile_endpoints_require_an_active_token(): void
    {
        $this->withToken('invalid')->getJson('/api/equipment')->assertUnauthorized();
        $this->postJson('/api/requests', [])->assertUnauthorized();
        $this->getJson('/api/mobile-config')->assertUnauthorized();
        $this->tokenFor('employee');
        User::where('username', 'employee')->update(['is_active' => false]);
        $this->getJson('/api/request-options')->assertUnauthorized();
    }

    public function test_equipment_search_and_options_use_configured_data(): void
    {
        $this->tokenFor('employee');
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $this->getJson('/api/equipment?q=EQ-FRZ-001')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $equipment->id)
            ->assertJsonPath('items.0.autoEscalates', true);
        $this->getJson('/api/request-options')->assertOk()
            ->assertJsonPath('defaultPriorityId', Priority::default()->id)
            ->assertJsonCount(FaultType::active()->count(), 'faultTypes');
        $equipment->update(['status' => EquipmentStatus::OutOfService]);
        $this->getJson('/api/equipment?q=EQ-FRZ-001')->assertJsonCount(0, 'items');
        $this->postJson('/api/requests', ['equipment_id' => $equipment->id, 'description' => 'Broken'])
            ->assertUnprocessable()->assertJsonValidationErrors('equipment_id');
    }

    public function test_employee_creation_persists_ownership_escalation_and_photo(): void
    {
        $this->tokenFor('employee');
        $employee = User::where('username', 'employee')->firstOrFail();
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $this->mock(FileUploadService::class)->shouldReceive('save')->once()
            ->andReturn(['url' => '/uploads/test-photo.jpg', 'name' => 'photo.jpg']);
        $id = $this->postJson('/api/requests', [
            'equipment_id' => $equipment->id, 'description' => 'Leaking', 'priority_id' => Priority::default()->id,
            'food_safety_impact' => false, 'created_by_id' => User::where('username', 'admin')->value('id'),
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated()->assertJsonPath('request.foodSafety', true)->json('request.id');
        $mr = MaintenanceRequest::findOrFail($id);
        $this->assertSame($employee->id, $mr->created_by_id);
        $this->assertSame(Priority::criticalForFoodSafety()->id, $mr->priority_id);
        $this->assertSame('photo.jpg', $mr->attachments()->firstOrFail()->file_name);
        $this->assertNotEmpty($mr->timeline);
        $this->getJson("/api/requests/{$id}")->assertOk()->assertJsonPath('request.descriptionFull', 'Leaking');
        $this->getJson('/api/my-requests')->assertJsonPath('items.0.id', $id);
    }

    public function test_invalid_request_data_is_rejected_without_creating_a_request(): void
    {
        $this->tokenFor('employee');
        $before = MaintenanceRequest::count();
        $this->postJson('/api/requests', ['equipment_id' => -1, 'description' => ' ', 'priority_id' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['equipment_id', 'description', 'priority_id']);
        $this->assertSame($before, MaintenanceRequest::count());
    }

    public function test_only_assigned_technician_can_mutate_and_other_employees_cannot_read(): void
    {
        $mr = $this->assignedRequest();
        foreach (['tech2', 'employee', 'admin'] as $username) {
            $this->tokenFor($username);
            foreach (['accept', 'accept-start', 'start', 'wait-parts', 'resume'] as $action) {
                $this->postJson("/api/requests/{$mr->id}/actions/{$action}")->assertForbidden();
            }
            $this->postJson("/api/requests/{$mr->id}/complete", $this->completion())->assertForbidden();
        }
        $this->tokenFor('tech2');
        $this->getJson("/api/requests/{$mr->id}")->assertForbidden();
        $this->postJson("/api/requests/{$mr->id}/comments", ['note' => 'Not my task'])->assertForbidden();
        $this->postJson('/api/requests', [])->assertForbidden();
        $this->getJson('/api/equipment')->assertForbidden();
        $mr->update(['created_by_id' => User::where('username', 'admin')->value('id')]);
        $this->tokenFor('employee');
        $this->getJson("/api/requests/{$mr->id}")->assertForbidden();
    }

    public function test_technician_cycle_persists_timestamps_comments_costs_and_stock_once(): void
    {
        $mr = $this->assignedRequest();
        $base = "/api/requests/{$mr->id}";
        $this->postJson("{$base}/actions/start")->assertConflict();
        $this->postJson("{$base}/actions/accept")->assertOk()->assertJsonPath('request.status', 'Accepted');
        $this->postJson("{$base}/actions/start")->assertOk()->assertJsonPath('request.status', 'InProgress');
        $this->postJson("{$base}/actions/wait-parts", ['note' => 'Waiting for a seal'])->assertOk()->assertJsonPath('request.status', 'WaitingParts');
        $this->postJson("{$base}/actions/resume")->assertOk()->assertJsonPath('request.status', 'InProgress');
        $this->postJson("{$base}/comments", ['note' => 'Seal arrived'])->assertOk()->assertJsonPath('request.timeline.0.note', 'Seal arrived');
        $part = SparePart::firstOrFail();
        $part->update(['is_food_grade' => true, 'quantity' => 10, 'unit_cost' => 3.5]);
        $data = $this->completion() + ['parts' => [['spare_part_id' => $part->id, 'quantity' => 2]]];
        $this->postJson("{$base}/complete", $data)->assertOk()->assertJsonPath('request.status', 'Completed');
        $this->postJson("{$base}/complete", $data)->assertConflict();
        $mr->refresh();
        $this->assertNotNull($mr->accepted_at);
        $this->assertNotNull($mr->started_at);
        $this->assertNotNull($mr->completed_at);
        $this->assertSame('12.50', $mr->cost_labor);
        $this->assertSame('7.00', $mr->cost_parts);
        $this->assertSame(8, $part->fresh()->quantity);
        $this->assertSame(1, $mr->partsUsed()->count());
    }

    public function test_completion_keeps_food_grade_stock_and_release_guards(): void
    {
        $mr = $this->assignedRequest();
        $mr->equipment->update(['food_contact' => true]);
        $this->postJson("/api/requests/{$mr->id}/actions/accept-start")->assertOk();
        $part = SparePart::firstOrFail();
        $part->update(['is_food_grade' => false, 'quantity' => 3]);
        $data = $this->completion() + ['parts' => [['spare_part_id' => $part->id, 'quantity' => 1]]];
        $this->postJson("/api/requests/{$mr->id}/complete", $data)->assertUnprocessable()->assertJsonValidationErrors('parts');
        $this->assertSame(3, $part->fresh()->quantity);
        $this->assertSame(RequestStatus::InProgress, $mr->fresh()->status);
        $part->update(['is_food_grade' => true]);
        $data['parts'][0]['quantity'] = 4;
        $this->postJson("/api/requests/{$mr->id}/complete", $data)->assertUnprocessable()->assertJsonValidationErrors('parts');
        $this->postJson("/api/requests/{$mr->id}/complete", $this->completion())->assertOk();
        $this->assertNull($mr->fresh()->released_at);
        $this->postJson("/api/requests/{$mr->id}/actions/close")->assertNotFound();
    }

    public function test_completion_requires_catalog_faults_and_valid_temporary_repair_date(): void
    {
        $mr = $this->assignedRequest();
        $this->postJson("/api/requests/{$mr->id}/actions/accept-start")->assertOk();
        $this->postJson("/api/requests/{$mr->id}/complete", ['resolution_notes' => 'Fixed'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fault_type_id', 'fault_cause_id']);
        $data = $this->completion() + ['is_temporary_repair' => true];
        $this->postJson("/api/requests/{$mr->id}/complete", $data)->assertUnprocessable()->assertJsonValidationErrors('permanent_repair_due');
        $data['permanent_repair_due'] = today()->addWeek()->toDateString();
        $this->postJson("/api/requests/{$mr->id}/complete", $data)->assertOk();
        $this->assertTrue($mr->fresh()->is_temporary_repair);
        $this->assertSame(1, $mr->followUps()->count());
    }

    public function test_preventive_checklist_is_loaded_and_saved_with_completion(): void
    {
        $mr = $this->assignedRequest();
        $plan = PreventiveMaintenancePlan::firstOrFail();
        $mr->update(['is_preventive' => true, 'preventive_maintenance_plan_id' => $plan->id]);
        $item = $plan->checklist->items->first();
        $this->getJson("/api/requests/{$mr->id}")->assertOk()->assertJsonPath('request.checklist.0.id', $item->id);
        $this->postJson("/api/requests/{$mr->id}/actions/accept-start")->assertOk();
        $this->postJson("/api/requests/{$mr->id}/complete", $this->completion() + [
            'check' => [$item->id => true], 'check_note' => [$item->id => 'Checked pressure'],
        ])->assertOk();
        $this->assertDatabaseHas('checklist_results', ['request_id' => $mr->id, 'checklist_item_id' => $item->id, 'is_compliant' => true, 'note' => 'Checked pressure']);
    }

    public function test_mobile_configuration_selects_polling_or_public_reverb_settings_without_secrets(): void
    {
        $this->tokenFor('employee');
        config(['broadcasting.default' => 'null']);
        $this->getJson('/api/mobile-config')->assertOk()->assertExactJson(['pollSeconds' => 15, 'websocket' => null]);
        config(['broadcasting.default' => 'reverb', 'reverb.apps.apps.0.key' => 'public-key', 'reverb.apps.apps.0.secret' => 'not-for-mobile',
            'reverb.apps.apps.0.options' => ['host' => 'realtime.example.com', 'port' => 443, 'scheme' => 'https']]);
        $response = $this->getJson('/api/mobile-config')->assertOk()->assertJsonPath('websocket.key', 'public-key')
            ->assertJsonPath('websocket.scheme', 'wss')->assertJsonPath('websocket.port', 443);
        $this->assertStringNotContainsString('not-for-mobile', $response->getContent());
    }
}
