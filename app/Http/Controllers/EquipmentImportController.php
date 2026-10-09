<?php

namespace App\Http\Controllers;

use App\Services\EquipmentImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentImportController extends Controller
{
    private const SESSION = 'equipment_import_rows';

    public function __construct(private EquipmentImporter $importer) {}

    public function template(): StreamedResponse
    {
        $writer = new Xlsx($this->importer->template());
        $name = 'equipment-import-template-'.app()->getLocale().'.xlsx';

        return response()->streamDownload(fn () => $writer->save('php://output'), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create(): View
    {
        return view('equipment.import.create');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'max:'.config('cmms.upload_max_kb')],
        ], [], ['file' => __('Import_File')]);

        $rows = $this->importer->parse($request->file('file'));
        if ($rows === []) {
            return back()->withErrors(['file' => __('Import_NoRows')]);
        }

        $request->session()->put(self::SESSION, $rows);

        return view('equipment.import.preview', [
            'rows' => $rows,
            'valid' => count(array_filter($rows, fn (array $r) => $r['errors'] === [] && ! $r['exists'])),
            'existing' => count(array_filter($rows, fn (array $r) => $r['errors'] === [] && $r['exists'])),
            'invalid' => count(array_filter($rows, fn (array $r) => $r['errors'] !== [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $request->session()->pull(self::SESSION, []);
        if ($rows === []) {
            return redirect()->route('equipment.import.create')->withErrors(['file' => __('Import_Expired')]);
        }

        $result = $this->importer->import($rows, $request->boolean('update_existing'));

        return redirect()->route('equipment.index')
            ->with('ok', __('Import_Done', ['created' => $result['created'], 'updated' => $result['updated']]));
    }
}
