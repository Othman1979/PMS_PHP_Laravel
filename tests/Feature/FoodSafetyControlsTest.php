<?php

namespace Tests\Feature;

use App\Enums\CalibrationStatus;
use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\SparePart;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CalibrationReminder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodSafetyControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    /** The demo oven: food-contact, critical, CCP, measuring device with expired calibration. */
    private function oven(): Equipment
    {
        return Equipment::where('code', 'EQ-OVN-001')->firstOrFail();
    }

    private function requestOn(Equipment $equipment, array $extra = []): MaintenanceRequest
    {
        $this->actingAs($this->user('employee'))->post('/r/'.$equipment->code, ['description' => 'عطل', ...$extra])->assertRedirect();

        return MaintenanceRequest::latest('id')->firstOrFail();
    }

    private function bringToInProgress(MaintenanceRequest $mr): User
    {
        $tech = $this->user('tech2');
        $mr->update(['status' => RequestStatus::InProgress, 'assigned_technician_id' => $tech->id]);

        return $tech;
    }

    private function completionPayload(array $extra = []): array
    {
        return [
            'fault_type_id' => FaultType::firstOrFail()->id,
            'fault_cause_id' => FaultCause::firstOrFail()->id,
            'resolution_notes' => 'تم الإصلاح',
            ...$extra,
        ];
    }

    public function test_request_on_ccp_equipment_is_escalated_and_food_safety_officer_notified(): void
    {
        $normal = Priority::where('is_default', true)->firstOrFail();
        $critical = Priority::criticalForFoodSafety();
        $this->assertNotSame($normal->id, $critical->id);

        $mr = $this->requestOn($this->oven(), ['priority_id' => $normal->id]);

        $this->assertTrue($mr->food_safety_impact);
        $this->assertSame($critical->id, $mr->priority_id);
        $this->assertTrue(UserNotification::where('user_id', $this->user('foodsafety')->id)->exists());
        $this->assertDatabaseHas('request_timelines', ['request_id' => $mr->id, 'note' => str_replace('{0}', $normal->label(), __('FoodSafetyEscalatedNote'))]);

        $this->actingAs($this->user('admin'))->get('/requests?food_safety=1')->assertOk()->assertSee($mr->request_number);
        $this->get('/')->assertOk()->assertSee(__('OpenFoodSafetyFaults'));
    }

    public function test_employee_can_flag_food_safety_impact_on_ordinary_equipment(): void
    {
        $pos = Equipment::where('code', 'EQ-POS-001')->firstOrFail();

        $this->assertFalse($this->requestOn($pos)->food_safety_impact);
        $this->assertTrue($this->requestOn($pos, ['food_safety_impact' => '1'])->food_safety_impact);
    }

    public function test_food_safety_decision_is_restricted_to_food_safety_roles(): void
    {
        $mr = $this->requestOn($this->oven());
        $payload = ['affected_product' => 'دجاج', 'food_safety_decision' => 'إتلاف الدفعة'];

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/food-safety", $payload)->assertForbidden();
        $this->actingAs($this->user('foodsafety'))->post("/requests/{$mr->id}/food-safety", $payload)->assertRedirect()->assertSessionHasNoErrors();

        $mr->refresh();
        $this->assertSame('دجاج', $mr->affected_product);
        $this->assertSame('إتلاف الدفعة', $mr->food_safety_decision);
        $this->assertDatabaseHas('activity_logs', ['action' => 'food_safety_decision', 'subject_id' => $mr->id]);
    }

    public function test_food_contact_equipment_cannot_be_closed_before_release_checklist_is_signed(): void
    {
        $mr = $this->requestOn($this->oven());
        $tech = $this->bringToInProgress($mr);
        $this->actingAs($tech)->post("/requests/{$mr->id}/complete", $this->completionPayload())->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Completed, $mr->fresh()->status);

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/close")->assertSessionHasErrors('release');
        $this->assertSame(RequestStatus::Completed, $mr->fresh()->status);

        $all = array_fill_keys(MaintenanceRequest::RELEASE_CHECKLIST, '1');
        $this->actingAs($tech)->post("/requests/{$mr->id}/release", ['release' => $all])->assertForbidden();
        $this->actingAs($this->user('foodsafety'))->post("/requests/{$mr->id}/release", ['release' => [...$all, 'sanitized' => '0']])
            ->assertSessionHasErrors('release');
        $this->post("/requests/{$mr->id}/release", ['release' => $all, 'release_notes' => 'حرارة 180 ضمن الحد'])->assertSessionHasNoErrors();

        $mr->refresh();
        $this->assertNotNull($mr->released_at);
        $this->assertSame($this->user('foodsafety')->id, $mr->released_by_id);
        $this->assertSame(['tools_removed', 'cleaned', 'sanitized', 'function_checked'], array_keys(array_filter($mr->release_checklist)));
        $this->assertDatabaseHas('activity_logs', ['action' => 'request_released', 'subject_id' => $mr->id]);

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/close")->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Closed, $mr->fresh()->status);
    }

    public function test_temporary_repair_requires_due_date_and_opens_follow_up_request(): void
    {
        $mr = $this->requestOn($this->oven());
        $tech = $this->bringToInProgress($mr);

        $this->actingAs($tech)->post("/requests/{$mr->id}/complete", $this->completionPayload(['is_temporary_repair' => '1']))
            ->assertSessionHasErrors('permanent_repair_due');

        $due = today()->addDays(7)->toDateString();
        $this->post("/requests/{$mr->id}/complete", $this->completionPayload(['is_temporary_repair' => '1', 'permanent_repair_due' => $due, 'cost_labor' => '25']))
            ->assertSessionHasNoErrors();

        $mr->refresh();
        $this->assertSame(RequestStatus::Completed, $mr->status);
        $this->assertTrue($mr->is_temporary_repair);
        $this->assertSame($due, $mr->permanent_repair_due->toDateString());
        $this->assertSame(25.0, (float) $mr->cost_labor);
        $this->assertSame(EquipmentStatus::WorkingWithIssues, $mr->equipment->fresh()->status);

        $followUp = $mr->followUps()->firstOrFail();
        $this->assertSame(RequestStatus::New, $followUp->status);
        $this->assertSame($mr->equipment_id, $followUp->equipment_id);
        $this->assertTrue($followUp->food_safety_impact);
        $this->assertSame($due, $followUp->due_at->toDateString());
        $this->assertTrue($mr->hasOpenFollowUp());

        $this->actingAs($this->user('admin'))->get("/requests/{$mr->id}")->assertOk()->assertSee($followUp->request_number);
    }

    public function test_non_food_grade_parts_are_rejected_on_food_contact_equipment(): void
    {
        $mr = $this->requestOn($this->oven());
        $tech = $this->bringToInProgress($mr);
        $nonFood = SparePart::where('is_food_grade', false)->firstOrFail();
        $food = SparePart::where('is_food_grade', true)->firstOrFail();
        $before = $food->quantity;

        $this->actingAs($tech)->get("/requests/{$mr->id}")->assertOk()->assertSee($food->name)->assertDontSee($nonFood->name);

        $this->post("/requests/{$mr->id}/complete", $this->completionPayload(['parts' => [['spare_part_id' => $nonFood->id, 'quantity' => 1]]]))
            ->assertSessionHasErrors('parts');
        $this->assertSame(RequestStatus::InProgress, $mr->fresh()->status);

        $this->post("/requests/{$mr->id}/complete", $this->completionPayload(['parts' => [['spare_part_id' => $food->id, 'quantity' => 1]]]))
            ->assertSessionHasNoErrors();
        $this->assertSame($before - 1, $food->fresh()->quantity);
    }

    public function test_spare_part_food_grade_flag_and_certificate_are_stored(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user('admin'))->post('/parts', [
            'name' => 'Food-grade grease', 'quantity' => 5, 'unit_cost' => 30, 'minimum_quantity' => 1,
            'is_food_grade' => '1', 'food_grade_certificate' => UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect('/parts');

        $part = SparePart::where('name', 'Food-grade grease')->firstOrFail();
        $this->assertTrue($part->is_food_grade);
        $this->assertNotNull($part->food_grade_certificate_url);
        $this->get('/parts')->assertOk()->assertSee(__('FoodGrade'));
    }

    public function test_calibration_status_history_and_reminders(): void
    {
        $oven = $this->oven();
        $freezer = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $this->assertSame(CalibrationStatus::Expired, $oven->calibrationStatus());
        $this->assertSame(CalibrationStatus::DueSoon, $freezer->calibrationStatus());
        $this->assertSame(CalibrationStatus::NotRequired, Equipment::where('code', 'EQ-POS-001')->firstOrFail()->calibrationStatus());

        $this->assertSame(2, app(CalibrationReminder::class)->send());
        $this->assertSame(2, UserNotification::where('user_id', $this->user('foodsafety')->id)->count());

        $this->actingAs($this->user('coord'))->post("/equipment/{$oven->id}/calibrations", [
            'calibrated_at' => today()->toDateString(), 'result' => 'Pass', 'provider' => 'Metrology Lab', 'certificate_number' => 'C-77',
        ])->assertSessionHasNoErrors();

        $oven->refresh();
        $this->assertSame(CalibrationStatus::Valid, $oven->calibrationStatus());
        $this->assertSame(today()->addDays(365)->toDateString(), $oven->next_calibration_date->toDateString());
        $this->assertSame(1, $oven->calibrations()->count());
        $this->assertSame('C-77', $oven->calibrations()->firstOrFail()->certificate_number);
        $this->assertSame(1, app(CalibrationReminder::class)->send(), 'only the due-soon freezer remains');

        $this->actingAs($this->user('admin'))->get("/equipment/{$oven->id}")->assertOk()->assertSee('C-77');
        $this->get('/reports/calibration')->assertOk()->assertSee('C-77');
        $this->get('/reports/food-safety')->assertOk()->assertSee($oven->name);
        $this->get('/reports/food-safety/export/xlsx')->assertOk();
    }

    public function test_food_safety_equipment_requires_commissioning_before_working(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        $base = [
            'name' => 'Blast Chiller', 'code' => 'EQ-BLC-001', 'category' => EquipmentCategory::Refrigeration->value,
            'department_id' => Department::firstOrFail()->id, 'food_contact' => '1', 'is_critical' => '1', 'ccp_reference' => 'CCP-3',
        ];

        $this->actingAs($admin)->post('/equipment', [...$base, 'status' => 'Working'])->assertSessionHasErrors('status');
        $this->post('/equipment', [...$base, 'status' => 'OutOfService'])->assertSessionHasNoErrors();

        $chiller = Equipment::where('code', 'EQ-BLC-001')->firstOrFail();
        $this->assertTrue($chiller->requiresCommissioning());
        $this->assertNull($chiller->commissioned_at);

        $this->actingAs($this->user('coord'))->post("/equipment/{$chiller->id}/commission", ['commissioning_notes' => 'ok'])->assertForbidden();
        $this->actingAs($this->user('foodsafety'))->post("/equipment/{$chiller->id}/commission", ['commissioning_notes' => 'ok'])
            ->assertSessionHasErrors('commissioning_notes');

        $this->actingAs($admin)->put("/equipment/{$chiller->id}", [...$base, 'status' => 'OutOfService',
            'purchase_spec' => UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'),
            'conformity_doc' => UploadedFile::fake()->create('ce.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertTrue($chiller->fresh()->hasCommissioningDocuments());

        $this->actingAs($this->user('foodsafety'))->post("/equipment/{$chiller->id}/commission", ['commissioning_notes' => 'تشغيل تجريبي 24 ساعة، -18 مستقرة'])
            ->assertSessionHasNoErrors();

        $chiller->refresh();
        $this->assertNotNull($chiller->commissioned_at);
        $this->assertSame($this->user('foodsafety')->id, $chiller->commissioned_by_id);
        $this->assertSame(EquipmentStatus::Working, $chiller->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'equipment_commissioned', 'subject_id' => $chiller->id]);
    }

    public function test_food_safety_officer_sees_reports_but_not_user_management(): void
    {
        $this->actingAs($this->user('foodsafety'))->get('/reports/food-safety')->assertOk();
        $this->get('/')->assertOk()->assertSee(__('FoodSafetySection'));
        $this->get('/users')->assertForbidden();
    }
}
