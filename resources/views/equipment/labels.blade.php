@php $rtl = app()->getLocale() === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('PrintQrLabels') }} - {{ __('AppName') }}</title>
    <link rel="stylesheet" href="{{ asset('lib/bootstrap/dist/css/'.($rtl ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css')) }}">
    <style>
        @font-face { font-family: "Noto Kufi Arabic"; font-weight: 100 900; font-display: swap; src: url("{{ asset('fonts/noto-kufi-arabic/NotoKufiArabic-arabic.woff2') }}") format("woff2"); unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC; }
        :root { --lw: 100mm; --lh: 50mm; --qr: min(calc(var(--lh) - 6mm), calc(var(--lw) * .42)); }
        body { background: #eef2f7; font-family: "Noto Kufi Arabic", "Segoe UI", system-ui, Tahoma, Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; background: #0f172a; color: #fff; padding: .6rem 1rem; display: flex; flex-wrap: wrap; gap: .6rem 1rem; align-items: center; z-index: 5; }
        .toolbar .form-select, .toolbar .form-control { width: auto; display: inline-block; }
        .toolbar .form-control { width: 5.5rem; }
        .toolbar label { font-size: .85rem; opacity: .85; margin-inline-end: .3rem; }
        .hint { font-size: .8rem; opacity: .75; flex-basis: 100%; }

        .sheet { display: grid; grid-template-columns: repeat(auto-fill, minmax(8.5cm, 1fr)); gap: .5cm; padding: 1cm; }
        .label { background: #fff; border: 2px dashed #94a3b8; border-radius: .4cm; padding: .4cm; display: flex; gap: .4cm; align-items: center; break-inside: avoid; page-break-inside: avoid; overflow: hidden; }
        .label img { width: 3.6cm; height: 3.6cm; flex-shrink: 0; }
        .label .name { font-weight: 800; font-size: 1.05rem; line-height: 1.25; }
        .label .code { font-family: monospace; font-weight: 700; font-size: 1rem; direction: ltr; unicode-bidi: isolate; }
        .label .meta { color: #475569; font-size: .8rem; }
        .label .scan { margin-top: .25cm; background: #f59e0b; color: #0f172a; font-weight: 800; border-radius: .2cm; padding: .12cm .25cm; font-size: .85rem; display: inline-block; }

        /* Label-printer mode: each label is exactly one page of the roll. */
        body.roll .sheet { display: flex; flex-direction: column; align-items: center; gap: .4cm; padding: .6cm; }
        body.roll .label { width: var(--lw); height: var(--lh); border-radius: 0; border: 1px solid #94a3b8; padding: 2mm 3mm; gap: 3mm; box-sizing: border-box; }
        body.roll .label img { width: var(--qr); height: var(--qr); }
        body.roll .label .min-w-0 { display: flex; flex-direction: column; justify-content: center; flex: 1; min-width: 0; overflow: hidden; height: 100%; }
        body.roll .label .name { font-size: calc(var(--lh) * .14); line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        body.roll .label .code { font-size: calc(var(--lh) * .12); white-space: nowrap; overflow: hidden; }
        body.roll .label .meta { font-size: calc(var(--lh) * .09); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        body.roll .label .scan { font-size: calc(var(--lh) * .09); padding: 1px 4px; margin-top: 1mm; align-self: flex-start; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        body.roll.small .label .meta, body.roll.small .label .scan { display: none; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; gap: .4cm; grid-template-columns: repeat(2, 1fr); }
            .label { border-color: #64748b; }
            body.roll .sheet { display: block; padding: 0; }
            body.roll .label { margin: 0; border: 0; page-break-after: always; break-after: page; }
            body.roll .label:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
    <style id="pageStyle"></style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-warning fw-bold" onclick="window.print()">{{ __('Print') }} ({{ $equipment->count() }} {{ __('LabelsCount') }})</button>
        <span>
            <label for="layout">{{ __('LabelLayout') }}</label>
            <select id="layout" class="form-select form-select-sm">
                <option value="a4">{{ __('LabelLayout_A4') }}</option>
                <option value="roll">{{ __('LabelLayout_Roll') }}</option>
            </select>
        </span>
        <span id="sizeBox" hidden>
            <label for="size">{{ __('LabelSize') }}</label>
            <select id="size" class="form-select form-select-sm">
                <option value="100x50">100 × 50 mm</option>
                <option value="80x50">80 × 50 mm</option>
                <option value="70x40">70 × 40 mm</option>
                <option value="60x40">60 × 40 mm</option>
                <option value="50x30">50 × 30 mm</option>
                <option value="40x30">40 × 30 mm</option>
                <option value="custom">{{ __('LabelCustom') }}</option>
            </select>
            <span id="customBox" hidden>
                <label for="lw">{{ __('LabelWidthMm') }}</label><input id="lw" type="number" min="20" max="200" class="form-control form-control-sm" value="100">
                <label for="lh">{{ __('LabelHeightMm') }}</label><input id="lh" type="number" min="15" max="200" class="form-control form-control-sm" value="50">
            </span>
        </span>
        <a class="btn btn-outline-light btn-sm" href="javascript:history.back()">{{ __('Back') }}</a>
        <span class="hint" id="hint">{{ __('QrHint') }}</span>
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
    <script>
        (function () {
            var KEY = 'cmms.labels';
            var layout = document.getElementById('layout'), size = document.getElementById('size');
            var lw = document.getElementById('lw'), lh = document.getElementById('lh');
            var sizeBox = document.getElementById('sizeBox'), customBox = document.getElementById('customBox');
            var pageStyle = document.getElementById('pageStyle'), hint = document.getElementById('hint');
            var hints = { a4: @json(__('QrHint')), roll: @json(__('LabelPrinterHint')) };

            try {
                var saved = JSON.parse(localStorage.getItem(KEY) || '{}');
                if (saved.layout) layout.value = saved.layout;
                if (saved.size) size.value = saved.size;
                if (saved.w) lw.value = saved.w;
                if (saved.h) lh.value = saved.h;
            } catch (e) {}

            function apply() {
                var roll = layout.value === 'roll';
                var w, h;
                if (size.value === 'custom') { w = +lw.value || 100; h = +lh.value || 50; }
                else { var p = size.value.split('x'); w = +p[0]; h = +p[1]; }

                sizeBox.hidden = !roll;
                customBox.hidden = size.value !== 'custom';
                document.body.classList.toggle('roll', roll);
                document.body.classList.toggle('small', roll && (h < 35 || w < 70));
                document.documentElement.style.setProperty('--lw', w + 'mm');
                document.documentElement.style.setProperty('--lh', h + 'mm');
                pageStyle.textContent = roll ? '@page { size: ' + w + 'mm ' + h + 'mm; margin: 0; }' : '@page { size: A4; margin: 10mm; }';
                hint.textContent = hints[roll ? 'roll' : 'a4'];
                try { localStorage.setItem(KEY, JSON.stringify({ layout: layout.value, size: size.value, w: lw.value, h: lh.value })); } catch (e) {}
                fitCodes(roll);
            }

            // Shrinks the equipment code until the whole code fits on the label (never clip it: EQ-0001 vs EQ-0002 must stay readable).
            function fitCodes(roll) {
                document.querySelectorAll('.label .code').forEach(function (code) {
                    code.style.fontSize = '';
                    if (!roll) return;
                    var px = parseFloat(getComputedStyle(code).fontSize);
                    while (code.scrollWidth > code.clientWidth && px > 5) {
                        px -= 0.5;
                        code.style.fontSize = px + 'px';
                    }
                });
            }

            [layout, size, lw, lh].forEach(function (el) { el.addEventListener('change', apply); el.addEventListener('input', apply); });
            apply();
            window.addEventListener('beforeprint', function () { fitCodes(document.body.classList.contains('roll')); });
        })();
    </script>
</body>
</html>
