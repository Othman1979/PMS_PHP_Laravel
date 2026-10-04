<x-reports.pdf.layout :report="$report" :filters="$filters" :rtl="$rtl">
    <table class="cards">
        <tr>
            @foreach ($cards as $card)
                <td><div class="v">{{ $card['value'] }}</div><div class="l">{{ $card['label'] }}</div></td>
            @endforeach
        </tr>
    </table>
    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <x-reports.pdf.table :columns="$section['columns']" :rows="$section['rows']" />
    @endforeach
</x-reports.pdf.layout>
