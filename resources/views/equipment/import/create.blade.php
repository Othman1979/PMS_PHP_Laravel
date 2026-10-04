<x-layouts.app :title="__('ImportEquipment')">
    <h2>{{ __('ImportEquipment') }}</h2>

    <ol class="import-steps">
        <li>{!! __('Import_Step1', ['link' => '<a href="'.route('equipment.import.template').'" data-no-dialog download>'.e(__('DownloadTemplate')).'</a>']) !!}</li>
        <li>{{ __('Import_Step2') }}</li>
        <li>{{ __('Import_Step3') }}</li>
    </ol>

    <form method="post" enctype="multipart/form-data" action="{{ route('equipment.import.preview') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="file">{{ __('Import_File') }}</label>
            <input id="file" name="file" type="file" accept=".xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
            @error('file')<span class="text-danger small">{{ $message }}</span>@enderror
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary">{{ __('Import_Preview') }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('equipment.index') }}">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
