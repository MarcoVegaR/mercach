<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Contracts\Services\UserServiceInterface;
use App\DTO\ListQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserServiceInterface::class);
    }

    public function test_list_returns_rows_and_meta(): void
    {
        User::factory()->count(3)->create();

        $result = $this->service->list(new ListQuery(perPage: 10));

        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertGreaterThanOrEqual(1, count($result['rows']));
        $this->assertArrayHasKey('total', $result['meta']);
    }

    public function test_export_streams_csv_with_expected_headers(): void
    {
        $user = User::factory()->create(['name' => 'csv_user']);

        $response = $this->service->export(new ListQuery, 'csv');

        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('content-type'));
        $this->assertTrue($response->headers->has('content-disposition'));

        $content = $response->getContent();
        // StreamedResponse may not return content directly; fallback
        if ($content === false || $content === null) {
            $content = $response->getCallback() ? $this->captureStreamedContent($response) : '';
        }
        $this->assertStringContainsString('csv_user', (string) $content);
    }

    private function captureStreamedContent(\Symfony\Component\HttpFoundation\StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
