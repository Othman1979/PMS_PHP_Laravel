<?php

namespace App\Http\Controllers;

use App\Enums\CalibrationResult;
use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Http\Requests\EquipmentRequest;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Equipment;
use App\Services\FileUploadService;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $equipment->load(['department', 'pmPlans', 'commissionedBy', 'maintenanceRequests' => fn ($q) => $q->with('assignedTechnician')->latest(),
            'calibrations' => fn ($q) => $q->with('recordedBy')->orderByDesc('calibrated_at')->orderByDesc('id')]);

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
        $data = [...$data, ...$this->foodSafetyDocuments($request)];

        $equipment = Equipment::create($data);
        ActivityLog::record('equipment_created', $equipment, $equipment->code.' — '.$equipment->name);

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
        $data = [...$data, ...$this->foodSafetyDocuments($request)];

        $equipment->update($data);
        ActivityLog::record('equipment_updated', $equipment, $equipment->code.' — '.$equipment->name);

        return redirect()->route('equipment.show', $equipment)->with('ok', __('Saved'));
    }

    /** Records a calibration event and rolls the equipment's last/next calibration dates forward. */
    public function calibrate(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'calibrated_at' => ['required', 'date', 'before_or_equal:today'],
            'next_due_date' => ['nullable', 'date', 'after:calibrated_at'],
            'result' => ['required', Rule::enum(CalibrationResult::class)],
            'provider' => ['nullable', 'string', 'max:200'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'certificate' => ['nullable', ...FileUploadService::rules()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $calibratedAt = Carbon::parse($data['calibrated_at']);
        $nextDue = isset($data['next_due_date']) ? Carbon::parse($data['next_due_date'])
            : ($equipment->calibration_interval_days ? $calibratedAt->copy()->addDays($equipment->calibration_interval_days) : null);
        $certificateUrl = $this->files->save($request->file('certificate'), 'calibration')['url'] ?? null;

        DB::transaction(function () use ($request, $equipment, $data, $calibratedAt, $nextDue, $certificateUrl) {
            $equipment->calibrations()->create([
                'calibrated_at' => $calibratedAt,
                'next_due_date' => $nextDue,
                'result' => $data['result'],
                'provider' => $data['provider'] ?? null,
                'certificate_number' => $data['certificate_number'] ?? null,
                'certificate_url' => $certificateUrl,
                'notes' => $data['notes'] ?? null,
                'recorded_by_id' => $request->user()->id,
            ]);
            $equipment->update(array_filter([
                'is_measuring_device' => true,
                'last_calibration_date' => $calibratedAt,
                'next_calibration_date' => $nextDue,
                'calibration_provider' => $data['provider'] ?? $equipment->calibration_provider,
                'calibration_certificate_url' => $certificateUrl ?? $equipment->calibration_certificate_url,
            ], fn ($v) => $v !== null));
        });
        ActivityLog::record('equipment_calibrated', $equipment, $equipment->code.' — '.$data['result'].' → '.($nextDue?->toDateString() ?? '-'));

        return redirect()->route('equipment.show', $equipment)->with('ok', __('CalibrationRecorded'));
    }

    /** Food-safety sign-off after the trial run: the equipment becomes "Working" only through this step. */
    public function commission(Request $request, Equipment $equipment): RedirectResponse
    {
        abort_unless($equipment->requiresCommissioning(), 404);
        $data = $request->validate(['commissioning_notes' => ['required', 'string', 'max:2000']]);

        if (! $equipment->hasCommissioningDocuments()) {
            throw ValidationException::withMessages(['commissioning_notes' => __('Error_CommissioningDocs')]);
        }

        $equipment->update([
            'commissioned_at' => now(),
            'commissioned_by_id' => $request->user()->id,
            'commissioning_notes' => trim($data['commissioning_notes']),
            'status' => EquipmentStatus::Working,
        ]);
        ActivityLog::record('equipment_commissioned', $equipment, $equipment->code.' — '.$equipment->name);

        return redirect()->route('equipment.show', $equipment)->with('ok', __('EquipmentCommissioned'));
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        if ($equipment->maintenanceRequests()->exists()) {
            return redirect()->route('equipment.show', $equipment)->with('err', __('Error_HasRequests'));
        }

        ActivityLog::record('equipment_deleted', null, $equipment->code.' — '.$equipment->name);
        $equipment->delete();

        return redirect()->route('equipment.index')->with('ok', __('Deleted'));
    }

    /** @return array<string, string> uploaded HACCP/FSSC documents (only the ones present in this request) */
    private function foodSafetyDocuments(Request $request): array
    {
        $urls = [];
        foreach ([['calibration_certificate', 'calibration', 'calibration_certificate_url'], ['purchase_spec', 'specs', 'purchase_spec_url'],
            ['conformity_doc', 'conformity', 'conformity_doc_url']] as [$field, $folder, $column]) {
            $url = $this->files->save($request->file($field), $folder)['url'] ?? null;
            if ($url !== null) {
                $urls[$column] = $url;
            }
        }

        return $urls;
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
