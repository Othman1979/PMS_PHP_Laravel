<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function index(): View
    {
        $dir = storage_path('app/backups');
        $backups = File::isDirectory($dir)
            ? collect(File::files($dir))->sortByDesc(fn ($f) => $f->getMTime())->take(5)
            : collect();

        return view('settings.general', [
            'autoAssign' => Setting::bool(Setting::AUTO_ASSIGN),
            'escalateOverdue' => Setting::bool(Setting::ESCALATE_OVERDUE, true),
            'backupKeep' => (int) Setting::get(Setting::BACKUP_KEEP, '14'),
            'backups' => $backups,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_keep' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        Setting::set(Setting::AUTO_ASSIGN, $request->boolean('auto_assign') ? '1' : '0');
        Setting::set(Setting::ESCALATE_OVERDUE, $request->boolean('escalate_overdue') ? '1' : '0');
        Setting::set(Setting::BACKUP_KEEP, (string) $data['backup_keep']);
        ActivityLog::record('settings_updated', null, sprintf('auto_assign=%d escalate_overdue=%d backup_keep=%d',
            $request->boolean('auto_assign'), $request->boolean('escalate_overdue'), $data['backup_keep']));

        return redirect()->route('settings.index')->with('ok', __('SettingsSaved'));
    }

    public function backup(DatabaseBackup $backup): StreamedResponse
    {
        $name = $backup->fileName();
        ActivityLog::record('backup_downloaded', null, $name);

        return response()->streamDownload(function () use ($backup) {
            foreach ($backup->stream() as $chunk) {
                echo $chunk;
            }
        }, $name, ['Content-Type' => 'application/sql; charset=utf-8']);
    }

    public function activity(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = ActivityLog::with('user')
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<', Carbon::parse($d)->addDay()))
            ->latest('id')->paginate(50)->withQueryString();

        return view('settings.activity', [
            'logs' => $logs,
            'users' => User::query()->orderBy('full_name')->get(['id', 'full_name']),
            'actions' => ActivityLog::ACTIONS,
            'filters' => $filters,
        ]);
    }
}
