<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentLabelsTest extends TestCase
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

    public function test_equipment_list_offers_selection_and_bulk_label_printing_to_managers(): void
    {
        $first = Equipment::orderBy('code')->firstOrFail();

        $this->actingAs($this->user('admin'))->get('/equipment')
            ->assertOk()
            ->assertSee('data-select-all', false)
            ->assertSee('name="ids[]" value="'.$first->id.'"', false)
            ->assertSee(route('equipment.labels'), false)
            ->assertSee(route('equipment.import.template'), false);

        $this->actingAs($this->user('tech1'))->get('/equipment')->assertForbidden();
    }

    public function test_labels_page_prints_only_selected_equipment(): void
    {
        [$a, $b, $c] = Equipment::orderBy('code')->take(3)->get()->all();

        $response = $this->actingAs($this->user('admin'))
            ->get(route('equipment.labels', ['ids' => [$a->id, $c->id]]))
            ->assertOk()
            ->assertSee($a->code)
            ->assertSee($c->code)
            ->assertSee('id="layout"', false)
            ->assertSee('100x50', false);

        if ($b->code !== $a->code && $b->code !== $c->code) {
            $response->assertDontSee('>'.$b->code.'<', false);
        }
    }

    public function test_labels_page_without_selection_prints_the_filtered_list(): void
    {
        $count = Equipment::count();

        $this->actingAs($this->user('admin'))->get(route('equipment.labels'))
            ->assertOk()
            ->assertSee("({$count} ");

        $this->actingAs($this->user('employee'))->get(route('equipment.labels'))->assertForbidden();
    }
}
