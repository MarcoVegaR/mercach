<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    protected string $modelClass = \App\Models\Payment::class;

    /**
     * Campos buscables por búsqueda global (LOWER LIKE).
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [
            'id',
            'local_id',
            'debtor_type',
            'debtor_id',
            'payer_document_number',
            'payer_account_number',
            'payer_phone_e164',
            'reference',
            // removed DB column 'status'; use filters to map by catalog if needed
        ];
    }

    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return [
            'id', 'local_id', 'debtor_type', 'debtor_id', 'company_bank_account_id',
            'method', 'origin_bank_id', 'payer_document_type', 'payer_document_number',
            'payer_account_number', 'payer_phone_e164', 'reference', 'amount_bs_minor',
            'paid_on', 'fx_rate_id', 'payment_status_id', 'created_at',
        ];
    }

    /**
     * Nombre de la columna de estado activo.
     */
    protected function activeColumn(): string
    {
        // Payments do not have an active column
        return 'active';
    }

    /**
     * Mapa de filtros específicos del recurso.
     *
     * @return array<string, callable(Builder<\App\Models\Payment>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            // Map UI label or code to payment_status_id
            'status' => function (Builder $b, $v): void {
                if ($v === null || $v === '') {
                    return;
                }
                // Accept id directly
                if (is_numeric($v)) {
                    $b->where('payment_status_id', (int) $v);

                    return;
                }
                $val = strtoupper((string) $v);
                $uiToCode = [
                    'REGISTERED' => 'REG',
                    'CONFIRMED' => 'CONF',
                    'APPLIED' => 'CONC',
                ];
                $code = $uiToCode[$val] ?? $val; // allow passing REG/CONF/CONC
                $id = \App\Models\PaymentStatus::query()->where('code', $code)->value('id');
                if ($id) {
                    $b->where('payment_status_id', (int) $id);
                }
            },
            'paid_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('paid_on', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('paid_on', '<=', $v['to']);
                }
            },
            'reference_like' => function (Builder $b, $v): void {
                $b->whereRaw('LOWER(reference) LIKE ?', ['%'.strtolower((string) $v).'%']);
            },
        ];
    }
}
