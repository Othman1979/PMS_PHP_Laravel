<x-reports.pdf.layout :report="$report" :filters="$filters" :rtl="$rtl">
    <x-reports.pdf.table :columns="$columns" :rows="$rows" :totals="$totals" />
</x-reports.pdf.layout>
