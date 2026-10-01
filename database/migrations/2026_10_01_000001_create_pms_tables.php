<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 200);
            $table->string('name_ar', 200);
            $table->string('category', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('text_en', 500);
            $table->string('text_ar', 500);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('category', 30);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('location', 200)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('vendor', 200)->nullable();
            $table->decimal('purchase_price', 18, 2)->nullable();
            $table->boolean('has_warranty')->default(false);
            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('warranty_provider', 200)->nullable();
            $table->string('warranty_number', 100)->nullable();
            $table->string('warranty_document_url', 500)->nullable();
            $table->string('status', 30)->default('Working');
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->string('manual_file_url', 500)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('preventive_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('strategy', 30)->default('TimeBased');
            $table->integer('frequency_days')->default(30);
            $table->string('task_description_en', 500);
            $table->string('task_description_ar', 500);
            $table->foreignId('checklist_id')->nullable()->constrained()->nullOnDelete();
            $table->date('last_executed_date')->nullable();
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('part_number', 100)->nullable();
            $table->string('manufacturer', 200)->nullable();
            $table->string('unit', 30)->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->integer('minimum_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->nullable()->index();
            $table->foreignId('equipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->string('priority', 20)->default('Normal');
            $table->string('status', 20)->default('New')->index();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('is_under_warranty')->default(false);
            $table->boolean('is_preventive')->default(false);
            $table->foreignId('preventive_maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost_labor', 18, 2)->default(0);
            $table->decimal('cost_parts', 18, 2)->default(0);
            $table->text('resolution_notes')->nullable();
            $table->text('technician_notes')->nullable();
            $table->string('department_confirmation', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->string('file_url', 500);
            $table->string('file_name', 200)->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('request_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20)->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');
            $table->string('note', 1000)->nullable();
        });

        Schema::create('request_parts_used', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost_at_use', 18, 2);
        });

        Schema::create('checklist_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->restrictOnDelete();
            $table->boolean('is_compliant')->default(false);
            $table->string('note', 1000)->nullable();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->index();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->string('status', 20)->default('PendingApproval');
            $table->foreignId('decision_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decision_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_name', 200);
            $table->string('part_number', 100)->nullable();
            $table->integer('quantity');
            $table->string('unit', 30)->nullable();
            $table->decimal('estimated_unit_price', 18, 2)->default(0);
            $table->string('notes', 500)->nullable();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->index();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete();
            $table->dateTime('received_at');
            $table->foreignId('received_by_id')->constrained('users')->restrictOnDelete();
            $table->string('supplier', 200)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('part_name', 200);
            $table->string('part_number', 100)->nullable();
            $table->string('manufacturer', 200)->nullable();
            $table->string('unit', 30)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->string('notes', 500)->nullable();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->dateTime('date')->index();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 800);
            $table->string('endpoint_hash', 64)->unique();
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('user_agent', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['push_subscriptions', 'stock_movements', 'goods_receipt_items', 'goods_receipts',
            'purchase_request_items', 'purchase_requests', 'checklist_results', 'request_parts_used',
            'request_timelines', 'request_attachments', 'maintenance_requests', 'spare_parts',
            'preventive_maintenance_plans', 'equipment', 'checklist_items', 'checklists'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
