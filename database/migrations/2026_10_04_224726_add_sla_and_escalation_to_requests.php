<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('priorities', function (Blueprint $table) {
            $table->unsignedSmallInteger('sla_hours')->nullable()->after('rank');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable()->after('assigned_at')->index();
            $table->dateTime('escalated_at')->nullable()->after('due_at');
        });

        foreach (['Critical' => 4, 'Urgent' => 24, 'Normal' => 72] as $code => $hours) {
            DB::table('priorities')->where('code', $code)->update(['sla_hours' => $hours]);
        }

        $slaByPriority = DB::table('priorities')->whereNotNull('sla_hours')->pluck('sla_hours', 'id');
        DB::table('maintenance_requests')->whereIn('priority_id', $slaByPriority->keys())->whereNull('due_at')
            ->select(['id', 'priority_id', 'created_at'])->orderBy('id')
            ->chunk(500, function ($rows) use ($slaByPriority) {
                foreach ($rows as $row) {
                    DB::table('maintenance_requests')->where('id', $row->id)->update([
                        'due_at' => Carbon::parse($row->created_at)->addHours((int) $slaByPriority[$row->priority_id]),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['due_at', 'escalated_at']);
        });
        Schema::table('priorities', function (Blueprint $table) {
            $table->dropColumn('sla_hours');
        });
    }
};
