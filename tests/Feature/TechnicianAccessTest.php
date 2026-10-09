<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
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

    private function newRequest(string $code = 'EQ-FRZ-001'): MaintenanceRequest
    {
        $this->actingAs($this->user('employee'))->post("/r/{$code}", ['description' => 'لا يعمل'])->assertRedirect();

        return MaintenanceRequest::latest('id')->firstOrFail();
    }

    private function assignedRequest(string $technician = 'tech1'): MaintenanceRequest
    {
        $mr = $this->newRequest();
        $this->actingAs($this->user('coord'))
            ->post("/requests/{$mr->id}/assign", ['technician_id' => $this->user($technician)->id])
            ->assertRedirect();

        return $mr->refresh();
    }

    public function test_technician_lands_on_my_tasks_screen(): void
    {
        $tech = $this->user('tech1');

        $this->actingAs($tech)->get('/')->assertRedirect(route('requests.mine'));
        $this->actingAs($tech)->get('/requests')->assertRedirect(route('requests.mine'));
        $this->actingAs($tech)->get('/requests/create')->assertRedirect(route('requests.mine'));
        $this->actingAs($tech)->post('/requests', ['description' => 'x'])->assertForbidden();
    }

    public function test_technician_cannot_browse_equipment_parts_or_reports(): void
    {
        $tech = $this->user('tech1');

        $this->actingAs($tech)->get('/equipment')->assertForbidden();
        $this->actingAs($tech)->get('/parts')->assertForbidden();
        $this->actingAs($tech)->get('/reports')->assertForbidden();
        $this->actingAs($tech)->get('/dashboard/stats')->assertForbidden();
    }

    public function test_my_tasks_screen_has_no_menus_and_lists_only_assigned_tasks(): void
    {
        $assigned = $this->assignedRequest('tech1');
        $other = $this->assignedRequest('tech2');
        $untriaged = $this->newRequest('EQ-POS-001');

        $response = $this->actingAs($this->user('tech1'))->get('/requests/mine')->assertOk();
        $response->assertDontSee('id="navPane"', false);
        $response->assertDontSee('href="'.route('requests.index').'"', false);
        $response->assertDontSee(route('equipment.index'));
        $response->assertSee(route('logout'));
        $response->assertSee($assigned->request_number);
        $response->assertDontSee($other->request_number);
        $response->assertDontSee($untriaged->request_number);
    }

    public function test_technician_opens_only_requests_assigned_to_them(): void
    {
        $assigned = $this->assignedRequest('tech1');
        $untriaged = $this->newRequest('EQ-POS-001');

        $this->actingAs($this->user('tech1'))->get("/requests/{$assigned->id}")
            ->assertOk()
            ->assertSee(route('requests.accept', $assigned))
            ->assertDontSee(route('equipment.show', $assigned->equipment));
        $this->actingAs($this->user('tech1'))->get("/requests/{$untriaged->id}")->assertForbidden();
        $this->actingAs($this->user('tech2'))->get("/requests/{$assigned->id}")->assertForbidden();
    }

    public function test_coordinator_keeps_menus_and_equipment_links(): void
    {
        $mr = $this->assignedRequest('tech1');

        $this->actingAs($this->user('coord'))->get('/')->assertOk()->assertSee('id="navPane"', false);
        $this->actingAs($this->user('coord'))->get("/requests/{$mr->id}")
            ->assertOk()
            ->assertSee(route('equipment.show', $mr->equipment));
    }
}
