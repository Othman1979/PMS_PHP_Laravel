<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Http\Middleware\SetLocale;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\User;
use App\Reports\EquipmentCostReport;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
    }

    private function completedRequest(Equipment $equipment, float $labor, float $parts): MaintenanceRequest
    {
        return MaintenanceRequest::create([
            'request_number' => 'MR-T-'.fake()->unique()->numerify('####'),
            'equipment_id' => $equipment->id,
            'department_id' => $equipment->department_id,
            'created_by_id' => User::where('username', 'employee')->firstOrFail()->id,
            'description' => 'عطل تجريبي',
            'priority_id' => Priority::first()->id,
            'status' => RequestStatus::Completed,
            'assigned_technician_id' => User::where('username', 'tech1')->firstOrFail()->id,
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subHour(),
            'cost_labor' => $labor,
            'cost_parts' => $parts,
        ]);
    }

    public function test_every_report_renders_for_admin(): void
    {
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $this->completedRequest($equipment, 100, 50);

        $this->get('/reports')->assertOk()->assertSee('id="reportGrid"', false);
        foreach (ReportRegistry::all() as $report) {
            $this->get('/reports/'.$report->key())->assertOk()->assertSee($report->title());
        }
        $this->get('/reports/does-not-exist')->assertNotFound();
    }

    public function test_reports_are_restricted_to_management(): void
    {
        $this->actingAs(User::where('username', 'tech1')->firstOrFail())->get('/reports')->assertForbidden();
        $this->actingAs(User::where('username', 'employee')->firstOrFail())->get('/reports/summary')->assertForbidden();
    }

    public function test_filters_are_carried_to_tabs_and_exports(): void
    {
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $query = 'from=2026-01-01&to=2026-12-31&department_id='.$equipment->department_id.'&state=open';

        $response = $this->get('/reports/requests?'.$query)->assertOk();
        $response->assertSee('/reports/summary?from=2026-01-01&amp;to=2026-12-31&amp;department_id='.$equipment->department_id.'&amp;state=open', false);
        $response->assertSee('/reports/requests/export/xlsx?from=2026-01-01&amp;to=2026-12-31&amp;department_id='.$equipment->department_id.'&amp;state=open', false);
        $response->assertSee('/reports/requests/export/pdf?from=2026-01-01', false);
    }

    public function test_requests_report_applies_filters(): void
    {
        $frz = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $other = Equipment::where('code', '!=', 'EQ-FRZ-001')->firstOrFail();
        $this->completedRequest($frz, 10, 0);
        $this->completedRequest($other, 20, 0);

        $this->get('/reports/requests?equipment_id='.$frz->id)
            ->assertOk()
            ->assertSee('"equipment":"'.$frz->name.'"', false)
            ->assertDontSee('"equipment":"'.$other->name.'"', false);
    }

    public function test_equipment_cost_report_flags_write_off(): void
    {
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $equipment->update(['purchase_price' => 1000]);
        $this->completedRequest($equipment, 700, 400);

        $rows = (new EquipmentCostReport)->rows(
            ReportFilters::fromRequest(request()->merge(['equipment_id' => $equipment->id]), ['equipment_id'], null)
        );

        $this->assertCount(1, $rows);
        $this->assertSame(1100.0, $rows[0]['cost']);
        $this->assertSame(110.0, $rows[0]['ratio']);
        $this->assertSame(EquipmentCostReport::VERDICT_WRITE_OFF, $rows[0]['verdict_code']);

        $this->assertSame(EquipmentCostReport::VERDICT_OK, EquipmentCostReport::verdict(10.0, 5.0));
        $this->assertSame(EquipmentCostReport::VERDICT_REVIEW, EquipmentCostReport::verdict(60.0, 5.0));
        $this->assertSame(EquipmentCostReport::VERDICT_REVIEW, EquipmentCostReport::verdict(null, 45.0));

        $this->get('/reports/equipment-cost')->assertOk()->assertSee(__('Verdict_write_off'));
    }

    public function test_exports_return_excel_and_pdf(): void
    {
        $equipment = Equipment::where('code', 'EQ-FRZ-001')->firstOrFail();
        $this->completedRequest($equipment, 100, 50);

        foreach (['requests', 'summary', 'equipment-cost', 'stock'] as $key) {
            $this->get("/reports/{$key}/export/xlsx")
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $pdf = $this->get("/reports/{$key}/export/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }

        $this->get('/reports/requests/export/csv')->assertNotFound();
    }

    public function test_arabic_pdf_export_works(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'ar')->get('/reports/requests/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
