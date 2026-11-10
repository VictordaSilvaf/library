<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\DomainRecordNotFoundException;

final class CatalogNotFoundException extends DomainRecordNotFoundException
{
    public function __construct()
    {
        parent::__construct('Catalog not found');
    }
}
