<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ConcessionaireServiceInterface;
use App\Http\Requests\ConcessionaireIndexRequest;
use App\Http\Requests\ConcessionaireStoreRequest;
use App\Http\Requests\ConcessionaireUpdateRequest;
use App\Http\Requests\DeleteConcessionaireRequest;
use App\Http\Requests\PrintConcessionaireLifeProofFormsRequest;
use App\Http\Requests\RecordConcessionaireLifeProofRequest;
use App\Http\Requests\SetConcessionaireActiveRequest;
use App\Models\Concessionaire;
use App\Models\User;
use App\Services\ConcessionaireLifeProofFormPdfGenerator;
use App\Services\ConcessionaireProfilePdfGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ConcessionaireController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ConcessionaireServiceInterface $serviceConcrete;

    public function __construct(
        ConcessionaireServiceInterface $service,
        private ConcessionaireLifeProofFormPdfGenerator $lifeProofFormPdfGenerator,
        private ConcessionaireProfilePdfGenerator $profilePdfGenerator,
    ) {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\Concessionaire::class;
    }

    protected function view(): string
    {
        return 'catalogs/concessionaire/index';
    }

    /**
     * Display a listing of the resource with extras injected.
     */
    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);

        // Inject stats (and other extras) from service
        $extras = $this->serviceConcrete->getIndexExtras();
        if (isset($extras['stats'])) {
            $response->with('stats', $extras['stats']);
        }
        if (isset($extras['filterOptions'])) {
            $response->with('filterOptions', $extras['filterOptions']);
        }

        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('catalogs.concessionaire.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ConcessionaireIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.concessionaire.index';
    }

    /**
     * Provide options for form selects (active-only catalogs)
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $types = \App\Models\ConcessionaireType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $docTypes = \App\Models\DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $areaCodes = \App\Models\PhoneAreaCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->code])
            ->toArray();

        return [
            'options' => [
                'concessionaire_types' => $types,
                'document_types' => $docTypes,
                'phone_area_codes' => $areaCodes,
            ],
        ];
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['concessionaire' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/concessionaire/form';
    }

    protected function storeRequestClass(): string
    {
        return ConcessionaireStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ConcessionaireUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.concessionaire.export';
    }

    /**
     * @return array{
     *     concessionaire_type_id: int|null,
     *     full_name: string|null,
     *     document_type_id: int|null,
     *     document_number: string|null,
     *     fiscal_address: string|null,
     *     email: string|null,
     *     phone_area_code_id: int|null,
     *     phone_number: string|null,
     *     photo_path: string|null,
     *     id_document_path: string|null,
     *     is_active: bool|null
     * }
     */
    protected function getEmptyModel(): array
    {
        return [
            'concessionaire_type_id' => null,
            'full_name' => null,
            'document_type_id' => null,
            'document_number' => null,
            'fiscal_address' => null,
            'email' => null,
            'phone_area_code_id' => null,
            'phone_number' => null,
            'photo_path' => null,
            'id_document_path' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, Concessionaire $concessionaire): \Inertia\Response
    {
        $this->authorize('view', $concessionaire);

        // Eager-load relations used in the show mapping so friendly names are available
        $concessionaire->loadMissing(['concessionaireType', 'documentType']);

        $data = [
            'item' => $this->service->toItem($concessionaire),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/concessionaire/show', $data);
    }

    public function printLifeProofForms(PrintConcessionaireLifeProofFormsRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $ids = array_map('intval', $request->validated('ids'));
        $positions = array_flip($ids);
        $concessionaires = Concessionaire::query()
            ->whereIn('id', $ids)
            ->with([
                'concessionaireType:id,name',
                'documentType:id,code,name',
                'phoneAreaCode:id,code',
                'contracts:id,number,contract_status_id,start_date,end_date',
                'contracts.status:id,code,name',
                'contracts.locals:id,code,name',
            ])
            ->get()
            ->sortBy(fn (Concessionaire $concessionaire): int => $positions[(int) $concessionaire->getKey()] ?? PHP_INT_MAX)
            ->values();

        $generated = $this->lifeProofFormPdfGenerator->render($concessionaires);

        return response($generated['raw'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$generated['filename'].'"',
        ]);
    }

    public function recordLifeProof(RecordConcessionaireLifeProofRequest $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $concessionaire->update([
            'last_life_proof_at' => (string) $request->validated('life_proof_at'),
        ]);

        return redirect()
            ->route('catalogs.concessionaire.show', $concessionaire)
            ->with('success', 'La fecha de fe de vida fue registrada correctamente.');
    }

    public function profilePdf(Concessionaire $concessionaire): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $concessionaire);
        $concessionaire->loadMissing([
            'concessionaireType:id,name',
            'documentType:id,code,name',
            'phoneAreaCode:id,code',
            'users:id,name,email',
            'contracts:id,number,contract_status_id,start_date,end_date',
            'contracts.status:id,code,name',
            'contracts.locals:id,code,name',
        ]);

        $generated = $this->profilePdfGenerator->render($concessionaire);

        return response($generated['raw'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$generated['filename'].'"',
        ]);
    }

    public function setActive(SetConcessionaireActiveRequest $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $concessionaire);
        $desired = (bool) $request->boolean('active');
        try {
            $this->serviceConcrete->setActive($concessionaire, $desired);
            $actionText = $desired ? 'activado' : 'desactivado';

            return redirect()->route('catalogs.concessionaire.index')
                ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.concessionaire.index')->with('error', $e->getMessage());
        }
    }

    public function destroy(DeleteConcessionaireRequest $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $concessionaire);
        try {
            $this->service->delete($concessionaire);

            return redirect()->route('catalogs.concessionaire.index')
                ->with('success', 'Registro eliminado correctamente.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.concessionaire.index')->with('error', $e->getMessage());
        }
    }

    public function invitePortalUser(Request $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $concessionaire);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $name = (string) $data['name'];
        $isPrimary = true; // 1:1 modelo: único usuario de portal por concesionario

        // Enforce email equals concessionaire email (fuente única)
        $cEmail = strtolower(trim((string) $concessionaire->getAttribute('email')));
        if ($cEmail === '' || $email !== $cEmail) {
            return redirect()
                ->route('catalogs.concessionaire.show', $concessionaire)
                ->with('error', 'El correo debe coincidir con el registrado en el concesionario.');
        }

        // Enforce 1:1: a concessionaire can only have one portal user, and a user can only belong to one concessionaire
        $existingConcessionaireUserId = $concessionaire->users()->value('users.id');
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            $linkedOther = $user->concessionaires()->where('concessionaires.id', '!=', $concessionaire->getKey())->exists();
            if ($linkedOther) {
                return redirect()
                    ->route('catalogs.concessionaire.show', $concessionaire)
                    ->with('error', 'El correo ya está vinculado a otro concesionario.');
            }
        }
        if (! is_null($existingConcessionaireUserId) && (! $user || (int) $user->getKey() !== (int) $existingConcessionaireUserId)) {
            return redirect()
                ->route('catalogs.concessionaire.show', $concessionaire)
                ->with('error', 'Este concesionario ya tiene un usuario de portal.');
        }

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(16)),
                'is_active' => true,
            ]);
        }

        // Ensure permission to access portal and basic settings
        try {
            $user->assignRole('concesionario');
        } catch (\Throwable $e) {
            try {
                $user->givePermissionTo('portal.access');
            } catch (\Throwable $e2) {
            }
        }

        // Attach pivot (1:1)
        $concessionaire->users()->syncWithoutDetaching([
            $user->id => [
                'is_primary' => $isPrimary,
                'status' => 'invited',
                'invited_at' => now(),
                'created_by' => optional($request->user())->id,
            ],
        ]);

        // Send password setup email and capture status
        $status = null;
        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            $status = 'exception:'.(string) $e->getMessage();
        }

        // Audit/log the invitation attempt/result (defense-in-depth & traceability)
        try {
            \Log::info('portal.invite_user', [
                'actor_id' => optional($request->user())->id,
                'actor_email' => optional($request->user())->email,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'concessionaire_id' => (int) $concessionaire->getKey(),
                'concessionaire_email' => (string) $concessionaire->getAttribute('email'),
                'invited_user_id' => (int) $user->getKey(),
                'invited_email' => (string) $email,
                'reset_status' => (string) $status,
            ]);
        } catch (\Throwable $e) {
        }

        // Respuesta amigable (admin): informar que se inició el proceso de invitación
        return redirect()
            ->route('catalogs.concessionaire.show', $concessionaire)
            ->with('success', 'Se inició el proceso de invitación del usuario de Portal.');
    }

    public function resetPortalUser(Request $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $concessionaire);

        // Verificar usuario vinculado (1:1)
        $linkedUserId = $concessionaire->users()->value('users.id');
        if (! $linkedUserId) {
            return redirect()->route('catalogs.concessionaire.show', $concessionaire)
                ->with('error', 'Este concesionario no tiene usuario de Portal vinculado.');
        }

        // Enviar reset al correo fuente (Concesionario.email)
        $email = strtolower(trim((string) $concessionaire->getAttribute('email')));
        if ($email === '') {
            return redirect()->route('catalogs.concessionaire.show', $concessionaire)
                ->with('error', 'El concesionario no posee correo registrado.');
        }

        $status = null;
        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            $status = 'exception:'.(string) $e->getMessage();
        }

        try {
            \Log::info('portal.reset_user', [
                'actor_id' => optional($request->user())->id,
                'actor_email' => optional($request->user())->email,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'concessionaire_id' => (int) $concessionaire->getKey(),
                'concessionaire_email' => (string) $email,
                'reset_status' => (string) $status,
            ]);
        } catch (\Throwable $e) {
        }

        return redirect()->route('catalogs.concessionaire.show', $concessionaire)
            ->with('success', 'Se envió el enlace de restablecimiento de acceso.');
    }
}
