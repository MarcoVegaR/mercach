<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Charge;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
        ];

        $chargeInfo = null;
        $totals = null;
        try {
            /** @var FxRateServiceInterface $fx */
            $fx = app(FxRateServiceInterface::class);
            // Resolve payment date for FX context
            $payment = Payment::query()->find((int) $receipt->getAttribute('payment_id'));
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
                    'by_ccy_minor' => [],
                ];
                $totals['bs_minor'] = (int) $rows->sum('amount_bs_minor');
                foreach ($rows as $r) {
                    $ccy = strtoupper((string) ($r->getAttribute('currency') ?? ''));
                    if ($ccy) {
                        // No contamos equivalentes por moneda sin datos confiables en este contexto
                        $totals['by_ccy_minor'][$ccy] = $totals['by_ccy_minor'][$ccy] ?? 0;
                    }
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
            'charge' => $chargeInfo,
            'totals' => $totals,
            'downloadUrl' => $downloadUrl,
        ]);
    }
}
