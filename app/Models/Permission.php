<?php

declare(strict_types=1);

namespace App\Models;

use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Custom Permission model that extends Spatie's Permission and is auditable.
 */
class Permission extends SpatiePermission implements AuditableContract
{
    use AuditableTrait;

    /**
     * Reduce noise if necessary.
     *
     * @var list<string>
     */
    protected $auditExclude = [
        // 'created_at', 'updated_at',
    ];
}
