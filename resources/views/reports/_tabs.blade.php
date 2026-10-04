<nav class="report-tabs report-noprint" aria-label="{{ __('Reports') }}">
    @foreach ($reports as $r)
        <a href="{{ route('reports.show', ['report' => $r->key()] + $query) }}" class="report-tab{{ $r->key() === $report->key() ? ' active' : '' }}" @if ($r->key() === $report->key()) aria-current="page" @endif>{{ $r->title() }}</a>
    @endforeach
</nav>
