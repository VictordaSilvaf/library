<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\Shared\Domain\Exception\DomainRecordNotFoundException;

class OrderNotFoundException extends DomainRecordNotFoundException
{
    public function __construct()
    {
        parent::__construct('Order not found');
    }
}
