<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique();
            $table->string('name_en', 60);
            $table->string('name_ar', 60);
            $table->string('hint_en', 120)->nullable();
            $table->string('hint_ar', 120)->nullable();
            $table->string('color', 9)->default('#0dcaf0');
            $table->unsignedSmallInteger('rank')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_critical')->default(false);
            $table->boolean('show_in_quick')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ([
            ['Scheduled', 'Scheduled', 'مجدول', null, null, '#6c757d', 0, false, false, false],
            ['Normal', 'Normal', 'عادي', 'Works but has a problem', 'يعمل لكن فيه مشكلة', '#22c55e', 1, true, false, true],
            ['Urgent', 'Urgent', 'مستعجل', 'Affects the work', 'يؤثر على العمل', '#f59e0b', 2, false, false, true],
            ['Critical', 'Critical (operations stopped)', 'طارئ', 'Equipment fully stopped', 'الجهاز متوقف تمامًا', '#ef4444', 3, false, true, true],
        ] as [$code, $en, $ar, $hintEn, $hintAr, $color, $rank, $default, $critical, $quick]) {
            DB::table('priorities')->insert([
                'code' => $code, 'name_en' => $en, 'name_ar' => $ar, 'hint_en' => $hintEn, 'hint_ar' => $hintAr,
                'color' => $color, 'rank' => $rank, 'is_default' => $default, 'is_critical' => $critical,
                'show_in_quick' => $quick, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['fault_types', 'fault_causes'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name_en', 100);
                $table->string('name_ar', 100);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->foreignId('priority_id')->nullable()->after('description')->constrained()->restrictOnDelete();
            $table->foreignId('fault_type_id')->nullable()->after('priority_id')->constrained()->nullOnDelete();
            $table->foreignId('fault_cause_id')->nullable()->after('fault_type_id')->constrained()->nullOnDelete();
        });

        $ids = DB::table('priorities')->pluck('id', 'code');
        foreach ($ids as $code => $id) {
            DB::table('maintenance_requests')->where('priority', $code)->update(['priority_id' => $id]);
        }
        DB::table('maintenance_requests')->whereNull('priority_id')->update(['priority_id' => $ids['Normal']]);

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default('Normal');
        });
        DB::table('maintenance_requests')->update([
            'priority' => DB::raw("COALESCE((SELECT code FROM priorities WHERE priorities.id = maintenance_requests.priority_id), 'Normal')"),
        ]);
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('priority_id');
            $table->dropConstrainedForeignId('fault_type_id');
            $table->dropConstrainedForeignId('fault_cause_id');
        });
        Schema::dropIfExists('fault_causes');
        Schema::dropIfExists('fault_types');
        Schema::dropIfExists('priorities');
    }
};
