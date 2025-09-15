<?php

declare(strict_types=1);

namespace App\Policies;

class DocumentTypePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.document-type';
    }
}
