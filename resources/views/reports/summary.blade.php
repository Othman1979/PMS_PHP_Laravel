<x-layouts.app :title="$report->title()">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">{{ __('Reports') }}</h2>
        @include('reports._toolbar')
    </div>

    @include('reports._tabs')
    @include('reports._filters')

    <div class="report-print-head d-none"><h2>{{ $report->title() }}</h2>
        <div class="text-muted small">@foreach ($filters->describe() as $label => $value)<span class="me-3">{{ $label }}: {{ $value }}</span>@endforeach</div>
    </div>

    <div class="row g-3 mb-3 report-cards">
        @foreach ($cards as $card)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card text-center h-100 {{ $card['tone'] ? 'border-'.$card['tone'] : '' }}"><div class="card-body py-3">
                    <div class="fs-2 fw-semibold">{{ $card['value'] }}</div><div class="small text-muted">{{ $card['label'] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 report-sections">
        @foreach ($sections as $section)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header">{{ $section['title'] }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 report-mini">
                            <thead><tr>
                                @foreach ($section['columns'] as $c)
                                    <th @class(['text-end' => $c->isNumeric()])>{{ $c->label }}</th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @forelse ($section['rows'] as $row)
                                    <tr>
                                        @foreach ($section['columns'] as $c)
                                            <td @class(['text-end num' => $c->isNumeric()])>{{ \App\Reports\ReportExporter::format($c, $row[$c->key] ?? null) }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ count($section['columns']) }}" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
