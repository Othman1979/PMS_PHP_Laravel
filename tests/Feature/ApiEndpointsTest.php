<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function tokenFor(string $username): string
    {
        return $this->postJson('/api/login', ['username' => $username, 'password' => '1234'])->json('token');
    }

    private function assignTo(string $username): MaintenanceRequest
    {
        $this->actingAs(User::where('username', 'employee')->firstOrFail())
            ->post('/r/EQ-FRZ-001', ['description' => 'for tech']);

        $tech = User::where('username', $username)->firstOrFail();
        $request = MaintenanceRequest::latest('id')->firstOrFail();
        $request->forceFill(['assigned_technician_id' => $tech->id, 'status' => RequestStatus::Assigned])->save();

        return $request->fresh();
    }

    public function test_technician_tasks_only_contains_their_assignments(): void
    {
        $assigned = $this->assignTo('tech1');
        $other = MaintenanceRequest::where('id', '!=', $assigned->id)
            ->where('assigned_technician_id', '!=', User::where('username', 'tech1')->value('id'))
            ->whereNotIn('status', RequestStatus::closedValues())->first();

        $tasks = collect($this->withToken($this->tokenFor('tech1'))->getJson('/api/tasks')->assertOk()->json('tasks'));
        $numbers = $tasks->pluck('requestNumber');

        $this->assertTrue($numbers->contains($assigned->request_number));
        if ($other !== null) {
            $this->assertFalse($numbers->contains($other->request_number));
        }
    }

    public function test_my_requests_returns_employees_own_requests(): void
    {
        $this->actingAs(User::where('username', 'employee')->firstOrFail())
            ->post('/r/EQ-FRZ-001', ['description' => 'leak test']);
        $employee = User::where('username', 'employee')->firstOrFail();

        $items = $this->withToken($this->tokenFor('employee'))->getJson('/api/my-requests')
            ->assertOk()->json('items');

        $this->assertNotEmpty($items);
        $this->assertSame($employee->id, MaintenanceRequest::latest('id')->first()->created_by_id);
    }

    public function test_notifications_endpoint_shape(): void
    {
        $response = $this->withToken($this->tokenFor('tech1'))->getJson('/api/notifications')
            ->assertOk();

        $response->assertJsonStructure(['unread', 'items']);
    }

    public function test_broadcasting_auth_with_token(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $tech = User::where('username', 'tech1')->firstOrFail();
        $token = $this->tokenFor('tech1');

        $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-technician.{$tech->id}",
        ])->assertOk()->assertJsonStructure(['auth']);

        $other = User::where('username', 'tech2')->firstOrFail();
        $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-technician.{$other->id}",
        ])->assertForbidden();

        $this->withToken('bogus-token')->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-technician.{$tech->id}",
        ])->assertUnauthorized();
    }
}
