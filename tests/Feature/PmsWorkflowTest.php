<?php

namespace Tests\Feature;

use App\Enums\EquipmentCategory;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\PurchaseRequest;
use App\Models\SparePart;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PmsWorkflowTest extends TestCase
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

    public function test_login_uses_username(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => '1234'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->user('admin'));
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/r/EQ-FRZ-001')->assertRedirect('/login');
    }

    public function test_role_restrictions(): void
    {
        $this->actingAs($this->user('tech1'))->get('/purchases')->assertForbidden();
        $this->actingAs($this->user('employee'))->get('/parts')->assertForbidden();
        $this->actingAs($this->user('coord'))->get('/users')->assertForbidden();
        $this->actingAs($this->user('coord'))->get('/purchases/create')->assertOk();
        $this->actingAs($this->user('admin'))->get('/users')->assertOk();
    }

    public function test_qr_quick_request_creates_request_for_equipment(): void
    {
        $equipment = Equipment::where('code', 'EQ-POS-001')->firstOrFail();

        $this->actingAs($this->user('employee'))->get('/r/EQ-POS-001')->assertOk()->assertSee($equipment->name);

        $urgent = Priority::where('code', 'Urgent')->firstOrFail();
        $this->post('/r/EQ-POS-001', ['description' => 'لا يبرد', 'priority_id' => $urgent->id])->assertRedirect();

        $mr = MaintenanceRequest::latest('id')->firstOrFail();
        $this->assertSame($equipment->id, $mr->equipment_id);
        $this->assertSame($equipment->department_id, $mr->department_id);
        $this->assertSame($urgent->id, $mr->priority_id);
        $this->assertSame(RequestStatus::New, $mr->status);
    }

    public function test_quick_request_validation_errors_are_arabic(): void
    {
        $this->actingAs($this->user('employee'))
            ->withUnencryptedCookie('pms_locale', 'ar')
            ->from('/r/EQ-FRZ-001')
            ->post('/r/EQ-FRZ-001', [
                'photo' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
            ])
            ->assertRedirect('/r/EQ-FRZ-001')
            ->assertSessionHasErrors([
                'description' => 'حقل الوصف مطلوب.',
                'photo' => 'يجب أن يكون الصورة صورة.',
            ]);
    }

    public function test_full_request_lifecycle_issues_parts_from_stock(): void
    {
        $mr = $this->newRequest();
        $part = SparePart::firstOrFail();
        $before = $part->quantity;
        $tech = $this->user('tech1');

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $tech->id])->assertRedirect();
        $this->assertSame(RequestStatus::Assigned, $mr->fresh()->status);

        $this->actingAs($tech)->post("/requests/{$mr->id}/accept")->assertRedirect();
        $this->post("/requests/{$mr->id}/start", ['note' => 'بلشت'])->assertRedirect();
        $this->assertSame(RequestStatus::InProgress, $mr->fresh()->status);

        $this->post("/requests/{$mr->id}/complete", [
            'fault_type_id' => FaultType::firstOrFail()->id,
            'fault_cause_id' => FaultCause::firstOrFail()->id,
            'resolution_notes' => 'تم تبديل القطعة',
            'cost_labor' => '10.5',
            'parts' => [['spare_part_id' => $part->id, 'quantity' => 2]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $mr->refresh();
        $this->assertSame(RequestStatus::Completed, $mr->status);
        $this->assertSame($before - 2, $part->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'spare_part_id' => $part->id, 'type' => StockMovementType::Issue->value,
            'quantity' => -2, 'balance_after' => $before - 2, 'maintenance_request_id' => $mr->id, 'user_id' => $tech->id,
        ]);

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/close")->assertRedirect();
        $this->assertSame(RequestStatus::Closed, $mr->fresh()->status);
    }

    public function test_completion_rejects_quantity_above_stock(): void
    {
        $mr = $this->newRequest();
        $part = SparePart::firstOrFail();
        $tech = $this->user('tech1');
        $mr->update(['status' => RequestStatus::InProgress, 'assigned_technician_id' => $tech->id]);

        $this->actingAs($tech)->post("/requests/{$mr->id}/complete", [
            'fault_type_id' => FaultType::firstOrFail()->id,
            'fault_cause_id' => FaultCause::firstOrFail()->id,
            'resolution_notes' => 'x',
            'parts' => [['spare_part_id' => $part->id, 'quantity' => $part->quantity + 1]],
        ])->assertSessionHasErrors('parts');

        $this->assertSame(RequestStatus::InProgress, $mr->fresh()->status);
        $this->assertSame($part->quantity, $part->fresh()->quantity);
        $this->assertDatabaseMissing('stock_movements', ['spare_part_id' => $part->id]);
    }

    public function test_purchase_request_approval_and_goods_receipt_add_stock(): void
    {
        $part = SparePart::firstOrFail();
        $before = $part->quantity;

        $this->actingAs($this->user('tech1'))->post('/purchases', ['items' => [['part_name' => 'X', 'quantity' => 1]]])->assertForbidden();

        $this->actingAs($this->user('coord'))->post('/purchases', [
            'reason' => 'تزويد المخزون',
            'items' => [
                ['spare_part_id' => $part->id, 'quantity' => 5, 'estimated_unit_price' => '40'],
                ['part_name' => 'كيبل كهرباء 2.5 مم', 'unit' => 'متر', 'quantity' => 100, 'estimated_unit_price' => '1.5'],
            ],
        ])->assertRedirect();
        $pr = PurchaseRequest::latest('id')->firstOrFail();
        $this->assertSame(PurchaseRequestStatus::PendingApproval, $pr->status);
        $this->assertCount(2, $pr->items);

        $this->post("/purchases/{$pr->id}/approve")->assertForbidden();
        $this->get("/purchases/{$pr->id}/receive")->assertRedirect();

        $this->actingAs($this->user('admin'))->post("/purchases/{$pr->id}/approve", ['note' => 'موافق'])->assertRedirect();
        $this->assertSame(PurchaseRequestStatus::Approved, $pr->fresh()->status);

        $this->actingAs($this->user('coord'))->get("/purchases/{$pr->id}/receive")->assertOk();
        $this->post("/purchases/{$pr->id}/receive", [
            'supplier' => 'شركة التوريد',
            'invoice_number' => 'INV-1',
            'invoice_date' => now()->toDateString(),
            'items' => [
                ['spare_part_id' => $part->id, 'part_name' => $part->name, 'quantity' => 5, 'unit_price' => '40'],
                ['part_name' => 'كيبل كهرباء 2.5 مم', 'part_number' => 'CB-25', 'unit' => 'متر', 'quantity' => 100, 'unit_price' => '1.5'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(PurchaseRequestStatus::Received, $pr->fresh()->status);
        $this->assertSame($before + 5, $part->fresh()->quantity);
        $cable = SparePart::where('name', 'كيبل كهرباء 2.5 مم')->firstOrFail();
        $this->assertSame(100, $cable->quantity);
        $this->assertSame('CB-25', $cable->part_number);
        $this->assertDatabaseHas('stock_movements', ['spare_part_id' => $cable->id, 'type' => StockMovementType::Receipt->value, 'quantity' => 100, 'balance_after' => 100]);

        $receipt = $pr->receipts()->firstOrFail();
        $this->get("/receipts/{$receipt->id}")->assertOk()->assertSee('INV-1');
        $this->get('/parts/movements?spare_part_id='.$cable->id)->assertOk()->assertSee($receipt->number);
    }

    public function test_equipment_warranty_is_optional_but_end_date_required_when_enabled(): void
    {
        $admin = $this->user('admin');
        $base = [
            'name' => 'Ice Machine', 'category' => EquipmentCategory::Refrigeration->value,
            'department_id' => Department::firstOrFail()->id, 'status' => 'Working',
        ];

        $this->actingAs($admin)->post('/equipment', [...$base, 'code' => 'EQ-ICE-001'])->assertSessionHasNoErrors();
        $this->assertNull(Equipment::where('code', 'EQ-ICE-001')->value('warranty_end'));

        $this->post('/equipment', [...$base, 'code' => 'EQ-ICE-002', 'has_warranty' => '1'])->assertSessionHasErrors('warranty_end');

        $this->post('/equipment', [...$base, 'code' => 'EQ-ICE-003', 'has_warranty' => '1', 'warranty_end' => '2028-01-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2028-01-01', Equipment::where('code', 'EQ-ICE-003')->firstOrFail()->warranty_end->toDateString());
    }

    public function test_department_in_use_cannot_be_deleted(): void
    {
        $kitchen = Department::where('name_en', 'Kitchen')->firstOrFail();
        $this->actingAs($this->user('admin'))->delete("/departments/{$kitchen->id}")->assertRedirect('/departments');
        $this->assertModelExists($kitchen);

        $empty = Department::create(['name_en' => 'Garden', 'name_ar' => 'الحديقة']);
        $this->delete("/departments/{$empty->id}")->assertRedirect('/departments');
        $this->assertModelMissing($empty);
    }

    public function test_admin_creates_technician_with_specialty(): void
    {
        $this->actingAs($this->user('admin'))->post('/users', [
            'full_name' => 'فني جديد', 'username' => 'tech9', 'role' => Role::Technician->value,
            'specialty' => EquipmentCategory::KitchenEquipment->value, 'password' => 'Tech9!pass', 'is_active' => '1',
        ])->assertRedirect('/users');

        $user = $this->user('tech9');
        $this->assertSame(Role::Technician, $user->role);
        $this->assertSame(EquipmentCategory::KitchenEquipment, $user->specialty);

        $this->post('/logout');
        $this->post('/login', ['username' => 'tech9', 'password' => 'Tech9!pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_reports_and_dashboard_render(): void
    {
        $this->newRequest();
        $this->actingAs($this->user('admin'))->get('/')->assertOk();
        $this->get('/reports')->assertOk();
        $this->get('/dashboard/stats')->assertOk()->assertJsonStructure(['open']);
    }

    private function newRequest(): MaintenanceRequest
    {
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $this->actingAs($this->user('employee'))->post('/r/EQ-FRZ-001', ['description' => 'تسريب ماء']);
        $this->post('/logout');

        return MaintenanceRequest::where('equipment_id', $equipment->id)->latest('id')->firstOrFail();
    }
}
