<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all operational data (equipment, requests, stock, purchasing, notifications, logs, uploads)
 * while keeping users and company setup: departments, settings, priorities, fault types/causes and checklists.
 */
class DataReset
{
    /** @var list<string> */
    public const TABLES = [
        'request_timelines', 'request_attachments', 'request_parts_used', 'checklist_results',
        'stock_movements', 'goods_receipt_items', 'goods_receipts', 'purchase_request_items', 'purchase_requests',
        'maintenance_requests', 'preventive_maintenance_plans', 'equipment_calibrations', 'equipment', 'spare_parts',
        'user_notifications', 'activity_logs',
    ];

    /** Ids keep growing so the Android app's saved last-notification cursor still sees new alerts. */
    public const KEEP_SEQUENCE = ['user_notifications'];

    /**
     * Empties every operational table and deletes uploaded files.
     *
     * @return array<string, int> rows deleted per table
     */
    public function run(): array
    {
        $counts = [];
        Schema::withoutForeignKeyConstraints(function () use (&$counts) {
            foreach (self::TABLES as $table) {
                $counts[$table] = DB::table($table)->count();
                if (in_array($table, self::KEEP_SEQUENCE, true)) {
                    DB::table($table)->delete();
                } else {
                    DB::table($table)->truncate();
                }
            }
        });

        File::deleteDirectory(public_path('uploads'));

        return $counts;
    }
}
