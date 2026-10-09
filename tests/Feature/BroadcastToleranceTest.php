<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BroadcastToleranceTest extends TestCase
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

    public function test_request_actions_succeed_when_reverb_is_unreachable(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k',
            'broadcasting.connections.reverb.secret' => 's',
            'broadcasting.connections.reverb.app_id' => 'a',
            'broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
        ]);
        Log::spy();

        $this->actingAs($this->user('employee'))->post('/r/EQ-FRZ-001', ['description' => 'لا يعمل'])->assertRedirect();
        $mr = MaintenanceRequest::latest('id')->firstOrFail();
        $tech = $this->user('tech1');

        $this->actingAs($this->user('coord'))
            ->post("/requests/{$mr->id}/assign", ['technician_id' => $tech->id])
            ->assertRedirect(route('requests.show', $mr))
            ->assertSessionHasNoErrors();

        $mr->refresh();
        $this->assertSame(RequestStatus::Assigned, $mr->status);
        $this->assertSame($tech->id, $mr->assigned_technician_id);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_starts_with($message, 'Realtime broadcast skipped'))->atLeast()->once();
    }
}
