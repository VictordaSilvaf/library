<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\DTO\CatalogInput;
use App\Catalog\Application\DTO\CatalogOutput;
use App\Catalog\Application\Handler\CreateCatalogHandler;
use App\Catalog\Domain\Exception\EmptyCatalogException;
use App\Catalog\Domain\Exception\InvalidCatalogStateException;
use App\Shared\Infrastructure\Http\Action;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

final class CreateCatalogAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly CreateCatalogHandler $handler,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $data = $this->getFormData();

        if (!is_array($data)) {
            throw new HttpBadRequestException($this->request, 'Invalid catalog payload');
        }

        try {
            $catalog = $this->handler->handle(CatalogInput::fromArray($data)->toCommand(new DateTimeImmutable()));
        } catch (EmptyCatalogException | InvalidCatalogStateException | InvalidArgumentException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }

        $output = CatalogOutput::fromCatalog($catalog);
        $this->logger->info("Catalog {$output->getId()} was created.");

        return $this->respondWithData($output, 201);
    }
}
