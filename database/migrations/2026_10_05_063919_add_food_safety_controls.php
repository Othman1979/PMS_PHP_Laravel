<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Food-safety controls for ISO 22000 / HACCP / FSSC 22000 evidence: equipment classification and calibration,
 * post-maintenance release sign-off, temporary repairs with follow-up, food-grade spare parts and equipment commissioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->boolean('food_contact')->default(false)->after('status');
            $table->boolean('is_critical')->default(false)->after('food_contact');
            $table->string('ccp_reference', 100)->nullable()->after('is_critical');
            $table->boolean('hygienic_design')->default(false)->after('ccp_reference');
            $table->boolean('is_measuring_device')->default(false)->after('hygienic_design');
            $table->unsignedSmallInteger('calibration_interval_days')->nullable()->after('is_measuring_device');
            $table->date('last_calibration_date')->nullable()->after('calibration_interval_days');
            $table->date('next_calibration_date')->nullable()->index()->after('last_calibration_date');
            $table->string('calibration_provider', 200)->nullable()->after('next_calibration_date');
            $table->string('calibration_certificate_url', 500)->nullable()->after('calibration_provider');
            $table->string('purchase_spec_url', 500)->nullable()->after('calibration_certificate_url');
            $table->string('conformity_doc_url', 500)->nullable()->after('purchase_spec_url');
            $table->dateTime('commissioned_at')->nullable()->after('conformity_doc_url');
            $table->foreignId('commissioned_by_id')->nullable()->after('commissioned_at')->constrained('users')->nullOnDelete();
            $table->text('commissioning_notes')->nullable()->after('commissioned_by_id');
        });

        DB::table('equipment')->update(['commissioned_at' => DB::raw('created_at')]);

        Schema::create('equipment_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->date('calibrated_at');
            $table->date('next_due_date')->nullable();
            $table->string('result', 20)->default('Pass');
            $table->string('provider', 200)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->string('certificate_url', 500)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->boolean('food_safety_impact')->default(false)->index()->after('is_preventive');
            $table->string('affected_product', 500)->nullable()->after('food_safety_impact');
            $table->string('food_safety_decision', 1000)->nullable()->after('affected_product');
            $table->boolean('is_temporary_repair')->default(false)->after('food_safety_decision');
            $table->date('permanent_repair_due')->nullable()->after('is_temporary_repair');
            $table->foreignId('follow_up_of_id')->nullable()->after('permanent_repair_due')->constrained('maintenance_requests')->nullOnDelete();
            $table->dateTime('released_at')->nullable()->after('follow_up_of_id');
            $table->foreignId('released_by_id')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->json('release_checklist')->nullable()->after('released_by_id');
            $table->string('release_notes', 1000)->nullable()->after('release_checklist');
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->boolean('is_food_grade')->default(false)->after('minimum_quantity');
            $table->string('food_grade_certificate_url', 500)->nullable()->after('is_food_grade');
        });
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropColumn(['is_food_grade', 'food_grade_certificate_url']);
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('follow_up_of_id');
            $table->dropConstrainedForeignId('released_by_id');
            $table->dropColumn(['food_safety_impact', 'affected_product', 'food_safety_decision', 'is_temporary_repair',
                'permanent_repair_due', 'released_at', 'release_checklist', 'release_notes']);
        });

        Schema::dropIfExists('equipment_calibrations');

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commissioned_by_id');
            $table->dropColumn(['food_contact', 'is_critical', 'ccp_reference', 'hygienic_design', 'is_measuring_device',
                'calibration_interval_days', 'last_calibration_date', 'next_calibration_date', 'calibration_provider',
                'calibration_certificate_url', 'purchase_spec_url', 'conformity_doc_url', 'commissioned_at', 'commissioning_notes']);
        });
    }
};
