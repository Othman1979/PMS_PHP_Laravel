@props(['columns', 'rows', 'totals' => []])
@php
    use App\Reports\ReportExporter;
    $badgeColors = ['bg-success' => '#107c10', 'bg-danger' => '#c42b1c', 'bg-warning text-dark' => '#9d5d00', 'bg-primary' => '#005fb8', 'bg-secondary' => '#616161', 'bg-dark' => '#1a1a1a', 'bg-info text-dark' => '#0b7285', 'bg-orange' => '#ca5010'];
@endphp
<table class="grid" autosize="1">
    <thead>
        <tr>
            @foreach ($columns as $column)
                <th>{{ $column->label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                    @php $value = $row[$column->key] ?? null; @endphp
                    @if ($column->type === 'badge' && $value !== null)
                        @php $color = $row[$column->colorKey] ?? ''; $hex = str_starts_with((string) $color, '#') ? $color : ($badgeColors[$color] ?? '#616161'); @endphp
                        <td style="text-align:center"><span class="badge" style="background:{{ $hex }}">{{ $value }}</span></td>
                    @else
                        <td @class(['num' => $column->isNumeric()])>{{ ReportExporter::format($column, $value) }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="empty">{{ __('NoData') }}</td></tr>
        @endforelse
        @if ($totals !== [] && count($rows))
            <tr class="total">
                @foreach ($columns as $column)
                    <td @class(['num' => $column->isNumeric()])>{{ $loop->first ? __('Total') : (isset($totals[$column->key]) ? ReportExporter::format($column, $totals[$column->key]) : '') }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
