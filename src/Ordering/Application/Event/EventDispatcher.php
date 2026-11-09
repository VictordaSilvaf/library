<?php

declare(strict_types=1);

namespace App\Ordering\Application\Event;

interface EventDispatcher
{
    public function dispatch(object $event): void;
}
