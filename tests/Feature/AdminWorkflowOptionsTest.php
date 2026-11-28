<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminWorkflowOptionsTest extends TestCase
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

    private function newRequest(?Priority $priority = null): MaintenanceRequest
    {
        $employee = $this->user('employee');

        return app(RequestWorkflow::class)->create($employee, null, $employee->department_id,
            'Sink leaking', $priority ?? Priority::default(), location: 'Kitchen');
    }

    public function test_admin_is_listed_with_technicians_and_can_work_an_assigned_request(): void
    {
        $admin = $this->user('admin');
        $mr = $this->newRequest();

        $this->actingAs($this->user('coord'))->get("/requests/{$mr->id}")->assertOk()->assertSee($admin->full_name);
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $admin->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $mr->fresh()->assigned_technician_id);

        $this->actingAs($admin)->post("/requests/{$mr->id}/accept-start")->assertRedirect();
        $this->assertSame(RequestStatus::InProgress, $mr->fresh()->status);
    }

    public function test_hidden_developer_admins_are_not_offered_or_assignable(): void
    {
        $developer = User::factory()->create(['role' => 'Admin', 'is_developer' => true, 'full_name' => 'Hidden Dev']);
        $mr = $this->newRequest();

        $this->actingAs($this->user('coord'))->get("/requests/{$mr->id}")->assertOk()->assertDontSee('Hidden Dev');
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $developer->id])
            ->assertSessionHasErrors('technician_id');
    }

    public function test_employees_still_cannot_be_assigned_requests(): void
    {
        $mr = $this->newRequest();

        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/assign", ['technician_id' => $this->user('employee')->id])
            ->assertSessionHasErrors('technician_id');
        $this->assertNull($mr->fresh()->assigned_technician_id);
    }

    public function test_finished_requests_are_archived_after_one_day_by_default(): void
    {
        $yesterday = $this->newRequest();
        $yesterday->forceFill(['status' => RequestStatus::Completed, 'completed_at' => now()->subHours(25)])->save();
        $today = $this->newRequest();
        $today->forceFill(['status' => RequestStatus::Completed, 'completed_at' => now()->subHours(2)])->save();

        Artisan::call('cmms:archive-requests');

        $this->assertNotNull($yesterday->fresh()->archived_at);
        $this->assertNull($today->fresh()->archived_at);
    }

    public function test_admin_can_permanently_delete_a_cancelled_request_and_its_files(): void
    {
        $mr = $this->newRequest();
        $dir = public_path('uploads/requests/'.$mr->id);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/photo.jpg', 'x');
        $mr->attachments()->create(['file_url' => '/uploads/requests/'.$mr->id.'/photo.jpg', 'file_name' => 'photo.jpg']);
        $mr->forceFill(['status' => RequestStatus::Cancelled, 'cancelled_at' => now()])->save();

        $this->actingAs($this->user('coord'))->delete("/requests/{$mr->id}")->assertForbidden();
        $this->actingAs($this->user('admin'))->get("/requests/{$mr->id}")->assertOk()->assertSee(__('DeleteRequest'));

        $this->actingAs($this->user('admin'))->delete("/requests/{$mr->id}")->assertRedirect('/requests');

        $this->assertNull(MaintenanceRequest::find($mr->id));
        $this->assertDatabaseMissing('request_timelines', ['request_id' => $mr->id]);
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertTrue(ActivityLog::where('action', 'request_deleted')->exists());
    }

    public function test_cancelled_temporary_repair_with_an_open_follow_up_cannot_be_deleted(): void
    {
        $temporary = $this->newRequest();
        $temporary->forceFill(['status' => RequestStatus::Cancelled, 'cancelled_at' => now()])->save();
        $followUp = $this->newRequest();
        $followUp->forceFill(['follow_up_of_id' => $temporary->id])->save();

        $this->actingAs($this->user('admin'))->delete("/requests/{$temporary->id}")->assertRedirect()->assertSessionHas('err');
        $this->assertNotNull($temporary->fresh());
        $this->assertSame($temporary->id, $followUp->fresh()->follow_up_of_id);
    }

    public function test_open_requests_cannot_be_deleted(): void
    {
        $mr = $this->newRequest();

        $this->actingAs($this->user('admin'))->get("/requests/{$mr->id}")->assertOk()->assertDontSee(__('DeleteRequest'));
        $this->actingAs($this->user('admin'))->delete("/requests/{$mr->id}")->assertRedirect()->assertSessionHas('err');
        $this->assertNotNull($mr->fresh());
    }

    public function test_permanent_repair_follow_ups_keep_their_agreed_deadline(): void
    {
        $normal = Priority::where('code', 'Normal')->firstOrFail();
        $temporary = $this->newRequest($normal);
        $temporary->forceFill(['status' => RequestStatus::Completed, 'is_temporary_repair' => true, 'permanent_repair_due' => now()->addDays(3)->toDateString()])->save();
        $followUp = $this->newRequest($normal);
        $followUp->forceFill(['follow_up_of_id' => $temporary->id, 'due_at' => now()->addDays(3)->endOfDay()])->save();

        (require database_path('migrations/2026_11_28_000000_archive_after_one_day_and_normal_priority_without_sla.php'))->up();
        $this->assertNotNull($followUp->fresh()->due_at);

        $followUp->forceFill(['due_at' => null])->save();
        (require database_path('migrations/2026_11_28_000001_restore_permanent_repair_deadlines_on_follow_ups.php'))->up();
        $this->assertSame(now()->addDays(3)->endOfDay()->toDateTimeString(), $followUp->fresh()->due_at->toDateTimeString());
    }

    public function test_normal_priority_requests_have_no_deadline_or_overdue_flag(): void
    {
        $normal = Priority::where('code', 'Normal')->firstOrFail();
        $normal->update(['sla_hours' => 72]);
        $open = $this->newRequest($normal);
        $open->forceFill(['due_at' => now()->subDay()])->save();
        $this->assertTrue($open->fresh()->isOverdue());

        (require database_path('migrations/2026_11_28_000000_archive_after_one_day_and_normal_priority_without_sla.php'))->up();

        $this->assertNull($normal->fresh()->sla_hours);
        $this->assertNull($open->fresh()->due_at);
        $this->assertFalse($open->fresh()->isOverdue());
        $this->assertNull($this->newRequest($normal->fresh())->due_at);
        $this->actingAs($this->user('coord'))->get("/requests/{$open->id}")->assertOk()->assertDontSee(__('DueAt'));
    }
}
