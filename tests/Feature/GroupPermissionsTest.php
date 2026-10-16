<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserGroup;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GroupPermissionsTest extends TestCase
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

    public function test_default_groups_mirror_the_previous_role_access(): void
    {
        $this->assertCount(6, UserGroup::all());
        $this->assertTrue($this->user('coord')->hasPermission(Permission::RequestsAssign));
        $this->assertFalse($this->user('coord')->hasPermission(Permission::UsersManage));
        $this->assertFalse($this->user('tech1')->hasPermission(Permission::RequestsCancel));
        $this->assertTrue($this->user('employee')->hasPermission(Permission::RequestsCancelOwn));
        $this->assertTrue($this->user('foodsafety')->hasPermission(Permission::RequestsFoodSafety));
        $this->assertTrue($this->user('admin')->hasPermission(Permission::SettingsManage));
    }

    public function test_admin_can_create_a_group_and_assign_it_so_the_user_inherits_the_union_of_permissions(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/groups')->assertOk()->assertSee(__('UserGroups'));
        $this->actingAs($admin)->post('/groups', [
            'name_ar' => 'مشرفو الجودة', 'name_en' => 'Quality', 'permissions' => ['reports.view', 'requests.cancel'],
        ])->assertRedirect('/groups')->assertSessionHasNoErrors();
        $group = UserGroup::where('name_en', 'Quality')->firstOrFail();
        $this->assertEqualsCanonicalizing(['reports.view', 'requests.cancel'], $group->permissionValues());

        $employee = $this->user('employee');
        $this->assertFalse($employee->hasPermission(Permission::ReportsView));
        $this->actingAs($admin)->put("/users/{$employee->id}", [
            'full_name' => $employee->full_name, 'username' => $employee->username, 'role' => 'Employee', 'is_active' => '1',
            'department_id' => $employee->department_id,
            'groups' => [UserGroup::defaultFor(Role::Employee)->id, $group->id],
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $employee = $employee->fresh();
        $this->assertTrue($employee->hasPermission(Permission::ReportsView));
        $this->assertTrue($employee->hasPermission(Permission::RequestsCancel));
        $this->assertTrue($employee->hasPermission(Permission::RequestsCreate));
        $this->actingAs($employee)->get('/reports')->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'group_created', 'subject_id' => $group->id]);
    }

    public function test_changing_group_permissions_takes_effect_and_built_in_groups_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        $coordGroup = UserGroup::defaultFor(Role::Coordinator);
        $this->actingAs($this->user('coord'))->get('/reports')->assertOk();

        $this->actingAs($admin)->put("/groups/{$coordGroup->id}", [
            'name_ar' => $coordGroup->name_ar, 'name_en' => $coordGroup->name_en, 'permissions' => ['requests.assign'],
        ])->assertRedirect('/groups');
        $this->actingAs($this->user('coord'))->get('/reports')->assertForbidden();
        $this->actingAs($this->user('coord'))->get('/users')->assertForbidden();

        $this->actingAs($admin)->delete("/groups/{$coordGroup->id}")->assertRedirect('/groups');
        $this->assertDatabaseHas('user_groups', ['id' => $coordGroup->id]);
        $this->actingAs($this->user('coord'))->get('/groups')->assertForbidden();
    }

    public function test_new_user_without_groups_joins_the_default_group_of_their_role(): void
    {
        $this->actingAs($this->user('admin'))->post('/users', [
            'full_name' => 'New Tech', 'username' => 'tech9', 'role' => 'Technician', 'password' => '12345678', 'is_active' => '1',
        ])->assertRedirect('/users');
        $tech = $this->user('tech9');
        $this->assertSame([UserGroup::defaultFor(Role::Technician)->id], $tech->groups()->pluck('user_groups.id')->all());
    }

    public function test_cancel_requires_a_reason_and_is_recorded(): void
    {
        $mr = $this->newRequest();
        $coord = $this->user('coord');
        $this->actingAs($coord)->from("/requests/{$mr->id}")->post("/requests/{$mr->id}/cancel")->assertRedirect("/requests/{$mr->id}")->assertSessionHasErrors('note');
        $this->assertSame(RequestStatus::New, $mr->fresh()->status);

        $this->actingAs($coord)->post("/requests/{$mr->id}/cancel", ['note' => 'Duplicate of MR-1'])->assertRedirect();
        $mr = $mr->fresh();
        $this->assertSame(RequestStatus::Cancelled, $mr->status);
        $this->assertSame('Duplicate of MR-1', $mr->cancel_reason);
        $this->assertSame($coord->id, $mr->cancelled_by_id);
        $this->assertNotNull($mr->cancelled_at);
        $this->actingAs($coord)->get("/requests/{$mr->id}")->assertOk()->assertSee('Duplicate of MR-1');

        $other = $this->newRequest();
        $this->actingAs($coord)->post('/requests/bulk', ['action' => 'cancel', 'ids' => [$other->id]])->assertSessionHasErrors('note');
        $this->assertSame(RequestStatus::New, $other->fresh()->status);
    }

    public function test_only_cancel_permission_holders_may_cancel_after_completion_and_requesters_only_before_work_starts(): void
    {
        $mr = $this->newRequest();
        $employee = $this->user('employee');
        $this->assertTrue($mr->canCancel($employee));

        $mr->forceFill(['status' => RequestStatus::InProgress, 'assigned_technician_id' => $this->user('tech1')->id])->save();
        $this->actingAs($employee)->post("/requests/{$mr->id}/cancel", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->user('kitchen'))->post("/requests/{$mr->id}/cancel", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->user('tech1'))->post("/requests/{$mr->id}/cancel", ['note' => 'x'])->assertForbidden();

        $mr->forceFill(['status' => RequestStatus::Completed, 'completed_at' => now()])->save();
        $this->actingAs($employee)->post("/requests/{$mr->id}/cancel", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->user('coord'))->post("/requests/{$mr->id}/cancel", ['note' => 'Completed by mistake'])->assertRedirect();
        $this->assertSame(RequestStatus::Cancelled, $mr->fresh()->status);

        $this->actingAs($employee)->post("/requests/{$mr->id}/reopen")->assertForbidden();
        $this->actingAs($this->user('tech1'))->post("/requests/{$mr->id}/reopen")->assertForbidden();
        $this->actingAs($this->user('admin'))->post("/requests/{$mr->id}/reopen", ['note' => 'back'])->assertRedirect();
        $mr = $mr->fresh();
        $this->assertSame(RequestStatus::Reopened, $mr->status);
        $this->assertNull($mr->cancel_reason);
    }

    public function test_finished_requests_are_archived_after_the_retention_period_and_hidden_from_lists(): void
    {
        $recent = $this->newRequest();
        $recent->forceFill(['status' => RequestStatus::Closed, 'closed_at' => now()->subDays(5)])->save();
        $old = $this->newRequest();
        $old->forceFill(['status' => RequestStatus::Closed, 'closed_at' => now()->subDays(31)])->save();
        $oldCancelled = $this->newRequest();
        $oldCancelled->forceFill(['status' => RequestStatus::Cancelled, 'cancelled_at' => now()->subDays(40)])->save();
        $open = $this->newRequest();
        $open->forceFill(['created_at' => now()->subDays(90), 'updated_at' => now()->subDays(90)])->save();

        Artisan::call('cmms:archive-requests');
        $this->assertNull($recent->fresh()->archived_at);
        $this->assertNotNull($old->fresh()->archived_at);
        $this->assertNotNull($oldCancelled->fresh()->archived_at);
        $this->assertNull($open->fresh()->archived_at);

        $coord = $this->user('coord');
        $this->actingAs($coord)->get('/requests?reset=1')->assertRedirect();
        $this->actingAs($coord)->get('/requests?all=1')->assertOk()
            ->assertSee($recent->request_number)->assertDontSee($old->request_number)->assertDontSee($oldCancelled->request_number);
        $this->actingAs($coord)->get('/requests?archived=1')->assertOk()
            ->assertSee($old->request_number)->assertSee($oldCancelled->request_number)->assertDontSee($recent->request_number);
        $this->actingAs($coord)->get("/requests/{$old->id}")->assertOk()->assertSee(__('Archived'));

        $this->actingAs($coord)->post("/requests/{$old->id}/reopen")->assertRedirect();
        $this->assertNull($old->fresh()->archived_at);

        Setting::set(Setting::ARCHIVE_DAYS, '3');
        Artisan::call('cmms:archive-requests');
        $this->assertNotNull($recent->fresh()->archived_at);
    }

    public function test_route_permissions_follow_groups_rather_than_roles(): void
    {
        $this->actingAs($this->user('foodsafety'))->get('/reports')->assertOk();
        $this->actingAs($this->user('kitchen'))->get('/equipment')->assertOk();
        $this->actingAs($this->user('kitchen'))->get('/reports')->assertForbidden();
        $this->actingAs($this->user('coord'))->get('/settings')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/settings')->assertOk()->assertSee(__('ArchiveDays'));
    }
}
