<?php

declare(strict_types=1);

namespace Tests\Catalog\Application\Handler;

use App\Catalog\Application\Command\CancelCatalog;
use App\Catalog\Application\Command\ConfirmCatalog;
use App\Catalog\Application\Command\CreateCatalog;
use App\Catalog\Application\Handler\CancelCatalogHandler;
use App\Catalog\Application\Handler\ConfirmCatalogHandler;
use App\Catalog\Application\Handler\CreateCatalogHandler;
use App\Catalog\Domain\Event\CatalogCancelled;
use App\Catalog\Domain\Event\CatalogConfirmed;
use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\Event\SynchronousEventDispatcher;
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

    private ConfirmCatalogHandler $confirm;

    private CancelCatalogHandler $cancel;

    protected function setUp(): void
    {
        $this->catalogs = new InMemoryCatalogRepository();
        $this->listener = new RecordingListener();
        $dispatcher = new SynchronousEventDispatcher([$this->listener]);
        $this->create = new CreateCatalogHandler($this->catalogs, $dispatcher);
        $this->confirm = new ConfirmCatalogHandler($this->catalogs, $dispatcher);
        $this->cancel = new CancelCatalogHandler($this->catalogs, $dispatcher);
    }

    public function testCreateDispatchesCatalogCreatedAndReconstitutesPending(): void
    {
        $id = CatalogId::random();
        $catalog = $this->create->handle($this->command($id));
        $stored = $this->catalogs->findById($id);

        $this->assertSame(CatalogStatus::PENDING, $catalog->getStatus());
        $this->assertSame(1, $catalog->getVersion());
        $this->assertInstanceOf(CatalogCreated::class, $this->listener->events[0]);
        $this->assertNotNull($stored);
        $this->assertSame([], $stored->releaseEvents());
        $this->assertSame('Dune', $stored->getName());
    }

    public function testConfirmThenCancelDispatchTheTransitionEvents(): void
    {
        $id = CatalogId::random();
        $this->create->handle($this->command($id));
        $this->listener->events = [];

        $this->confirm->handle(new ConfirmCatalog($id, $this->moment('+1 hour')));
        $this->cancel->handle(new CancelCatalog($id, $this->moment('+2 hours')));

        $this->assertInstanceOf(CatalogConfirmed::class, $this->listener->events[0]);
        $this->assertInstanceOf(CatalogCancelled::class, $this->listener->events[1]);
        $this->assertSame(CatalogStatus::CANCELLED, $this->catalogs->findById($id)?->getStatus());
        $this->assertSame(3, $this->catalogs->findById($id)?->getVersion());
    }

    public function testMissingCatalogDoesNotDispatchAnEvent(): void
    {
        try {
            $this->confirm->handle(new ConfirmCatalog(CatalogId::random(), $this->moment('+1 hour')));
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
