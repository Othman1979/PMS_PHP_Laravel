<x-layouts.app :title="__('GeneralSettings')">
    <h2 class="mb-3">{{ __('GeneralSettings') }}</h2>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><strong>{{ __('WorkflowSettings') }}</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('settings.update') }}">
                        @csrf @method('put')
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="auto_assign" name="auto_assign" value="1" @checked($autoAssign)>
                            <label class="form-check-label fw-semibold" for="auto_assign">{{ __('AutoAssign') }}</label>
                            <div class="form-text">{{ __('AutoAssign_Hint') }}</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="escalate_overdue" name="escalate_overdue" value="1" @checked($escalateOverdue)>
                            <label class="form-check-label fw-semibold" for="escalate_overdue">{{ __('EscalateOverdue') }}</label>
                            <div class="form-text">{{ __('EscalateOverdue_Hint') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="backup_keep">{{ __('BackupKeep') }}</label>
                            <input id="backup_keep" name="backup_keep" type="number" min="1" max="365" class="form-control" style="max-width:140px" value="{{ old('backup_keep', $backupKeep) }}" required>
                            <div class="form-text">{{ __('BackupKeep_Hint') }}</div>
                            @error('backup_keep')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><strong>{{ __('Backup') }}</strong></div>
                <div class="card-body">
                    <p class="text-muted small">{{ __('Backup_Hint') }}</p>
                    <form method="post" action="{{ route('settings.backup') }}">
                        @csrf
                        <button class="btn btn-success">⬇ {{ __('DownloadBackup') }}</button>
                    </form>
                    @if ($backups->isNotEmpty())
                        <hr>
                        <div class="small fw-semibold mb-1">{{ __('ScheduledBackups') }}</div>
                        <ul class="list-unstyled small mb-0">
                            @foreach ($backups as $f)
                                <li class="d-flex justify-content-between"><span dir="ltr">{{ $f->getFilename() }}</span><span class="text-muted">{{ number_format($f->getSize() / 1024) }} KB</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong>{{ __('ActivityLog') }}</strong></div>
                <div class="card-body">
                    <p class="text-muted small mb-2">{{ __('ActivityLog_Hint') }}</p>
                    <a class="btn btn-outline-secondary" href="{{ route('settings.activity') }}">{{ __('OpenActivityLog') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
