<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseRequestItem;
use App\Models\RequestPartUsed;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Services\FileUploadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SparePartController extends Controller
{
    public function __construct(private readonly FileUploadService $files) {}

    public function index(): View
    {
        return view('parts.index', ['parts' => SparePart::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('parts.form', ['part' => new SparePart(['quantity' => 0, 'minimum_quantity' => 0, 'unit_cost' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $part = SparePart::create([...$this->validated($request), ...$this->certificate($request)]);
            if ($part->quantity !== 0) {
                $this->logAdjustment($part, $part->quantity, $request->user()->id, 'OpeningBalance');
            }
        });

        return redirect()->route('parts.index')->with('ok', __('Saved'));
    }

    public function edit(SparePart $part): View
    {
        return view('parts.form', ['part' => $part]);
    }

    public function update(Request $request, SparePart $part): RedirectResponse
    {
        DB::transaction(function () use ($request, $part) {
            $previous = $part->quantity;
            $part->update([...$this->validated($request), ...$this->certificate($request)]);
            if ($part->quantity !== $previous) {
                $this->logAdjustment($part, $part->quantity - $previous, $request->user()->id);
            }
        });

        return redirect()->route('parts.index')->with('ok', __('Saved'));
    }

    public function adjust(Request $request, SparePart $part): RedirectResponse
    {
        $delta = (int) $request->validate(['delta' => ['required', 'integer', 'between:-1000,1000']])['delta'];

        DB::transaction(function () use ($request, $part, $delta) {
            $part = SparePart::query()->lockForUpdate()->findOrFail($part->id);
            $newQty = max(0, $part->quantity + $delta);
            if ($newQty !== $part->quantity) {
                $change = $newQty - $part->quantity;
                $part->update(['quantity' => $newQty]);
                $this->logAdjustment($part, $change, $request->user()->id);
                ActivityLog::record('stock_adjusted', $part, $part->name.': '.($change > 0 ? '+' : '').$change.' → '.$newQty);
            }
        });

        return redirect()->route('parts.index');
    }

    public function destroy(SparePart $part): RedirectResponse
    {
        $inUse = RequestPartUsed::query()->where('spare_part_id', $part->id)->exists()
            || GoodsReceiptItem::query()->where('spare_part_id', $part->id)->exists()
            || PurchaseRequestItem::query()->where('spare_part_id', $part->id)->exists();
        if ($inUse) {
            return redirect()->route('parts.index')->with('err', __('Error_InUse'));
        }

        $part->delete();

        return redirect()->route('parts.index')->with('ok', __('Deleted'));
    }

    public function movements(Request $request): View
    {
        $partId = $request->integer('spare_part_id') ?: null;
        $type = StockMovementType::tryFrom((string) $request->query('type'));
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        $query = StockMovement::query()
            ->when($partId, fn ($q) => $q->where('spare_part_id', $partId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($from, fn ($q) => $q->where('date', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('date', '<', $to->addDay()->startOfDay()));

        $totals = (clone $query)->selectRaw('type, SUM(ABS(quantity) * unit_cost) as value')->groupBy('type')->pluck('value', 'type');

        return view('parts.movements', [
            'movements' => $query->with(['sparePart', 'user', 'goodsReceipt.purchaseRequest', 'maintenanceRequest.equipment', 'maintenanceRequest.department'])
                ->orderByDesc('date')->orderByDesc('id')->take(500)->get(),
            'parts' => SparePart::query()->orderBy('name')->get(['id', 'name']),
            'part' => $partId ? SparePart::find($partId) : null,
            'partId' => $partId,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'totalReceived' => (float) ($totals[StockMovementType::Receipt->value] ?? 0),
            'totalIssued' => (float) ($totals[StockMovementType::Issue->value] ?? 0),
        ]);
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return collect($request->validate([
            'name' => ['required', 'string', 'max:200'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'quantity' => ['required', 'integer', 'min:0'],
            'minimum_quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'is_food_grade' => ['nullable', 'boolean'],
            'food_grade_certificate' => ['nullable', ...FileUploadService::rules()],
        ], [], ['food_grade_certificate' => __('FoodGradeCertificate')]))->except(['is_food_grade', 'food_grade_certificate'])->all();
    }

    /** @return array<string, mixed> */
    private function certificate(Request $request): array
    {
        $data = ['is_food_grade' => $request->boolean('is_food_grade')];
        $url = $this->files->save($request->file('food_grade_certificate'), 'food-grade')['url'] ?? null;
        if ($url !== null) {
            $data['food_grade_certificate_url'] = $url;
        }

        return $data;
    }

    private function logAdjustment(SparePart $part, int $delta, int $userId, ?string $note = null): void
    {
        StockMovement::create([
            'spare_part_id' => $part->id,
            'date' => now(),
            'type' => StockMovementType::Adjustment,
            'quantity' => $delta,
            'balance_after' => $part->quantity,
            'unit_cost' => $part->unit_cost,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }
}
