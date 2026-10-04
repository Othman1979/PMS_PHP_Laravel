<?php

namespace App\Services;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Models\Department;
use App\Models\Equipment;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Builds the Excel template for bulk equipment entry and turns an uploaded
 * workbook into validated rows ready to be saved.
 *
 * @phpstan-type ImportRow array{line: int, data: array<string, mixed>, display: array<string, string>, errors: list<string>, exists: bool}
 */
class EquipmentImporter
{
    public const FIELDS = [
        'code', 'name', 'category', 'department', 'location', 'status', 'manufacturer', 'model', 'serial_number',
        'purchase_date', 'vendor', 'purchase_price', 'has_warranty', 'warranty_start', 'warranty_end',
        'warranty_provider', 'warranty_number', 'notes',
    ];

    public const REQUIRED = ['name', 'category', 'department'];

    private const EXAMPLES = [
        ['', 'ثلاجة عرض', 'Refrigeration', null, 'المطبخ الرئيسي', 'Working', 'Samsung', 'RS-500', 'SN-12345', '2025-01-15', 'شركة التبريد', 2500, 'نعم', '2025-01-15', '2027-01-15', 'شركة التبريد', 'W-889', 'ملاحظات'],
        ['EQ-0101', 'فرن غاز', 'KitchenEquipment', null, 'المطبخ', 'Working', 'Zanussi', 'ZG-4', '', '2024-06-01', '', 1800, 'لا', '', '', '', '', ''],
    ];

    private const HEADER_FILL = 'FF1F4E79';

    private const REQUIRED_FILL = 'FFC00000';

    private const LISTS_SHEET = 'Lists';

    private const MAX_ROWS = 2000;

    public function template(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $rtl = app()->getLocale() === 'ar';

        $lists = $spreadsheet->getActiveSheet();
        $lists->setTitle(self::LISTS_SHEET)->setRightToLeft($rtl);
        $departments = Department::active()->ordered()->get();
        $columns = [
            [__('Category'), array_values(EquipmentCategory::options())],
            [__('Department'), $departments->map(fn (Department $d) => $d->localized_name)->all()],
            [__('Status'), array_values(EquipmentStatus::options())],
            [__('Warranty'), [__('Yes'), __('No')]],
        ];
        $ranges = [];
        foreach ($columns as $i => [$title, $values]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $lists->setCellValue("{$letter}1", $title);
            foreach ($values as $r => $value) {
                $lists->setCellValue($letter.($r + 2), $value);
            }
            $lists->getColumnDimension($letter)->setWidth(28);
            $ranges[] = count($values) > 0 ? sprintf("'%s'!\$%s\$2:\$%s\$%d", self::LISTS_SHEET, $letter, $letter, count($values) + 1) : null;
        }
        $lists->getStyle('A1:D1')->getFont()->setBold(true);

        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle(__('EquipmentRegistry'))->setRightToLeft($rtl);
        $sheet->getStyle('A1:R200')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);
        foreach (self::FIELDS as $i => $field) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $required = in_array($field, self::REQUIRED, true);
            $sheet->setCellValue("{$letter}1", __('Import_'.$field).($required ? ' *' : ''));
            $sheet->getStyle("{$letter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $required ? self::REQUIRED_FILL : self::HEADER_FILL]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getColumnDimension($letter)->setWidth(in_array($field, ['name', 'notes', 'location', 'vendor', 'warranty_provider'], true) ? 26 : 16);
            $sheet->getStyle("{$letter}2:{$letter}".(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode(match ($field) {
                'purchase_date', 'warranty_start', 'warranty_end' => 'yyyy-mm-dd',
                'purchase_price' => '#,##0.00',
                default => '@',
            });
        }
        $sheet->getStyle('A1:R1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:R1');

        foreach (['category' => 0, 'department' => 1, 'status' => 2, 'has_warranty' => 3] as $field => $list) {
            if ($ranges[$list] === null) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex(array_search($field, self::FIELDS, true) + 1);
            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle(__('Import_InvalidValue'))->setError(__('Import_PickFromList'))
                ->setFormula1($ranges[$list]);
            $sheet->setDataValidation("{$letter}2:{$letter}".(self::MAX_ROWS + 1), $validation);
        }

        $defaultDepartment = $departments->first()?->localized_name ?? '';
        foreach (self::EXAMPLES as $r => $example) {
            foreach ($example as $i => $value) {
                $field = self::FIELDS[$i];
                $value = match ($field) {
                    'department' => $defaultDepartment,
                    'category' => EquipmentCategory::from($value)->label(),
                    'status' => EquipmentStatus::from($value)->label(),
                    'has_warranty' => $value === 'نعم' ? __('Yes') : __('No'),
                    default => $value,
                };
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).($r + 2), $value);
            }
        }

        $help = $spreadsheet->createSheet(1);
        $help->setTitle(__('Import_HelpSheet'))->setRightToLeft($rtl);
        $help->getColumnDimension('A')->setWidth(110);
        $help->getStyle('A:A')->getAlignment()->setWrapText(true);
        $row = 1;
        foreach (explode("\n", __('Import_HelpText')) as $line) {
            $help->setCellValue("A{$row}", $line);
            $row++;
        }
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @return list<ImportRow>
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (Throwable) {
            return [];
        }

        $sheet = $spreadsheet->getSheet(0);
        $map = $this->headerMap($sheet);
        if ($map === []) {
            return [];
        }

        $departments = Department::all();
        $existing = Equipment::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower((string) $code) => $id]);
        $rows = [];
        $seenCodes = [];
        $highest = $sheet->getHighestDataRow();

        for ($line = 2; $line <= min($highest, self::MAX_ROWS + 1); $line++) {
            $raw = [];
            foreach ($map as $column => $field) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$line);
                $raw[$field] = $this->cellValue($cell->getValue(), $field, $cell->getStyle()->getNumberFormat()->getFormatCode());
            }
            if (collect($raw)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $rows[] = $this->buildRow($line, $raw, $departments, $existing, $seenCodes);
        }

        return $rows;
    }

    /**
     * @param  list<ImportRow>  $rows
     * @return array{created: int, updated: int}
     */
    public function import(array $rows, bool $updateExisting): array
    {
        $created = 0;
        $updated = 0;
        $next = $this->nextCodeNumber();

        foreach ($rows as $row) {
            if ($row['errors'] !== [] || ($row['exists'] && ! $updateExisting)) {
                continue;
            }
            $data = $row['data'];
            if (($data['code'] ?? '') === '') {
                $data['code'] = sprintf('EQ-%04d', $next++);
            }

            if ($row['exists']) {
                Equipment::where('code', $data['code'])->firstOrFail()->update($data);
                $updated++;
            } else {
                Equipment::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return array<int, string> column index => field
     */
    private function headerMap(Worksheet $sheet): array
    {
        $labels = [];
        foreach (self::FIELDS as $field) {
            foreach (['ar', 'en'] as $locale) {
                $labels[$this->normalize(__('Import_'.$field, [], $locale))] = $field;
            }
            $labels[$this->normalize($field)] = $field;
        }

        $map = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn(1));
        for ($column = 1; $column <= $lastColumn; $column++) {
            $header = $this->normalize((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).'1')->getValue());
            if ($header !== '' && isset($labels[$header])) {
                $map[$column] = $labels[$header];
            }
        }

        return $map;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(str_replace(['*', '_'], ['', ' '], preg_replace('/\s+/u', ' ', $value) ?? '')));
    }

    private function cellValue(mixed $value, string $field, string $format): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array($field, ['purchase_date', 'warranty_start', 'warranty_end'], true)) {
            if (is_numeric($value) && (ExcelDate::isDateTimeFormatCode($format) || (float) $value > 1000)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            return $this->parseDate(trim((string) $value));
        }
        if ($field === 'purchase_price') {
            $clean = str_replace([',', ' '], '', (string) $value);

            return $clean === '' ? null : $clean;
        }

        return trim((string) $value);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
                if ($date !== null && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  Collection<int, Department>  $departments
     * @param  Collection<string, int>  $existing
     * @param  array<string, int>  $seenCodes
     * @return ImportRow
     */
    private function buildRow(int $line, array $raw, Collection $departments, Collection $existing, array &$seenCodes): array
    {
        $errors = [];
        $display = [];
        foreach (self::FIELDS as $field) {
            $display[$field] = (string) ($raw[$field] ?? '');
        }

        $category = $this->matchEnum(EquipmentCategory::class, 'Category_', $raw['category'] ?? null);
        $status = ($raw['status'] ?? '') === '' ? EquipmentStatus::Working : $this->matchEnum(EquipmentStatus::class, 'EqStatus_', $raw['status']);
        $department = $this->matchDepartment($departments, $raw['department'] ?? null);
        $hasWarranty = $this->parseBool($raw['has_warranty'] ?? null);

        if (($raw['category'] ?? '') !== '' && $category === null) {
            $errors[] = __('Import_UnknownCategory', ['value' => $raw['category']]);
        }
        if (($raw['status'] ?? '') !== '' && $status === null) {
            $errors[] = __('Import_UnknownStatus', ['value' => $raw['status']]);
        }
        if (($raw['department'] ?? '') !== '' && $department === null) {
            $errors[] = __('Import_UnknownDepartment', ['value' => $raw['department']]);
        }
        if (($raw['has_warranty'] ?? '') !== '' && $hasWarranty === null) {
            $errors[] = __('Import_UnknownYesNo', ['value' => $raw['has_warranty']]);
        }

        $data = [
            'code' => (string) ($raw['code'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'category' => $category?->value,
            'department_id' => $department?->id,
            'location' => $this->nullable($raw['location'] ?? null),
            'status' => $status?->value,
            'manufacturer' => $this->nullable($raw['manufacturer'] ?? null),
            'model' => $this->nullable($raw['model'] ?? null),
            'serial_number' => $this->nullable($raw['serial_number'] ?? null),
            'purchase_date' => $this->nullable($raw['purchase_date'] ?? null),
            'vendor' => $this->nullable($raw['vendor'] ?? null),
            'purchase_price' => $this->nullable($raw['purchase_price'] ?? null),
            'has_warranty' => $hasWarranty ?? (($raw['warranty_end'] ?? '') !== ''),
            'warranty_start' => $this->nullable($raw['warranty_start'] ?? null),
            'warranty_end' => $this->nullable($raw['warranty_end'] ?? null),
            'warranty_provider' => $this->nullable($raw['warranty_provider'] ?? null),
            'warranty_number' => $this->nullable($raw['warranty_number'] ?? null),
            'notes' => $this->nullable($raw['notes'] ?? null),
        ];
        if (! $data['has_warranty']) {
            $data = [...$data, 'warranty_start' => null, 'warranty_end' => null, 'warranty_provider' => null, 'warranty_number' => null];
        }

        $validator = Validator::make($data, [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['required'],
            'department_id' => ['required'],
            'location' => ['nullable', 'string', 'max:200'],
            'status' => ['required'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date_format:Y-m-d'],
            'vendor' => ['nullable', 'string', 'max:200'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'warranty_start' => ['nullable', 'date_format:Y-m-d'],
            'warranty_end' => ['nullable', 'required_if:has_warranty,true', 'date_format:Y-m-d', 'after_or_equal:warranty_start'],
            'warranty_provider' => ['nullable', 'string', 'max:200'],
            'warranty_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], [], collect(self::FIELDS)->mapWithKeys(fn (string $f) => [$f === 'department' ? 'department_id' : $f => __('Import_'.$f)])->all());

        foreach ($validator->errors()->all() as $message) {
            $errors[] = $message;
        }

        $codeKey = mb_strtolower($data['code']);
        $exists = $codeKey !== '' && $existing->has($codeKey);
        if ($codeKey !== '') {
            if (isset($seenCodes[$codeKey])) {
                $errors[] = __('Import_DuplicateCode', ['line' => $seenCodes[$codeKey]]);
            }
            $seenCodes[$codeKey] = $line;
        }

        return ['line' => $line, 'data' => $data, 'display' => $display, 'errors' => array_values(array_unique($errors)), 'exists' => $exists];
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function matchEnum(string $enum, string $labelPrefix, mixed $value): ?object
    {
        $needle = $this->normalize((string) $value);
        if ($needle === '') {
            return null;
        }
        foreach ($enum::cases() as $case) {
            if ($this->normalize($case->value) === $needle) {
                return $case;
            }
            foreach (['ar', 'en'] as $locale) {
                if ($this->normalize(__($labelPrefix.$case->value, [], $locale)) === $needle) {
                    return $case;
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Department>  $departments
     */
    private function matchDepartment(Collection $departments, mixed $value): ?Department
    {
        $needle = $this->normalize((string) $value);
        if ($needle === '') {
            return null;
        }

        return $departments->first(fn (Department $d) => $this->normalize($d->name_ar) === $needle || $this->normalize($d->name_en) === $needle || (string) $d->id === $needle);
    }

    private function parseBool(mixed $value): ?bool
    {
        $needle = $this->normalize((string) $value);
        if ($needle === '') {
            return null;
        }

        return match ($needle) {
            '1', 'yes', 'y', 'true', 'نعم', 'يوجد', 'x', '✓' => true,
            '0', 'no', 'n', 'false', 'لا', 'لايوجد', 'لا يوجد' => false,
            default => null,
        };
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nextCodeNumber(): int
    {
        $max = Equipment::where('code', 'like', 'EQ-%')->pluck('code')
            ->map(fn (string $code) => preg_match('/^EQ-(\d+)$/', $code, $m) ? (int) $m[1] : 0)->max();

        return ((int) $max) + 1;
    }
}
