<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\SparePart;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReviewFixesTest extends TestCase
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

    private function newRequest(string $code = 'EQ-FRZ-001'): MaintenanceRequest
    {
        $this->actingAs($this->user('employee'))->post("/r/{$code}", ['description' => 'لا يعمل'])->assertRedirect();

        return MaintenanceRequest::latest('id')->firstOrFail();
    }

    /** @param  array<int, array{spare_part_id: int, quantity: int}>  $parts */
    private function runToCompletion(MaintenanceRequest $mr, string $labor, array $parts): void
    {
        $tech = $this->user('tech1');
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $tech->id])->assertRedirect();
        $this->actingAs($tech)->post("/requests/{$mr->id}/accept")->assertRedirect();
        $this->post("/requests/{$mr->id}/start")->assertRedirect();
        $this->post("/requests/{$mr->id}/complete", [
            'fault_type_id' => FaultType::firstOrFail()->id,
            'fault_cause_id' => FaultCause::firstOrFail()->id,
            'resolution_notes' => 'تم',
            'cost_labor' => $labor,
            'parts' => $parts,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_recompletion_after_not_resolved_keeps_parts_and_labor_costs(): void
    {
        $mr = $this->newRequest();
        $part = SparePart::firstOrFail();
        $stockBefore = $part->quantity;
        $partCost = 2 * (float) $part->unit_cost;

        $this->runToCompletion($mr, '10', [['spare_part_id' => $part->id, 'quantity' => 2]]);
        $this->actingAs($this->user('employee'))->post("/requests/{$mr->id}/confirm", ['resolved' => '0'])->assertRedirect();
        $this->assertSame(RequestStatus::Reopened, $mr->fresh()->status);

        $this->runToCompletion($mr, '5', []);

        $mr->refresh();
        $this->assertSame(RequestStatus::Completed, $mr->status);
        $this->assertEqualsWithDelta($partCost, (float) $mr->cost_parts, 0.001);
        $this->assertEqualsWithDelta(15.0, (float) $mr->cost_labor, 0.001);
        $this->assertEqualsWithDelta($partCost + 15.0, $mr->totalCost(), 0.001);
        $this->assertSame($stockBefore - 2, $part->fresh()->quantity);
    }

    public function test_dashboard_stats_is_restricted_to_management_roles(): void
    {
        $this->actingAs($this->user('employee'))->getJson('/dashboard/stats')->assertForbidden();
        $this->actingAs($this->user('tech1'))->getJson('/dashboard/stats')->assertForbidden();
        $this->actingAs($this->user('coord'))->getJson('/dashboard/stats')->assertOk()->assertJsonStructure(['open', 'changes', 'now']);
    }

    public function test_department_manager_dashboard_stats_only_count_own_department(): void
    {
        $kitchenRequest = $this->newRequest('EQ-FRZ-001');
        $otherDepartment = Department::where('name_en', '!=', 'Kitchen')->firstOrFail();
        MaintenanceRequest::query()->whereKey($kitchenRequest->id)->update(['department_id' => $otherDepartment->id]);
        $this->newRequest('EQ-FRZ-001');

        $json = $this->actingAs($this->user('kitchen'))
            ->getJson('/dashboard/stats?'.http_build_query(['since' => now()->subHour()->toIso8601String()]))
            ->assertOk()
            ->json();

        $kitchenOpen = MaintenanceRequest::query()
            ->where('department_id', $this->user('kitchen')->department_id)
            ->whereNotIn('status', RequestStatus::closed())
            ->count();

        $this->assertSame($kitchenOpen, $json['open']);
        $this->assertNotContains($kitchenRequest->request_number, array_column($json['changes'], 'requestNumber'));
    }

    public function test_transition_rejects_request_changed_by_someone_else(): void
    {
        $mr = $this->newRequest();
        $stale = MaintenanceRequest::findOrFail($mr->id);
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/cancel")->assertRedirect();

        $this->expectException(ValidationException::class);
        app(RequestWorkflow::class)->transition($stale, RequestStatus::UnderReview, $this->user('coord'));
    }

    public function test_staff_password_must_be_at_least_eight_characters_but_employees_keep_short_pins(): void
    {
        $this->actingAs($this->user('coord'))->put('/password', [
            'current_password' => '1234', 'password' => '5678', 'password_confirmation' => '5678',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->user('employee'))->put('/password', [
            'current_password' => '1234', 'password' => '5678', 'password_confirmation' => '5678',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok', 'PasswordChanged');

        $this->actingAs($this->user('admin'))->post('/users', [
            'full_name' => 'x', 'username' => 'coord2', 'role' => Role::Coordinator->value, 'password' => 'short', 'is_active' => '1',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['username' => 'coord2']);
    }

    public function test_employee_sees_password_changed_message_on_quick_screen(): void
    {
        $this->actingAs($this->user('employee'))->followingRedirects()->put('/password', [
            'current_password' => '1234', 'password' => '5678', 'password_confirmation' => '5678',
        ])->assertOk()->assertSee(__('PasswordChanged'));
    }

    public function test_employee_done_page_links_to_request_tracking(): void
    {
        $mr = $this->newRequest();

        $this->actingAs($this->user('employee'))->get("/r/done/{$mr->id}")
            ->assertOk()
            ->assertSee(route('requests.show', $mr), false);
    }

    public function test_quick_screen_issue_chips_are_separate_from_fault_type_chips(): void
    {
        $this->actingAs($this->user('employee'))->get('/r/EQ-FRZ-001')
            ->assertOk()
            ->assertSee("querySelectorAll('.quick-chip[data-text]')", false);
    }

    public function test_forbidden_page_is_localized(): void
    {
        $this->actingAs($this->user('employee'))->withUnencryptedCookie('pms_locale', 'ar')->get('/parts')
            ->assertForbidden()->assertSee('غير مصرّح');
        $this->actingAs($this->user('employee'))->withUnencryptedCookie('pms_locale', 'en')->get('/parts')
            ->assertForbidden()->assertSee('Access denied');
    }
}
