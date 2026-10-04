<?php

namespace Tests\Feature;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\User;
use App\Services\EquipmentImporter;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EquipmentImportTest extends TestCase
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

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function workbook(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'equipment.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_template_download_contains_headers_examples_and_lists(): void
    {
        $response = $this->actingAs($this->user('admin'))->get(route('equipment.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getSheet(0);
        $this->assertSame(__('Import_code'), $sheet->getCell('A1')->getValue());
        $this->assertSame(__('Import_name').' *', $sheet->getCell('B1')->getValue());
        $this->assertSame(EquipmentCategory::Refrigeration->label(), $sheet->getCell('C2')->getValue());
        $this->assertTrue($sheet->getCell('C2')->hasDataValidation());
        $this->assertSame(__('Import_HelpSheet'), $spreadsheet->getSheet(1)->getTitle());
        $this->assertSame(__('Category'), $spreadsheet->getSheetByName('Lists')->getCell('A1')->getValue());

        $this->actingAs($this->user('tech1'))->get(route('equipment.import.template'))->assertForbidden();
    }

    public function test_preview_validates_rows_and_import_creates_equipment(): void
    {
        $admin = $this->user('admin');
        $department = Department::firstOrFail();
        $existing = Equipment::firstOrFail();
        $headers = array_map(fn (string $f) => __('Import_'.$f), EquipmentImporter::FIELDS);

        $file = $this->workbook($headers, [
            ['', 'آلة ثلج', EquipmentCategory::Refrigeration->label(), $department->name_ar, 'المطبخ', EquipmentStatus::Working->label(), 'Hoshizaki', 'IM-65', 'SN-1', '2025-02-01', 'المورد', '3,500', 'نعم', '2025-02-01', '2027-02-01', 'المورد', 'W-1', ''],
            ['EQ-NEW-2', 'Dish washer', 'KitchenEquipment', $department->name_en, '', '', '', '', '', '15/03/2024', '', 1200, 'No', '', '', '', '', ''],
            ['', '', 'Refrigeration', $department->name_ar, '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['EQ-BAD', 'Bad category', 'Spaceship', 'No such dept', '', '', '', '', '', '', '', '', 'maybe', '', '', '', '', ''],
            [$existing->code, 'Renamed', EquipmentCategory::Other->label(), $department->name_ar, '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $preview = $this->actingAs($admin)->post(route('equipment.import.preview'), ['file' => $file])
            ->assertOk()
            ->assertViewIs('equipment.import.preview')
            ->assertViewHas('valid', 2)
            ->assertViewHas('existing', 1)
            ->assertViewHas('invalid', 2)
            ->assertSee(__('Import_UnknownCategory', ['value' => 'Spaceship']))
            ->assertSee(__('Import_UnknownDepartment', ['value' => 'No such dept']));

        $rows = $preview->viewData('rows');
        $this->assertSame('3500', $rows[0]['data']['purchase_price']);
        $this->assertSame('2024-03-15', $rows[1]['data']['purchase_date']);
        $this->assertSame(EquipmentStatus::Working->value, $rows[1]['data']['status']);
        $this->assertTrue($rows[4]['exists']);

        $before = Equipment::count();
        $this->post(route('equipment.import.store'))
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('ok', __('Import_Done', ['created' => 2, 'updated' => 0]));

        $this->assertSame($before + 2, Equipment::count());
        $ice = Equipment::where('name', 'آلة ثلج')->firstOrFail();
        $this->assertMatchesRegularExpression('/^EQ-\d{4}$/', $ice->code);
        $this->assertTrue($ice->has_warranty);
        $this->assertSame('2027-02-01', $ice->warranty_end->toDateString());
        $this->assertSame('3500.00', $ice->purchase_price);
        $this->assertSame($department->id, $ice->department_id);
        $this->assertSame(EquipmentCategory::KitchenEquipment, Equipment::where('code', 'EQ-NEW-2')->firstOrFail()->category);
        $this->assertNotSame('Renamed', $existing->fresh()->name);
        $this->assertDatabaseMissing('equipment', ['code' => 'EQ-BAD']);
    }

    public function test_import_can_update_existing_equipment_by_code(): void
    {
        $admin = $this->user('admin');
        $existing = Equipment::firstOrFail();
        $headers = array_map(fn (string $f) => __('Import_'.$f), EquipmentImporter::FIELDS);
        $file = $this->workbook($headers, [
            [$existing->code, 'Renamed', EquipmentCategory::Other->label(), $existing->department->name_ar, '', EquipmentStatus::Down->label(), '', '', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $this->actingAs($admin)->post(route('equipment.import.preview'), ['file' => $file])->assertOk()->assertViewHas('existing', 1);
        $this->post(route('equipment.import.store'), ['update_existing' => '1'])
            ->assertRedirect(route('equipment.index'))
            ->assertSessionHas('ok', __('Import_Done', ['created' => 0, 'updated' => 1]));

        $existing->refresh();
        $this->assertSame('Renamed', $existing->name);
        $this->assertSame(EquipmentStatus::Down, $existing->status);
    }

    public function test_preview_rejects_files_without_matching_headers(): void
    {
        $file = $this->workbook(['foo', 'bar'], [['1', '2']]);

        $this->actingAs($this->user('admin'))
            ->from(route('equipment.import.create'))
            ->post(route('equipment.import.preview'), ['file' => $file])
            ->assertRedirect(route('equipment.import.create'))
            ->assertSessionHasErrors('file');

        $this->post(route('equipment.import.store'))->assertRedirect(route('equipment.import.create'))->assertSessionHasErrors('file');
    }
}
