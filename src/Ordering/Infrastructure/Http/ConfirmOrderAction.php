<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Shared\Infrastructure\Http\Action;
use App\Ordering\Application\Command\ConfirmOrder;
use App\Ordering\Application\Handler\ConfirmOrderHandler;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateException;
use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\ValueObject\OrderId;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

class ConfirmOrderAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly ConfirmOrderHandler $handler,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        try {
            $output = $this->handler->handle(new ConfirmOrder($this->orderId()));
        } catch (OrderNotFoundException $exception) {
            throw new HttpNotFoundException($this->request, $exception->getMessage());
        } catch (EmptyOrderException | InvalidOrderStateException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }

        $this->logger->info("Order {$output->getId()} was confirmed.");

        return $this->respondWithData($output);
    }

    private function orderId(): OrderId
    {
        try {
            return new OrderId((string) $this->resolveArg('id'));
        } catch (InvalidArgumentException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }
    }
}
