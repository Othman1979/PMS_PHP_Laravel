<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Events\RequestChanged;
use App\Jobs\SendWebPushNotification;
use App\Models\Department;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\PushSubscription;
use App\Models\SparePart;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Support\Localized;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
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
        $this->actingAs($this->user('employee'))->withUnencryptedCookie('cmms_locale', 'ar')->get('/parts')
            ->assertForbidden()->assertSee('غير مصرّح');
        $this->actingAs($this->user('employee'))->withUnencryptedCookie('cmms_locale', 'en')->get('/parts')
            ->assertForbidden()->assertSee('Access denied');
    }

    private function subscribe(User $user, string $locale): void
    {
        $user->forceFill(['locale' => $locale])->save();
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'https://push.example/'.$user->username,
            'endpoint_hash' => hash('sha256', $user->username), 'p256dh' => 'k', 'auth' => 'a',
        ]);
    }

    public function test_language_switch_is_remembered_on_the_user(): void
    {
        $this->actingAs($this->user('coord'))->post('/language', ['locale' => 'en', 'return' => '/'])->assertRedirect('/');

        $this->assertSame('en', $this->user('coord')->locale);
    }

    public function test_push_notifications_are_written_in_each_recipients_language(): void
    {
        config(['cmms.vapid.public_key' => 'pub', 'cmms.vapid.private_key' => 'priv']);
        Bus::fake();
        $this->subscribe($this->user('admin'), 'ar');
        $this->subscribe($this->user('coord'), 'en');

        $this->newRequest();

        $titles = collect(Bus::dispatchedAfterResponse(SendWebPushNotification::class))
            ->map(fn (SendWebPushNotification $job) => json_decode($job->payload, true)['title'])
            ->all();

        $this->assertCount(2, $titles);
        $this->assertTrue(collect($titles)->contains(fn (string $t) => str_contains($t, 'طلب صيانة جديد')));
        $this->assertTrue(collect($titles)->contains(fn (string $t) => str_contains($t, 'New maintenance request')));
    }

    public function test_lifecycle_changes_notify_technician_requester_and_staff(): void
    {
        config(['cmms.vapid.public_key' => 'pub', 'cmms.vapid.private_key' => 'priv']);
        Bus::fake();
        foreach (['coord', 'tech1', 'employee', 'kitchen'] as $username) {
            $this->subscribe($this->user($username), 'en');
        }
        $byTitle = fn (string $needle) => collect(Bus::dispatchedAfterResponse(SendWebPushNotification::class))
            ->map(fn (SendWebPushNotification $job) => json_decode($job->payload, true))
            ->filter(fn (array $p) => str_contains($p['title'], $needle))
            ->flatMap(fn (array $p, int $i) => Bus::dispatchedAfterResponse(SendWebPushNotification::class)[$i]->subscriptionIds)
            ->all();
        $subscriptionOf = fn (string $username) => PushSubscription::where('user_id', $this->user($username)->id)->value('id');

        $mr = $this->newRequest();
        $this->runToCompletion($mr, '0', []);

        $this->assertContains($subscriptionOf('tech1'), $byTitle('Request assigned to you'));
        $this->assertContains($subscriptionOf('employee'), $byTitle('A technician was assigned'));
        $this->assertContains($subscriptionOf('employee'), $byTitle('please confirm'));
        $this->assertContains($subscriptionOf('kitchen'), $byTitle('please confirm'));
        $this->assertContains($subscriptionOf('coord'), $byTitle('Work completed on'));

        $this->actingAs($this->user('employee'))->post("/requests/{$mr->id}/confirm", ['resolved' => '0'])->assertRedirect();
        $this->assertContains($subscriptionOf('tech1'), $byTitle('not resolved'));
        $this->assertContains($subscriptionOf('coord'), $byTitle('not resolved'));

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/cancel")->assertRedirect();
        $this->assertContains($subscriptionOf('tech1'), $byTitle('Request cancelled'));
        $this->assertContains($subscriptionOf('employee'), $byTitle('Request cancelled'));
    }

    public function test_in_app_notifications_are_stored_listed_and_marked_read(): void
    {
        $this->user('coord')->forceFill(['locale' => 'en'])->save();
        $mr = $this->newRequest();

        $json = $this->actingAs($this->user('coord'))->getJson('/notifications')->assertOk()->json();
        $this->assertSame(1, $json['unread']);
        $this->assertStringContainsString('New maintenance request', $json['items'][0]['title']);
        $this->assertSame(route('requests.show', $mr, false), $json['items'][0]['url']);

        $this->get('/notifications/'.$json['items'][0]['id'].'/open')->assertRedirect(route('requests.show', $mr, false));
        $this->assertSame(0, $this->getJson('/notifications')->json('unread'));

        $this->actingAs($this->user('employee'))->get('/notifications/'.$json['items'][0]['id'].'/open')->assertForbidden();
    }

    public function test_request_change_event_targets_staff_department_technician_requester_and_request_channels(): void
    {
        Event::fake([RequestChanged::class]);
        $mr = $this->newRequest();
        $tech = $this->user('tech1');
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $tech->id])->assertRedirect();

        Event::assertDispatched(RequestChanged::class, fn (RequestChanged $e) => $e->isNew && $e->payload['id'] === $mr->id && $e->payload['createdBy'] === $mr->createdBy->full_name);
        Event::assertDispatched(RequestChanged::class, function (RequestChanged $e) use ($mr, $tech) {
            $channels = collect($e->broadcastOn())->map(fn ($c) => $c->name)->all();

            return ! $e->isNew
                && $e->payload['status'] === RequestStatus::Assigned->value
                && $e->payload['statusLabel'] === Localized::all(fn () => RequestStatus::Assigned->label())
                && $channels === ['private-staff', 'private-department.'.$mr->department_id, 'private-request.'.$mr->id,
                    'private-technician.'.$tech->id, 'private-user.'.$mr->created_by_id];
        });
    }

    public function test_private_channel_authorization_follows_request_visibility(): void
    {
        $mr = $this->newRequest();
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $auth = fn (string $username, string $channel) => $this->actingAs($this->user($username))
            ->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1']);

        $auth('coord', 'private-staff')->assertOk();
        $auth('employee', 'private-staff')->assertForbidden();
        $auth('kitchen', 'private-department.'.$mr->department_id)->assertOk();
        $auth('tech1', 'private-department.'.$mr->department_id)->assertForbidden();
        $auth('employee', 'private-request.'.$mr->id)->assertOk();
        $auth('tech2', 'private-request.'.$mr->id)->assertForbidden();
        $mr->update(['assigned_technician_id' => $this->user('tech2')->id, 'status' => RequestStatus::Assigned]);
        $auth('tech2', 'private-request.'.$mr->id)->assertOk();
        $auth('tech1', 'private-user.'.$this->user('employee')->id)->assertForbidden();
    }
}
