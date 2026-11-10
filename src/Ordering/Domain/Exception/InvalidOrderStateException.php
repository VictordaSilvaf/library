<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

class InvalidOrderStateException extends DomainException
{
}
