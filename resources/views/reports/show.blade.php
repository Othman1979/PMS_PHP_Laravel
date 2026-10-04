<x-layouts.app :title="$report->title()">
    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset('lib/tabulator/tabulator.min.css') }}">
    </x-slot:styles>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">{{ __('Reports') }}</h2>
        @include('reports._toolbar', ['printJs' => 'window.reportGrid && window.reportGrid.print()'])
    </div>

    @include('reports._tabs')
    @include('reports._filters')

    @if ($report instanceof \App\Reports\EquipmentCostReport)
        <div class="alert alert-light border small report-noprint mb-3">
            {{ __('EquipmentCostHint', ['review' => \App\Reports\EquipmentCostReport::REVIEW_RATIO, 'writeoff' => \App\Reports\EquipmentCostReport::WRITE_OFF_RATIO, 'share' => \App\Reports\EquipmentCostReport::REVIEW_DEPARTMENT_SHARE]) }}
        </div>
    @endif

    <div class="card report-card">
        <div class="report-grid-bar report-noprint">
            <div class="report-grid-title"><strong>{{ $report->title() }}</strong> <span class="text-muted">({{ $rows->count() }})</span></div>
            <div class="report-grid-tools">
                <input type="search" class="form-control form-control-sm" id="reportSearch" placeholder="{{ __('QuickSearch') }}" aria-label="{{ __('QuickSearch') }}">
                <select class="form-select form-select-sm" id="reportGroup" aria-label="{{ __('GroupBy') }}">
                    <option value="">{{ __('NoGrouping') }}</option>
                    @foreach ($columns as $c)
                        @if (! empty($c['groupable']))
                            <option value="{{ $c['key'] }}" @selected($report->defaultGroup() === $c['key'])>{{ __('GroupBy') }}: {{ $c['label'] }}</option>
                        @endif
                    @endforeach
                </select>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">{{ __('Columns') }}</button>
                    <div class="dropdown-menu dropdown-menu-end report-columns-menu" id="reportColumns"></div>
                </div>
            </div>
        </div>
        <div id="reportGrid" class="report-grid" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"></div>
    </div>

    <script type="application/json" id="reportData">{!! json_encode([
        'title' => $report->title(),
        'filters' => $filters->describe(),
        'columns' => $columns,
        'rows' => $rows,
        'groupBy' => $report->defaultGroup(),
        'rtl' => app()->getLocale() === 'ar',
        'locale' => app()->getLocale(),
        'lang' => [
            'filter' => __('FilterColumn'), 'noData' => __('NoData'), 'rows' => __('Rows'), 'total' => __('Total'),
            'pageSize' => __('PageSize'), 'first' => __('First'), 'last' => __('Last'), 'prev' => __('Prev'), 'next' => __('Next'),
            'all' => __('All'), 'showing' => __('Showing'), 'of' => __('Of'), 'pages' => __('Pages'), 'loading' => __('Loading'),
            'item' => __('Row'), 'items' => __('Rows'), 'generatedAt' => __('GeneratedAt'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <x-slot:scripts>
        <script src="{{ asset('lib/tabulator/tabulator.min.js') }}"></script>
        <script src="{{ asset('js/report-grid.js') }}?v={{ filemtime(public_path('js/report-grid.js')) }}"></script>
    </x-slot:scripts>
</x-layouts.app>
