<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Http\Requests\EquipmentRequest;
use App\Models\Department;
use App\Models\Equipment;
use App\Services\FileUploadService;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    public function __construct(private FileUploadService $files, private QrCodeService $qr) {}

    public function index(Request $request): View
    {
        return view('equipment.index', [
            'equipment' => $this->filter($request)->orderBy('code')->get(),
            'category' => EquipmentCategory::tryFrom((string) $request->query('category')),
            'status' => EquipmentStatus::tryFrom((string) $request->query('status')),
            'q' => trim((string) $request->query('q')),
        ]);
    }

    public function show(Equipment $equipment): View
    {
        $equipment->load(['department', 'pmPlans', 'maintenanceRequests' => fn ($q) => $q->with('assignedTechnician')->latest()]);

        return view('equipment.show', ['equipment' => $equipment, 'quickUrl' => $this->qr->quickRequestUrl($equipment->code)]);
    }

    public function qr(Equipment $equipment): Response
    {
        return response($this->qr->svg($this->qr->quickRequestUrl($equipment->code)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function labels(Request $request): View
    {
        $ids = collect($request->input('ids', $request->filled('id') ? [$request->integer('id')] : []))
            ->map(fn ($id) => (int) $id)->filter()->values();

        $list = $ids->isNotEmpty()
            ? Equipment::with('department')->whereKey($ids)->orderBy('code')->get()
            : $this->filter($request)->orderBy('code')->get();

        return view('equipment.labels', [
            'equipment' => $list,
            'urls' => $list->mapWithKeys(fn (Equipment $e) => [$e->id => $this->qr->quickRequestUrl($e->code)]),
        ]);
    }

    public function create(): View
    {
        return view('equipment.form', [
            'equipment' => new Equipment([
                'code' => sprintf('EQ-%04d', Equipment::count() + 1),
                'status' => EquipmentStatus::Working,
                'category' => EquipmentCategory::Refrigeration,
            ]),
            'departments' => Department::active()->ordered()->get(),
        ]);
    }

    public function store(EquipmentRequest $request): RedirectResponse
    {
        $data = $request->equipmentData();
        $data['photo_url'] = $this->files->save($request->file('photo'), 'equipment')['url'] ?? null;
        $data['manual_file_url'] = $this->files->save($request->file('manual'), 'manuals')['url'] ?? null;
        if ($data['has_warranty']) {
            $data['warranty_document_url'] = $this->files->save($request->file('warranty_doc'), 'warranty')['url'] ?? null;
        }

        $equipment = Equipment::create($data);

        return redirect()->route('equipment.show', $equipment)->with('ok', __('Saved'));
    }

    public function edit(Equipment $equipment): View
    {
        return view('equipment.form', [
            'equipment' => $equipment,
            'departments' => Department::active()->ordered()->get(),
        ]);
    }

    public function update(EquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->equipmentData();
        foreach ([['photo', 'equipment', 'photo_url'], ['manual', 'manuals', 'manual_file_url']] as [$field, $folder, $column]) {
            $url = $this->files->save($request->file($field), $folder)['url'] ?? null;
            if ($url !== null) {
                $data[$column] = $url;
            }
        }
        if ($data['has_warranty']) {
            $url = $this->files->save($request->file('warranty_doc'), 'warranty')['url'] ?? null;
            if ($url !== null) {
                $data['warranty_document_url'] = $url;
            }
        }

        $equipment->update($data);

        return redirect()->route('equipment.show', $equipment)->with('ok', __('Saved'));
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        if ($equipment->maintenanceRequests()->exists()) {
            return redirect()->route('equipment.show', $equipment)->with('err', __('Error_HasRequests'));
        }

        $equipment->delete();

        return redirect()->route('equipment.index')->with('ok', __('Deleted'));
    }

    private function filter(Request $request): Builder
    {
        $category = EquipmentCategory::tryFrom((string) $request->query('category'));
        $status = EquipmentStatus::tryFrom((string) $request->query('status'));
        $q = trim((string) $request->query('q'));

        return Equipment::with('department')
            ->when($category, fn ($w) => $w->where('category', $category))
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")->orWhere('serial_number', 'like', "%{$q}%")));
    }
}
