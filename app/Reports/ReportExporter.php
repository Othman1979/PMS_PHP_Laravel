<?php

namespace App\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds Excel and PDF files from a report using the same columns, rows and totals as the grid.
 */
class ReportExporter
{
    private const HEADER_FILL = 'FFE5F0FB';

    private const BORDER = 'FFD1D1D1';

    public function xlsx(Report $report, ReportFilters $filters): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle($report->title())->setCreator(__('AppName'));

        if ($report instanceof SummaryReport) {
            $summary = $report->build($filters);
            $cards = collect($summary['cards'])->map(fn (array $c) => ['label' => $c['label'], 'value' => $c['value']]);
            $this->fillSheet($spreadsheet->getActiveSheet(), $report->title(), $filters, [Column::text('label', __('Metric')), Column::text('value', __('Value'))], $cards, []);
            foreach ($summary['sections'] as $section) {
                $sheet = $spreadsheet->createSheet();
                $this->fillSheet($sheet, $section['title'], $filters, $section['columns'], $section['rows'], $this->sectionTotals($section['columns'], $section['rows']));
            }
        } else {
            $rows = $report->rows($filters);
            $this->fillSheet($spreadsheet->getActiveSheet(), $report->title(), $filters, $report->columns(), $rows, $report->totals($rows));
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $this->fileName($report, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(Report $report, ReportFilters $filters): Response
    {
        $rtl = app()->getLocale() === 'ar';
        $view = $report instanceof SummaryReport
            ? view('reports.pdf.summary', ['report' => $report, 'filters' => $filters, 'rtl' => $rtl] + $report->build($filters))
            : view('reports.pdf.grid', ['report' => $report, 'filters' => $filters, 'rtl' => $rtl, 'columns' => $report->columns(), 'rows' => $rows = $report->rows($filters), 'totals' => $report->totals($rows)]);

        $mpdf = $this->mpdf($rtl, $report->columns() > 8 ? 'A4-L' : 'A4-L');
        $mpdf->SetTitle($report->title());
        $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#616161">'.__('AppName').' — '.__('Page').' {PAGENO} / {nbpg}</div>');
        $mpdf->WriteHTML($view->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->fileName($report, 'pdf').'"',
        ]);
    }

    /** Formats a cell value for PDF/HTML output. */
    public static function format(Column $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return $column->isNumeric() ? '' : '-';
        }

        return match ($column->type) {
            'money' => number_format((float) $value, 2),
            'percent' => number_format((float) $value, 1).'%',
            'hours' => number_format((float) $value, 1),
            'int' => number_format((int) $value),
            default => (string) $value,
        };
    }

    private function mpdf(bool $rtl, string $format): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'tempDir' => $tempDir,
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'notokufi' => ['R' => 'NotoKufiArabic-Regular.ttf', 'B' => 'NotoKufiArabic-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'notokufi',
            'backupSubsFont' => ['dejavusans'],
            'useSubstitutions' => true,
            'default_font_size' => 9,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->shrink_tables_to_fit = 1;

        return $mpdf;
    }

    /**
     * @param  list<Column>  $columns
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, float|int>  $totals
     */
    private function fillSheet(Worksheet $sheet, string $title, ReportFilters $filters, array $columns, Collection $rows, array $totals): void
    {
        $sheet->setTitle(Str::limit(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title), 28, ''));
        $sheet->setRightToLeft(app()->getLocale() === 'ar');
        $lastCol = $sheet->getCell([count($columns), 1])->getColumn();

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 2;
        $described = $filters->describe();
        if ($described !== []) {
            $sheet->setCellValue("A{$row}", collect($described)->map(fn (string $v, string $k) => "{$k}: {$v}")->implode(' · '));
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF616161');
            $row++;
        }
        $row++;

        $headerRow = $row;
        foreach ($columns as $i => $column) {
            $sheet->setCellValue([$i + 1, $row], $column->label);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        $firstDataRow = $row;
        foreach ($rows as $data) {
            foreach ($columns as $i => $column) {
                $this->writeCell($sheet, $i + 1, $row, $column, $data[$column->key] ?? null);
            }
            $row++;
        }

        if ($totals !== [] && $rows->isNotEmpty()) {
            $sheet->setCellValue([1, $row], __('Total'));
            foreach ($columns as $i => $column) {
                if (isset($totals[$column->key])) {
                    $this->writeCell($sheet, $i + 1, $row, $column, $totals[$column->key]);
                }
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F3F3');
            $row++;
        }

        $lastRow = max($row - 1, $headerRow);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::BORDER);
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");

        foreach ($columns as $i => $column) {
            $letter = $sheet->getCell([$i + 1, 1])->getColumn();
            $sheet->getColumnDimension($letter)->setAutoSize(true);
            if ($column->isNumeric()) {
                $sheet->getStyle("{$letter}{$firstDataRow}:{$letter}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        if ($rows->isEmpty()) {
            $sheet->setCellValue([1, $firstDataRow], __('NoData'));
        }
    }

    private function writeCell(Worksheet $sheet, int $col, int $row, Column $column, mixed $value): void
    {
        $cell = $sheet->getCell([$col, $row]);
        if ($value === null || $value === '') {
            return;
        }

        if ($column->isNumeric()) {
            $cell->setValueExplicit((float) $value, DataType::TYPE_NUMERIC);
            $cell->getStyle()->getNumberFormat()->setFormatCode(match ($column->type) {
                'money' => '#,##0.00',
                'percent' => '0.0"%"',
                'hours' => '0.0',
                default => '#,##0',
            });

            return;
        }

        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
    }

    /**
     * @param  list<Column>  $columns
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    private function sectionTotals(array $columns, Collection $rows): array
    {
        $totals = [];
        foreach ($columns as $column) {
            if ($column->total) {
                $totals[$column->key] = $column->type === 'int' ? (int) $rows->sum($column->key) : round((float) $rows->sum($column->key), 2);
            }
        }

        return $totals;
    }

    private function fileName(Report $report, string $extension): string
    {
        return $report->key().'-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}
