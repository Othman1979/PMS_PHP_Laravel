<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('PrintQrLabels') }} - {{ __('AppName') }}</title>
    <link rel="stylesheet" href="{{ asset('lib/bootstrap/dist/css/'.(app()->getLocale() === 'ar' ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css')) }}">
    <style>
        @font-face { font-family: "Noto Kufi Arabic"; font-weight: 100 900; font-display: swap; src: url("{{ asset('fonts/noto-kufi-arabic/NotoKufiArabic-arabic.woff2') }}") format("woff2"); unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC; }
        body { background: #eef2f7; font-family: "Noto Kufi Arabic", "Segoe UI", system-ui, Tahoma, Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; background: #0f172a; color: #fff; padding: .75rem 1rem; display: flex; gap: .75rem; align-items: center; z-index: 5; }
        .sheet { display: grid; grid-template-columns: repeat(auto-fill, minmax(8.5cm, 1fr)); gap: .5cm; padding: 1cm; }
        .label { background: #fff; border: 2px dashed #94a3b8; border-radius: .4cm; padding: .4cm; display: flex; gap: .4cm; align-items: center; break-inside: avoid; page-break-inside: avoid; }
        .label img { width: 3.6cm; height: 3.6cm; flex-shrink: 0; }
        .label .name { font-weight: 800; font-size: 1.05rem; line-height: 1.25; }
        .label .code { font-family: monospace; font-weight: 700; font-size: 1rem; }
        .label .meta { color: #475569; font-size: .8rem; }
        .label .scan { margin-top: .25cm; background: #f59e0b; color: #0f172a; font-weight: 800; border-radius: .2cm; padding: .12cm .25cm; font-size: .85rem; display: inline-block; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; gap: .4cm; grid-template-columns: repeat(2, 1fr); }
            .label { border-color: #64748b; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-warning fw-bold" onclick="window.print()">{{ __('Print') }} ({{ $equipment->count() }})</button>
        <a class="btn btn-outline-light btn-sm" href="javascript:history.back()">{{ __('Back') }}</a>
        <span class="small opacity-75">{{ __('QrHint') }}</span>
    </div>
    <div class="sheet">
        @foreach ($equipment as $e)
            <div class="label">
                <img src="{{ route('equipment.qr', $e) }}" alt="{{ $urls[$e->id] }}">
                <div class="min-w-0">
                    <div class="name">{{ $e->name }}</div>
                    <div class="code">{{ $e->code }}</div>
                    <div class="meta">{{ $e->department?->localized_name }}{{ $e->location ? ' · '.$e->location : '' }}</div>
                    <div class="scan">{{ __('Quick_LabelScan') }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
