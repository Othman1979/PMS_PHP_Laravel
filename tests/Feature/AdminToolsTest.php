<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminToolsTest extends TestCase
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
        $this->actingAs($this->user('employee'))->post('/r/EQ-FRZ-001', ['description' => 'لا يعمل'])->assertRedirect();

        return MaintenanceRequest::latest('id')->firstOrFail();
    }

    public function test_admin_can_view_and_save_general_settings(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee(__('AutoAssign'));
        $this->actingAs($admin)->put('/settings', ['auto_assign' => '1', 'backup_keep' => 7])->assertRedirect('/settings');

        $this->assertTrue(Setting::bool(Setting::AUTO_ASSIGN));
        $this->assertFalse(Setting::bool(Setting::ESCALATE_OVERDUE, true));
        $this->assertSame('7', Setting::get(Setting::BACKUP_KEEP));
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings_updated', 'user_id' => $admin->id]);
    }

    public function test_settings_pages_are_admin_only(): void
    {
        $this->actingAs($this->user('coord'))->get('/settings')->assertForbidden();
        $this->actingAs($this->user('coord'))->get('/settings/activity')->assertForbidden();
        $this->actingAs($this->user('tech1'))->post('/settings/backup')->assertForbidden();
    }

    public function test_backup_download_is_a_restorable_sql_dump(): void
    {
        $response = $this->actingAs($this->user('admin'))->post('/settings/backup');

        $response->assertOk()->assertDownload();
        $sql = $response->streamedContent();
        $users = DB::connection()->getQueryGrammar()->wrapTable('users');
        $equipment = DB::connection()->getQueryGrammar()->wrapTable('equipment');
        $this->assertStringContainsString("DROP TABLE IF EXISTS {$users};", $sql);
        $this->assertStringContainsString("CREATE TABLE {$users}", $sql);
        $this->assertStringContainsString("INSERT INTO {$users}", $sql);
        $this->assertStringContainsString("INSERT INTO {$equipment}", $sql);
        $this->assertStringContainsString('EQ-FRZ-001', $sql);
        $this->assertDatabaseHas('activity_logs', ['action' => 'backup_downloaded']);
    }

    public function test_backup_command_writes_file_and_prunes_old_ones(): void
    {
        $dir = storage_path('app/backups');
        if (is_dir($dir)) {
            foreach (glob($dir.'/pms-backup-*.sql') ?: [] as $f) {
                unlink($f);
            }
        }
        Setting::set(Setting::BACKUP_KEEP, '1');
        @mkdir($dir, 0775, true);
        touch($dir.'/pms-backup-20200101-0000.sql', strtotime('2020-01-01'));

        $this->artisan('pms:backup')->assertSuccessful();

        $files = glob($dir.'/pms-backup-*.sql');
        $this->assertCount(1, $files);
        $this->assertFileDoesNotExist($dir.'/pms-backup-20200101-0000.sql');
        $this->assertStringContainsString('CREATE TABLE', file_get_contents($files[0]));
        unlink($files[0]);
    }

    public function test_activity_log_records_login_logout_and_request_lifecycle(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'admin', 'password' => '1234'])->assertRedirect();
        $admin = $this->user('admin');
        $this->assertDatabaseHas('activity_logs', ['action' => 'login_failed', 'description' => 'admin']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $admin->id]);

        $mr = $this->newRequest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'request_created', 'subject_id' => $mr->id, 'user_id' => $this->user('employee')->id]);

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/cancel")->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'request_transition', 'subject_id' => $mr->id, 'description' => $mr->request_number.': New → Cancelled']);

        $this->actingAs($admin)->post('/logout')->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'logout', 'user_id' => $admin->id]);

        $this->actingAs($admin)->get('/settings/activity?action=request_transition')
            ->assertOk()->assertSee(__('Activity_request_transition'))->assertSee($mr->request_number);
        $this->assertNotNull(ActivityLog::query()->where('action', 'request_transition')->first()?->subjectUrl());
    }

    public function test_bulk_assign_and_close_skip_ineligible_rows(): void
    {
        $a = $this->newRequest();
        $b = $this->newRequest();
        $coord = $this->user('coord');
        $tech = $this->user('tech1');
        $this->actingAs($coord)->post("/requests/{$b->id}/cancel")->assertRedirect();

        $this->actingAs($coord)->post('/requests/bulk', ['action' => 'assign', 'ids' => [$a->id, $b->id], 'technician_id' => $tech->id])
            ->assertRedirect('/requests')
            ->assertSessionHas('ok', __('BulkDone', ['count' => 1, 'skipped' => 1]));

        $this->assertSame(RequestStatus::Assigned, $a->fresh()->status);
        $this->assertSame($tech->id, $a->fresh()->assigned_technician_id);
        $this->assertSame(RequestStatus::Cancelled, $b->fresh()->status);

        $this->actingAs($tech)->post("/requests/{$a->id}/accept-start")->assertRedirect();
        $this->actingAs($tech)->post("/requests/{$a->id}/complete", [
            'fault_type_id' => FaultType::firstOrFail()->id,
            'fault_cause_id' => FaultCause::firstOrFail()->id,
            'resolution_notes' => 'done',
            'cost_labor' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($coord)->post('/requests/bulk', ['action' => 'close', 'ids' => [$a->id]])->assertRedirect();
        $this->assertSame(RequestStatus::Closed, $a->fresh()->status);
    }

    public function test_bulk_actions_require_manager_and_technician_for_assign(): void
    {
        $mr = $this->newRequest();
        $this->actingAs($this->user('tech1'))->post('/requests/bulk', ['action' => 'cancel', 'ids' => [$mr->id]])->assertForbidden();
        $this->actingAs($this->user('coord'))->post('/requests/bulk', ['action' => 'assign', 'ids' => [$mr->id]])->assertSessionHasErrors('technician_id');
    }

    public function test_dashboard_renders_charts_and_offline_page_is_served(): void
    {
        $this->newRequest();
        $this->actingAs($this->user('admin'))->get('/')->assertOk()
            ->assertSee('chartTrend')->assertSee('lib/chartjs/chart.umd.js')->assertSee(__('AgingOpenRequests'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString("OFFLINE_URL = '/offline.html'", file_get_contents(public_path('sw.js')));
    }
}
