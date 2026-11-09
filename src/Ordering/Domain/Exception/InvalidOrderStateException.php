<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Exception;

use App\Domain\DomainException\DomainException;

class InvalidOrderStateException extends DomainException
{
}
