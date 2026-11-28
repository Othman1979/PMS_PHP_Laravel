<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Finished requests move to the archive after one day; Normal-priority faults have no time limit. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'archive_days'], ['value' => '1', 'updated_at' => now()]);
        Cache::forget('settings.all');

        $normalIds = DB::table('priorities')->where('code', 'Normal')->pluck('id');
        DB::table('priorities')->whereIn('id', $normalIds)->update(['sla_hours' => null]);
        DB::table('maintenance_requests')->whereIn('priority_id', $normalIds)
            ->whereNotIn('status', ['Completed', 'Closed', 'Cancelled'])
            ->update(['due_at' => null, 'escalated_at' => null]);
    }

    public function down(): void
    {
        DB::table('priorities')->where('code', 'Normal')->whereNull('sla_hours')->update(['sla_hours' => 72]);
        DB::table('settings')->where('key', 'archive_days')->update(['value' => '30']);
        Cache::forget('settings.all');
    }
};
