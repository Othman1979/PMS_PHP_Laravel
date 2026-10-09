<?php

namespace Database\Seeders;

use App\Enums\EquipmentCategory;
use App\Enums\Role;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\Priority;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Base data needed by every installation: departments, standard checklists, priorities, fault types/causes and the first admin account.
 * Demo users/equipment live in DemoSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Department::query()->doesntExist()) {
            foreach ([
                ['Kitchen', 'المطبخ'], ['Bar', 'البار'], ['Hall', 'الصالة'],
                ['Warehouse', 'المستودع'], ['Management', 'الإدارة'],
            ] as [$en, $ar]) {
                Department::create(['name_en' => $en, 'name_ar' => $ar]);
            }
        }

        if (Checklist::query()->doesntExist()) {
            $this->checklist('Refrigeration Preventive Checklist', 'قائمة فحص الصيانة الوقائية للتبريد', EquipmentCategory::Refrigeration, [
                ['Check temperature is within safe range', 'فحص درجة الحرارة والتأكد من التزامها بالحد الآمن'],
                ['Clean condenser from dust', 'تنظيف الكباس (المكثف) من الغبار'],
                ['Inspect door gaskets and seals', 'فحص إحكام الأبواب وحلقات العزل'],
                ['Check refrigerant level and compressor efficiency', 'فحص مستوى غاز التبريد وكفاءة الكمبروسر'],
            ]);
            $this->checklist('Kitchen Equipment Preventive Checklist', 'قائمة فحص معدات المطبخ', EquipmentCategory::KitchenEquipment, [
                ['Clean hood filters and grease traps', 'تنظيف مرشحات الشفاط ومصائد الشحوم'],
                ['Inspect gas hoses and safety valves', 'فحص خراطيم الغاز وصمامات الأمان'],
                ['Calibrate temperature settings', 'معايرة إعدادات الحرارة'],
                ['Inspect electrical and mechanical safety components', 'فحص مكونات السلامة الكهربائية والميكانيكية'],
            ]);
            $this->checklist('Safety Systems Test Checklist', 'قائمة اختبار أنظمة السلامة', EquipmentCategory::SafetySystems, [
                ['Test fire alarm and suppression systems', 'اختبار أنظمة إنذار وإطفاء الحريق'],
                ['Test emergency lighting', 'اختبار الإضاءة الاحتياطية'],
                ['Verify extinguisher pressure and expiry', 'التحقق من ضغط طفايات الحريق وصلاحيتها'],
            ]);
        }

        if (Priority::query()->doesntExist()) {
            foreach ([
                ['Scheduled', 'Scheduled', 'مجدول', null, null, '#6c757d', 0, false, false, false],
                ['Normal', 'Normal', 'عادي', 'Works but has a problem', 'يعمل لكن فيه مشكلة', '#22c55e', 1, true, false, true],
                ['Urgent', 'Urgent', 'مستعجل', 'Affects the work', 'يؤثر على العمل', '#f59e0b', 2, false, false, true],
                ['Critical', 'Critical (operations stopped)', 'طارئ', 'Equipment fully stopped', 'الجهاز متوقف تمامًا', '#ef4444', 3, false, true, true],
            ] as [$code, $en, $ar, $hintEn, $hintAr, $color, $rank, $default, $critical, $quick]) {
                Priority::create([
                    'code' => $code, 'name_en' => $en, 'name_ar' => $ar, 'hint_en' => $hintEn, 'hint_ar' => $hintAr, 'color' => $color,
                    'rank' => $rank, 'is_default' => $default, 'is_critical' => $critical, 'show_in_quick' => $quick,
                ]);
            }
        }

        if (FaultType::query()->doesntExist()) {
            foreach ([
                ['Electrical', 'كهربائي'], ['Mechanical', 'ميكانيكي'], ['Cooling / Refrigerant', 'تبريد / غاز'],
                ['Gas', 'غاز الطهي'], ['Plumbing / Leak', 'سباكة / تسريب'], ['Electronic / Control board', 'إلكتروني / لوحة تحكم'], ['Other', 'أخرى'],
            ] as $i => [$en, $ar]) {
                FaultType::create(['name_en' => $en, 'name_ar' => $ar, 'sort_order' => $i + 1]);
            }
        }

        if (FaultCause::query()->doesntExist()) {
            foreach ([
                ['Power surge / overload', 'كهرباء زائدة / حمل زائد'], ['Manufacturing defect', 'سوء تصنيع'], ['Normal wear and tear', 'استهلاك طبيعي'],
                ['Misuse by staff', 'سوء استخدام'], ['Lack of cleaning / maintenance', 'قلة تنظيف / صيانة'], ['Water / humidity damage', 'ماء / رطوبة'],
                ['Blockage / dirt build-up', 'انسداد / تراكم أوساخ'], ['Unknown', 'غير معروف'],
            ] as $i => [$en, $ar]) {
                FaultCause::create(['name_en' => $en, 'name_ar' => $ar, 'sort_order' => $i + 1]);
            }
        }

        if (User::query()->where('role', Role::Admin)->doesntExist()) {
            User::create([
                'username' => config('cmms.admin_username'),
                'full_name' => 'مدير الصيانة',
                'password' => config('cmms.admin_password'),
                'role' => Role::Admin,
                'department_id' => Department::where('name_en', 'Management')->value('id'),
            ]);
        }
    }

    /** @param list<array{0: string, 1: string}> $items */
    private function checklist(string $en, string $ar, EquipmentCategory $category, array $items): void
    {
        $checklist = Checklist::create(['name_en' => $en, 'name_ar' => $ar, 'category' => $category]);
        foreach ($items as $i => [$textEn, $textAr]) {
            $checklist->items()->create(['text_en' => $textEn, 'text_ar' => $textAr, 'sort_order' => $i + 1]);
        }
    }
}
