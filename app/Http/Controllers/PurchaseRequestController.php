<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseRequestStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\PurchaseRequest;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** General stock-replenishment purchasing: supervisor/admin request → admin approval → goods receipt (stock in). */
class PurchaseRequestController extends Controller
{
    public function __construct(private FileUploadService $files, private WebPushService $push) {}

    public function index(Request $request): View
    {
        $status = PurchaseRequestStatus::tryFrom((string) $request->query('status'));

        return view('purchases.index', [
            'purchases' => PurchaseRequest::with(['createdBy', 'items'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(PurchaseRequest $purchase): View
    {
        return view('purchases.show', ['pr' => $this->load($purchase)]);
    }

    public function create(): View
    {
        return view('purchases.create', ['parts' => SparePart::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'max:50'],
            'items.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'items.*.part_name' => ['nullable', 'string', 'max:200'],
            'items.*.part_number' => ['nullable', 'string', 'max:100'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.estimated_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $parts = SparePart::query()->whereIn('id', collect($data['items'])->pluck('spare_part_id')->filter())->get()->keyBy('id');
        $items = [];
        foreach ($data['items'] as $i) {
            $qty = (int) ($i['quantity'] ?? 0);
            $part = isset($i['spare_part_id']) ? $parts->get($i['spare_part_id']) : null;
            $name = $part?->name ?? trim((string) ($i['part_name'] ?? ''));
            if ($qty <= 0 || $name === '') {
                continue;
            }
            $items[] = [
                'spare_part_id' => $part?->id,
                'part_name' => $name,
                'part_number' => filled($i['part_number'] ?? null) ? trim($i['part_number']) : $part?->part_number,
                'unit' => filled($i['unit'] ?? null) ? trim($i['unit']) : $part?->unit,
                'quantity' => $qty,
                'estimated_unit_price' => (float) ($i['estimated_unit_price'] ?? 0),
                'notes' => $i['notes'] ?? null,
            ];
        }
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('Error_NoItems')]);
        }

        $me = $request->user();
        $pr = DB::transaction(function () use ($me, $data, $items) {
            $pr = PurchaseRequest::create([
                'created_by_id' => $me->id,
                'reason' => $data['reason'] ?? null,
                'status' => PurchaseRequestStatus::PendingApproval,
            ]);
            $pr->update(['number' => sprintf('PR-%s-%05d', $pr->created_at->format('Y'), $pr->id)]);
            $pr->items()->createMany($items);

            return $pr;
        });

        $adminIds = User::query()->where('role', Role::Admin)->where('is_active', true)->whereKeyNot($me->id)->pluck('id');
        $this->push->sendLocalized($adminIds, fn () => [__('Push_PurchaseNewTitle').' '.$pr->number,
            Str::limit($me->full_name.' — '.$this->summary($items), 120)], route('purchases.show', $pr, false), 'purchase-'.$pr->id);

        return redirect()->route('purchases.show', $pr)->with('ok', __('Saved'));
    }

    public function approve(Request $request, PurchaseRequest $purchase): RedirectResponse
    {
        return $this->decide($request, $purchase, PurchaseRequestStatus::Approved);
    }

    public function reject(Request $request, PurchaseRequest $purchase): RedirectResponse
    {
        return $this->decide($request, $purchase, PurchaseRequestStatus::Rejected);
    }

    public function cancel(Request $request, PurchaseRequest $purchase): RedirectResponse
    {
        $me = $request->user();
        abort_unless($purchase->created_by_id === $me->id || $me->isAdmin(), 403);
        if ($purchase->status === PurchaseRequestStatus::PendingApproval) {
            $purchase->update(['status' => PurchaseRequestStatus::Cancelled]);
        }

        return redirect()->route('purchases.show', $purchase);
    }

    public function receiveForm(PurchaseRequest $purchase): View|RedirectResponse
    {
        if ($purchase->status !== PurchaseRequestStatus::Approved) {
            return redirect()->route('purchases.show', $purchase);
        }

        return view('purchases.receive', ['pr' => $this->load($purchase)]);
    }

    public function receive(Request $request, PurchaseRequest $purchase): RedirectResponse
    {
        if ($purchase->status !== PurchaseRequestStatus::Approved) {
            return redirect()->route('purchases.show', $purchase);
        }

        $data = $request->validate([
            'supplier' => ['nullable', 'string', 'max:200'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:'.config('pms.upload_max_kb')],
            'items' => ['required', 'array', 'max:50'],
            'items.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'items.*.part_name' => ['nullable', 'string', 'max:200'],
            'items.*.part_number' => ['nullable', 'string', 'max:100'],
            'items.*.manufacturer' => ['nullable', 'string', 'max:200'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = collect($data['items'])
            ->filter(fn ($i) => (int) ($i['quantity'] ?? 0) > 0 && (filled($i['spare_part_id'] ?? null) || filled($i['part_name'] ?? null)))
            ->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => __('Error_NoItems')]);
        }

        $me = $request->user();
        $attachment = $this->files->save($request->file('attachment'), 'receipts');

        $receipt = DB::transaction(function () use ($purchase, $data, $lines, $me, $attachment) {
            $receipt = GoodsReceipt::create([
                'purchase_request_id' => $purchase->id,
                'received_at' => now(),
                'received_by_id' => $me->id,
                'supplier' => filled($data['supplier'] ?? null) ? trim($data['supplier']) : null,
                'invoice_number' => filled($data['invoice_number'] ?? null) ? trim($data['invoice_number']) : null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'attachment_url' => $attachment['url'] ?? null,
            ]);
            $receipt->update(['number' => sprintf('GR-%s-%05d', $receipt->received_at->format('Y'), $receipt->id)]);

            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                $price = (float) ($line['unit_price'] ?? 0);
                $part = $this->resolvePart($line, $price);
                $total = $part->quantity + $qty;
                $part->unit_cost = round($total > 0 ? ($part->quantity * (float) $part->unit_cost + $qty * $price) / $total : $price, 2);
                $part->quantity = $total;
                $part->save();

                $receipt->items()->create([
                    'spare_part_id' => $part->id,
                    'part_name' => $part->name,
                    'part_number' => filled($line['part_number'] ?? null) ? trim($line['part_number']) : $part->part_number,
                    'manufacturer' => filled($line['manufacturer'] ?? null) ? trim($line['manufacturer']) : $part->manufacturer,
                    'unit' => filled($line['unit'] ?? null) ? trim($line['unit']) : $part->unit,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'notes' => $line['notes'] ?? null,
                ]);
                StockMovement::create([
                    'spare_part_id' => $part->id,
                    'date' => now(),
                    'type' => StockMovementType::Receipt,
                    'quantity' => $qty,
                    'balance_after' => $part->quantity,
                    'unit_cost' => $price,
                    'goods_receipt_id' => $receipt->id,
                    'user_id' => $me->id,
                    'note' => $receipt->supplier,
                ]);
            }

            $purchase->update(['status' => PurchaseRequestStatus::Received]);

            return $receipt;
        });

        if ($purchase->created_by_id !== $me->id) {
            $received = $receipt->items()->get(['part_name', 'quantity'])->toArray();
            $this->push->sendLocalized($purchase->created_by_id, fn () => [__('Push_PurchaseReceivedTitle').' '.$purchase->number,
                Str::limit($this->summary($received), 120)], route('purchases.show', $purchase, false), 'purchase-'.$purchase->id);
        }

        return redirect()->route('receipts.show', $receipt)->with('ok', __('Saved'));
    }

    public function receipt(GoodsReceipt $receipt): View
    {
        return view('purchases.receipt', [
            'receipt' => $receipt->load(['receivedBy', 'items', 'purchaseRequest.createdBy', 'purchaseRequest.decisionBy']),
        ]);
    }

    private function decide(Request $request, PurchaseRequest $purchase, PurchaseRequestStatus $decision): RedirectResponse
    {
        if ($purchase->status !== PurchaseRequestStatus::PendingApproval) {
            return redirect()->route('purchases.show', $purchase);
        }
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $me = $request->user();
        $purchase->update([
            'status' => $decision,
            'decision_by_id' => $me->id,
            'decision_at' => now(),
            'decision_note' => $note,
        ]);

        $recipients = collect([$purchase->created_by_id]);
        if ($decision === PurchaseRequestStatus::Approved) {
            $recipients = $recipients->merge(User::query()->where('role', Role::Coordinator)->where('is_active', true)->pluck('id'));
        }
        $titleKey = $decision === PurchaseRequestStatus::Approved ? 'Push_PurchaseApprovedTitle' : 'Push_PurchaseRejectedTitle';
        $this->push->sendLocalized($recipients->reject(fn ($id) => $id === $me->id)->unique()->values(),
            fn () => [__($titleKey).' '.$purchase->number, Str::limit($note ?? $purchase->reason ?? $purchase->number, 120)],
            route('purchases.show', $purchase, false), 'purchase-'.$purchase->id);

        return redirect()->route('purchases.show', $purchase)->with('ok', __('Saved'));
    }

    /** @param array<string, mixed> $line */
    private function resolvePart(array $line, float $price): SparePart
    {
        $part = filled($line['spare_part_id'] ?? null) ? SparePart::query()->lockForUpdate()->find($line['spare_part_id']) : null;
        $name = trim((string) ($line['part_name'] ?? ''));
        if ($part === null && $name !== '') {
            $part = SparePart::query()->lockForUpdate()->where('name', $name)->first();
        }
        $part ??= new SparePart(['name' => $name, 'quantity' => 0, 'unit_cost' => $price, 'minimum_quantity' => 0]);

        foreach (['part_number', 'manufacturer', 'unit'] as $field) {
            if (filled($line[$field] ?? null)) {
                $part->{$field} = trim($line[$field]);
            }
        }

        return $part;
    }

    /** @param iterable<array{part_name: string, quantity: int}> $items */
    private function summary(iterable $items): string
    {
        return collect($items)->map(fn ($i) => $i['part_name'].' × '.$i['quantity'])->implode('، ');
    }

    private function load(PurchaseRequest $purchase): PurchaseRequest
    {
        return $purchase->load(['createdBy', 'decisionBy', 'items.sparePart', 'receipts.items', 'receipts.receivedBy']);
    }
}
