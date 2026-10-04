<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function employee(): User
    {
        return User::where('username', 'employee')->firstOrFail();
    }

    public function test_employee_lands_on_quick_request_screen(): void
    {
        $this->actingAs($this->employee())->get('/')->assertRedirect(route('quick.find'));
        $this->actingAs($this->employee())->get('/requests')->assertRedirect(route('quick.find'));
        $this->actingAs($this->employee())->get('/requests/create')->assertRedirect(route('quick.find'));
    }

    public function test_employee_cannot_browse_equipment_or_admin_screens(): void
    {
        $this->actingAs($this->employee())->get('/equipment')->assertForbidden();
        $this->actingAs($this->employee())->get('/parts')->assertForbidden();
        $this->actingAs($this->employee())->get('/reports')->assertForbidden();
    }

    public function test_quick_screen_has_no_menus_for_employee(): void
    {
        $response = $this->actingAs($this->employee())->get('/r/EQ-FRZ-001')->assertOk();
        $response->assertDontSee(route('requests.index'));
        $response->assertDontSee('id="navPane"', false);
        $response->assertSee(route('logout'));
    }

    public function test_department_manager_keeps_menus(): void
    {
        $manager = User::where('username', 'kitchen')->firstOrFail();
        $this->actingAs($manager)->get('/')->assertOk()->assertSee('id="navPane"', false);
        $this->actingAs($manager)->get('/r/EQ-FRZ-001')->assertOk()->assertSee(route('requests.index'));
    }
}
