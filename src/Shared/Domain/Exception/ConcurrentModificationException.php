<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class ConcurrentModificationException extends DomainException
{
    public function __construct(string $streamId, int $expectedVersion, int $actualVersion)
    {
        parent::__construct(sprintf(
            'Stream %s expected version %d and found %d.',
            $streamId,
            $expectedVersion,
            $actualVersion,
        ));
    }
}
