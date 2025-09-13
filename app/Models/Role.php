<?php

declare(strict_types=1);

namespace App\Models;

use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Custom Role model that extends Spatie's Role.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Temporary properties used by owen-it/laravel-auditing when dispatching
 * custom audit events. Declared here for static analysis purposes.
 * @property string|null $auditEvent
 * @property array<string, mixed> $auditCustomOld
 * @property array<string, mixed> $auditCustomNew
 * @property bool $isCustomEvent
 *
 * @author Laravel Boilerplate
 */
class Role extends SpatieRole implements AuditableContract
{
    use AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Attributes excluded from auditing to reduce noise.
     *
     * @var list<string>
     */
    protected $auditExclude = [
        // Exclude timestamps if desired; comment out to include them
        // 'created_at', 'updated_at',
    ];
}
