<?php

declare(strict_types=1);

namespace Tests\Ordering\Infrastructure\Http;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Application\Handlers\HttpErrorHandler;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use DI\Container;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use Tests\Ordering\Support\InMemoryOrderRepository;
use Tests\TestCase;

class OrderActionTest extends TestCase
{
    public function testCreateOrder(): void
    {
        $app = $this->getAppInstance();
        $orders = $this->useOrders($app);
        $customerId = CustomerId::random()->getValue();

        $response = $app->handle($this->createRequest('POST', '/orders')->withParsedBody([
            'customerId' => $customerId,
            'books' => [
                $this->book('Domain-Driven Design', '40.00'),
            ],
        ]));

        $payload = $this->payload($response);
        $data = $payload['data'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($customerId, $data['customerId']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame(4000, $data['total']['amount']);
        $this->assertSame('USD', $data['total']['currency']);
        $this->assertSame('Domain-Driven Design', $data['books'][0]['title']);
        $this->assertSame(4000, $data['books'][0]['price']['amount']);
        $this->assertSame([], $orders->findById(new OrderId($data['id']))->releaseEvents());
    }

    public function testConfirmOrder(): void
    {
        $app = $this->getAppInstance();
        $this->useOrders($app);
        $created = $this->payload($app->handle($this->createRequest('POST', '/orders')->withParsedBody([
            'customerId' => CustomerId::random()->getValue(),
            'books' => [
                $this->book('Domain-Driven Design', '40.00'),
            ],
        ])));

        $response = $app->handle($this->createRequest('POST', '/orders/' . $created['data']['id'] . '/confirm'));
        $data = $this->payload($response)['data'];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('confirmed', $data['status']);
        $this->assertSame(4000, $data['total']['amount']);
    }

    public function testCancelOrder(): void
    {
        $app = $this->getAppInstance();
        $this->useOrders($app);
        $created = $this->payload($app->handle($this->createRequest('POST', '/orders')->withParsedBody([
            'customerId' => CustomerId::random()->getValue(),
            'books' => [
                $this->book('Domain-Driven Design', '40.00'),
            ],
        ])));

        $response = $app->handle($this->createRequest('POST', '/orders/' . $created['data']['id'] . '/cancel'));
        $data = $this->payload($response)['data'];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('cancelled', $data['status']);
        $this->assertSame($created['data']['id'], $data['id']);
    }

    public function testCreateRejectsEmptyOrder(): void
    {
        $app = $this->appWithErrors();
        $this->useOrders($app);

        $response = $app->handle($this->createRequest('POST', '/orders')->withParsedBody([
            'customerId' => CustomerId::random()->getValue(),
            'books' => [],
        ]));

        $expected = new ActionPayload(400, null, new ActionError(ActionError::BAD_REQUEST, 'Books are required'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(json_encode($expected, JSON_PRETTY_PRINT), (string) $response->getBody());
    }

    public function testConfirmReturnsNotFoundWhenTheOrderDoesNotExist(): void
    {
        $app = $this->appWithErrors();
        $this->useOrders($app);

        $response = $app->handle($this->createRequest('POST', '/orders/' . OrderId::random()->getValue() . '/confirm'));
        $expected = new ActionPayload(404, null, new ActionError(ActionError::RESOURCE_NOT_FOUND, 'Order not found'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(json_encode($expected, JSON_PRETTY_PRINT), (string) $response->getBody());
    }

    private function useOrders(App $app): InMemoryOrderRepository
    {
        $orders = new InMemoryOrderRepository();

        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(OrderRepository::class, $orders);

        return $orders;
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
     * @return array{title: string, author: string, price: string, currency: string}
     */
    private function book(string $title, string $price): array
    {
        return [
            'title' => $title,
            'author' => 'Eric Evans',
            'price' => $price,
            'currency' => 'USD',
        ];
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
