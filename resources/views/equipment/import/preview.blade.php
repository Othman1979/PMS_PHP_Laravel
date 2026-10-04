@php $fields = \App\Services\EquipmentImporter::FIELDS; @endphp
<x-layouts.app :title="__('ImportEquipment')" dialog-size="xl">
    <h2>{{ __('Import_PreviewTitle') }}</h2>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-success fs-6">{{ __('Import_WillCreate', ['count' => $valid]) }}</span>
        <span class="badge bg-info text-dark fs-6">{{ __('Import_Existing', ['count' => $existing]) }}</span>
        <span class="badge bg-danger fs-6">{{ __('Import_WithErrors', ['count' => $invalid]) }}</span>
    </div>

    <div class="table-responsive import-preview mb-3">
        <table class="table table-sm table-bordered align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Status') }}</th>
                    @foreach ($fields as $f)
                        <th>{{ __('Import_'.$f) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr @class(['table-danger' => $row['errors'] !== [], 'table-info' => $row['errors'] === [] && $row['exists']])>
                        <td>{{ $row['line'] }}</td>
                        <td class="text-nowrap">
                            @if ($row['errors'] !== [])
                                <ul class="mb-0 ps-3 small text-danger">
                                    @foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            @elseif ($row['exists'])
                                <span class="badge bg-info text-dark">{{ __('Import_ExistsBadge') }}</span>
                            @else
                                <span class="badge bg-success">{{ __('Import_NewBadge') }}</span>
                            @endif
                        </td>
                        @foreach ($fields as $f)
                            <td>{{ $row['display'][$f] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="post" action="{{ route('equipment.import.store') }}" class="d-flex flex-wrap align-items-center gap-3">
        @csrf
        @if ($existing > 0)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="update_existing" value="1" id="update_existing">
                <label class="form-check-label" for="update_existing">{{ __('Import_UpdateExisting') }}</label>
            </div>
        @endif
        <button class="btn btn-primary" @disabled($valid + $existing === 0)>{{ __('Import_Confirm') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('equipment.import.create') }}">{{ __('Import_ChooseAnother') }}</a>
        @if ($invalid > 0)
            <span class="small text-muted">{{ __('Import_ErrorsSkipped') }}</span>
        @endif
    </form>
</x-layouts.app>
