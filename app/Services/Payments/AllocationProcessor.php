<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\ChargeStatusCode;
use App\Exceptions\DomainActionException;
use App\Models\Charge;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\FxConversionHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Procesador de asignaciones de pagos a cargos.
 *
 * Extrae la lógica compleja de PaymentService::storeAllocations()
 * para mejorar testabilidad y mantenibilidad.
 */
class AllocationProcessor
{
    public function __construct(
        private FxConversionHelper $fxHelper,
    ) {}

    /**
     * Resultado del procesamiento de asignaciones.
     *
     * @param  array<int, array{charge_id: int, amount_bs_minor: int}>  $items
     * @param  array<string, mixed>  $options
     * @return array{
     *     applied_from_payment: int,
     *     applied_from_credit: int,
     *     after_available: int,
     *     did_set_applied: bool,
     *     created_credit: bool,
     *     touched_charges: list<int>
     * }
     */
    public function process(Payment $payment, array $items, array $options = []): array
    {
        $useCredit = (bool) ($options['use_credit'] ?? false);
        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));

        // Normalizar items
        $normalized = $this->normalizeItems($items);
        if (empty($normalized)) {
            throw new DomainActionException('No hay items válidos para asignar.');
        }

        // Pre-cargar cargos
        $chargeIds = array_column($normalized, 'charge_id');
        $charges = Charge::query()
            ->whereIn('id', $chargeIds)
            ->get(['id', 'debtor_type', 'debtor_id', 'local_id', 'charge_status_id', 'currency', 'amount_minor', 'amount_bs_minor_issued'])
            ->keyBy('id');

        // Validar items contra cargos
        $this->validateItems($payment, $normalized, $charges, $paidOn);

        // Determinar dominio de locales permitidos
        $allowedLocalIds = $this->resolveAllowedLocalIds($payment, $paidOn);

        // Validar pertenencia al dominio
        $this->validateDebtorDomain($payment, $normalized, $charges, $allowedLocalIds);

        // Ejecutar asignaciones en transacción
        return DB::transaction(function () use ($payment, $normalized, $charges, $useCredit, $paidOn) {
            return $this->executeAllocations($payment, $normalized, $charges, $useCredit, $paidOn);
        });
    }

    /**
     * Normalizar y ordenar items.
     *
     * @param  array<int, array{charge_id: int, amount_bs_minor: int}>  $items
     * @return list<array{charge_id: int, amount_bs_minor: int<1, max>}>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = array_map(static fn ($it) => [
            'charge_id' => (int) $it['charge_id'],
            'amount_bs_minor' => (int) $it['amount_bs_minor'],
        ], $items);

        // Filtrar items con monto <= 0
        $normalized = array_filter($normalized, static fn ($it) => $it['amount_bs_minor'] > 0);

        // Ordenar por charge_id para consistencia
        usort($normalized, static fn ($a, $b) => $a['charge_id'] <=> $b['charge_id']);

        /** @var list<array{charge_id: int, amount_bs_minor: int<1, max>}> */
        return $normalized;
    }

    /**
     * Validar que los items sean válidos.
     *
     * @param  list<array{charge_id: int, amount_bs_minor: int<1, max>}>  $items
     * @param  Collection<int, Charge>  $charges
     */
    private function validateItems(Payment $payment, array $items, Collection $charges, Carbon $paidOn): void
    {
        $collectableIds = ChargeStatusCode::collectableIds();
        $errors = [];

        foreach ($items as $it) {
            $cid = $it['charge_id'];
            $charge = $charges->get($cid);

            if (! $charge) {
                $errors[] = "Cargo {$cid} no existe.";

                continue;
            }

            $statusId = (int) ($charge->getAttribute('charge_status_id') ?? 0);
            if (! empty($collectableIds) && ! in_array($statusId, $collectableIds, true)) {
                $errors[] = "Cargo {$cid} no está en estado cobrable.";
            }
        }

        if (! empty($errors)) {
            throw new DomainActionException(implode(' ', $errors));
        }
    }

    /**
     * Resolver IDs de locales permitidos según el deudor del pago.
     *
     * @return list<int>
     */
    private function resolveAllowedLocalIds(Payment $payment, Carbon $paidOn): array
    {
        if ((string) $payment->getAttribute('debtor_type') !== 'CONCESSIONAIRE') {
            return [];
        }

        $concessionaireId = (int) $payment->getAttribute('debtor_id');

        return DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', $concessionaireId)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $paidOn->toDateString())
            ->whereIn('cs.code', \App\Enums\ContractStatusCode::activeForCharges())
            ->pluck('l.id')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Validar que los cargos pertenezcan al dominio del deudor.
     *
     * @param  list<array{charge_id: int, amount_bs_minor: int<1, max>}>  $items
     * @param  Collection<int, Charge>  $charges
     * @param  list<int>  $allowedLocalIds
     */
    private function validateDebtorDomain(Payment $payment, array $items, Collection $charges, array $allowedLocalIds): void
    {
        $errors = [];
        $paymentDebtorType = (string) $payment->getAttribute('debtor_type');
        $paymentDebtorId = (int) $payment->getAttribute('debtor_id');

        foreach ($items as $it) {
            $charge = $charges->get($it['charge_id']);
            if (! $charge) {
                continue;
            }

            $cDebtorType = (string) ($charge->getAttribute('debtor_type') ?? '');
            $cDebtorId = (int) ($charge->getAttribute('debtor_id') ?? 0);
            $cid = $it['charge_id'];

            if ($paymentDebtorType === 'LOCAL') {
                if (! ($cDebtorType === 'LOCAL' && $cDebtorId === $paymentDebtorId)) {
                    $errors[] = "Cargo {$cid} no pertenece al deudor del pago.";
                }
            } else { // CONCESSIONAIRE
                $ok = false;
                if ($cDebtorType === 'CONCESSIONAIRE' && $cDebtorId === $paymentDebtorId) {
                    $ok = true;
                }
                if (! $ok && $cDebtorType === 'LOCAL' && in_array($cDebtorId, $allowedLocalIds, true)) {
                    $ok = true;
                }
                if (! $ok) {
                    $errors[] = "Cargo {$cid} no pertenece al dominio del concesionario.";
                }
            }
        }

        if (! empty($errors)) {
            throw new DomainActionException(implode(' ', $errors));
        }
    }

    /**
     * Ejecutar las asignaciones dentro de una transacción.
     *
     * @param  list<array{charge_id: int, amount_bs_minor: int<1, max>}>  $items
     * @param  Collection<int, Charge>  $charges
     * @return array{applied_from_payment: int, applied_from_credit: int, after_available: int, did_set_applied: bool, created_credit: bool, touched_charges: list<int>}
     */
    private function executeAllocations(
        Payment $payment,
        array $items,
        Collection $charges,
        bool $useCredit,
        Carbon $paidOn,
    ): array {
        Log::info('allocation_processor.begin', [
            'payment_id' => (int) $payment->getKey(),
            'items_count' => count($items),
        ]);

        // Lock payment row
        DB::table('payments')->where('id', $payment->getKey())->lockForUpdate()->first();

        // Calcular disponible del pago
        $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()
            ->where('payment_id', $payment->getKey())
            ->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);

        // Pre-cargar créditos si aplica
        $credits = collect();
        if ($useCredit) {
            $credits = CustomerCredit::query()
                ->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                ->where('debtor_id', (int) $payment->getAttribute('debtor_id'))
                ->where('status', 'OPEN')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        $appliedFromPayment = 0;
        $appliedFromCredit = 0;
        $touched = [];

        foreach ($items as $it) {
            $amt = $it['amount_bs_minor'];
            $charge = $charges->get($it['charge_id']);
            if (! $charge) {
                continue;
            }

            $touched[] = $it['charge_id'];

            // Asignar desde fondos del pago
            $fromPayment = min($amt, $available);
            if ($fromPayment > 0) {
                $this->upsertAllocation($payment, $charge, $fromPayment);
                $appliedFromPayment += $fromPayment;
                $available -= $fromPayment;
            }

            // Resto desde créditos
            $remain = $amt - $fromPayment;
            if ($remain > 0 && $useCredit && $credits->isNotEmpty()) {
                $fromCredit = $this->applyCredits($payment, $charge, $remain, $credits);
                $appliedFromCredit += $fromCredit;
            }
        }

        $afterAvailable = max(0, $amountPayment - $currentAssigned - $appliedFromPayment);
        $totalApplied = $currentAssigned + $appliedFromPayment;

        // Crear crédito por sobrepago si no hay cargos pendientes
        $createdCredit = false;
        if ($afterAvailable > 0 && $this->noOutstandingCharges($payment, $paidOn)) {
            $this->createCustomerCredit($payment, $afterAvailable);
            $createdCredit = true;
        }

        // Actualizar estados de cargos tocados
        $this->updateChargeStatuses($touched, $paidOn);

        // Marcar pago como APPLIED si corresponde
        $didSetApplied = false;
        if (($afterAvailable === 0 && $totalApplied > 0) || $createdCredit) {
            $payment->setAttribute('status', 'APPLIED');
            $payment->save();
            $didSetApplied = true;
        }

        Log::info('allocation_processor.complete', [
            'payment_id' => (int) $payment->getKey(),
            'applied_from_payment' => $appliedFromPayment,
            'applied_from_credit' => $appliedFromCredit,
            'after_available' => $afterAvailable,
            'did_set_applied' => $didSetApplied,
            'created_credit' => $createdCredit,
        ]);

        return [
            'applied_from_payment' => $appliedFromPayment,
            'applied_from_credit' => $appliedFromCredit,
            'after_available' => $afterAvailable,
            'did_set_applied' => $didSetApplied,
            'created_credit' => $createdCredit,
            'touched_charges' => $touched,
        ];
    }

    /**
     * Insertar o incrementar una allocation.
     */
    private function upsertAllocation(Payment $payment, Charge $charge, int $amount): void
    {
        $existing = PaymentAllocation::query()
            ->where('payment_id', (int) $payment->getKey())
            ->where('charge_id', (int) $charge->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing) {
            $existing->increment('amount_bs_minor', $amount);
        } else {
            $rawLocalId = $charge->getAttribute('local_id');
            $localId = $rawLocalId !== null ? (int) $rawLocalId : null;

            (new PaymentAllocation([
                'payment_id' => (int) $payment->getKey(),
                'charge_id' => (int) $charge->getKey(),
                'local_id' => $localId,
                'debtor_type' => (string) $charge->getAttribute('debtor_type'),
                'debtor_id' => (int) $charge->getAttribute('debtor_id'),
                'amount_bs_minor' => $amount,
            ]))->save();
        }
    }

    /**
     * Aplicar créditos a un cargo.
     *
     * @param  Collection<int, CustomerCredit>  $credits
     * @return int Monto aplicado desde créditos
     */
    private function applyCredits(Payment $payment, Charge $charge, int $needed, Collection $credits): int
    {
        $used = 0;

        foreach ($credits as $credit) {
            if ($needed <= 0) {
                break;
            }

            $balance = (int) $credit->getAttribute('balance_minor');
            if ($balance <= 0) {
                continue;
            }

            $use = min($balance, $needed);

            (new CreditApplication([
                'customer_credit_id' => (int) $credit->getKey(),
                'payment_id' => (int) $payment->getKey(),
                'charge_id' => (int) $charge->getKey(),
                'amount_minor' => $use,
            ]))->save();

            $credit->decrement('balance_minor', $use);
            if ($credit->getAttribute('balance_minor') <= 0) {
                $credit->update(['status' => 'USED']);
            }

            $used += $use;
            $needed -= $use;
        }

        return $used;
    }

    /**
     * Verificar si no hay cargos pendientes para el deudor.
     */
    private function noOutstandingCharges(Payment $payment, Carbon $paidOn): bool
    {
        $debtorType = (string) $payment->getAttribute('debtor_type');
        $debtorId = (int) $payment->getAttribute('debtor_id');

        $collectableIds = ChargeStatusCode::collectableIds();
        if (empty($collectableIds)) {
            return true;
        }

        $q = Charge::query()
            ->whereIn('charge_status_id', $collectableIds)
            ->whereNull('deleted_at');

        if ($debtorType === 'CONCESSIONAIRE') {
            $localIds = $this->resolveAllowedLocalIds($payment, $paidOn);
            $q->where(function ($query) use ($debtorId, $localIds) {
                $query->where(function ($sub) use ($debtorId) {
                    $sub->where('debtor_type', 'CONCESSIONAIRE')
                        ->where('debtor_id', $debtorId);
                });

                if (! empty($localIds)) {
                    $query->orWhere(function ($sub) use ($localIds) {
                        $sub->where('debtor_type', 'LOCAL')
                            ->whereIn('debtor_id', $localIds);
                    });
                }
            });
        } else {
            $q->where('debtor_type', $debtorType)->where('debtor_id', $debtorId);
        }

        $charges = $q->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued']);
        if ($charges->isEmpty()) {
            return true;
        }

        $outstandingMap = $this->fxHelper->chargesOutstandingVesBatch($charges, $paidOn);
        $totalOutstanding = array_sum($outstandingMap);

        // Allow small tolerance for FX rounding (1 minor per charge max)
        $tolerance = count($outstandingMap);

        return $totalOutstanding <= $tolerance;
    }

    /**
     * Crear crédito a favor del cliente.
     */
    private function createCustomerCredit(Payment $payment, int $amount): void
    {
        (new CustomerCredit([
            'debtor_type' => (string) $payment->getAttribute('debtor_type'),
            'debtor_id' => (int) $payment->getAttribute('debtor_id'),
            'source_payment_id' => (int) $payment->getKey(),
            'currency' => 'VES',
            'balance_minor' => $amount,
            'status' => 'OPEN',
            'created_from' => 'overpayment',
        ]))->save();
    }

    /**
     * Actualizar estados de cargos tocados.
     *
     * @param  list<int>  $chargeIds
     */
    private function updateChargeStatuses(array $chargeIds, Carbon $paidOn): void
    {
        if (empty($chargeIds)) {
            return;
        }

        $issuedId = ChargeStatusCode::ISSUED->id();
        $partialId = ChargeStatusCode::PARTIAL->id();
        $settledId = ChargeStatusCode::SETTLED->id();

        $charges = Charge::query()
            ->whereIn('id', $chargeIds)
            ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'charge_status_id']);

        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $outstanding = $this->fxHelper->chargeOutstandingVes($charge, $paidOn);

            // Allow 1 minor tolerance for FX rounding
            if ($outstanding <= 1) {
                Charge::query()->where('id', $cid)->update([
                    'charge_status_id' => $settledId,
                    'settled_on' => $paidOn->toDateString(),
                ]);
            } else {
                $allocated = (int) PaymentAllocation::query()
                    ->where('charge_id', $cid)
                    ->sum('amount_bs_minor');
                $credited = $this->fxHelper->sumCreditApplicationsVes($cid, $paidOn);

                if (($allocated + $credited) > 0) {
                    Charge::query()->where('id', $cid)->update([
                        'charge_status_id' => $partialId,
                    ]);
                }
            }
        }
    }
}
