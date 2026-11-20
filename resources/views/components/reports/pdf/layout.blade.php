<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: notokufi; font-size: 9pt; color: #141a24; }
    table.brand { width: 100%; border-collapse: collapse; margin-bottom: 6px; border-bottom: 2px solid #121d31; }
    table.brand td { padding: 0 0 5px; vertical-align: middle; }
    table.brand .company { font-size: 13pt; font-weight: bold; color: #141a24; }
    table.brand .contact { font-size: 8pt; color: #5a6270; }
    table.brand .logo { width: 70px; text-align: {{ $rtl ? 'left' : 'right' }}; }
    table.brand img { max-height: 42px; max-width: 70px; }
    h1 { font-size: 15pt; margin: 6px 0 4px; color: #121d31; }
    .meta { font-size: 8pt; color: #5a6270; margin: 0 0 10px; }
    .meta span { margin-{{ $rtl ? 'left' : 'right' }}: 10px; }
    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.grid th { background: #e9edf3; color: #121d31; border: 1px solid #ccd3de; padding: 4px 5px; font-size: 8.5pt; font-weight: bold; text-align: center; }
    table.grid td { border: 1px solid #d9d9d9; padding: 3px 5px; vertical-align: top; }
    table.grid tr:nth-child(even) td { background: #fafafa; }
    table.grid td.num { text-align: {{ $rtl ? 'left' : 'right' }}; direction: ltr; white-space: nowrap; }
    table.grid tr.total td { background: #f5f4f0; font-weight: bold; border-top: 2px solid #868686; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5pt; color: #fff; background: #5a6270; white-space: nowrap; }
    .empty { text-align: center; color: #5a6270; padding: 12px; }
    h2 { font-size: 11pt; margin: 10px 0 4px; color: #141a24; }
    table.cards { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 8px; }
    table.cards td { border: 1px solid #e5e5e5; border-radius: 6px; text-align: center; padding: 8px 4px; width: 16.6%; }
    table.cards .v { font-size: 15pt; font-weight: bold; }
    table.cards .l { font-size: 7.5pt; color: #5a6270; }
</style>
</head>
<body>
<table class="brand">
    <tr>
        <td>
            <div class="company">{{ \App\Support\Branding::fullName() }}</div>
            @if ($contact = \App\Support\Branding::contactLine())<div class="contact">{{ $contact }}</div>@endif
        </td>
        @if ($logoPath = \App\Support\Branding::logoPath())
            <td class="logo"><img src="{{ $logoPath }}" alt=""></td>
        @endif
    </tr>
</table>
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
