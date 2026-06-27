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
     * Override global search to include debtor name (concessionaire/local).
     * Uses translate() for accent-insensitive matching (no unaccent extension needed).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applySearch(Builder $builder, string $searchTerm): Builder
    {
        if (empty($searchTerm)) {
            return $builder;
        }

        $strip = "translate(LOWER(%s), 'áéíóúàèìòùäëïöüâêîôûñç', 'aeiouaeiouaeiouaeiounç')";
        $needle = strtolower(str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'à', 'è', 'ì', 'ò', 'ù', 'ä', 'ë', 'ï', 'ö', 'ü', 'â', 'ê', 'î', 'ô', 'û', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n'],
            $searchTerm
        ));
        $like = '%'.$needle.'%';

        return $builder->where(function (Builder $q) use ($strip, $like) {
            foreach ($this->searchable() as $column) {
                $q->orWhereRaw(sprintf($strip, "CAST(payments.{$column} AS TEXT)").' LIKE ?', [$like]);
            }

            $q->orWhereRaw(
                'payments.debtor_type = \'CONCESSIONAIRE\' AND EXISTS (
                    SELECT 1 FROM concessionaires c
                    WHERE c.id = payments.debtor_id
                      AND '.sprintf($strip, 'c.full_name').' LIKE ?
                )',
                [$like]
            );

            $q->orWhereRaw(
                'payments.debtor_type = \'LOCAL\' AND EXISTS (
                    SELECT 1 FROM locals l
                    WHERE l.id = payments.debtor_id
                      AND ('.sprintf($strip, 'l.code').' LIKE ? OR '.sprintf($strip, 'COALESCE(l.name, \'\')').' LIKE ?)
                )',
                [$like, $like]
            );
        });
    }

    /**
     * Mapa de filtros específicos del recurso.
     *
     * @return array<string, callable(Builder<\App\Models\Payment>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            'has_available' => function (Builder $b, $v): void {
                if ($v === null || $v === '' || $v === false) {
                    return;
                }

                // Pagos que tienen saldo por aplicar, ya sea como remanente en el propio pago
                // (monto > asignado) o como crédito a favor asociado a ese pago.
                $b->where(function (Builder $q): void {
                    // Caso 1: monto mayor que suma de asignaciones (ignorando soft-deletes)
                    $q->whereRaw(
                        'COALESCE(amount_bs_minor, 0) > COALESCE((
                            SELECT SUM(pa.amount_bs_minor)
                            FROM payment_allocations pa
                            WHERE pa.payment_id = payments.id
                              AND pa.deleted_at IS NULL
                        ), 0)'
                    )
                    // Caso 2: existe crédito a favor (abierto) generado por este pago
                    // La clausura de orWhereExists recibe un Query Builder base, no un Eloquent Builder.
                        ->orWhereExists(function (\Illuminate\Database\Query\Builder $sub): void {
                            $sub->from('customer_credits as cc')
                                ->whereColumn('cc.source_payment_id', 'payments.id')
                                ->whereNull('cc.deleted_at')
                                ->where('cc.status', 'OPEN')
                                ->where('cc.balance_minor', '>', 0);
                        });
                });
            },
            'method' => function (Builder $b, $v): void {
                if ($v === null || $v === '') {
                    return;
                }

                $method = strtoupper(trim((string) $v));
                if ($method === '') {
                    return;
                }

                $b->where(function (Builder $q) use ($method): void {
                    $q->whereRaw('UPPER(COALESCE(method, \'\')) = ?', [$method])
                        ->orWhereHas('paymentType', function (Builder $paymentType) use ($method): void {
                            $paymentType->whereRaw('UPPER(code) = ?', [$method]);
                        });
                });
            },
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
