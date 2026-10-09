<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Events\RequestChanged;
use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UxImprovementsTest extends TestCase
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

    private function newRequest(): MaintenanceRequest
    {
        $this->actingAs($this->user('employee'))->post('/r/EQ-POS-001', ['description' => 'لا يعمل'])->assertRedirect();

        return MaintenanceRequest::latest('id')->firstOrFail();
    }

    public function test_new_request_gets_due_date_from_priority_sla(): void
    {
        Priority::query()->where('is_default', true)->update(['sla_hours' => 48]);
        $this->travelTo(now()->startOfMinute());

        $mr = $this->newRequest();

        $this->assertNotNull($mr->due_at);
        $this->assertTrue($mr->due_at->equalTo(now()->addHours(48)));
        $this->assertFalse($mr->isOverdue());
    }

    public function test_accept_and_start_moves_assigned_request_to_in_progress_in_one_action(): void
    {
        $mr = $this->newRequest();
        $tech = $this->user('tech1');
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $tech->id])->assertRedirect();

        $this->actingAs($tech)->post("/requests/{$mr->id}/accept-start", ['note' => 'في الطريق'])->assertRedirect();

        $mr->refresh();
        $this->assertSame(RequestStatus::InProgress, $mr->status);
        $this->assertNotNull($mr->accepted_at);
        $this->assertNotNull($mr->started_at);
        $this->assertSame(
            ['New', 'Assigned', 'Accepted', 'InProgress'],
            $mr->timeline()->orderBy('id')->pluck('status_to')->map(fn (RequestStatus $s) => $s->value)->all(),
        );
    }

    public function test_requester_comment_lands_on_timeline_and_broadcasts_without_status_change(): void
    {
        Event::fake([RequestChanged::class]);
        $mr = $this->newRequest();

        $this->actingAs($this->user('employee'))->post("/requests/{$mr->id}/comment", ['note' => 'هل من جديد؟'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::New, $mr->fresh()->status);
        $this->assertDatabaseHas('request_timelines', ['request_id' => $mr->id, 'status_from' => 'New', 'status_to' => 'New', 'note' => 'هل من جديد؟']);
        Event::assertDispatched(RequestChanged::class, fn (RequestChanged $e) => $e->payload['id'] === $mr->id && $e->payload['fromStatus'] === null);
    }

    public function test_comment_requires_visibility_and_text(): void
    {
        $mr = $this->newRequest();

        $outsider = User::factory()->create(['role' => Role::Employee, 'department_id' => Department::where('name_en', 'Hall')->value('id')]);

        $this->actingAs($outsider)->post("/requests/{$mr->id}/comment", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/comment", ['note' => ''])->assertSessionHasErrors('note');
    }

    public function test_transition_broadcast_carries_previous_status(): void
    {
        Event::fake([RequestChanged::class]);
        $mr = $this->newRequest();

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/review")->assertRedirect();

        Event::assertDispatched(RequestChanged::class, fn (RequestChanged $e) => $e->payload['fromStatus'] === 'New' && $e->payload['status'] === 'UnderReview');
    }

    public function test_overdue_requests_are_escalated_once_and_flagged(): void
    {
        Priority::query()->where('is_default', true)->update(['sla_hours' => 1]);
        $mr = $this->newRequest();
        $this->travel(2)->hours();

        $this->assertTrue($mr->fresh()->isOverdue());
        $workflow = app(RequestWorkflow::class);
        $this->assertSame(1, $workflow->escalateOverdue());
        $this->assertSame(0, $workflow->escalateOverdue());
        $this->assertNotNull($mr->fresh()->escalated_at);

        $this->actingAs($this->user('coord'))->get('/requests?overdue=1')->assertOk()->assertSee($mr->request_number);
    }

    public function test_requests_index_shows_status_counters_and_remembers_filters(): void
    {
        $mr = $this->newRequest();
        $coord = $this->user('coord');

        $this->actingAs($coord)->get('/requests?status=New')->assertOk()->assertSee('data-count="New"', false)->assertSee($mr->request_number);
        $this->get('/requests')->assertRedirect(route('requests.index', ['status' => 'New']));
        $this->get('/requests?all=1')->assertOk();
        $this->get('/requests?reset=1')->assertRedirect(route('requests.index'));
        $this->get('/requests')->assertOk();

        $this->get('/requests?status=Closed')->assertOk()->assertDontSee($mr->request_number);
        $this->get('/requests?q='.$mr->request_number)->assertOk()->assertSee($mr->request_number);
    }

    public function test_auto_assign_setting_assigns_new_requests_to_least_busy_matching_technician(): void
    {
        Setting::set(Setting::AUTO_ASSIGN, '1');

        $mr = $this->newRequest();

        $this->assertSame(RequestStatus::Assigned, $mr->status);
        $this->assertNotNull($mr->assigned_technician_id);
        $this->assertSame(RequestStatus::Assigned, $mr->fresh()->status);
    }

    public function test_quick_screen_lists_employee_open_requests_and_full_history(): void
    {
        $mr = $this->newRequest();
        $employee = $this->user('employee');

        $this->actingAs($employee)->get('/r')->assertOk()->assertSee($mr->request_number)->assertSee(route('quick.mine'), false);
        $this->get('/r/mine')->assertOk()->assertSee($mr->request_number);
        $this->actingAs(User::factory()->create(['role' => Role::Employee]))->get('/r/mine')->assertOk()->assertDontSee($mr->request_number);
    }

    public function test_technician_complete_form_lists_only_in_stock_parts_in_searchable_list(): void
    {
        $mr = $this->newRequest();
        $tech = $this->user('tech1');
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $tech->id])->assertRedirect();
        $this->actingAs($tech)->post("/requests/{$mr->id}/accept-start")->assertRedirect();
        $out = SparePart::query()->create(['name' => 'Empty part', 'quantity' => 0, 'unit_cost' => 1, 'unit' => 'pc', 'minimum_quantity' => 0]);

        $this->get("/requests/{$mr->id}")->assertOk()
            ->assertSee('id="partsList"', false)
            ->assertSee('data-draft-key="cmms.draft.complete.'.$mr->id.'"', false)
            ->assertDontSee($out->name);
    }
}
