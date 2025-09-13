<?php

declare(strict_types=1);

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\DTO\ListQuery;
use App\Models\Audit;
use App\Models\User;
use App\Repositories\AuditRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(AuditRepositoryInterface::class);

    // Create test user
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // Create test audit records
    createTestAudits();
});

function createTestAudits(): void
{
    // Create various audit records for testing
    Audit::create([
        'user_type' => User::class,
        'user_id' => test()->user->id,
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => test()->user->id,
        'old_values' => null,
        'new_values' => ['name' => 'Test User'],
        'url' => '/users',
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Test Agent',
        'tags' => null,
        'created_at' => now()->subDays(2),
    ]);

    Audit::create([
        'user_type' => User::class,
        'user_id' => test()->user->id,
        'event' => 'updated',
        'auditable_type' => User::class,
        'auditable_id' => test()->user->id,
        'old_values' => ['name' => 'Test User'],
        'new_values' => ['name' => 'Updated User'],
        'url' => '/users/'.test()->user->id,
        'ip_address' => '192.168.1.2',
        'user_agent' => 'Test Agent Updated',
        'tags' => 'important',
        'created_at' => now()->subDay(),
    ]);

    Audit::create([
        'user_type' => User::class,
        'user_id' => test()->user->id,
        'event' => 'login',
        'auditable_type' => null,
        'auditable_id' => null,
        'old_values' => null,
        'new_values' => ['ip' => '192.168.1.3'],
        'url' => '/login',
        'ip_address' => '192.168.1.3',
        'user_agent' => 'Login Agent',
        'tags' => null,
        'created_at' => now(),
    ]);
}

it('can instantiate repository', function () {
    expect($this->repository)->toBeInstanceOf(AuditRepository::class);
    expect($this->repository)->toBeInstanceOf(AuditRepositoryInterface::class);
});

it('can paginate audits with default parameters', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: []
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(3);
    expect($result->perPage())->toBe(10);
    expect($result->currentPage())->toBe(1);
    expect($result->items())->toHaveCount(3);

    // Should be sorted by created_at desc by default
    $items = $result->items();
    expect($items[0]->event)->toBe('login');
    expect($items[1]->event)->toBe('updated');
    expect($items[2]->event)->toBe('created');
});

it('can search audits by user name or ip (global search)', function () {
    $query = new ListQuery(
        // Search by partial IP should match the login record created below
        q: '192.168.1.3',
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: []
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->event)->toBe('login');
});

it('can filter audits by user_id', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: ['user_id' => $this->user->id]
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(3);
    foreach ($result->items() as $audit) {
        expect($audit->user_id)->toBe($this->user->id);
    }
});

it('can filter audits by event', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: ['event' => 'updated']
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->event)->toBe('updated');
});

it('can filter audits by auditable_type', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: ['auditable_type' => User::class]
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(2);
    foreach ($result->items() as $audit) {
        expect($audit->auditable_type)->toBe(User::class);
    }
});

it('can filter audits by ip_address', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: ['ip_address' => '192.168.1.1']
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->ip_address)->toBe('192.168.1.1');
});

it('can filter audits by date range', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: [
            'created_between' => [
                'from' => now()->subDays(2)->format('Y-m-d'),
                'to' => now()->subDay()->format('Y-m-d'),
            ],
        ]
    );

    $result = $this->repository->paginate($query);

    expect($result->total())->toBe(2);
});

it('can sort audits by allowed columns', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: 'event',
        dir: 'asc',
        filters: []
    );

    $result = $this->repository->paginate($query);

    $events = collect($result->items())->pluck('event')->toArray();
    expect($events)->toBe(['created', 'login', 'updated']);
});

it('loads user relationship eagerly', function () {
    $query = new ListQuery(
        q: null,
        page: 1,
        perPage: 10,
        sort: null,
        dir: 'desc',
        filters: []
    );

    $result = $this->repository->paginate($query, ['user']);

    // Check that user relationship is loaded
    $audit = collect($result->items())->first();
    expect($audit->relationLoaded('user'))->toBeTrue();
    expect($audit->user->name)->toBe('Test User');
});

it('respects pagination parameters', function () {
    $query = new ListQuery(
        q: null,
        page: 2,
        perPage: 2,
        sort: null,
        dir: 'desc',
        filters: []
    );

    $result = $this->repository->paginate($query);

    expect($result->currentPage())->toBe(2);
    expect($result->perPage())->toBe(2);
    expect($result->items())->toHaveCount(1); // Only 1 item on page 2 when perPage is 2
});
