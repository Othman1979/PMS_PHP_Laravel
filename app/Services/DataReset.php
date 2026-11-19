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

    public function __construct(private DatabaseBackup $backup) {}

    /**
     * Writes a safety backup, then empties every operational table and deletes uploaded files.
     *
     * @return array{backup: string, tables: array<string, int>}
     */
    public function run(): array
    {
        $backupPath = $this->backup->toFile(storage_path('app/backups'));

        $counts = [];
        Schema::withoutForeignKeyConstraints(function () use (&$counts) {
            foreach (self::TABLES as $table) {
                $counts[$table] = DB::table($table)->count();
                DB::table($table)->truncate();
            }
        });

        File::deleteDirectory(public_path('uploads'));

        return ['backup' => $backupPath, 'tables' => $counts];
    }
}
