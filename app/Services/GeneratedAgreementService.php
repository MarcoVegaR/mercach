<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MaritalStatus;
use App\Enums\Sex;
use App\Models\AgreementSequence;
use App\Models\Concessionaire;
use App\Models\Contract;
use App\Models\GeneratedAgreement;
use App\Models\Local;
use App\Models\TradeCategory;
use App\Support\PdfAssetLoader;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;
use Dompdf\FrameDecorator\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeneratedAgreementService
{
    public const TEMPLATE_VERSION = '2026-11-25-v11';

    public function __construct(private PdfAssetLoader $assets) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function snapshot(array $data): array
    {
        $ids = [(int) $data['concessionaire_id'], ...array_map('intval', $data['additional_concessionaire_ids'] ?? [])];
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['additional_concessionaire_ids' => 'No repitas al cesionario principal ni a los adicionales.']);
        }
        $people = Concessionaire::query()->with(['documentType', 'concessionaireType', 'phoneAreaCode'])->whereIn('id', $ids)->get()->keyBy('id');
        $signers = [];
        foreach ($ids as $index => $id) {
            $field = $index === 0 ? 'concessionaire_id' : 'additional_concessionaire_ids';
            $person = $people->get($id);
            if (! $person) {
                throw ValidationException::withMessages([$field => 'Uno de los cesionarios seleccionados ya no está disponible.']);
            }
            $signers[] = $this->personSnapshot($person, $field);
        }
        $local = Local::query()->with(['localLocation', 'market'])->findOrFail($data['local_id']);
        $trade = TradeCategory::query()->findOrFail($data['trade_category_id']);
        $source = null;
        if (! empty($data['source_contract_id'])) {
            $contract = Contract::query()->whereHas('concessionaires', fn ($query) => $query->where('concessionaires.id', $data['concessionaire_id']))
                ->whereHas('locals', fn ($query) => $query->where('locals.id', $local->id))
                ->where('trade_category_id', $trade->id)->find($data['source_contract_id']);
            if (! $contract) {
                throw ValidationException::withMessages(['source_contract_id' => 'El cesionario, local o rubro no coincide con el contrato seleccionado. Revisa la selección o utiliza datos nuevos.']);
            }
            $source = ['id' => $contract->id, 'number' => $contract->number];
        }

        if (! $local->is_active || (float) $local->area_m2 <= 0 || ! $local->localLocation || ! $local->market) {
            throw ValidationException::withMessages(['local_id' => 'Actualiza la superficie, ubicación y mercado del local antes de generar.']);
        }
        if (! $trade->is_active) {
            throw ValidationException::withMessages(['trade_category_id' => 'Selecciona un rubro activo.']);
        }

        $words = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $signature = Carbon::parse($data['signature_date'])->locale('es');

        return [
            'concessionaire' => $signers[0],
            'additional_concessionaires' => array_slice($signers, 1),
            'source_contract' => $source,
            'local' => [
                'id' => $local->id, 'code' => $local->code, 'name' => $local->name,
                'area_m2' => number_format((float) $local->area_m2, 2, ',', '.'),
                'location' => $local->localLocation->name,
                'market' => $local->market->name, 'market_address' => $local->market->address,
            ],
            'trade' => ['id' => $trade->id, 'name' => $trade->name],
            'start_date' => $data['start_date'], 'signature_date' => $signature->toDateString(),
            'end_date' => $signature->copy()->addYearsNoOverflow(20)->toDateString(),
            'signature_date_words' => 'el día '.$words->format($signature->day).' del mes de '.$signature->translatedFormat('F').' del año '.$signature->year,
            'template_version' => self::TEMPLATE_VERSION,
            'issuer_year' => (int) now()->year,
        ];
    }

    /** @return array<string, mixed> */
    private function personSnapshot(Concessionaire $person, string $field): array
    {
        if (! $person->is_active || $person->concessionaireType?->code !== 'PNAT' || ! in_array($person->documentType?->code, ['V', 'E'], true)) {
            throw ValidationException::withMessages([$field => $person->full_name.': este formato requiere una persona natural activa con documento V o E.']);
        }
        $missing = $this->missingProfileFields($person);
        if ($missing !== []) {
            throw ValidationException::withMessages([$field => $person->full_name.': completa '.implode(', ', $missing).' en su perfil antes de generar el convenio.']);
        }
        $female = $person->sex === Sex::Female->value;

        return [
            'id' => $person->id, 'name' => $person->full_name,
            'document' => $person->documentType->code.'-'.number_format((float) $person->document_number, 0, ',', '.'),
            'sex' => $person->sex, 'marital_status' => $person->marital_status,
            'marital_status_label' => MaritalStatus::from($person->marital_status)->label($female),
            'citizen' => $female ? 'la ciudadana' : 'el ciudadano',
            'nationality' => $person->documentType->code === 'V' ? ($female ? 'venezolana' : 'venezolano') : ($female ? 'extranjera' : 'extranjero'),
            'party' => $female ? 'LA CESIONARIA' : 'EL CESIONARIO',
            'identified' => $female ? 'identificada' : 'identificado',
            'address' => $person->fiscal_address, 'email' => $person->email,
            'phone' => $person->phoneAreaCode->code.' '.$person->phone_number,
        ];
    }

    /** @return list<string> */
    public function missingProfileFields(Concessionaire $person): array
    {
        $complete = [
            'nombre' => trim((string) $person->full_name) !== '',
            'cédula' => preg_match('/^\d+$/', (string) $person->document_number) === 1,
            'sexo' => Sex::tryFrom((string) $person->sex) !== null,
            'estado civil' => MaritalStatus::tryFrom((string) $person->marital_status) !== null,
            'dirección' => trim((string) $person->fiscal_address) !== '',
            'correo' => filter_var($person->email, FILTER_VALIDATE_EMAIL) !== false,
            'teléfono' => $person->phoneAreaCode !== null && preg_match('/^\d{7}$/', (string) $person->phone_number) === 1,
        ];

        return array_keys(array_filter($complete, fn (bool $value): bool => ! $value));
    }

    /** @param array<string, mixed> $snapshot */
    public function html(array $snapshot, string $number = 'VISTA PREVIA — SIN EMITIR'): string
    {
        return view('pdf.generated_agreement', [
            'snapshot' => $snapshot, 'number' => $number,
            'letterhead' => $this->assets->branding('letterhead'),
            'logo' => $this->assets->branding('logo'),
        ])->render();
    }

    public function pdf(string $html): string
    {
        $pdf = new \Dompdf\Dompdf;
        $pdf->loadHtml($html);
        $pdf->setPaper([0, 0, 612.28, 935.43]);
        $pdf->setCallbacks([[
            'event' => 'end_frame',
            'f' => function (Frame $frame, Canvas $canvas, FontMetrics $metrics): void {
                if (! $frame instanceof Text || $frame->get_parent()->get_node()->attributes?->getNamedItem('class')?->nodeValue !== 'clause-end') {
                    return;
                }

                $style = $frame->get_style();
                [$x, $y, $width] = $frame->get_content_box();
                $block = $frame->find_block_parent()->get_content_box();
                $start = $x + $width;
                $available = $block['x'] + $block['w'] - $start;
                $hyphenWidth = $metrics->getTextWidth('-', $style->font_family, $style->font_size);
                $count = (int) floor(max(0, $available) / $hyphenWidth);
                if ($count > 0) {
                    $canvas->text($start, $y, str_repeat('-', $count), $style->font_family, $style->font_size, $style->color);
                }
            },
        ]]);
        $pdf->render();

        return $pdf->output();
    }

    public function previewHash(string $html, string $token): string
    {
        return hash_hmac('sha256', $token.$html, (string) config('app.key'));
    }

    /** @param array<string, mixed> $data */
    public function issue(array $data, int $userId): GeneratedAgreement
    {
        $path = 'generated-agreements/'.Str::uuid().'.pdf';
        try {
            return DB::transaction(function () use ($data, $userId, $path): GeneratedAgreement {
                $year = (int) now()->year;
                AgreementSequence::query()->firstOrCreate(['year' => $year], ['next_number' => 1]);
                $sequence = AgreementSequence::query()->whereKey($year)->lockForUpdate()->firstOrFail();
                $existing = GeneratedAgreement::query()->where('issuance_token', $data['issuance_token'])->first();
                if ($existing) {
                    abort_unless($existing->created_by === $userId, 403);

                    return $existing;
                }

                $snapshot = $this->snapshot($data);
                if (! hash_equals($this->previewHash($this->html($snapshot), $data['issuance_token']), $data['preview_hash'] ?? '')) {
                    throw ValidationException::withMessages(['preview_hash' => 'Los datos o la plantilla cambiaron. Genera y revisa nuevamente la vista previa antes de emitir.']);
                }
                $number = str_pad((string) $sequence->next_number, 4, '0', STR_PAD_LEFT).'/'.$year;
                $html = $this->html($snapshot, $number);
                if (! Storage::disk('local')->put($path, $this->pdf($html))) {
                    throw new \RuntimeException('No se pudo guardar el PDF del convenio.');
                }
                $agreement = GeneratedAgreement::query()->create([
                    'year' => $year, 'sequence_number' => $sequence->next_number,
                    'number' => $number, 'issuance_token' => $data['issuance_token'],
                    'concessionaire_id' => $data['concessionaire_id'], 'local_id' => $data['local_id'],
                    'trade_category_id' => $data['trade_category_id'],
                    'start_date' => $snapshot['start_date'], 'signature_date' => $snapshot['signature_date'],
                    'snapshot' => $snapshot, 'template_version' => self::TEMPLATE_VERSION,
                    'rendered_html' => $html, 'pdf_path' => $path, 'created_by' => $userId,
                ]);
                $sequence->increment('next_number');

                return $agreement;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
