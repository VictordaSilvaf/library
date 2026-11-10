<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Bootstrap\PayloadCodec;
use App\Catalog\Infrastructure\Persistence\DoctrineCatalogRepository;
use App\Shared\Domain\Exception\ConcurrentModificationException;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Tests\TestCase;

final class DoctrineCatalogRepositoryTest extends TestCase
{
    private DoctrineCatalogRepository $repository;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([dirname(__DIR__, 4) . '/src/Catalog/Domain']));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Tests\CatalogProxies');
        $config->setAutoGenerateProxyClasses(true);
        $entityManager = new EntityManager(DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]), $config);
        $this->repository = new DoctrineCatalogRepository($entityManager, PayloadCodec::serializer());
    }

    public function testReloadRebuildsTheCatalogFromTheEventStream(): void
    {
        $id = CatalogId::random();
        $catalog = $this->catalog($id);
        $this->repository->save($catalog);

        $loaded = $this->repository->findById($id);

        $this->assertNotNull($loaded);
        $this->assertSame(CatalogStatus::DRAFT, $loaded->getStatus());
        $this->assertSame('Dune', $loaded->getName());
        $this->assertSame(1, $loaded->getVersion());
        $this->assertSame([], $loaded->releaseEvents());

        $loaded->publish($this->moment('+1 hour'));
        $this->repository->save($loaded);

        $published = $this->repository->findById($id);

        $this->assertSame(CatalogStatus::PUBLISHED, $published?->getStatus());
        $this->assertSame(2, $published?->getVersion());
    }

    public function testAStaleStreamIsRejected(): void
    {
        $id = CatalogId::random();
        $this->repository->save($this->catalog($id));
        $first = $this->repository->findById($id);
        $second = $this->repository->findById($id);
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $first->publish($this->moment('+1 hour'));
        $second->publish($this->moment('+2 hours'));
        $this->repository->save($first);

        $this->expectException(ConcurrentModificationException::class);
        $this->repository->save($second);
    }

    private function catalog(CatalogId $id): Catalog
    {
        return Catalog::create(
            $id,
            new Sku('BOOK-01'),
            'Dune',
            'A novel',
            Money::create('40.00', 'USD'),
            CategoryId::random(),
            new ImageCollection(new Image('https://example.com/dune.jpg')),
            $this->moment(),
        );
    }

    private function moment(string $modify = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-11-10 15:00:00', new DateTimeZone('UTC'));

        return $modify === '' ? $time : $time->modify($modify);
    }
}
