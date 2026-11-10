<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Event;

use App\Bootstrap\PayloadCodec;
use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Infrastructure\Persistence\DoctrineOrderRepository;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventDispatcher;
use App\Shared\Domain\Event\SynchronousEventDispatcher;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Event\OutboxEventPublisher;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use RuntimeException;
use Tests\Ordering\Support\RecordingListener;
use Tests\TestCase;

final class OutboxEventPublisherTest extends TestCase
{
    public function testAFailedDispatchLeavesTheEventUnpublished(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([dirname(__DIR__, 4) . '/src/Ordering/Domain']));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Tests\OutboxProxies');
        $config->setAutoGenerateProxyClasses(true);
        $entityManager = new EntityManager(DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]), $config);
        $repository = new DoctrineOrderRepository($entityManager, PayloadCodec::serializer());
        $order = Order::create(CustomerId::random(), new ProductCollection([
            new Product('Domain-Driven Design', 'Eric Evans', Money::create('40.00', 'USD')),
        ]));
        $events = $repository->save($order);
        $eventId = $events[0]->getEventId()->getValue();
        $failing = new OutboxEventPublisher($entityManager->getConnection(), new class implements EventDispatcher {
            public function dispatch(DomainEvent $event): void
            {
                throw new RuntimeException('listener down');
            }
        });

        try {
            $failing->publish($events);
            $this->fail('A failing listener must leave the event unpublished.');
        } catch (RuntimeException) {
            $this->assertNull($this->publishedAt($entityManager, $eventId));
        }

        $listener = new RecordingListener();
        (new OutboxEventPublisher(
            $entityManager->getConnection(),
            new SynchronousEventDispatcher([$listener]),
        ))->publish($events);

        $this->assertNotNull($this->publishedAt($entityManager, $eventId));
        $this->assertInstanceOf(OrderCreated::class, $listener->events[0]);
    }

    private function publishedAt(EntityManager $entityManager, string $eventId): mixed
    {
        return $entityManager->getConnection()->fetchOne(
            'SELECT published_at FROM domain_events WHERE event_id = ?',
            [$eventId],
        );
    }
}
