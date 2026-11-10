<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

interface DomainEventLogger
{
    public function handle(object $event): void;
}
