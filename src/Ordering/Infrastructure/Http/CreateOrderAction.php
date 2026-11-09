<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Application\Actions\Action;
use App\Ordering\Application\DTO\OrderInput;
use App\Ordering\Application\Handler\CreateOrderHandler;
use App\Ordering\Domain\Exception\EmptyOrderException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

class CreateOrderAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly CreateOrderHandler $handler,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $data = $this->getFormData();

        if (!is_array($data)) {
            throw new HttpBadRequestException($this->request, 'Invalid order payload');
        }

        try {
            $output = $this->handler->handle(OrderInput::fromArray($data)->toCommand());
        } catch (EmptyOrderException | InvalidArgumentException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }

        $this->logger->info("Order {$output->getId()} was created.");

        return $this->respondWithData($output, 201);
    }
}
