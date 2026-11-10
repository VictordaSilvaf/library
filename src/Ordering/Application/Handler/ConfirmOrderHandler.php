<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\ConfirmOrder;
use App\Ordering\Application\DTO\OrderOutput;
use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\Repository\OrderRepository;

class ConfirmOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    public function handle(ConfirmOrder $command): OrderOutput
    {
        $order = $this->orders->findById($command->getOrderId());

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        $order->confirm();
        $this->orders->save($order);

        return OrderOutput::fromOrder($order);
    }
}
