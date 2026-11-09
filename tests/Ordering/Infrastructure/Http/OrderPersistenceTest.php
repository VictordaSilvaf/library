<?php

declare(strict_types=1);

namespace Tests\Ordering\Infrastructure\Http;

use App\Ordering\Domain\ValueObject\CustomerId;
use DI\Container;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Tests\TestCase;

class OrderPersistenceTest extends TestCase
{
    private ?Connection $connection = null;

    private ?string $orderId = null;

    protected function tearDown(): void
    {
        if ($this->connection !== null && $this->orderId !== null) {
            $this->connection->executeStatement('DELETE FROM orders WHERE id = ?', [$this->orderId]);
        }

        parent::tearDown();
    }

    public function testCreateOrderWritesTheRow(): void
    {
        $app = $this->getAppInstance();
        $customerId = CustomerId::random()->getValue();

        $response = $app->handle($this->createRequest('POST', '/orders')->withParsedBody([
            'customerId' => $customerId,
            'products' => [[
                'title' => 'Domain-Driven Design',
                'author' => 'Eric Evans',
                'price' => '40.00',
                'currency' => 'BRL',
            ]],
        ]));

        $payload = json_decode((string) $response->getBody(), true);
        $this->orderId = $payload['data']['id'];

        /** @var Container $container */
        $container = $app->getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();
        $this->connection = $entityManager->getConnection();

        $row = $this->connection->fetchAssociative(
            'SELECT customer_id, status, total_amount, total_currency FROM orders WHERE id = ?',
            [$this->orderId]
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertIsArray($row);
        $this->assertSame($customerId, $row['customer_id']);
        $this->assertSame('pending', $row['status']);
        $this->assertSame(4000, (int) $row['total_amount']);
        $this->assertSame('BRL', $row['total_currency']);
    }
}
