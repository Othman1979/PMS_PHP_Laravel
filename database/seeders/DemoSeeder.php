<?php

namespace Database\Seeders;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Enums\PmStrategy;
use App\Enums\Role;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo accounts (password 1234) and sample equipment — for local testing only. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $kitchen = Department::where('name_en', 'Kitchen')->firstOrFail();
        $management = Department::where('name_en', 'Management')->firstOrFail();
        $hall = Department::where('name_en', 'Hall')->firstOrFail();

        foreach ([
            ['admin', 'مدير الصيانة', Role::Admin, $management, null],
            ['coord', 'منسق الصيانة', Role::Coordinator, $management, null],
            ['tech1', 'فني التبريد', Role::Technician, $management, EquipmentCategory::Refrigeration],
            ['tech2', 'فني معدات المطبخ', Role::Technician, $management, EquipmentCategory::KitchenEquipment],
            ['tech3', 'فني الأجهزة والأنظمة', Role::Technician, $management, EquipmentCategory::Administrative],
            ['kitchen', 'مشرف المطبخ', Role::DepartmentManager, $kitchen, null],
            ['employee', 'موظف المطبخ', Role::Employee, $kitchen, null],
        ] as [$username, $name, $role, $dept, $specialty]) {
            User::updateOrCreate(['username' => $username], [
                'full_name' => $name,
                'password' => '1234',
                'role' => $role,
                'department_id' => $dept->id,
                'specialty' => $specialty,
                'is_active' => true,
            ]);
        }

        if (Equipment::query()->exists()) {
            return;
        }

        $freezer = Equipment::create([
            'code' => 'EQ-FRZ-001', 'name' => 'Main Kitchen Freezer #1', 'category' => EquipmentCategory::Refrigeration,
            'department_id' => $kitchen->id, 'location' => 'Main Kitchen', 'manufacturer' => 'Carrier', 'model' => 'CF-500',
            'serial_number' => 'SN-8842100', 'purchase_date' => today()->subYear(), 'vendor' => 'CoolTech Supplies',
            'has_warranty' => true, 'warranty_start' => today()->subYear(), 'warranty_end' => today()->addMonths(2),
            'warranty_provider' => 'Manufacturer', 'status' => EquipmentStatus::Working,
        ]);
        Equipment::create([
            'code' => 'EQ-OVN-001', 'name' => 'Convection Oven', 'category' => EquipmentCategory::KitchenEquipment,
            'department_id' => $kitchen->id, 'location' => 'Main Kitchen', 'manufacturer' => 'Rational', 'model' => 'iCombi Pro',
            'purchase_date' => today()->subYears(2), 'has_warranty' => true, 'warranty_start' => today()->subYears(2),
            'warranty_end' => today()->subYear(), 'status' => EquipmentStatus::WorkingWithIssues,
        ]);
        Equipment::create([
            'code' => 'EQ-POS-001', 'name' => 'POS Terminal - Front Counter', 'category' => EquipmentCategory::Administrative,
            'department_id' => $hall->id, 'location' => 'Front Counter', 'manufacturer' => 'HP', 'model' => 'Engage One',
            'status' => EquipmentStatus::Working,
        ]);

        $freezer->pmPlans()->create([
            'strategy' => PmStrategy::TimeBased, 'frequency_days' => 30,
            'task_description_en' => 'Monthly condenser cleaning and door gasket inspection',
            'task_description_ar' => 'تنظيف المكثف شهريًا وفحص حلقات عزل الأبواب',
            'checklist_id' => Checklist::where('category', EquipmentCategory::Refrigeration)->value('id'),
            'next_due_date' => today()->addDays(5),
        ]);
        $freezer->update(['next_maintenance_date' => today()->addDays(5)]);

        foreach ([
            ['Door Gasket (Universal)', 10, 45, 3], ['Hood Filter', 6, 120, 2],
            ['Compressor Relay', 4, 85, 1], ['Fryer Thermostat', 3, 150, 1],
        ] as [$name, $qty, $cost, $min]) {
            SparePart::create(['name' => $name, 'quantity' => $qty, 'unit_cost' => $cost, 'minimum_quantity' => $min]);
        }
    }
}
