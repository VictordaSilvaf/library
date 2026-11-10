<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Event;

interface EventListener
{
    public function handle(object $event): void;
}
