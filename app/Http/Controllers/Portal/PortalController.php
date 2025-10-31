<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Contracts\Services\ContractServiceInterface;
use App\Contracts\Services\EconomicProfileServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function __construct(private EconomicProfileServiceInterface $economic, private ContractServiceInterface $contracts) {}

    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $at = now()->toDateString();

        $concessionaire = null;
        $profile = null;
        try {
            $cid = $user->concessionaires()->value('concessionaires.id');
            if ($cid) {
                $concessionaire = [
                    'id' => (int) $cid,
                    'full_name' => (string) optional($user->concessionaires()->where('concessionaires.id', $cid)->first())->getAttribute('full_name'),
                ];
                $profile = $this->economic->forConcessionaire((int) $cid, now());
            }
        } catch (\Throwable $e) {
        }

        return Inertia::render('portal/index-modern', [
            'user' => [
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ],
            'concessionaire' => $concessionaire,
            'profile' => $profile,
            'at' => $at,
        ]);
    }

    public function debt(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $at = $request->date('at') ?? now();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);

        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only']);
        $data = $this->economic->forConcessionaire((int) $cid, $at, $filters);

        return Inertia::render('portal/debt-modern', $data + [
            'at' => $at->format('Y-m-d'),
        ]);
    }

    public function receipts(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $cidList = $user?->concessionaires()->pluck('concessionaires.id')->all() ?? [];
        abort_if(empty($cidList), 403);

        // Local IDs associated to user's concessionaires (active contracts today)
        $today = now()->toDateString();
        $localIds = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->whereIn('cc.concessionaire_id', $cidList)
            ->whereNull('c.deleted_at')
            ->whereDate('c.start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $today);
            })
            ->pluck('cl.local_id')->unique()->values()->all();

        $rows = DB::table('receipts as r')
            ->join('payments as p', 'p.id', '=', 'r.payment_id')
            ->where(function ($q) use ($cidList, $localIds) {
                $q->where(function ($q2) use ($cidList) {
                    $q2->where('p.debtor_type', 'CONCESSIONAIRE')->whereIn('p.debtor_id', $cidList);
                });
                if (! empty($localIds)) {
                    $q->orWhere(function ($q3) use ($localIds) {
                        $q3->where('p.debtor_type', 'LOCAL')->whereIn('p.debtor_id', $localIds);
                    });
                }
            })
            ->orderByDesc('r.issued_at')
            ->limit(200)
            ->get([
                'r.id', 'r.receipt_number', 'r.issued_at', 'r.status', 'p.debtor_type', 'p.debtor_id',
            ]);

        $items = $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'receipt_number' => (string) $r->receipt_number,
            'issued_at' => (string) $r->issued_at,
            'status' => (string) $r->status,
        ])->all();

        return Inertia::render('portal/receipts-modern', [
            'items' => $items,
        ]);
    }

    public function contracts(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $cidList = $user?->concessionaires()->pluck('concessionaires.id')->all() ?? [];
        abort_if(empty($cidList), 403);

        $rows = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->leftJoin('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->leftJoin('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->selectRaw('c.id, c.number, cs.code as status, c.start_date, c.end_date, COUNT(cl.local_id) as locals_count')
            ->whereIn('cc.concessionaire_id', $cidList)
            ->whereNull('c.deleted_at')
            ->groupBy('c.id', 'c.number', 'cs.code', 'c.start_date', 'c.end_date')
            ->orderByDesc('c.start_date')
            ->limit(200)
            ->get();

        // Resolve local names per contract
        $ids = $rows->pluck('id')->map(fn ($v) => (int) $v)->all();
        $localsByContract = empty($ids) ? collect() : DB::table('contract_local as cl')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->whereIn('cl.contract_id', $ids)
            ->orderBy('l.name')
            ->get(['cl.contract_id', 'l.name'])
            ->groupBy('contract_id')
            ->map(fn ($grp) => $grp->pluck('name')->map(fn ($n) => (string) $n)->values()->all());

        $items = $rows->map(function ($r) use ($localsByContract) {
            $id = (int) $r->id;
            $names = (array) ($localsByContract[$id] ?? []);

            return [
                'id' => $id,
                'number' => (string) ($r->number ?? ''),
                'status' => (string) ($r->status ?? ''),
                'start_date' => (string) ($r->start_date ?? ''),
                'end_date' => (string) ($r->end_date ?? ''),
                'locals_label' => implode(', ', array_slice($names, 0, 3)).(count($names) > 3 ? '…' : ''),
                'locals_count' => (int) ($r->locals_count ?? 0),
            ];
        })->all();

        return Inertia::render('portal/contracts-modern', [
            'items' => $items,
        ]);
    }

    public function downloadReceipt(Request $request, Receipt $receipt): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $cidList = $user?->concessionaires()->pluck('concessionaires.id')->all() ?? [];
        abort_if(empty($cidList), 403);

        // Scope check
        /** @var Payment|null $payment */
        $payment = Payment::query()->find((int) $receipt->getAttribute('payment_id'));
        if (! $payment) {
            abort(404);
        }

        $scopeOk = false;
        $debtorType = strtoupper((string) ($payment->getAttribute('debtor_type') ?? ''));
        $debtorId = (int) ($payment->getAttribute('debtor_id') ?? 0);
        if ($debtorType === 'CONCESSIONAIRE' && in_array($debtorId, $cidList, true)) {
            $scopeOk = true;
        } elseif ($debtorType === 'LOCAL' && $debtorId > 0) {
            $today = now()->toDateString();
            $ownLocal = DB::table('concessionaire_contract as cc')
                ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                ->whereIn('cc.concessionaire_id', $cidList)
                ->where('cl.local_id', $debtorId)
                ->whereNull('c.deleted_at')
                ->whereDate('c.start_date', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $today);
                })
                ->exists();
            $scopeOk = $ownLocal;
        }

        abort_unless($scopeOk, 403);

        // Use same logic as admin download to ensure PDF is ready and then stream
        $disk = Storage::disk('local');
        $path = (string) ($receipt->getAttribute('pdf_path') ?? '');
        if ($path === '' || ! $disk->exists($path)) {
            try {
                $gen = app(\App\Services\ReceiptPdfGenerator::class)->render($receipt);
                $receipt->fill([
                    'pdf_path' => $gen['pdf_path'],
                    'pdf_sha256' => $gen['pdf_sha256'],
                    'rendered_at' => $gen['rendered_at'],
                ])->save();
                $path = $gen['pdf_path'];
            } catch (\Throwable $e) {
                return response('PDF no disponible aún para este recibo.', 404);
            }
        }
        if (! $disk->exists($path)) {
            return response('PDF no disponible aún para este recibo.', 404);
        }
        $full = $disk->path($path);

        return response()->file($full, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->getAttribute('receipt_number').'.pdf"',
        ]);
    }

    public function contractShow(Request $request, int $contract): \Inertia\Response
    {
        $user = $request->user();
        $cidList = $user?->concessionaires()->pluck('concessionaires.id')->all() ?? [];
        abort_if(empty($cidList), 403);

        $exists = DB::table('concessionaire_contract')
            ->where('contract_id', $contract)
            ->whereIn('concessionaire_id', $cidList)
            ->exists();
        abort_unless($exists, 404);

        /** @var Contract $model */
        $model = Contract::query()->findOrFail($contract);
        $item = $this->contracts->toItem($model);

        return \Inertia\Inertia::render('portal/contracts/show', [
            'item' => [
                'id' => (int) ($item['id'] ?? 0),
                'number' => (string) ($item['number'] ?? ''),
                'contract_status' => (string) ($item['contract_status'] ?? ''),
                'contract_status_code' => (string) ($item['contract_status_code'] ?? ''),
                'contract_modality' => (string) ($item['contract_modality'] ?? ''),
                'start_date' => (string) ($item['start_date'] ?? ''),
                'end_date' => (string) ($item['end_date'] ?? ''),
                'monthly_price_eur' => $item['monthly_price_eur'] ?? null,
                'locals_count' => (int) ($item['locals_count'] ?? 0),
                'locals' => (array) ($item['locals'] ?? []),
                'pdf_path' => (string) ($item['pdf_path'] ?? ''),
                'status_history' => (array) ($item['status_history'] ?? []),
            ],
        ]);
    }
}
