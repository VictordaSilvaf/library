<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Http;

use App\Catalog\Domain\Repository\CatalogRepository;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Shared\Domain\Event\EventPublisher;
use App\Shared\Domain\Event\SynchronousEventDispatcher;
use App\Shared\Infrastructure\Event\DirectEventPublisher;
use App\Shared\Infrastructure\Http\ActionError;
use App\Shared\Infrastructure\Http\ActionPayload;
use App\Shared\Infrastructure\Http\HttpErrorHandler;
use DI\Container;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use Tests\Catalog\Support\InMemoryCatalogRepository;
use Tests\TestCase;

final class CatalogActionTest extends TestCase
{
    public function testCreatePublishAndWithdraw(): void
    {
        $app = $this->getAppInstance();
        $catalogs = $this->useCatalogs($app);
        $categoryId = CategoryId::random()->getValue();

        $created = $app->handle($this->createRequest('POST', '/catalogs')->withParsedBody([
            'sku' => 'book-01',
            'name' => '  Dune  ',
            'description' => 'A novel',
            'price' => '40.00',
            'currency' => 'USD',
            'categoryId' => $categoryId,
            'images' => [['url' => 'https://example.com/dune.jpg']],
        ]));
        $data = $this->payload($created)['data'];

        $this->assertSame(201, $created->getStatusCode());
        $this->assertSame('Dune', $data['name']);
        $this->assertSame('draft', $data['status']);
        $this->assertSame(4000, $data['price']['amount']);
        $this->assertSame([], $catalogs->findById(new CatalogId($data['id']))->releaseEvents());

        $published = $app->handle($this->createRequest('POST', '/catalogs/' . $data['id'] . '/publish'));
        $withdrawn = $app->handle($this->createRequest('POST', '/catalogs/' . $data['id'] . '/withdraw'));

        $this->assertSame('published', $this->payload($published)['data']['status']);
        $this->assertSame(200, $withdrawn->getStatusCode());
        $this->assertSame('withdrawn', $this->payload($withdrawn)['data']['status']);
    }

    public function testCreateRejectsACatalogWithoutImages(): void
    {
        $app = $this->appWithErrors();
        $this->useCatalogs($app);

        $response = $app->handle($this->createRequest('POST', '/catalogs')->withParsedBody([
            'sku' => 'book-01',
            'name' => 'Dune',
            'description' => 'A novel',
            'price' => '40.00',
            'currency' => 'USD',
            'categoryId' => CategoryId::random()->getValue(),
            'images' => [],
        ]));
        $expected = new ActionPayload(
            400,
            null,
            new ActionError(ActionError::BAD_REQUEST, 'Catalog must contain at least one image.'),
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(json_encode($expected, JSON_PRETTY_PRINT), (string) $response->getBody());
    }

    public function testPublishReturnsNotFoundWhenTheCatalogDoesNotExist(): void
    {
        $app = $this->appWithErrors();
        $this->useCatalogs($app);

        $response = $app->handle($this->createRequest('POST', '/catalogs/' . CatalogId::random()->getValue() . '/publish'));
        $expected = new ActionPayload(404, null, new ActionError(ActionError::RESOURCE_NOT_FOUND, 'Catalog not found'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(json_encode($expected, JSON_PRETTY_PRINT), (string) $response->getBody());
    }

    private function useCatalogs(App $app): InMemoryCatalogRepository
    {
        $catalogs = new InMemoryCatalogRepository();

        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(CatalogRepository::class, $catalogs);
        $container->set(EventPublisher::class, new DirectEventPublisher(new SynchronousEventDispatcher([])));

        return $catalogs;
    }

    private function appWithErrors(): App
    {
        $app = $this->getAppInstance();
        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();
        $errorMiddleware = new ErrorMiddleware($callableResolver, $responseFactory, true, false, false);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler($callableResolver, $responseFactory));
        $app->add($errorMiddleware);

        return $app;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(\Psr\Http\Message\ResponseInterface $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true);

        return $payload;
    }
}
