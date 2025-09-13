<?php

declare(strict_types=1);

use App\Contracts\Services\AuditServiceInterface;
use App\DTO\ListQuery;
use App\Models\Audit;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AuditServiceInterface::class);

    // Create test user
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // Create test audit record
    $this->audit = Audit::create([
        'user_type' => User::class,
        'user_id' => $this->user->id,
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => $this->user->id,
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'Test User'],
        'url' => '/users',
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Test Agent',
        'tags' => 'test',
        'created_at' => now(),
    ]);
});

it('can instantiate service', function () {
    expect($this->service)->toBeInstanceOf(AuditService::class);
    expect($this->service)->toBeInstanceOf(AuditServiceInterface::class);
});

it('can list audits with proper format', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: []
    );

    $result = $this->service->list($query, ['user']);

    expect($result)->toHaveKeys(['rows', 'meta']);
    expect($result['rows'])->toBeArray();
    expect($result['meta'])->toBeArray();
    expect($result['rows'])->toHaveCount(1);

    $audit = $result['rows'][0];
    expect($audit)->toHaveKeys([
        'id',
        'created_at',
        'user_id',
        'user_name',
        'event',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'url',
        'tags',
        'old_values',
        'new_values',
        'user_agent',
    ]);
});

it('transforms audit model to row format correctly', function () {
    $row = $this->service->toRow($this->audit);

    expect($row)->toBe([
        'id' => $this->audit->id,
        'created_at' => $this->audit->created_at->toISOString(),
        'user_id' => $this->user->id,
        'user_name' => null, // User relation not loaded
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => $this->user->id,
        'ip_address' => '192.168.1.1',
        'url' => '/users',
        'tags' => 'test',
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'Test User'],
        'user_agent' => 'Test Agent',
    ]);
});

it('includes user name when relation is loaded', function () {
    $auditWithUser = Audit::with('user')->find($this->audit->id);
    $row = $this->service->toRow($auditWithUser);

    expect($row['user_name'])->toBe('Test User');
});

it('transforms model to item format', function () {
    $item = $this->service->toItem($this->audit);
    $row = $this->service->toRow($this->audit);

    expect($item)->toBe($row);
});

it('provides default export columns', function () {
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('defaultExportColumns');
    $method->setAccessible(true);
    $columns = $method->invoke($this->service);

    expect($columns)->toBe([
        'id',
        'created_at',
        'user_name',
        'event',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'url',
    ]);
});

it('generates default export filename', function () {
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('defaultExportFilename');
    $method->setAccessible(true);

    $filename = $method->invoke($this->service, 'csv');

    expect($filename)->toMatch('/^auditoria_export_\d{8}_\d{4}\.csv$/');
});

it('generates correct filename extensions for different formats', function () {
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('defaultExportFilename');
    $method->setAccessible(true);

    expect($method->invoke($this->service, 'csv'))->toEndWith('.csv');
    expect($method->invoke($this->service, 'xlsx'))->toEndWith('.xlsx');
    expect($method->invoke($this->service, 'pdf'))->toEndWith('.pdf');
    expect($method->invoke($this->service, 'json'))->toEndWith('.json');
});

it('can export audits', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: []
    );

    $response = $this->service->export($query, 'csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('handles null values gracefully in toRow', function () {
    $audit = Audit::create([
        'user_type' => null,
        'user_id' => null,
        'event' => 'system_event',
        'auditable_type' => null,
        'auditable_id' => null,
        'old_values' => null,
        'new_values' => null,
        'url' => null,
        'ip_address' => null,
        'user_agent' => null,
        'tags' => null,
    ]);

    $row = $this->service->toRow($audit);

    expect($row['user_id'])->toBeNull();
    expect($row['user_name'])->toBeNull();
    expect($row['auditable_type'])->toBeNull();
    expect($row['auditable_id'])->toBeNull();
    expect($row['ip_address'])->toBeNull();
    expect($row['url'])->toBeNull();
    expect($row['tags'])->toBeNull();
    expect($row['old_values'])->toBeNull();
    expect($row['new_values'])->toBeNull();
    expect($row['user_agent'])->toBeNull();
});
