<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para redirects 303 tras mutaciones y flash messages.
 *
 * Verifica que las operaciones de mutación (POST/PUT/PATCH/DELETE)
 * usen redirects apropiados con status 303 y flash messages.
 */
class RedirectsAndFlashTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function post_mutation_redirects_with_303_status(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->post('/redirect-test-create-unique', ['name' => 'Test Resource']);

        // Inertia convierte redirects tras mutaciones a 303
        $response->assertStatus(303);
        $response->assertRedirect('/redirect-test-index-unique');
        $response->assertSessionHas('success', 'Recurso creado exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function put_mutation_redirects_with_303_status(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->put('/redirect-test-update-unique/123', ['name' => 'Updated Resource']);

        $response->assertStatus(303);
        $response->assertRedirect('/redirect-test-show-unique/123');
        $response->assertSessionHas('success', 'Recurso actualizado exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function delete_mutation_redirects_with_303_status(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->delete('/redirect-test-delete-unique/123');

        $response->assertStatus(303);
        $response->assertRedirect('/redirect-test-index-unique');
        $response->assertSessionHas('success', 'Recurso eliminado exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function patch_mutation_redirects_with_303_status(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->patch('/redirect-test-patch-unique/456', ['active' => true]);

        $response->assertStatus(303);
        $response->assertRedirect('/redirect-test-show-unique/456');
        $response->assertSessionHas('info', 'Estado actualizado');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_requests_do_not_redirect_with_303(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/redirect-test-get-unique');

        // Los GET redirects siguen siendo 302 con Inertia
        $response->assertStatus(302);
        $response->assertRedirect('/redirect-test-other-unique');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_inertia_requests_use_standard_redirects(): void
    {
        // Sin header X-Inertia, debe usar redirect estándar 302
        $response = $this->actingAs($this->user)
            ->post('/redirect-test-standard-unique', ['data' => 'test']);

        $response->assertStatus(302);
        $response->assertRedirect('/redirect-test-target-unique');
        $response->assertSessionHas('success', 'Success message');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function flash_messages_persist_across_redirects(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->post('/redirect-test-flash-redirect-unique');

        $response->assertStatus(303);
        $response->assertSessionHas('success', 'Operación exitosa');
        $response->assertSessionHas('error', 'Advertencia menor');
        $response->assertSessionHas('info', 'Información adicional');

        // Verificar que los flash messages están disponibles en la siguiente request
        $followUpResponse = $this->actingAs($this->user)->get('/redirect-test-flash-target-unique');
        $data = $followUpResponse->json();

        $this->assertEquals('Operación exitosa', $data['flash']['success']);
        $this->assertEquals('Advertencia menor', $data['flash']['error']);
        $this->assertEquals('Información adicional', $data['flash']['info']);
    }

    protected function defineRoutes($router): void
    {
        $router->post('/redirect-test-create-unique', function () {
            return redirect('/redirect-test-index-unique')->with('success', 'Recurso creado exitosamente');
        })->middleware('web');

        $router->get('/redirect-test-index-unique', function () {
            return response()->json(['message' => 'Index page']);
        })->middleware('web');

        $router->put('/redirect-test-update-unique/{id}', function ($id) {
            return redirect("/redirect-test-show-unique/{$id}")->with('success', 'Recurso actualizado exitosamente');
        })->middleware('web');

        $router->get('/redirect-test-show-unique/{id}', function ($id) {
            return response()->json(['message' => "Show resource {$id}"]);
        })->middleware('web');

        $router->delete('/redirect-test-delete-unique/{id}', function ($id) {
            return redirect('/redirect-test-index-unique')->with('success', 'Recurso eliminado exitosamente');
        })->middleware('web');

        $router->patch('/redirect-test-patch-unique/{id}', function ($id) {
            return redirect("/redirect-test-show-unique/{$id}")->with('info', 'Estado actualizado');
        })->middleware('web');

        $router->get('/redirect-test-get-unique', function () {
            return redirect('/redirect-test-other-unique');
        })->middleware('web');

        $router->get('/redirect-test-other-unique', function () {
            return response()->json(['message' => 'Other page']);
        })->middleware('web');

        $router->post('/redirect-test-standard-unique', function () {
            return redirect('/redirect-test-target-unique')->with('success', 'Success message');
        })->middleware('web');

        $router->get('/redirect-test-target-unique', function () {
            return response()->json(['message' => 'Target page']);
        })->middleware('web');

        $router->post('/redirect-test-flash-redirect-unique', function () {
            return redirect('/redirect-test-flash-target-unique')
                ->with('success', 'Operación exitosa')
                ->with('error', 'Advertencia menor')
                ->with('info', 'Información adicional');
        })->middleware('web');

        $router->get('/redirect-test-flash-target-unique', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'info' => $request->session()->get('info'),
                ],
            ]);
        })->middleware('web');
    }
}
