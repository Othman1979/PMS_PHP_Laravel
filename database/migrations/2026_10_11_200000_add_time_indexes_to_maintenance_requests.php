<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('completed_at');
            $table->index(['status', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['status', 'department_id']);
        });
    }
};
