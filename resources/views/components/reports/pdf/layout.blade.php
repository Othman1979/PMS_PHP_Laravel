<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: notokufi; font-size: 9pt; color: #1a1a1a; }
    h1 { font-size: 15pt; margin: 0 0 4px; color: #004c99; }
    .meta { font-size: 8pt; color: #616161; margin: 0 0 10px; }
    .meta span { margin-{{ $rtl ? 'left' : 'right' }}: 10px; }
    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.grid th { background: #e5f0fb; color: #004c99; border: 1px solid #c9d8ea; padding: 4px 5px; font-size: 8.5pt; font-weight: bold; text-align: center; }
    table.grid td { border: 1px solid #d9d9d9; padding: 3px 5px; vertical-align: top; }
    table.grid tr:nth-child(even) td { background: #fafafa; }
    table.grid td.num { text-align: {{ $rtl ? 'left' : 'right' }}; direction: ltr; white-space: nowrap; }
    table.grid tr.total td { background: #f3f3f3; font-weight: bold; border-top: 2px solid #868686; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5pt; color: #fff; background: #616161; white-space: nowrap; }
    .empty { text-align: center; color: #616161; padding: 12px; }
    h2 { font-size: 11pt; margin: 10px 0 4px; color: #1a1a1a; }
    table.cards { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 8px; }
    table.cards td { border: 1px solid #e5e5e5; border-radius: 6px; text-align: center; padding: 8px 4px; width: 16.6%; }
    table.cards .v { font-size: 15pt; font-weight: bold; }
    table.cards .l { font-size: 7.5pt; color: #616161; }
</style>
</head>
<body>
<h1>{{ $report->title() }}</h1>
<p class="meta">
    <span>{{ __('GeneratedAt') }}: {{ now()->format('Y-m-d H:i') }}</span>
    @foreach ($filters->describe() as $label => $value)
        <span>{{ $label }}: {{ $value }}</span>
    @endforeach
</p>
{{ $slot }}
</body>
</html>
