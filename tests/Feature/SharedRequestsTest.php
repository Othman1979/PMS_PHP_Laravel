<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\TechnicianTrade;
use App\Models\Department;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Reports\RequestsReport;
use App\Reports\TechniciansReport;
use App\Services\RequestWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedRequestsTest extends TestCase
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

    private function token(string $username): void
    {
        $this->user($username)->forceFill(['api_token' => hash('sha256', $username)])->save();
        $this->withToken($username);
    }

    private function sharedRequest(): MaintenanceRequest
    {
        $employee = $this->user('employee');
        $mr = app(RequestWorkflow::class)->create($employee, null, $employee->department_id,
            'Ceiling light failure', Priority::default(), location: 'Above washbasin');
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", [
            'technician_id' => $this->user('tech1')->id,
            'technician_ids' => [$this->user('tech2')->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $mr->fresh();
    }

    public function test_participant_can_confirm_and_comment_but_cannot_change_shared_status(): void
    {
        $mr = $this->sharedRequest();
        $this->token('tech2');
        $this->getJson('/api/tasks')->assertJsonPath('tasks.0.id', $mr->id);
        $this->postJson("/api/requests/{$mr->id}/participate")->assertOk()->assertJsonPath('request.status', 'Assigned');
        $before = $mr->timeline()->count();
        $this->postJson("/api/requests/{$mr->id}/participate")->assertOk();
        $this->assertSame($before, $mr->timeline()->count());
        $this->postJson("/api/requests/{$mr->id}/comments", ['note' => 'Wiring checked'])->assertOk()
            ->assertJsonPath('request.timeline.0.by', $this->user('tech2')->full_name);
        foreach (['accept', 'accept-start', 'start', 'wait-parts', 'resume'] as $action) {
            $this->postJson("/api/requests/{$mr->id}/actions/{$action}")->assertForbidden();
        }
        $this->postJson("/api/requests/{$mr->id}/complete", [])->assertForbidden();
        $this->assertSame(RequestStatus::Assigned, $mr->fresh()->status);
        $this->assertSame($this->user('tech1')->id, $mr->assigned_technician_id);
    }

    public function test_lead_must_confirm_actual_workers_and_cannot_credit_unassigned_users(): void
    {
        $mr = $this->sharedRequest();
        $this->token('tech1');
        $this->postJson("/api/requests/{$mr->id}/actions/accept-start")->assertOk();
        $data = [
            'resolution_notes' => 'Replaced the light and checked wiring',
            'fault_type_id' => FaultType::active()->firstOrFail()->id,
            'fault_cause_id' => FaultCause::active()->firstOrFail()->id,
            'cost_labor' => 25,
        ];
        $this->postJson("/api/requests/{$mr->id}/complete", $data)->assertUnprocessable()
            ->assertJsonValidationErrors('worked_technician_ids');
        $this->postJson("/api/requests/{$mr->id}/complete", $data + ['worked_technician_ids' => [$this->user('employee')->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('worked_technician_ids');
        $this->assertSame(0.0, (float) $mr->fresh()->cost_labor);
        $this->postJson("/api/requests/{$mr->id}/complete", $data + ['worked_technician_ids' => [$this->user('tech2')->id]])
            ->assertOk()->assertJsonPath('request.status', 'Completed');
        $this->assertSame(1, $mr->teamMembers()->whereNotNull('worked_at')->count());
        $this->assertSame($this->user('tech2')->id, $mr->teamMembers()->whereNotNull('worked_at')->firstOrFail()->user_id);
        $this->assertSame(25.0, (float) $mr->fresh()->cost_labor);
    }

    public function test_general_fault_form_and_api_require_location_without_equipment(): void
    {
        $employee = $this->user('employee');
        $this->actingAs($employee)->get('/r/general')->assertOk()->assertSee('name="location"', false);
        $this->token('employee');
        $data = ['department_id' => $employee->department_id, 'description' => 'Department power outage'];
        $this->postJson('/api/requests', $data)->assertUnprocessable()->assertJsonValidationErrors('location');
        $id = $this->postJson('/api/requests', $data + ['location' => 'Preparation area'])
            ->assertCreated()->assertJsonPath('request.equipmentId', null)->assertJsonPath('request.location', 'Preparation area')->json('request.id');
        $this->assertSame($employee->id, MaintenanceRequest::findOrFail($id)->created_by_id);
    }

    public function test_manager_can_transfer_lead_without_losing_team_or_history(): void
    {
        $mr = $this->sharedRequest();
        $this->actingAs($this->user('tech2'))->post("/requests/{$mr->id}/team", ['lead_id' => $this->user('tech2')->id])->assertForbidden();
        $before = $mr->timeline()->count();
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/team", ['lead_id' => $this->user('tech2')->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->user('tech2')->id, $mr->fresh()->assigned_technician_id);
        $this->assertSame(2, $mr->teamMembers()->count());
        $this->assertSame($before + 1, $mr->timeline()->count());
        $this->assertStringContainsString($this->user('tech1')->full_name, $mr->timeline()->latest('id')->firstOrFail()->note);
        $this->actingAs($this->user('tech1'))->post("/requests/{$mr->id}/accept-start")->assertForbidden();
        $this->actingAs($this->user('tech2'))->post("/requests/{$mr->id}/accept-start")->assertRedirect();
        $this->assertSame(RequestStatus::InProgress, $mr->fresh()->status);
        $this->actingAs($this->user('tech1'))->post("/requests/{$mr->id}/note", ['note' => 'Mechanical inspection done'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get("/requests/{$mr->id}")->assertOk()->assertSee('Mechanical inspection done');
    }

    public function test_invalid_team_updates_roll_back_and_general_faults_cannot_spoof_departments(): void
    {
        $mr = $this->sharedRequest();
        $this->post("/requests/{$mr->id}/team", ['technician_ids' => [$this->user('employee')->id]])
            ->assertSessionHasErrors('technician_ids');
        $this->assertSame(2, $mr->teamMembers()->count());
        $this->token('employee');
        $other = Department::where('id', '!=', $this->user('employee')->department_id)->firstOrFail();
        $this->postJson('/api/requests', ['department_id' => $other->id, 'location' => 'Hall', 'description' => 'Broken light'])
            ->assertForbidden();
        $this->actingAs($this->user('employee'))->post('/r/general', [
            'department_id' => $this->user('employee')->department_id, 'location' => '<script>alert(1)</script>', 'description' => 'Light failure',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $general = MaintenanceRequest::latest('id')->firstOrFail();
        $this->assertNull($general->equipment_id);
        $this->get("/requests/{$general->id}")->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_reports_count_one_request_and_credit_only_actual_contributors(): void
    {
        $mr = $this->sharedRequest();
        $mr->update(['status' => RequestStatus::Completed, 'completed_at' => now(), 'cost_labor' => 25]);
        $mr->teamMembers()->where('user_id', $this->user('tech2')->id)->update(['worked_at' => now()]);
        $filters = ReportFilters::fromRequest(request()->merge(['technician_id' => $this->user('tech2')->id]), ['technician_id'], null);
        $requestsReport = new RequestsReport;
        $rows = $requestsReport->rows($filters);
        $this->assertCount(1, $rows);
        $this->assertSame(25.0, $requestsReport->totals($rows)['cost']);
        $this->assertStringContainsString($this->user('tech2')->full_name, $rows[0]['workers']);
        $this->assertStringNotContainsString($this->user('tech1')->full_name, $rows[0]['workers']);
        $technicians = (new TechniciansReport)->rows($filters);
        $this->assertCount(1, $technicians);
        $this->assertSame(1, $technicians[0]['participant']);
        $this->assertSame(0, $technicians[0]['lead']);
        $this->assertSame(0.0, $technicians[0]['cost']);
        $this->get('/reports/technicians/export/xlsx')->assertOk();
        $this->get('/reports/requests/export/pdf')->assertOk();
    }

    public function test_admin_can_set_trade_and_team_api_displays_it_with_existing_specialty(): void
    {
        foreach (TechnicianTrade::cases() as $trade) {
            $this->actingAs($this->user('admin'))->post('/users', [
                'username' => 'qa_'.$trade->value, 'full_name' => 'Technician '.$trade->value,
                'role' => 'Technician', 'password' => 'TradeTest123', 'is_active' => '1',
                'trade' => $trade->value, 'specialty' => 'Refrigeration',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($trade, User::where('username', 'qa_'.$trade->value)->firstOrFail()->trade);
        }
        $mr = $this->sharedRequest();
        $tech = $this->user('tech2');
        $tech->update(['trade' => TechnicianTrade::Mechanical]);
        $this->token('tech2');
        $team = $this->getJson("/api/requests/{$mr->id}")->assertOk()->json('request.team');
        $this->assertStringContainsString('Mechanical', collect($team)->firstWhere('id', $tech->id)['specialty']['en']);
    }

    public function test_upgrade_backfills_legacy_completed_lead_without_duplicate_memberships(): void
    {
        $mr = $this->sharedRequest();
        $mr->update(['status' => RequestStatus::Completed, 'completed_at' => now()]);
        $migration = require database_path('migrations/2026_10_11_160201_add_request_teams_and_general_location.php');
        $migration->down();
        $migration->up();
        $this->assertSame(1, $mr->teamMembers()->count());
        $this->assertSame([$this->user('tech1')->id], $mr->fresh()->actualTechnicianIds());
        $this->assertTrue($mr->teamMembers()->firstOrFail()->worked_as_lead);
    }
}
