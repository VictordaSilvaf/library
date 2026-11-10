<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Command\PublishCatalog;
use App\Catalog\Application\DTO\CatalogOutput;
use App\Catalog\Application\Handler\PublishCatalogHandler;
use App\Catalog\Domain\Exception\InvalidCatalogStateException;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Shared\Infrastructure\Http\Action;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

final class PublishCatalogAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PublishCatalogHandler $handler,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        try {
            $catalog = $this->handler->handle(new PublishCatalog($this->catalogId(), new DateTimeImmutable()));
        } catch (InvalidCatalogStateException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }

        $output = CatalogOutput::fromCatalog($catalog);
        $this->logger->info("Catalog {$output->getId()} was published.");

        return $this->respondWithData($output);
    }

    private function catalogId(): CatalogId
    {
        try {
            return new CatalogId((string) $this->resolveArg('id'));
        } catch (InvalidArgumentException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }
    }
}
