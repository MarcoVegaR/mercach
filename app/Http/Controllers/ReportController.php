<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    /**
     * Show the bank validations report page.
     */
    public function bankValidations(Request $request): Response
    {
        $this->authorize('viewBankValidations', 'Report');

        // DataTable query params
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 10), 100);
        $q = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'paid_on');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Nested filters
        $filters = (array) $request->input('filters', []);
        // Backward compatibility (date_from/date_to)
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $filters['paid_between'] = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }

        $query = Payment::query()
            ->with(['companyBankAccount.bank', 'originBank'])
            ->whereNotNull('gateway_response');

        // Global search
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('payer_document_number', 'like', "%{$q}%")
                    ->orWhere('payer_account_number', 'like', "%{$q}%");
            });
        }

        // Filters: paid_between
        $paidBetween = (array) ($filters['paid_between'] ?? []);
        if (! empty($paidBetween['from'])) {
            $query->whereDate('paid_on', '>=', (string) $paidBetween['from']);
        }
        if (! empty($paidBetween['to'])) {
            $query->whereDate('paid_on', '<=', (string) $paidBetween['to']);
        }
        // response_code
        if (! empty($filters['response_code'])) {
            $query->where('gateway_resp_code', (string) $filters['response_code']);
        }
        // status
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        // Sorting
        $sortable = [
            'paid_on' => 'paid_on',
            'reference' => 'reference',
            'amount_bs' => 'amount_bs_minor',
            'gateway_resp_code' => 'gateway_resp_code',
            'status' => 'status',
        ];
        $orderCol = $sortable[$sort] ?? 'paid_on';
        $query->orderBy($orderCol, $dir)->orderBy('id', 'desc');

        $payments = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        $rows = $payments->getCollection()->map(function ($payment) {
            // Extract ReqId from gateway_response if available
            $reqId = null;
            $raw = $payment->getAttribute('gateway_response');
            if (is_array($raw)) {
                $reqId = $raw['sReqId'] ?? null;
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $reqId = $decoded['sReqId'] ?? null;
                }
            }

            // Get account numbers
            $companyAccount = null;
            if ($payment->companyBankAccount) {
                $bank = $payment->companyBankAccount->bank;
                $bankName = $bank ? (string) $bank->name : '';
                $accountNumber = (string) ($payment->companyBankAccount->account_number ?? '');
                $companyAccount = trim(($bankName !== '' ? $bankName.' ' : '').$accountNumber);
            }

            $payerAccount = $payment->payer_account_number;
            $payerPhone = $payment->payer_phone_e164;

            // Determine origen based on method
            $origin = null;
            if (strtoupper($payment->method ?? '') === 'PMOV') {
                $origin = $payerPhone;
            } else {
                $origin = $payerAccount;
            }

            return [
                'id' => $payment->id,
                'paid_on' => $payment->paid_on,
                'reference' => $payment->reference,
                'origin_account' => $origin,
                'destination_account' => $companyAccount,
                'amount_bs' => $payment->amount_bs_minor / 100.0,
                'payer_document' => $payment->payer_document_number,
                'gateway_resp_code' => $payment->gateway_resp_code,
                'gateway_message' => $payment->gateway_message,
                'req_id' => $reqId,
                'status' => $payment->status,
                'method' => $payment->method,
            ];
        });

        return Inertia::render('reports/bank-validations/index', [
            'rows' => $rows,
            'meta' => [
                'current_page' => $payments->currentPage(),
                'from' => $payments->firstItem(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
            'responseCodes' => $this->getResponseCodes(),
        ]);
    }

    /**
     * Export bank validations report.
     */
    public function exportBankValidations(Request $request): SymfonyResponse
    {
        $this->authorize('exportBankValidations', 'Report');

        $q = trim((string) $request->input('q', ''));
        $filters = (array) $request->input('filters', []);

        $query = Payment::query()
            ->with(['companyBankAccount.bank', 'originBank'])
            ->whereNotNull('gateway_response');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('payer_document_number', 'like', "%{$q}%")
                    ->orWhere('payer_account_number', 'like', "%{$q}%");
            });
        }

        $paidBetween = (array) ($filters['paid_between'] ?? []);
        // Backwards compatibility date_from/date_to
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $paidBetween = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }
        if (! empty($paidBetween['from'])) {
            $query->whereDate('paid_on', '>=', (string) $paidBetween['from']);
        }
        if (! empty($paidBetween['to'])) {
            $query->whereDate('paid_on', '<=', (string) $paidBetween['to']);
        }
        if (! empty($filters['response_code'])) {
            $query->where('gateway_resp_code', (string) $filters['response_code']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $payments = $query->limit(5000)->get();

        $data = $payments->map(function ($payment) {
            $reqId = null;
            $raw = $payment->getAttribute('gateway_response');
            if (is_array($raw)) {
                $reqId = $raw['sReqId'] ?? null;
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $reqId = $decoded['sReqId'] ?? null;
                }
            }

            $companyAccount = null;
            if ($payment->companyBankAccount) {
                $bank = $payment->companyBankAccount->bank;
                $bankName = $bank ? (string) $bank->name : '';
                $accountNumber = (string) ($payment->companyBankAccount->account_number ?? '');
                $companyAccount = trim(($bankName !== '' ? $bankName.' ' : '').$accountNumber);
            }

            $origin = null;
            if (strtoupper($payment->method ?? '') === 'PMOV') {
                $origin = $payment->payer_phone_e164;
            } else {
                $origin = $payment->payer_account_number;
            }

            return [
                'Fecha de pago' => $payment->paid_on,
                'Nro. Referencia' => $payment->reference,
                'Cuenta/Origen' => $origin,
                'Cuenta/Destino' => $companyAccount,
                'Monto' => number_format($payment->amount_bs_minor / 100.0, 2, ',', '.'),
                'Cedula/RIF' => $payment->payer_document_number,
                'Codigo/Respuesta' => $payment->gateway_resp_code,
                'Respuesta' => $payment->gateway_message,
                'ReqId' => $reqId,
            ];
        });

        $format = (string) $request->input('format', 'csv');

        if ($format === 'json') {
            return response()->json($data);
        }

        // CSV export
        $filename = 'validaciones_bancarias_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Get common response codes for filter dropdown.
     */
    /**
     * @return array<int, array{code:string,label:string}>
     */
    private function getResponseCodes(): array
    {
        return [
            ['code' => 'ACCP', 'label' => 'ACCP - Transacción Aprobada'],
            ['code' => '00', 'label' => '00 - Aprobado'],
            ['code' => '831', 'label' => '831 - Transacción ya fue validada'],
            ['code' => '830', 'label' => '830 - Descripción de error no disponible'],
            ['code' => 'BE11', 'label' => 'BE11 - Id. Emisor no corresponde'],
            ['code' => '706', 'label' => '706 - Cod. Banco de numero cuenta invalido'],
            ['code' => '707', 'label' => '707 - Transacción duplicada'],
            ['code' => '708', 'label' => '708 - Cuenta destino inválida'],
            ['code' => '709', 'label' => '709 - Referencia no encontrada'],
            ['code' => '710', 'label' => '710 - Monto no coincide'],
        ];
    }
}
