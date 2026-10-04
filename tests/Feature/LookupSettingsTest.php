<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupSettingsTest extends TestCase
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

    public function test_admin_manages_priorities_and_default_is_unique(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/priorities')->assertOk()->assertSee('مجدول');

        $this->post('/priorities', [
            'name_en' => 'Very High', 'name_ar' => 'عالي جداً', 'color' => '#123456', 'rank' => 5,
            'is_default' => 1, 'is_critical' => 0, 'show_in_quick' => 1, 'is_active' => 1,
        ])->assertRedirect('/priorities')->assertSessionHasNoErrors();

        $new = Priority::where('name_en', 'Very High')->firstOrFail();
        $this->assertTrue($new->is_default);
        $this->assertSame(1, Priority::where('is_default', true)->count());
        $this->assertSame($new->id, Priority::default()->id);

        $this->put("/priorities/{$new->id}", [
            'name_en' => 'Very High', 'name_ar' => 'عالي جداً', 'color' => '#ff0000', 'rank' => 5,
            'is_default' => 0, 'is_critical' => 1, 'show_in_quick' => 0, 'is_active' => 1,
        ])->assertRedirect('/priorities');
        $this->assertSame('#ff0000', $new->fresh()->color);
        $this->assertTrue($new->fresh()->is_critical);

        $this->delete("/priorities/{$new->id}")->assertRedirect('/priorities');
        $this->assertDatabaseMissing('priorities', ['id' => $new->id]);
    }

    public function test_priority_in_use_cannot_be_deleted_and_renames_reflect_everywhere(): void
    {
        $normal = Priority::where('code', 'Normal')->firstOrFail();
        $this->actingAs($this->user('employee'))->post('/r/EQ-FRZ-001', ['description' => 'x', 'priority_id' => $normal->id]);
        $mr = MaintenanceRequest::latest('id')->firstOrFail();
        $this->assertSame($normal->id, $mr->priority_id);

        $this->actingAs($this->user('admin'))->delete("/priorities/{$normal->id}")->assertSessionHas('err', 'Error_LookupInUse');
        $this->assertDatabaseHas('priorities', ['id' => $normal->id]);

        $normal->update(['name_ar' => 'روتيني']);
        $this->withUnencryptedCookie('pms_locale', 'ar')->get("/requests/{$mr->id}")->assertOk()->assertSee('روتيني');
    }

    public function test_quick_screen_offers_only_active_quick_priorities_and_fault_types(): void
    {
        $urgent = Priority::where('code', 'Urgent')->firstOrFail();
        $urgent->update(['is_active' => false]);
        $hidden = FaultType::firstOrFail();
        $hidden->update(['is_active' => false]);
        $shown = FaultType::where('is_active', true)->firstOrFail();

        $response = $this->actingAs($this->user('employee'))->get('/r/EQ-FRZ-001')->assertOk();
        $response->assertDontSee('name="priority_id" value="'.$urgent->id.'"', false);
        $response->assertSee($shown->name_ar);
        $response->assertDontSee($hidden->name_ar);

        $this->post('/r/EQ-FRZ-001', ['description' => 'x', 'priority_id' => $urgent->id])->assertSessionHasErrors('priority_id');
        $this->post('/r/EQ-FRZ-001', ['description' => 'x', 'fault_type_id' => $shown->id])->assertRedirect();
        $this->assertSame($shown->id, MaintenanceRequest::latest('id')->firstOrFail()->fault_type_id);
    }

    public function test_technician_must_pick_fault_type_and_cause_on_completion(): void
    {
        $tech = $this->user('tech1');
        $this->actingAs($this->user('employee'))->post('/r/EQ-FRZ-001', ['description' => 'x']);
        $mr = MaintenanceRequest::latest('id')->firstOrFail();
        $mr->update(['status' => RequestStatus::InProgress, 'assigned_technician_id' => $tech->id]);

        $this->actingAs($tech)->post("/requests/{$mr->id}/complete", ['resolution_notes' => 'done'])
            ->assertSessionHasErrors(['fault_type_id', 'fault_cause_id']);

        $type = FaultType::firstOrFail();
        $cause = FaultCause::where('name_en', 'Manufacturing defect')->firstOrFail();
        $this->post("/requests/{$mr->id}/complete", [
            'resolution_notes' => 'done', 'fault_type_id' => $type->id, 'fault_cause_id' => $cause->id,
        ])->assertSessionHasNoErrors();

        $mr->refresh();
        $this->assertSame(RequestStatus::Completed, $mr->status);
        $this->assertSame($type->id, $mr->fault_type_id);
        $this->assertSame($cause->id, $mr->fault_cause_id);

        $this->actingAs($this->user('admin'))->withUnencryptedCookie('pms_locale', 'ar')
            ->get('/reports?fault_cause_id='.$cause->id)->assertOk()->assertSee('سوء تصنيع')->assertSee($mr->request_number);
        $this->get('/reports?fault_cause_id='.FaultCause::where('name_en', 'Unknown')->value('id'))->assertOk()->assertDontSee($mr->request_number);
    }

    public function test_only_admin_can_manage_lookups(): void
    {
        foreach (['tech1', 'coord', 'employee', 'kitchen'] as $username) {
            $this->actingAs($this->user($username))->get('/fault-causes')->assertForbidden();
            $this->post('/fault-causes', ['name_en' => 'Hack', 'name_ar' => 'اختراق'])->assertForbidden();
        }
        $this->assertDatabaseMissing('fault_causes', ['name_en' => 'Hack']);

        $this->actingAs($this->user('admin'))->post('/fault-types', ['name_en' => 'Software', 'name_ar' => 'برمجي', 'sort_order' => 9, 'is_active' => 1])
            ->assertRedirect('/fault-types')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fault_types', ['name_en' => 'Software', 'is_active' => true]);
    }
}
