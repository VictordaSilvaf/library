<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Application\DTO\OrderOutput;
use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\Repository\OrderRepository;

class CancelOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    public function handle(CancelOrder $command): OrderOutput
    {
        $order = $this->orders->findById($command->getOrderId());

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        $order->cancel();
        $this->orders->save($order);
        $order->releaseEvents();

        return OrderOutput::fromOrder($order);
    }
}
