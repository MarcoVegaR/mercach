<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tests para RequestId middleware y Laravel Context.
 *
 * Verifica que el middleware RequestId:
 * - Genere y comparta request_id correctamente
 * - Agregue context al logging automáticamente
 * - Incluya user_id cuando hay usuario autenticado
 */
class RequestIdContextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function request_id_is_shared_in_inertia_props(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/request-id-test-unique-'.time());

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requestId')
            ->where('requestId', fn ($requestId) => is_string($requestId) && ! empty($requestId))
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function request_id_is_included_in_response_headers(): void
    {
        $response = $this->actingAs($this->user)->get('/test-headers-unique');

        $response->assertOk();
        $response->assertHeader('X-Request-Id');

        $requestId = $response->headers->get('X-Request-Id');
        $this->assertIsString($requestId);
        $this->assertNotEmpty($requestId);
        // Verificar que es un UUID válido
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $requestId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function existing_request_id_header_is_preserved(): void
    {
        $customRequestId = 'custom-request-id-12345';

        $response = $this->actingAs($this->user)
            ->withHeader('X-Request-Id', $customRequestId)
            ->get('/test-custom-id-unique');

        $response->assertOk();
        $response->assertHeader('X-Request-Id', $customRequestId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function context_includes_request_id_and_user_id(): void
    {
        $response = $this->actingAs($this->user)->get('/test-context-unique');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('request_id', $data['context']);
        $this->assertArrayHasKey('user_id', $data['context']);

        $this->assertNotEmpty($data['context']['request_id']);
        $this->assertEquals($this->user->id, $data['context']['user_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function context_includes_null_user_id_for_guest_requests(): void
    {
        $response = $this->get('/test-guest-context-unique');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('request_id', $data['context']);
        $this->assertArrayHasKey('user_id', $data['context']);

        $this->assertNotEmpty($data['context']['request_id']);
        $this->assertNull($data['context']['user_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function request_attributes_contain_request_id(): void
    {
        $response = $this->actingAs($this->user)->get('/test-attributes-unique');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('request_id', $data);
        $this->assertNotEmpty($data['request_id']);
        $this->assertIsString($data['request_id']);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/request-id-test-unique-{timestamp}', function () {
            return \Inertia\Inertia::render('TestPage');
        })->middleware('web')->where('timestamp', '[0-9]+');

        $router->get('/test-headers-unique', function () {
            return response()->json(['message' => 'OK']);
        })->middleware('web');

        $router->get('/test-custom-id-unique', function () {
            return response()->json(['message' => 'OK']);
        })->middleware('web');

        $router->get('/test-context-unique', function () {
            $context = \Illuminate\Support\Facades\Context::all();

            return response()->json(['context' => $context]);
        })->middleware('web');

        $router->get('/test-guest-context-unique', function () {
            $context = \Illuminate\Support\Facades\Context::all();

            return response()->json(['context' => $context]);
        })->middleware('web');

        $router->get('/test-attributes-unique', function (\Illuminate\Http\Request $request) {
            $requestId = $request->attributes->get('request_id');

            return response()->json(['request_id' => $requestId]);
        })->middleware('web');
    }
}
