<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianAccessTest extends TestCase
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

    private function technician(): User
    {
        return $this->user('tech1');
    }

    public function test_technician_lands_on_my_tasks_screen(): void
    {
        $this->actingAs($this->technician())->get('/')->assertRedirect(route('requests.mine'));
        $this->actingAs($this->technician())->get('/requests')->assertRedirect(route('requests.mine'));
        $this->actingAs($this->technician())->get('/requests/create')->assertRedirect(route('requests.mine'));
    }

    public function test_technician_cannot_browse_equipment_or_admin_screens(): void
    {
        $this->actingAs($this->technician())->get('/equipment')->assertForbidden();
        $this->actingAs($this->technician())->get('/parts')->assertForbidden();
        $this->actingAs($this->technician())->get('/reports')->assertForbidden();
        $this->actingAs($this->technician())->get('/pm')->assertForbidden();
    }

    public function test_tasks_screen_has_no_menus_for_technician(): void
    {
        $response = $this->actingAs($this->technician())->get('/requests/mine')->assertOk();
        $response->assertDontSee('id="navPane"', false);
        $response->assertSee(route('logout'));
    }

    public function test_technician_is_redirected_from_quick_request_screens(): void
    {
        $this->actingAs($this->technician())->get('/r')->assertRedirect(route('requests.mine'));
        $this->actingAs($this->technician())->get('/r/mine')->assertRedirect(route('requests.mine'));
        $this->actingAs($this->technician())->get('/r/EQ-FRZ-001')->assertRedirect(route('requests.mine'));
        $this->actingAs($this->technician())->post('/r/EQ-FRZ-001', ['description' => 'x'])->assertForbidden();
    }

    public function test_technician_cannot_create_requests(): void
    {
        $this->actingAs($this->technician())->post('/requests', [
            'department_id' => Department::firstOrFail()->id,
            'description' => 'x',
            'priority_id' => Priority::firstOrFail()->id,
        ])->assertForbidden();
        $this->assertSame(0, MaintenanceRequest::count());
    }

    public function test_technician_sees_only_assigned_requests(): void
    {
        $this->actingAs($this->user('employee'))->post('/r/EQ-FRZ-001', ['description' => 'leak'])->assertRedirect();
        $this->actingAs($this->user('employee'))->post('/r/EQ-POS-001', ['description' => 'broken'])->assertRedirect();
        $assigned = MaintenanceRequest::orderBy('id')->firstOrFail();
        $unassigned = MaintenanceRequest::orderByDesc('id')->firstOrFail();

        $tech = $this->technician();
        $this->actingAs($this->user('coord'))->post("/requests/{$assigned->id}/assign", ['technician_id' => $tech->id])->assertRedirect();

        $this->actingAs($tech)->get("/requests/{$unassigned->id}")->assertForbidden();
        $this->actingAs($tech)->post("/requests/{$unassigned->id}/comment", ['note' => 'x'])->assertForbidden();
        $this->actingAs($tech)->post("/requests/{$unassigned->id}/accept")->assertForbidden();

        $this->actingAs($tech)->get("/requests/{$assigned->id}")->assertOk()
            ->assertSee($assigned->request_number)
            ->assertSee(__('AcceptAndStart'));

        $this->get('/requests/mine')->assertOk()
            ->assertSee($assigned->request_number)
            ->assertDontSee($unassigned->request_number);
    }

    public function test_coordinator_keeps_menus(): void
    {
        $this->actingAs($this->user('coord'))->get('/')->assertOk()->assertSee('id="navPane"', false);
        $this->actingAs($this->user('coord'))->get('/requests')->assertOk();
        $this->actingAs($this->user('coord'))->get('/r/EQ-FRZ-001')->assertOk();
    }
}
