<?php

declare(strict_types=1);

namespace Tests\Catalog\Application\Handler;

use App\Catalog\Application\Command\CreateCatalog;
use App\Catalog\Application\Command\PublishCatalog;
use App\Catalog\Application\Command\WithdrawCatalog;
use App\Catalog\Application\Handler\CreateCatalogHandler;
use App\Catalog\Application\Handler\PublishCatalogHandler;
use App\Catalog\Application\Handler\WithdrawCatalogHandler;
use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\Event\CatalogPublished;
use App\Catalog\Domain\Event\CatalogWithdrawn;
use App\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\Event\SynchronousEventDispatcher;
use App\Shared\Infrastructure\Event\DirectEventPublisher;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Catalog\Support\InMemoryCatalogRepository;
use Tests\Ordering\Support\RecordingListener;
use Tests\TestCase;

final class CatalogHandlerTest extends TestCase
{
    private InMemoryCatalogRepository $catalogs;

    private RecordingListener $listener;

    private CreateCatalogHandler $create;

    private PublishCatalogHandler $publish;

    private WithdrawCatalogHandler $withdraw;

    protected function setUp(): void
    {
        $this->catalogs = new InMemoryCatalogRepository();
        $this->listener = new RecordingListener();
        $publisher = new DirectEventPublisher(new SynchronousEventDispatcher([$this->listener]));
        $this->create = new CreateCatalogHandler($this->catalogs, $publisher);
        $this->publish = new PublishCatalogHandler($this->catalogs, $publisher);
        $this->withdraw = new WithdrawCatalogHandler($this->catalogs, $publisher);
    }

    public function testCreateDispatchesCatalogCreatedAndReconstitutesDraft(): void
    {
        $id = CatalogId::random();
        $catalog = $this->create->handle($this->command($id));
        $stored = $this->catalogs->findById($id);

        $this->assertSame(CatalogStatus::DRAFT, $catalog->getStatus());
        $this->assertSame(1, $catalog->getVersion());
        $this->assertInstanceOf(CatalogCreated::class, $this->listener->events[0]);
        $this->assertNotNull($stored);
        $this->assertSame([], $stored->releaseEvents());
        $this->assertSame('Dune', $stored->getName());
    }

    public function testPublishThenWithdrawDispatchTheTransitionEvents(): void
    {
        $id = CatalogId::random();
        $this->create->handle($this->command($id));
        $this->listener->events = [];

        $this->publish->handle(new PublishCatalog($id, $this->moment('+1 hour')));
        $this->withdraw->handle(new WithdrawCatalog($id, $this->moment('+2 hours')));

        $this->assertInstanceOf(CatalogPublished::class, $this->listener->events[0]);
        $this->assertInstanceOf(CatalogWithdrawn::class, $this->listener->events[1]);
        $this->assertSame(CatalogStatus::WITHDRAWN, $this->catalogs->findById($id)?->getStatus());
        $this->assertSame(3, $this->catalogs->findById($id)?->getVersion());
    }

    public function testMissingCatalogDoesNotDispatchAnEvent(): void
    {
        try {
            $this->publish->handle(new PublishCatalog(CatalogId::random(), $this->moment('+1 hour')));
            $this->fail('A missing catalog must be rejected');
        } catch (CatalogNotFoundException) {
            $this->assertSame([], $this->listener->events);
        }
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
