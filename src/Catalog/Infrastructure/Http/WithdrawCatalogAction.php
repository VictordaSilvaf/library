<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Command\WithdrawCatalog;
use App\Catalog\Application\DTO\CatalogOutput;
use App\Catalog\Application\Handler\WithdrawCatalogHandler;
use App\Catalog\Domain\Exception\InvalidCatalogStateException;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Shared\Infrastructure\Http\Action;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

final class WithdrawCatalogAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly WithdrawCatalogHandler $handler,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        try {
            $catalog = $this->handler->handle(new WithdrawCatalog($this->catalogId(), new DateTimeImmutable()));
        } catch (InvalidCatalogStateException $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }

        $output = CatalogOutput::fromCatalog($catalog);
        $this->logger->info("Catalog {$output->getId()} was withdrawn.");

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
