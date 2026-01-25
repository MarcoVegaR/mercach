<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Charge;
use App\Models\Concessionaire;
use App\Models\Local;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function download(Request $request, Receipt $receipt): Response
    {
        $this->authorize('viewAny', \App\Models\Payment::class);
        $disk = Storage::disk('local');
        $refresh = $request->boolean('refresh', false);
        $path = (string) ($receipt->getAttribute('pdf_path') ?? '');
        if ($refresh || $path === '' || ! $disk->exists($path)) {
            try {
                $gen = app(\App\Services\ReceiptPdfGenerator::class)->render($receipt);
                $receipt->fill([
                    'pdf_path' => $gen['pdf_path'],
                    'pdf_sha256' => $gen['pdf_sha256'],
                    'rendered_at' => $gen['rendered_at'],
                ])->save();
                $path = $gen['pdf_path'];
            } catch (\Throwable $e) {
                \Log::error('receipt.pdf.generate_failed', [
                    'userId' => auth()->id(),
                    'receipt_id' => (int) $receipt->getKey(),
                    'error' => $e->getMessage(),
                ]);

                return response('PDF no disponible aún para este recibo.', 404);
            }
        }

        if (! $disk->exists($path)) {
            \Log::error('receipt.pdf.missing_after_generate', [
                'userId' => auth()->id(),
                'path' => $disk->path($path),
                'receipt_id' => (int) $receipt->getKey(),
            ]);

            return response('PDF no disponible aún para este recibo.', 404);
        }

        $full = $disk->path($path);

        return response()->file($full, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->getAttribute('receipt_number').'.pdf"',
        ]);
    }

    public function publicShow(Request $request, string $token): View|Response
    {
        $receipt = Receipt::query()->where('public_token', $token)->first();
        if (! $receipt) {
            return response('Recibo no encontrado o inválido.', 404);
        }

        // Optional HMAC signature validation (QR payload signature)
        $sig = (string) $request->query('sig', '');
        $sigProvided = $sig !== '';
        if ($sig !== '') {
            try {
                $payload = [
                    'uid' => (string) $receipt->getAttribute('public_token'),
                    'num' => (string) $receipt->getAttribute('receipt_number'),
                    'at' => (string) ($receipt->getAttribute('issued_at') ?? ''),
                ];
                $rawKey = (string) (config('app.qr_sign_key') ?: config('app.key'));
                if (str_starts_with($rawKey, 'base64:')) {
                    $rawKey = base64_decode(substr($rawKey, 7)) ?: '';
                }
                $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
                $rawHmac = hash_hmac('sha256', (string) $data, (string) $rawKey, true);
                $expectedFull = rtrim(strtr(base64_encode($rawHmac), '+/', '-_'), '=');
                $expectedShort = rtrim(strtr(base64_encode(substr($rawHmac, 0, 16)), '+/', '-_'), '=');
                if (! (hash_equals($expectedFull, $sig) || hash_equals($expectedShort, $sig))) {
                    return response('Firma de verificación inválida.', 403);
                }
            } catch (\Throwable $e) {
                return response('Error al validar la firma de verificación.', 400);
            }
        }

        // Allow public download when explicitly requested
        if ($request->boolean('download')) {
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
                    \Log::error('receipt.public.pdf.generate_failed', [
                        'receipt_id' => (int) $receipt->getKey(),
                        'error' => $e->getMessage(),
                    ]);

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

        // Build verification details
        $issuer = null;
        try {
            $mid = $receipt->getAttribute('market_id');
            if (is_numeric($mid) && (int) $mid > 0) {
                $m = Market::query()->find((int) $mid);
                $issuer = $m?->getAttribute('name');
            }
        } catch (\Throwable $e) {
        }

        $scope = strtoupper((string) ($receipt->getAttribute('scope') ?? 'PAYMENT'));
        $concept = strtoupper((string) ($receipt->getAttribute('concept') ?? ''));

        $summary = [
            'status' => (string) ($receipt->getAttribute('status') ?? ''),
            'hash' => (string) ($receipt->getAttribute('pdf_sha256') ?? ''),
            'issued_at' => (string) ($receipt->getAttribute('issued_at') ?? ''),
            'template_version' => (string) ($receipt->getAttribute('template_version') ?? ''),
            'rendered_at' => (string) ($receipt->getAttribute('rendered_at') ?? ''),
            'voided_at' => (string) ($receipt->getAttribute('voided_at') ?? ''),
            'void_reason' => (string) ($receipt->getAttribute('void_reason') ?? ''),
            'series_code' => (string) ($receipt->getAttribute('series_code') ?? ''),
            'number_seq' => (string) ($receipt->getAttribute('number_seq') ?? ''),
            'allocations_hash' => (string) ($receipt->getAttribute('allocations_hash') ?? ''),
        ];

        $locals = [];
        $concessionaires = [];

        $chargeInfo = null;
        $totals = null;
        $paymentInfo = null;
        try {
            /** @var FxRateServiceInterface $fx */
            $fx = app(FxRateServiceInterface::class);
            // Resolve payment date for FX context
            $payment = Payment::query()->find((int) $receipt->getAttribute('payment_id'));
            if ($payment) {
                $plocalId = $payment->getAttribute('local_id');
                if (is_numeric($plocalId) && (int) $plocalId > 0) {
                    $l = Local::query()->find((int) $plocalId);
                    if ($l) {
                        $locals[] = [
                            'id' => (int) $l->getKey(),
                            'code' => (string) ($l->getAttribute('code') ?? ''),
                            'name' => (string) ($l->getAttribute('name') ?? ''),
                        ];
                    }
                }

                $debtorType = strtoupper((string) ($payment->getAttribute('debtor_type') ?? ''));
                $debtorId = $payment->getAttribute('debtor_id');
                if ($debtorType === 'CONCESSIONAIRE' && is_numeric($debtorId) && (int) $debtorId > 0) {
                    $cn = Concessionaire::query()->find((int) $debtorId);
                    if ($cn) {
                        $concessionaires[] = [
                            'id' => (int) $cn->getKey(),
                            'full_name' => (string) ($cn->getAttribute('full_name') ?? ''),
                            'document_number' => (string) ($cn->getAttribute('document_number') ?? ''),
                        ];
                    }
                }
            }

            $paidOnRaw = (string) ($payment?->getAttribute('paid_on') ?? '');
            $paidOnFormatted = $paidOnRaw;
            if ($paidOnRaw !== '') {
                try {
                    $paidOnFormatted = Carbon::parse($paidOnRaw)->format('d/m/Y');
                } catch (\Throwable $e) {
                }
            }
            $paymentInfo = [
                'paid_on' => $paidOnRaw,
                'paid_on_fmt' => $paidOnFormatted,
            ];
            $paidOn = new \DateTimeImmutable((string) ($payment?->getAttribute('paid_on') ?? date('Y-m-d')));
            if ($scope === 'CHARGE') {
                $chargeId = (int) ($receipt->getAttribute('charge_id') ?? 0);
                if ($chargeId > 0) {
                    $c = Charge::query()->find($chargeId);
                    $curr = (string) ($c?->getAttribute('currency') ?? '');
                    $amtMinor = (int) ($c?->getAttribute('amount_minor') ?? 0);
                    $appliedBs = (int) PaymentAllocation::query()
                        ->where('payment_id', (int) $receipt->getAttribute('payment_id'))
                        ->where('charge_id', $chargeId)
                        ->sum('amount_bs_minor');
                    $appliedCcy = null;
                    $rateToVes = null;
                    if (in_array($curr, ['USD', 'EUR'], true)) {
                        $rate = $fx->resolveAt($curr, $paidOn);
                        $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                        if ($rateToVes && $rateToVes > 0) {
                            $appliedCcy = (int) round(($appliedBs / 100.0) / $rateToVes * 100);
                        }
                    }
                    $chargeInfo = [
                        'currency' => $curr,
                        'amount_minor' => $amtMinor,
                        'applied_bs_minor' => $appliedBs,
                        'applied_currency_minor' => $appliedCcy,
                        'rate_to_ves' => $rateToVes,
                    ];
                }
            } else {
                // PAYMENT scope: totals by currency and Bs
                $rows = PaymentAllocation::query()
                    ->where('payment_id', (int) $receipt->getAttribute('payment_id'))
                    ->leftJoin('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
                    ->get(['amount_bs_minor', 'c.currency']);
                $totals = [
                    'bs_minor' => 0,
                    'charges_count' => 0,
                    'currencies' => [],
                ];
                $totals['bs_minor'] = (int) $rows->sum('amount_bs_minor');
                $totals['charges_count'] = (int) $rows->count();
                foreach ($rows as $r) {
                    $ccy = strtoupper((string) ($r->getAttribute('currency') ?? ''));
                    if ($ccy) {
                        $totals['currencies'][] = $ccy;
                    }
                }

                $totals['currencies'] = array_values(array_unique($totals['currencies']));
            }
        } catch (\Throwable $e) {
        }

        try {
            $allocLocals = DB::table('payment_allocations as pa')
                ->join('charges as c', 'c.id', '=', 'pa.charge_id')
                ->join('locals as l', 'l.id', '=', 'c.local_id')
                ->where('pa.payment_id', (int) $receipt->getAttribute('payment_id'))
                ->whereNull('l.deleted_at')
                ->distinct()
                ->orderBy('l.code')
                ->get(['l.id', 'l.code', 'l.name']);

            foreach ($allocLocals as $l) {
                $locals[] = [
                    'id' => (int) $l->id,
                    'code' => (string) $l->code,
                    'name' => (string) $l->name,
                ];
            }

            $localsById = [];
            foreach ($locals as $l) {
                $lid = (int) $l['id'];
                if ($lid > 0) {
                    $localsById[$lid] = $l;
                }
            }
            $locals = array_values($localsById);

            if ($concessionaires === []) {
                $allocConcessionaires = DB::table('payment_allocations as pa')
                    ->join('charges as ch', 'ch.id', '=', 'pa.charge_id')
                    ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'ch.contract_id')
                    ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
                    ->where('pa.payment_id', (int) $receipt->getAttribute('payment_id'))
                    ->where('cc.is_primary', true)
                    ->whereNull('cn.deleted_at')
                    ->distinct()
                    ->orderBy('cn.full_name')
                    ->get(['cn.id', 'cn.full_name', 'cn.document_number']);

                foreach ($allocConcessionaires as $cn) {
                    $concessionaires[] = [
                        'id' => (int) $cn->id,
                        'full_name' => (string) $cn->full_name,
                        'document_number' => (string) $cn->document_number,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        $downloadUrl = url()->current().(str_contains(url()->full(), '?') ? '&' : '?').http_build_query([
            'download' => 1,
            'sig' => $sig,
        ]);

        return view('public.receipt', [
            'receipt' => $receipt,
            'issuer' => $issuer,
            'scope' => $scope,
            'concept' => $concept,
            'summary' => $summary,
            'locals' => $locals,
            'concessionaires' => $concessionaires,
            'charge' => $chargeInfo,
            'totals' => $totals,
            'payment' => $paymentInfo,
            'sig' => $sig,
            'downloadUrl' => $downloadUrl,
        ]);
    }
}
