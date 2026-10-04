<div class="report-toolbar report-noprint">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="reportPrint" onclick="{{ $printJs ?? 'window.print()' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/></svg>
        {{ __('Print') }}
    </button>
    <a class="btn btn-outline-success btn-sm" href="{{ route('reports.export', ['report' => $report->key(), 'format' => 'xlsx'] + $query) }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13l4 5M12 13l-4 5"/></svg>
        {{ __('ExportExcel') }}
    </a>
    <a class="btn btn-outline-danger btn-sm" href="{{ route('reports.export', ['report' => $report->key(), 'format' => 'pdf'] + $query) }}" target="_blank" rel="noopener">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 17h8M8 13h8"/></svg>
        {{ __('ExportPdf') }}
    </a>
</div>
