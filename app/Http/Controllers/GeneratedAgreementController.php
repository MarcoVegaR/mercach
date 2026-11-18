<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DomainActionException;
use App\Http\Requests\GenerateAgreementRequest;
use App\Http\Requests\GeneratedAgreementIndexRequest;
use App\Models\Concessionaire;
use App\Models\Contract;
use App\Models\GeneratedAgreement;
use App\Models\Local;
use App\Models\TradeCategory;
use App\Services\GeneratedAgreementService;
use App\Services\GeneratedAgreementWordExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedAgreementController extends Controller
{
    public function index(GeneratedAgreementIndexRequest $request, GeneratedAgreementService $service): Response
    {
        $filters = $request->validated();
        $agreements = GeneratedAgreement::query()
            ->with('issuer:id,name')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('number', '%'.$search.'%')
                        ->orWhereLike('snapshot->concessionaire->name', '%'.$search.'%')
                        ->orWhereLike('snapshot->concessionaire->document', '%'.$search.'%')
                        ->orWhereLike('snapshot->additional_concessionaires', '%'.$search.'%')
                        ->orWhereLike('snapshot->local->code', '%'.$search.'%');
                });
            })
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString()
            ->through(fn (GeneratedAgreement $agreement): array => [
                'id' => $agreement->id, 'number' => $agreement->number,
                'name' => $agreement->snapshot['concessionaire']['name'],
                'local' => $agreement->snapshot['local']['code'],
                'created_at' => $agreement->created_at->format('d/m/Y H:i'),
                'issuer' => $agreement->issuer?->name,
            ]);

        return Inertia::render('agreements/index', [
            'agreements' => $agreements, 'filters' => $filters,
            'issuanceToken' => (string) Str::uuid(),
            'today' => now((string) config('app.timezone'))->toDateString(),
            'concessionaires' => fn () => Concessionaire::query()->with('phoneAreaCode')->where('is_active', true)
                ->whereHas('concessionaireType', fn ($query) => $query->where('code', 'PNAT'))
                ->whereHas('documentType', fn ($query) => $query->whereIn('code', ['V', 'E']))
                ->orderBy('full_name')->get()->map(fn (Concessionaire $person): array => [
                    'id' => $person->id, 'full_name' => $person->full_name, 'document_number' => $person->document_number,
                    'missing_fields' => $service->missingProfileFields($person),
                ]),
            'locals' => Local::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'area_m2']),
            'trades' => TradeCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'contracts' => Contract::query()->with(['locals' => fn ($query) => $query->where('locals.is_active', true), 'concessionaires:id', 'status:id,name', 'tradeCategory:id,name'])
                ->whereHas('locals', fn ($query) => $query->where('locals.is_active', true))
                ->orderByDesc('start_date')->get()->map(fn (Contract $contract): array => [
                    'id' => $contract->id, 'number' => $contract->number, 'status' => $contract->status?->name,
                    'concessionaire_ids' => $contract->concessionaires->modelKeys(),
                    'local_ids' => $contract->locals->modelKeys(),
                    'local_codes' => $contract->locals->pluck('code')->all(),
                    'trade_category_id' => $contract->trade_category_id,
                    'trade_name' => $contract->tradeCategory?->name,
                ]),
        ]);
    }

    public function preview(GenerateAgreementRequest $request, GeneratedAgreementService $service): Response
    {
        $data = $request->validated();
        $snapshot = $service->snapshot($data);
        $html = $service->html($snapshot);
        $values = [...$data, 'preview_hash' => $service->previewHash($html, $data['issuance_token'])];

        return Inertia::render('agreements/preview', [
            'pdfUrl' => route('agreements.preview-pdf', $values),
            'wordUrl' => route('agreements.preview-word', $values),
            'values' => $values,
            'snapshot' => $snapshot,
        ]);
    }

    public function previewPdf(GenerateAgreementRequest $request, GeneratedAgreementService $service): \Illuminate\Http\Response
    {
        $data = $request->validated();
        $html = $service->html($service->snapshot($data));
        abort_unless(hash_equals($service->previewHash($html, $data['issuance_token']), $data['preview_hash']), 409, 'Los datos cambiaron. Genera nuevamente la vista previa.');

        return response($service->pdf($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista_previa_convenio.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(GenerateAgreementRequest $request, GeneratedAgreementService $service): RedirectResponse
    {
        $agreement = $service->issue($request->validated(), (int) $request->user()->id);

        return to_route('agreements.show', $agreement)->with('success', 'Convenio emitido. No se ha registrado ni activado ningún contrato.');
    }

    public function previewWord(GenerateAgreementRequest $request, GeneratedAgreementService $service, GeneratedAgreementWordExporter $exporter): \Illuminate\Http\Response
    {
        $data = $request->validated();
        $html = $service->html($service->snapshot($data));
        abort_unless(hash_equals($service->previewHash($html, $data['issuance_token']), $data['preview_hash']), 409, 'Los datos cambiaron. Genera nuevamente la vista previa.');

        return $this->wordResponse($exporter, $html, 'vista_previa_convenio.docx');
    }

    public function downloadWord(GeneratedAgreement $agreement, GeneratedAgreementWordExporter $exporter): \Illuminate\Http\Response
    {
        $snapshot = $agreement->snapshot;
        $filename = 'convenio_'.Str::slug($snapshot['concessionaire']['name'], '_').'_local_'.Str::slug($snapshot['local']['code'], '_').'_'.$agreement->created_at->format('Y-m-d').'_'.str_replace('/', '_', $agreement->number).'.docx';

        return $this->wordResponse($exporter, $agreement->rendered_html, $filename);
    }

    private function wordResponse(GeneratedAgreementWordExporter $exporter, string $html, string $filename): \Illuminate\Http\Response
    {
        try {
            $bytes = $exporter->render($html);
        } catch (DomainActionException $exception) {
            return response($exception->getMessage(), 422, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response($bytes, 200, [
            'Content-Type' => GeneratedAgreementWordExporter::MIME_TYPE,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function show(GeneratedAgreement $agreement): Response
    {
        return Inertia::render('agreements/show', [
            'agreement' => [
                'id' => $agreement->id, 'number' => $agreement->number,
                'snapshot' => $agreement->snapshot,
                'template_version' => $agreement->template_version,
                'created_at' => $agreement->created_at->format('d/m/Y H:i'),
                'issuer' => $agreement->issuer?->name,
            ],
        ]);
    }

    public function download(GeneratedAgreement $agreement): StreamedResponse
    {
        $snapshot = $agreement->snapshot;
        $filename = 'convenio_'.Str::slug($snapshot['concessionaire']['name'], '_').'_local_'.Str::slug($snapshot['local']['code'], '_').'_'.$agreement->created_at->format('Y-m-d').'_'.str_replace('/', '_', $agreement->number).'.pdf';

        return Storage::disk('local')->response($agreement->pdf_path, $filename, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
