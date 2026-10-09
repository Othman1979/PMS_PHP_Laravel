<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    public function test_login_returns_role_and_name(): void
    {
        $this->postJson('/api/login', ['username' => 'tech1', 'password' => '1234'])
            ->assertOk()
            ->assertJsonPath('role', 'Technician')
            ->assertJsonPath('username', 'tech1')
            ->assertJsonStructure(['id', 'token']);

        $this->postJson('/api/login', ['username' => 'employee', 'password' => '1234'])
            ->assertOk()
            ->assertJsonPath('role', 'Employee');

        $this->postJson('/api/login', ['username' => 'coord', 'password' => '1234'])
            ->assertOk()
            ->assertJsonPath('role', 'Coordinator');
    }

    public function test_token_authenticates_api_requests(): void
    {
        $token = $this->postJson('/api/login', ['username' => 'tech1', 'password' => '1234'])
            ->assertOk()->json('token');

        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/tasks')->assertOk()->assertJsonStructure(['tasks', 'done']);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/login', ['username' => 'tech1', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        $this->postJson('/api/login', ['username' => 'nobody', 'password' => '1234'])
            ->assertUnprocessable();
    }

    public function test_login_rejects_inactive_user(): void
    {
        User::where('username', 'tech1')->update(['is_active' => false]);

        $this->postJson('/api/login', ['username' => 'tech1', 'password' => '1234'])
            ->assertUnprocessable();
    }
}
