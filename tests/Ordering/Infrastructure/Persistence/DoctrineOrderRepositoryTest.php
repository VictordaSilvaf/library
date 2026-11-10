<?php

declare(strict_types=1);

namespace Tests\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Bootstrap\PayloadCodec;
use App\Ordering\Infrastructure\Persistence\DoctrineOrderRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Tests\TestCase;

class DoctrineOrderRepositoryTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineOrderRepository $repository;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([dirname(__DIR__, 4) . '/src/Ordering/Domain']));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Tests\DoctrineProxies');
        $config->setAutoGenerateProxyClasses(true);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->entityManager = new EntityManager($connection, $config);
        $this->repository = new DoctrineOrderRepository($this->entityManager, PayloadCodec::serializer());
    }

    public function testPersistsAndReloadsAnOrderById(): void
    {
        $customerId = CustomerId::random();
        $order = Order::create($customerId, new ProductCollection([
            $this->product('Domain-Driven Design', '40.50', 'BRL'),
        ]));
        $id = $order->getId();

        $this->repository->save($order);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($id);

        $this->assertInstanceOf(Order::class, $loaded);
        $this->assertSame($id->getValue(), $loaded->getId()->getValue());
        $this->assertSame($customerId->getValue(), $loaded->getCustomerId()->getValue());
        $this->assertTrue($loaded->getStatus()->isPending());
        $this->assertSame(4050, $loaded->getTotal()->getAmount());
        $this->assertSame('BRL', $loaded->getTotal()->getCurrency());
        $this->assertSame('Domain-Driven Design', $loaded->getProducts()->getProducts()[0]->getTitle());
        $this->assertSame(4050, $loaded->getProducts()->getProducts()[0]->getPrice()->getAmount());
        $this->assertSame('BRL', $loaded->getProducts()->getProducts()[0]->getPrice()->getCurrency());
        $this->assertSame([], $loaded->releaseEvents());
        $this->assertNull($loaded->getConfirmedAt());
        $this->assertNull($loaded->getCancelledAt());
        $this->assertSame(
            $loaded->getId()->getValue(),
            $this->repository->findByCustomerId($customerId)?->getId()->getValue()
        );

        $loaded->confirm();
        $this->repository->save($loaded);
        $this->entityManager->clear();

        $confirmed = $this->repository->findById($id);

        $this->assertInstanceOf(Order::class, $confirmed);
        $this->assertTrue($confirmed->getStatus()->isConfirmed());
        $this->assertInstanceOf(\DateTimeImmutable::class, $confirmed->getConfirmedAt());
        $this->assertSame([], $confirmed->releaseEvents());
    }

    public function testAddProductIsPersistedOnTheNextSave(): void
    {
        $order = Order::create(CustomerId::random(), new ProductCollection([
            $this->product('Domain-Driven Design', '40', 'USD'),
        ]));
        $this->repository->save($order);

        $order->addProduct($this->product('Implementing Domain-Driven Design', '45', 'USD'));
        $this->repository->save($order);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($order->getId());

        $this->assertInstanceOf(Order::class, $loaded);
        $this->assertCount(2, $loaded->getProducts()->getProducts());
        $this->assertSame(8500, $loaded->getTotal()->getAmount());
        $this->assertSame('USD', $loaded->getTotal()->getCurrency());
    }

    public function testFindByIdReturnsNullWhenTheOrderDoesNotExist(): void
    {
        $this->assertNull($this->repository->findById(OrderId::random()));
    }

    private function product(string $title, string $amount, string $currency): Product
    {
        return new Product($title, 'Eric Evans', Money::create($amount, $currency));
    }
}
