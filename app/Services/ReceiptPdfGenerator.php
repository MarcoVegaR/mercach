<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Concessionaire;
use App\Models\Local;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Support\FxConversionHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ReceiptPdfGenerator
{
    /**
     * Render PDF and store in local storage. Returns ['pdf_path','pdf_sha256','rendered_at'].
     *
     * @return array{pdf_path:string, pdf_sha256:string, rendered_at:string}
     */
    public function render(Receipt $receipt): array
    {
        $start = microtime(true);

        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail((int) $receipt->getAttribute('payment_id'));

        // Company account label
        $companyLabel = null;
        try {
            $accId = (int) ($payment->getAttribute('company_bank_account_id') ?? 0);
            if ($accId > 0) {
                /** @var null|CompanyBankAccount $acc */
                $acc = CompanyBankAccount::query()->with('bank')->find($accId);
                if ($acc) {
                    /** @var null|Bank $bank */
                    $bank = $acc->bank;
                    $bankName = $bank?->getAttribute('name');
                    $accountNumber = (string) ($acc->getAttribute('account_number') ?? '');
                    $masked = '';
                    if ($accountNumber !== '') {
                        $digits = preg_replace('/\D+/', '', $accountNumber);
                        $tail = $digits !== null && $digits !== '' ? substr($digits, -4) : substr($accountNumber, -4);
                        $masked = '•••• '.($tail ?: '');
                    }
                    $companyLabel = trim(($bankName ? ($bankName.' • ') : '').$masked) ?: null;
                }
            }
        } catch (\Throwable $e) {
        }

        // Debtor label
        $debtorLabel = null;
        try {
            $debtorType = strtoupper((string) ($payment->getAttribute('debtor_type') ?? ''));
            $debtorId = (int) ($payment->getAttribute('debtor_id') ?? 0);
            if ($debtorType === 'CONCESSIONAIRE' && $debtorId > 0) {
                /** @var null|Concessionaire $c */
                $c = Concessionaire::query()->find($debtorId);
                $debtorLabel = $c?->getAttribute('full_name');
            } elseif ($debtorType === 'LOCAL' && $debtorId > 0) {
                /** @var null|Local $l */
                $l = Local::query()->find($debtorId);
                if ($l) {
                    $code = (string) ($l->getAttribute('code') ?? '');
                    $name = (string) ($l->getAttribute('name') ?? '');
                    $debtorLabel = trim(($code ? $code.' • ' : '').$name) ?: null;
                }
            }
        } catch (\Throwable $e) {
        }

        // Origin bank name
        $originBankName = null;
        try {
            $originBankId = (int) ($payment->getAttribute('origin_bank_id') ?? 0);
            if ($originBankId > 0) {
                $originBank = Bank::query()->find($originBankId);
                $originBankName = $originBank?->getAttribute('name');
            }
        } catch (\Throwable $e) {
        }

        // Allocations breakdown (with charge info)
        $rows = PaymentAllocation::query()
            ->where('payment_id', (int) $payment->getKey())
            ->leftJoin('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
            ->orderBy('payment_allocations.id')
            ->get([
                'payment_allocations.charge_id',
                'payment_allocations.amount_bs_minor',
                'c.currency',
                'c.amount_minor',
                'c.period',
                'c.local_id',
                'c.kind',
                'c.condo_period_id',
            ]);

        // FX service to compute equivalents
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        $paidOn = new \DateTimeImmutable((string) ($payment->getAttribute('paid_on') ?? date('Y-m-d')));

        $fxHelper = null;
        try {
            $fxHelper = new FxConversionHelper($fx);
        } catch (\Throwable $e) {
        }

        $items = [];
        $totals = [
            'bs_minor' => 0,
            'by_ccy_minor' => [], // ['USD'=>int_minor, 'EUR'=>int_minor]
        ];

        // Pre-compute outstanding balances (in VES minor) per charge, using centralized FX helper
        $outstandingByChargeId = [];
        if ($fxHelper) {
            try {
                $chargeIds = $rows->pluck('charge_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();
                if ($chargeIds->isNotEmpty()) {
                    /** @var \Illuminate\Support\Collection<int, \App\Models\Charge> $charges */
                    $charges = \App\Models\Charge::query()
                        ->whereIn('id', $chargeIds->all())
                        ->get();
                    if ($charges->isNotEmpty()) {
                        $outstandingByChargeId = $fxHelper->chargesOutstandingVesBatch($charges, $paidOn);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        foreach ($rows as $r) {
            $chargeId = (int) $r->getAttribute('charge_id');
            $appliedBsMinor = (int) $r->getAttribute('amount_bs_minor');
            $currency = strtoupper((string) ($r->getAttribute('currency') ?? ''));
            $chargeAmountMinor = (int) ($r->getAttribute('amount_minor') ?? 0);
            $period = (string) ($r->getAttribute('period') ?? '');
            $kind = (string) ($r->getAttribute('kind') ?? '');
            $condoPeriodId = $r->getAttribute('condo_period_id');

            $appliedCcyMinor = null;
            $chargeBsEquivMinor = null;
            $rateToVes = null;
            $balanceCurrencyMinor = null;
            $outstandingBsMinor = null;

            // Get outstanding first to determine if charge is fully paid
            if (array_key_exists($chargeId, $outstandingByChargeId)) {
                $outstandingBsMinor = (int) $outstandingByChargeId[$chargeId];
            }

            if ($currency === 'USD' || $currency === 'EUR') {
                $rate = $fx->resolveAt($currency, $paidOn);
                $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                if ($rateToVes && $rateToVes > 0) {
                    $chargeBsEquivMinor = $this->fxMinorFromCcyToVes((int) $chargeAmountMinor, (float) $rateToVes);

                    // Calculate balance in currency
                    if (! is_null($outstandingBsMinor)) {
                        // Allow 1 minor tolerance for FX rounding when determining if fully paid
                        if ($outstandingBsMinor <= 1) {
                            $balanceCurrencyMinor = 0;
                        } else {
                            $balanceCurrencyMinor = $this->fxMinorFromVesToCcy((int) $outstandingBsMinor, (float) $rateToVes);
                        }
                    }

                    // For totals: use original charge amount if fully paid, otherwise convert from Bs
                    if ($balanceCurrencyMinor === 0) {
                        // Fully paid - use original charge amount (avoids rounding errors)
                        $appliedCcyMinor = (int) $chargeAmountMinor;
                    } else {
                        // Partially paid - convert from Bs
                        $appliedCcyMinor = $this->fxMinorFromVesToCcy((int) $appliedBsMinor, (float) $rateToVes);
                    }
                    $totals['by_ccy_minor'][$currency] = ($totals['by_ccy_minor'][$currency] ?? 0) + $appliedCcyMinor;
                }
            } elseif ($currency === 'VES') {
                $appliedCcyMinor = (int) $appliedBsMinor; // same units
                $chargeBsEquivMinor = (int) $chargeAmountMinor;
                $balanceCurrencyMinor = $outstandingBsMinor;
            }

            if (is_null($balanceCurrencyMinor) && ! is_null($appliedCcyMinor)) {
                $balanceCurrencyMinor = max(0, (int) $chargeAmountMinor - (int) $appliedCcyMinor);
            }

            // Build concept with local code
            $localCode = null;
            try {
                $localId = (int) ($r->getAttribute('local_id') ?? 0);
                if ($localId > 0) {
                    $loc = Local::query()->find($localId);
                    $localCode = $loc?->getAttribute('code');
                }
            } catch (\Throwable $e) {
            }

            $concept = 'Tasa de Uso';
            if (! empty($condoPeriodId) || str_contains(strtoupper($kind), 'CONDO')) {
                $concept = 'Gastos Comunes';
            }
            if ($localCode) {
                $concept .= ' • '.$localCode;
            }

            $totals['bs_minor'] += $appliedBsMinor;

            $items[] = [
                'charge_id' => $chargeId,
                'period' => $period,
                'concept' => $concept,
                'kind' => $kind,
                'currency' => $currency,
                'charge_amount_minor' => $chargeAmountMinor,
                'charge_bs_equiv_minor' => $chargeBsEquivMinor,
                'applied_bs_minor' => $appliedBsMinor,
                'applied_currency_minor' => $appliedCcyMinor,
                'balance_currency_minor' => $balanceCurrencyMinor,
            ];
        }

        $ratesLegend = [];
        $ratesMeta = [
            'tz' => (string) config('app.timezone', 'America/Caracas'),
            'rounding' => 'Tasa a 4 decimales; importes a 2 decimales',
        ];
        foreach (['USD', 'EUR'] as $ccy) {
            $rate = $fx->resolveAt($ccy, $paidOn);
            if ($rate) {
                $ratesLegend[$ccy] = (float) $rate->getAttribute('rate_to_ves');
                $pub = $rate->getAttribute('published_at');
                $pubStr = null;
                if ($pub) {
                    try {
                        $pubStr = \Illuminate\Support\Carbon::parse((string) $pub)->setTimezone((string) $ratesMeta['tz'])->format('Y-m-d H:i');
                    } catch (\Throwable $e) {
                    }
                }
                $ratesMeta[$ccy] = [
                    'rate' => (float) $rate->getAttribute('rate_to_ves'),
                    'published_at' => $pubStr,
                    'source' => (string) ($rate->getAttribute('source') ?? ''),
                ];
            }
        }

        $hmacSigFull = null;
        $hmacSigShort = null;
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
            $sigRawFull = hash_hmac('sha256', (string) $data, (string) $rawKey, true);
            $hmacSigFull = rtrim(strtr(base64_encode($sigRawFull), '+/', '-_'), '=');
            $sigRawShort = substr($sigRawFull, 0, 16);
            $hmacSigShort = rtrim(strtr(base64_encode($sigRawShort), '+/', '-_'), '=');
        } catch (\Throwable $e) {
        }
        $params = ['token' => (string) $receipt->getAttribute('public_token')];
        if (! empty($hmacSigShort)) {
            $params['sig'] = $hmacSigShort;
        }
        $verifyUrl = URL::route('receipts.public.short', $params);

        $qrPngBase64 = null;
        $qrMime = 'image/png';
        $qrBackend = null;
        $qrCached = false;
        $verifyUrlLen = strlen($verifyUrl);
        $qrBinary = null;
        $qrCachePath = null;

        try {
            $diskLocal = Storage::disk('local');
            $qrCacheDir = 'receipts/qr';
            $qrCacheKey = sha1($verifyUrl);
            $qrCachePath = $qrCacheDir.'/'.$qrCacheKey.'.png';
            if ($diskLocal->exists($qrCachePath)) {
                $bin = $diskLocal->get($qrCachePath);
                $qrBinary = $bin;
                $qrPngBase64 = base64_encode($bin);
                $qrMime = 'image/png';
                $qrBackend = 'cache';
                $qrCached = true;
            }
        } catch (\Throwable $e) {
        }

        if (! $qrPngBase64) {
            try {
                /** @var \Illuminate\Contracts\Filesystem\Filesystem $diskLocal */
                $diskLocal = isset($diskLocal) ? $diskLocal : Storage::disk('local');
                $diskLocal->makeDirectory('receipts/qr');
            } catch (\Throwable $e) {
            }

            try {
                if (class_exists(\BaconQrCode\Writer::class)) {
                    $backend = null;
                    if (class_exists(\BaconQrCode\Renderer\Image\SvgImageBackEnd::class)) {
                        $backend = new \BaconQrCode\Renderer\Image\SvgImageBackEnd;
                        $qrBackend = 'bacon_svg';
                        $qrMime = 'image/svg+xml';
                    }
                    if ($backend) {
                        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(260, 4),
                            $backend
                        );
                        $writer = new \BaconQrCode\Writer($renderer);
                        $bin = $writer->writeString($verifyUrl);
                        $qrBinary = $bin;
                        $qrPngBase64 = base64_encode($bin);
                    }
                }
            } catch (\Throwable $e) {
            }

            if (! $qrPngBase64) {
                try {
                    if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                        $bin = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(260)->margin(4)->generate($verifyUrl);
                        $qrBinary = $bin;
                        $qrPngBase64 = base64_encode($bin);
                        $qrMime = 'image/png';
                        $qrBackend = $qrBackend ?: 'simple_qrcode';
                    }
                } catch (\Throwable $e) {
                }
            }

            if ($qrBinary !== null && $qrCachePath !== null && $qrMime === 'image/png') {
                try {
                    /** @var \Illuminate\Contracts\Filesystem\Filesystem $diskLocal */
                    $diskLocal = isset($diskLocal) ? $diskLocal : Storage::disk('local');
                    $diskLocal->put($qrCachePath, $qrBinary);
                } catch (\Throwable $e) {
                }
            }
        }

        $marketName = null;
        $marketAddress = null;
        try {
            $mid = $receipt->getAttribute('market_id');
            if (is_numeric($mid) && (int) $mid > 0) {
                $m = Market::query()->find((int) $mid);
                if ($m) {
                    $marketName = (string) ($m->getAttribute('name') ?? '');
                    $marketAddress = (string) ($m->getAttribute('address') ?? '');
                }
            }
        } catch (\Throwable $e) {
        }

        // Optional letterhead background from storage/app/branding/letterhead.(png|jpg|svg)
        $letterheadBase64 = null;
        $letterheadMime = null;
        try {
            $diskLocal = Storage::disk('local');
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $p = 'branding/letterhead.'.$ext;
                if ($diskLocal->exists($p)) {
                    $bin = $diskLocal->get($p);
                    $letterheadBase64 = base64_encode($bin);
                    $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                    break;
                }
            }
        } catch (\Throwable $e) {
        }
        // Fallback: direct filesystem read (storage/app/branding)
        if (empty($letterheadBase64)) {
            try {
                $dir = storage_path('app/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'letterhead.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $letterheadBase64 = base64_encode($bin);
                            $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        // Second fallback: storage/app/private/branding (matches config('filesystems.disks.local.root'))
        if (empty($letterheadBase64)) {
            try {
                $dir = storage_path('app/private/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'letterhead.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $letterheadBase64 = base64_encode($bin);
                            $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (empty($letterheadBase64)) {
            try {
                $uploadsDisk = (string) config('filesystems.uploads_disk', 'public');
                $diskUploads = Storage::disk($uploadsDisk);
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $p = 'branding/letterhead.'.$ext;
                    if ($diskUploads->exists($p)) {
                        $bin = $diskUploads->get($p);
                        $letterheadBase64 = base64_encode($bin);
                        $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                        break;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (empty($letterheadBase64)) {
            try {
                foreach ([public_path('branding'), public_path()] as $dir) {
                    foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                        $fp = $dir.DIRECTORY_SEPARATOR.'letterhead.'.$ext;
                        if (is_file($fp) && is_readable($fp)) {
                            $bin = @file_get_contents($fp);
                            if ($bin !== false) {
                                $letterheadBase64 = base64_encode($bin);
                                $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                                break 2;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // Optional logo (top-right) from storage/app/branding/logo.(png|jpg|svg)
        $logoBase64 = null;
        $logoMime = null;
        try {
            $diskLocal = Storage::disk('local');
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $p = 'branding/logo.'.$ext;
                if ($diskLocal->exists($p)) {
                    $bin = $diskLocal->get($p);
                    $logoBase64 = base64_encode($bin);
                    $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                    break;
                }
            }
        } catch (\Throwable $e) {
        }
        // Fallback: direct filesystem read (storage/app/branding)
        if (empty($logoBase64)) {
            try {
                $dir = storage_path('app/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'logo.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $logoBase64 = base64_encode($bin);
                            $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        // Second fallback: storage/app/private/branding
        if (empty($logoBase64)) {
            try {
                $dir = storage_path('app/private/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'logo.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $logoBase64 = base64_encode($bin);
                            $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (empty($logoBase64)) {
            try {
                $uploadsDisk = (string) config('filesystems.uploads_disk', 'public');
                $diskUploads = Storage::disk($uploadsDisk);
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $p = 'branding/logo.'.$ext;
                    if ($diskUploads->exists($p)) {
                        $bin = $diskUploads->get($p);
                        $logoBase64 = base64_encode($bin);
                        $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                        break;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (empty($logoBase64)) {
            try {
                foreach ([public_path('branding'), public_path()] as $dir) {
                    foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                        $fp = $dir.DIRECTORY_SEPARATOR.'logo.'.$ext;
                        if (is_file($fp) && is_readable($fp)) {
                            $bin = @file_get_contents($fp);
                            if ($bin !== false) {
                                $logoBase64 = base64_encode($bin);
                                $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                                break 2;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $scope = strtoupper((string) ($receipt->getAttribute('scope') ?? 'PAYMENT'));
        $concept = strtoupper((string) ($receipt->getAttribute('concept') ?? ''));
        $builtAt = now()->toDateTimeString();
        // Display receipt number: <seq padded>-<MM>-<YYYY>
        try {
            $issuedAt = $receipt->getAttribute('issued_at');
            $dt = $issuedAt ? \Illuminate\Support\Carbon::parse((string) $issuedAt) : now();
        } catch (\Throwable $e) {
            $dt = now();
        }
        $mm = $dt->format('m');
        $yyyy = $dt->format('Y');
        $seqRaw = $receipt->getAttribute('sequence_number') ?? $receipt->getAttribute('correlative') ?? $receipt->getKey();
        $seqNum = is_numeric($seqRaw) ? (int) $seqRaw : (int) $receipt->getKey();
        $seqPadded = str_pad((string) $seqNum, 6, '0', STR_PAD_LEFT);
        $displayReceiptNo = $seqPadded.'-'.$mm.'-'.$yyyy;

        if ($scope === 'CHARGE') {
            $chargeId = (int) ($receipt->getAttribute('charge_id') ?? 0);
            $charge = null;
            if ($chargeId > 0) {
                $charge = \App\Models\Charge::query()->find($chargeId);
            }

            $chargeCurrency = (string) ($charge?->getAttribute('currency') ?? '');
            $chargeAmountMinor = (int) ($charge?->getAttribute('amount_minor') ?? 0);
            $chargePeriod = (string) ($charge?->getAttribute('period') ?? '');
            $chargeKind = (string) ($charge?->getAttribute('kind') ?? '');

            $appliedBsMinor = (int) PaymentAllocation::query()
                ->where('payment_id', (int) $payment->getKey())
                ->where('charge_id', $chargeId)
                ->sum('amount_bs_minor');

            $appliedCurrencyMinor = null;
            $chargeRateToVes = null;
            $chargeBsEquivMinor = null;
            if (in_array($chargeCurrency, ['USD', 'EUR'], true)) {
                $rate = $fx->resolveAt($chargeCurrency, $paidOn);
                $ves = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                $chargeRateToVes = $ves;
                if ($ves && $ves > 0) {
                    $appliedCurrencyMinor = $this->fxMinorFromVesToCcy((int) $appliedBsMinor, (float) $ves);
                    $chargeBsEquivMinor = $this->fxMinorFromCcyToVes((int) $chargeAmountMinor, (float) $ves);
                }
            }

            // Compute remaining balance considering ALL allocations (not only this payment)
            $appliedCurrencyAllMinor = 0;
            try {
                $allocAll = \App\Models\PaymentAllocation::query()
                    ->where('charge_id', $chargeId)
                    ->leftJoin('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
                    ->get(['payment_allocations.amount_bs_minor', 'p.paid_on']);
                foreach ($allocAll as $al) {
                    $amtBs = (int) ($al->getAttribute('amount_bs_minor') ?? 0);
                    $paidEachRaw = (string) ($al->getAttribute('paid_on') ?? '');
                    $paidEach = $paidEachRaw !== '' ? new \DateTimeImmutable($paidEachRaw) : $paidOn;
                    if (in_array($chargeCurrency, ['USD', 'EUR'], true)) {
                        $rateEach = $fx->resolveAt($chargeCurrency, $paidEach);
                        $vesEach = $rateEach ? (float) $rateEach->getAttribute('rate_to_ves') : null;
                        if ($vesEach && $vesEach > 0) {
                            $appliedCurrencyAllMinor += $this->fxMinorFromVesToCcy((int) $amtBs, (float) $vesEach);
                        }
                    } elseif ($chargeCurrency === 'VES') {
                        $appliedCurrencyAllMinor += $amtBs;
                    }
                }
            } catch (\Throwable $e) {
            }

            $balanceCurrencyMinor = max(0, (int) $chargeAmountMinor - (int) $appliedCurrencyAllMinor);
            $balanceBsMinor = null;
            if ($chargeRateToVes && $chargeRateToVes > 0) {
                $balanceBsMinor = $this->fxMinorFromCcyToVes((int) $balanceCurrencyMinor, (float) $chargeRateToVes);
            }

            // Local label/name from charge
            $localLabel = null;
            $localName = null;
            try {
                $lid = (int) ($charge?->getAttribute('local_id') ?? 0);
                if ($lid > 0) {
                    $loc = Local::query()->find($lid);
                    if ($loc) {
                        $code = (string) ($loc->getAttribute('code') ?? '');
                        $name = (string) ($loc->getAttribute('name') ?? '');
                        $localLabel = trim(($code ? $code.' • ' : '').$name) ?: null;
                        $localName = $name ?: null;
                    }
                }
            } catch (\Throwable $e) {
            }

            // Amount in words (prefer Bs applied)
            $amountLettersBs = $this->amountToWordsEs((int) $appliedBsMinor, 'VES');
            $amountLettersCcy = null;
            if (! is_null($appliedCurrencyMinor) && in_array($chargeCurrency, ['USD', 'EUR'], true)) {
                $amountLettersCcy = $this->amountToWordsEs((int) $appliedCurrencyMinor, $chargeCurrency);
            }

            if ($concept === 'GC') {
                $condoPeriodId = $charge?->getAttribute('condo_period_id');
                $gc = [
                    'items' => [],
                    'totals' => [
                        'usd_minor' => 0,
                        'bs_minor' => 0,
                    ],
                    'coef' => null,
                    'area_local' => null,
                    'area_total' => null,
                ];
                if (is_numeric($condoPeriodId) && (int) $condoPeriodId > 0) {
                    $localId = (int) ($charge?->getAttribute('local_id') ?? 0);
                    $marketId = null;
                    if ($localId > 0) {
                        $loc = Local::query()->find($localId);
                        $marketId = $loc?->getAttribute('market_id');
                    }

                    // Determine included locals set for this period (supports exclusions-only model)
                    $includedLocalIds = [];
                    $hasAny = \App\Models\CondoParticipant::query()
                        ->where('condo_period_id', (int) $condoPeriodId)
                        ->exists();
                    if ($hasAny) {
                        $explicitIncluded = \App\Models\CondoParticipant::query()
                            ->where('condo_period_id', (int) $condoPeriodId)
                            ->where('included', true)
                            ->pluck('local_id')
                            ->filter()
                            ->values()
                            ->all();
                        if (! empty($explicitIncluded)) {
                            $includedLocalIds = $explicitIncluded;
                        } else {
                            $excluded = \App\Models\CondoParticipant::query()
                                ->where('condo_period_id', (int) $condoPeriodId)
                                ->where('included', false)
                                ->pluck('local_id')
                                ->filter()
                                ->values()
                                ->all();
                            if ($marketId) {
                                $all = Local::query()
                                    ->where('market_id', (int) $marketId)
                                    ->where('is_active', true)
                                    ->pluck('id')
                                    ->values()
                                    ->all();
                                $includedLocalIds = array_values(array_diff($all, $excluded));
                            }
                        }
                    } else {
                        if ($marketId) {
                            $includedLocalIds = Local::query()
                                ->where('market_id', (int) $marketId)
                                ->where('is_active', true)
                                ->pluck('id')
                                ->values()
                                ->all();
                        }
                    }

                    $totalArea = 0.0;
                    $localArea = 0.0;
                    if (! empty($includedLocalIds)) {
                        $areas = Local::query()
                            ->whereIn('id', $includedLocalIds)
                            ->get(['id', 'area_m2'])
                            ->keyBy('id');
                        foreach ($areas as $lid => $row) {
                            $a = (float) ($row->getAttribute('area_m2') ?? 0);
                            $totalArea += $a;
                        }
                        if (isset($areas[$localId])) {
                            $localArea = (float) ($areas[$localId]->getAttribute('area_m2') ?? 0);
                        }
                    }
                    $gc['area_local'] = $localArea;
                    $gc['area_total'] = $totalArea;
                    $gc['coef'] = ($totalArea > 0 ? ($localArea / $totalArea) : null);

                    $coef = is_null($gc['coef']) ? 0.0 : (float) $gc['coef'];
                    $exp = \App\Models\CondoExpense::query()
                        ->leftJoin('expense_types as et', 'et.id', '=', 'condo_expenses.expense_type_id')
                        ->where('condo_period_id', (int) $condoPeriodId)
                        ->where('condo_expenses.is_active', true)
                        ->orderBy('et.name')
                        ->get(['condo_expenses.amount_usd_minor', 'condo_expenses.invoice_number', 'et.name as type_name']);
                    foreach ($exp as $e) {
                        $amount = (int) $e->getAttribute('amount_usd_minor');
                        $prorated = (int) round($amount * $coef);
                        $proratedBs = null;
                        if ($chargeRateToVes && $chargeRateToVes > 0) {
                            $proratedBs = $this->fxMinorFromCcyToVes((int) $prorated, (float) $chargeRateToVes);
                        }
                        $gc['items'][] = [
                            'type' => (string) ($e->getAttribute('type_name') ?? ''),
                            'amount_usd_minor' => $prorated,
                            'amount_bs_minor' => $proratedBs,
                            'invoice' => (string) ($e->getAttribute('invoice_number') ?? ''),
                        ];
                        $gc['totals']['usd_minor'] += $prorated;
                        if (! is_null($proratedBs)) {
                            $gc['totals']['bs_minor'] += $proratedBs;
                        }
                    }

                    // Align prorrated sum to the exact charge amount (minor rounding adjustments)
                    if ($gc['totals']['usd_minor'] !== $chargeAmountMinor && ! empty($gc['items'])) {
                        $diff = (int) $chargeAmountMinor - (int) $gc['totals']['usd_minor'];
                        $lastIdx = count($gc['items']) - 1;
                        $gc['items'][$lastIdx]['amount_usd_minor'] = max(0, ((int) $gc['items'][$lastIdx]['amount_usd_minor']) + $diff);
                        $gc['totals']['usd_minor'] = (int) $gc['totals']['usd_minor'] + $diff;
                    }

                    // Fallback: if no FX available, distribute Applied (Bs) proportionally by USD items
                    if ((! $chargeRateToVes || $chargeRateToVes <= 0) && $appliedBsMinor > 0 && (int) $gc['totals']['usd_minor'] > 0 && ! empty($gc['items'])) {
                        $sumAssigned = 0;
                        $n = count($gc['items']);
                        foreach ($gc['items'] as $idx => &$row) {
                            $usdMinor = (int) $row['amount_usd_minor'];
                            if ($idx < $n - 1) {
                                $share = (int) floor(($appliedBsMinor * $usdMinor) / max(1, (int) $gc['totals']['usd_minor']));
                                $row['amount_bs_minor'] = $share;
                                $sumAssigned += $share;
                            } else {
                                $row['amount_bs_minor'] = max(0, (int) $appliedBsMinor - (int) $sumAssigned);
                            }
                        }
                        unset($row);
                        $gc['totals']['bs_minor'] = (int) $appliedBsMinor;
                    }

                    // Align Bs total to applied Bs (or rate-implied) to avoid rounding drifts
                    if (! empty($gc['items']) && $chargeRateToVes && $chargeRateToVes > 0) {
                        $desiredBsTotal = $appliedBsMinor > 0
                            ? (int) $appliedBsMinor
                            : $this->fxMinorFromCcyToVes((int) $gc['totals']['usd_minor'], (float) $chargeRateToVes);
                        if ((int) $gc['totals']['bs_minor'] !== $desiredBsTotal) {
                            $diffBs = $desiredBsTotal - (int) $gc['totals']['bs_minor'];
                            $lastIdx = count($gc['items']) - 1;
                            $gc['items'][$lastIdx]['amount_bs_minor'] = max(0, ((int) ($gc['items'][$lastIdx]['amount_bs_minor'] ?? 0)) + $diffBs);
                            $gc['totals']['bs_minor'] = (int) $gc['totals']['bs_minor'] + $diffBs;
                        }
                    }
                }

                $html = view('pdf.receipt_common_expenses', [
                    'receipt' => $receipt,
                    'payment' => $payment,
                    'company_label' => $companyLabel,
                    'debtor_label' => $debtorLabel,
                    'verify_url' => $verifyUrl,
                    'qr_png_base64' => $qrPngBase64,
                    'qr_mime' => $qrMime,
                    'letterhead_base64' => $letterheadBase64,
                    'letterhead_mime' => $letterheadMime,
                    'logo_base64' => $logoBase64,
                    'logo_mime' => $logoMime,
                    'built_at' => $builtAt,
                    'display_receipt_no' => $displayReceiptNo,
                    'market_name' => $marketName,
                    'market_address' => $marketAddress,
                    'charge' => [
                        'id' => $chargeId,
                        'currency' => $chargeCurrency,
                        'amount_minor' => $chargeAmountMinor,
                        'bs_equiv_minor' => $chargeBsEquivMinor,
                        'period' => $chargePeriod,
                        'kind' => $chargeKind,
                    ],
                    'applied' => [
                        'bs_minor' => $appliedBsMinor,
                        'currency_minor' => $appliedCurrencyMinor,
                    ],
                    'balance' => [
                        'bs_minor' => $balanceBsMinor,
                        'currency_minor' => $balanceCurrencyMinor,
                    ],
                    'gc' => $gc,
                    'rates' => $ratesLegend,
                    'rates_meta' => $ratesMeta,
                    'local_label' => $localLabel,
                    'local_name' => $localName,
                    'amount_letters_bs' => $amountLettersBs,
                    'amount_letters_ccy' => $amountLettersCcy,
                    'receipt_type' => 'GASTOS COMUNES',
                ])->render();
            } else {
                $html = view('pdf.receipt_use_fee', [
                    'receipt' => $receipt,
                    'payment' => $payment,
                    'company_label' => $companyLabel,
                    'debtor_label' => $debtorLabel,
                    'verify_url' => $verifyUrl,
                    'qr_png_base64' => $qrPngBase64,
                    'qr_mime' => $qrMime,
                    'letterhead_base64' => $letterheadBase64,
                    'letterhead_mime' => $letterheadMime,
                    'logo_base64' => $logoBase64,
                    'logo_mime' => $logoMime,
                    'built_at' => $builtAt,
                    'display_receipt_no' => $displayReceiptNo,
                    'market_name' => $marketName,
                    'market_address' => $marketAddress,
                    'charge' => [
                        'id' => $chargeId,
                        'currency' => $chargeCurrency,
                        'amount_minor' => $chargeAmountMinor,
                        'bs_equiv_minor' => $chargeBsEquivMinor,
                        'period' => $chargePeriod,
                        'kind' => $chargeKind,
                    ],
                    'applied' => [
                        'bs_minor' => $appliedBsMinor,
                        'currency_minor' => $appliedCurrencyMinor,
                    ],
                    'balance' => [
                        'bs_minor' => $balanceBsMinor,
                        'currency_minor' => $balanceCurrencyMinor,
                    ],
                    'rates' => $ratesLegend,
                    'rates_meta' => $ratesMeta,
                    'local_label' => $localLabel,
                    'local_name' => $localName,
                    'amount_letters_bs' => $amountLettersBs,
                    'amount_letters_ccy' => $amountLettersCcy,
                    'receipt_type' => 'TASA POR USO DE BIEN PÚBLICO',
                ])->render();
            }
        } else {
            $html = view('pdf.receipt', [
                'receipt' => $receipt,
                'payment' => $payment,
                'company_label' => $companyLabel,
                'debtor_label' => $debtorLabel,
                'origin_bank_name' => $originBankName,
                'items' => $items,
                'totals' => $totals,
                'rates' => $ratesLegend,
                'verify_url' => $verifyUrl,
                'qr_png_base64' => $qrPngBase64,
                'qr_mime' => $qrMime,
                'letterhead_base64' => $letterheadBase64,
                'letterhead_mime' => $letterheadMime,
                'logo_base64' => $logoBase64,
                'logo_mime' => $logoMime,
                'built_at' => $builtAt,
                'market_name' => $marketName,
                'market_address' => $marketAddress,
            ])->render();
        }

        // Render PDF using available engine
        $raw = null;
        $pdfEngine = null;
        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4');
            $raw = $pdf->output();
            $pdfEngine = 'barryvdh/laravel-dompdf';
        } elseif (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            $raw = $dompdf->output();
            $pdfEngine = 'dompdf';
        } else {
            throw new \RuntimeException('PDF library not installed. Please require dompdf/dompdf or barryvdh/laravel-dompdf.');
        }

        $disk = Storage::disk('local');
        $dir = 'receipts/'.date('Y');
        $name = (string) $receipt->getAttribute('receipt_number');
        $path = $dir.'/'.$name.'.pdf';

        $disk->makeDirectory('receipts');
        $disk->makeDirectory($dir);
        $ok = $disk->put($path, $raw);
        if (! $ok || ! $disk->exists($path)) {
            \Log::error('receipt.pdf.write_failed', [
                'path' => $disk->path($path),
            ]);
            throw new \RuntimeException('Unable to write PDF to storage');
        }

        $sha = hash('sha256', $raw);
        $renderedAt = now()->toDateTimeString();

        return [
            'pdf_path' => $path,
            'pdf_sha256' => $sha,
            'rendered_at' => $renderedAt,
        ];
    }

    private function amountToWordsEs(int $minor, string $currency): string
    {
        $units = (int) floor($minor / 100);
        $cents = (int) ($minor % 100);
        $words = $this->numToWordsEs($units);
        $ccy = strtoupper($currency);
        $ccyName = $ccy === 'USD' ? 'DÓLARES' : ($ccy === 'EUR' ? 'EUROS' : 'BOLÍVARES');

        return trim(($words ?: 'CERO').' CON '.str_pad((string) $cents, 2, '0', STR_PAD_LEFT).'/100 '.$ccyName);
    }

    private function numToWordsEs(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        $u = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $tens = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $hund = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $toUnder100 = function (int $x) use ($u, $tens): string {
            if ($x < 20) {
                return $u[$x];
            }
            if ($x < 30) {
                if ($x === 20) {
                    return 'VEINTE';
                }
                $r = $x - 20;
                // Accents for 22, 23, 26
                if ($r === 2) {
                    return 'VEINTIDÓS';
                }
                if ($r === 3) {
                    return 'VEINTITRÉS';
                }
                if ($r === 6) {
                    return 'VEINTISÉIS';
                }

                return 'VEINTI'.$u[$r];
            }
            $d = intdiv($x, 10);
            $r = $x % 10;

            return $r === 0 ? $tens[$d] : $tens[$d].' Y '.$u[$r];
        };

        $toUnder1000 = function (int $x) use ($hund, $toUnder100): string {
            if ($x === 100) {
                return 'CIEN';
            }
            $c = intdiv($x, 100);
            $r = $x % 100;
            $h = $c > 0 ? $hund[$c].($r ? ' ' : '') : '';

            return trim($h.$toUnder100($r));
        };

        $parts = [];
        $millones = intdiv($n, 1000000);
        $n %= 1000000;
        $miles = intdiv($n, 1000);
        $n %= 1000;
        $resto = $n;
        if ($millones > 0) {
            $parts[] = $millones === 1 ? 'UN MILLÓN' : $this->numToWordsEs($millones).' MILLONES';
        }
        if ($miles > 0) {
            $parts[] = $miles === 1 ? 'MIL' : $toUnder1000($miles).' MIL';
        }
        if ($resto > 0) {
            $parts[] = $toUnder1000($resto);
        }
        $txt = trim(implode(' ', $parts));
        $txt = preg_replace('/\bUNO\b/u', 'UN', (string) $txt) ?: $txt;

        return $txt;
    }

    private function fxMinorFromVesToCcy(int $vesMinor, float $rate): int
    {
        if (! ($rate > 0)) {
            return 0;
        }

        // Same integer-friendly policy as FxConversionHelper::fromVes:
        // Bs (2dp) / rate (2dp) => 4dp, then truncate back to 2dp via intdiv.
        $prod = (int) round(($vesMinor * 100) / $rate);

        return (int) intdiv($prod, 100);
    }

    private function fxMinorFromCcyToVes(int $ccyMinor, float $rate): int
    {
        if (! ($rate > 0)) {
            return 0;
        }
        if (function_exists('bcdiv') && function_exists('bcmul')) {
            $ccyUnits = bcdiv((string) $ccyMinor, '100', 8);
            $vesUnits = bcmul($ccyUnits, (string) $rate, 8);
            $vesMinorStr = bcmul($vesUnits, '100', 8);

            return (int) ((float) $vesMinorStr);
        }

        // Truncate instead of round
        $prod = (int) round($ccyMinor * ($rate * 100));

        return (int) intdiv($prod, 100);
    }
}
