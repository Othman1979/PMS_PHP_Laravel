<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Open permanent-repair follow-ups keep the deadline agreed on the temporary repair, whatever their priority. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('maintenance_requests as follow_up')
            ->join('maintenance_requests as parent', 'parent.id', '=', 'follow_up.follow_up_of_id')
            ->whereNull('follow_up.due_at')
            ->whereNotNull('parent.permanent_repair_due')
            ->whereNotIn('follow_up.status', ['Completed', 'Closed', 'Cancelled'])
            ->get(['follow_up.id', 'parent.permanent_repair_due'])
            ->each(fn (object $row) => DB::table('maintenance_requests')->where('id', $row->id)
                ->update(['due_at' => Carbon::parse($row->permanent_repair_due)->endOfDay()]));
    }

    public function down(): void {}
};
