<?php

declare(strict_types=1);

namespace Tests\Catalog\Application\Handler;

use App\Catalog\Application\Command\CreateCatalog;
use App\Catalog\Application\Command\PublishCatalog;
use App\Catalog\Application\Command\WithdrawCatalog;
use App\Catalog\Application\Handler\CreateCatalogHandler;
use App\Catalog\Application\Handler\PublishCatalogHandler;
use App\Catalog\Application\Handler\WithdrawCatalogHandler;
use App\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Catalog\Support\InMemoryCatalogRepository;
use Tests\TestCase;

final class CatalogHandlerTest extends TestCase
{
    private InMemoryCatalogRepository $catalogs;

    private CreateCatalogHandler $create;

    private PublishCatalogHandler $publish;

    private WithdrawCatalogHandler $withdraw;

    protected function setUp(): void
    {
        $this->catalogs = new InMemoryCatalogRepository();
        $this->create = new CreateCatalogHandler($this->catalogs);
        $this->publish = new PublishCatalogHandler($this->catalogs);
        $this->withdraw = new WithdrawCatalogHandler($this->catalogs);
    }

    public function testCreateStoresADraft(): void
    {
        $id = CatalogId::random();
        $catalog = $this->create->handle($this->command($id));
        $stored = $this->catalogs->findById($id);

        $this->assertSame(CatalogStatus::DRAFT, $catalog->getStatus());
        $this->assertSame(1, $catalog->getVersion());
        $this->assertNotNull($stored);
        $this->assertSame([], $stored->releaseEvents());
        $this->assertSame('Dune', $stored->getName());
    }

    public function testPublishThenWithdrawStoresTheTransitions(): void
    {
        $id = CatalogId::random();
        $this->create->handle($this->command($id));
        $this->publish->handle(new PublishCatalog($id, $this->moment('+1 hour')));
        $this->withdraw->handle(new WithdrawCatalog($id, $this->moment('+2 hours')));

        $this->assertSame(CatalogStatus::WITHDRAWN, $this->catalogs->findById($id)?->getStatus());
        $this->assertSame(3, $this->catalogs->findById($id)?->getVersion());
    }

    public function testMissingCatalogIsRejected(): void
    {
        $this->expectException(CatalogNotFoundException::class);

        $this->publish->handle(new PublishCatalog(CatalogId::random(), $this->moment('+1 hour')));
    }

    private function command(CatalogId $id): CreateCatalog
    {
        return new CreateCatalog(
            $id,
            new Sku('BOOK-01'),
            '  Dune  ',
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
