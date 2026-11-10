<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Aggregate;

use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\Event\CatalogPublished;
use App\Catalog\Domain\Event\CatalogWithdrawn;
use App\Catalog\Domain\Event\CatalogDescriptionChanged;
use App\Catalog\Domain\Event\CatalogImagesReplaced;
use App\Catalog\Domain\Event\CatalogNameChanged;
use App\Catalog\Domain\Event\CatalogPriceChanged;
use App\Catalog\Domain\Exception\EmptyCatalogException;
use App\Catalog\Domain\Exception\InvalidCatalogStateException;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\Image;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventId;
use App\Shared\Domain\Event\EventSourcedAggregate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;

final class Catalog
{
    use EventSourcedAggregate;

    private const NAME_LIMIT = 255;

    private const DESCRIPTION_LIMIT = 4000;

    private CatalogId $id;

    private Sku $sku;

    private string $name;

    private string $description;

    private Money $price;

    private CategoryId $categoryId;

    private CatalogStatus $status;

    private ImageCollection $images;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $publishedAt = null;

    private ?DateTimeImmutable $withdrawnAt = null;

    private function __construct()
    {
    }

    public static function create(
        CatalogId $id,
        Sku $sku,
        string $name,
        string $description,
        Money $price,
        CategoryId $categoryId,
        ImageCollection $images,
        DateTimeImmutable $occurredAt,
    ): self {
        $name = self::normalizeText($name, 'name');
        $description = self::normalizeText($description, 'description');
        self::assertValidName($name);
        self::assertValidDescription($description);
        self::assertValidPrice($price);
        $images = $images->copy();
        self::assertHasImages($images);

        $catalog = new self();
        $catalog->record(new CatalogCreated(
            eventId: EventId::random(),
            catalogId: $id,
            sku: $sku,
            name: $name,
            description: $description,
            price: $price,
            categoryId: $categoryId,
            images: $images,
            status: CatalogStatus::DRAFT,
            occurredAt: self::utc($occurredAt),
        ));

        return $catalog;
    }

    /**
     * @param list<DomainEvent> $history
     */
    public static function reconstitute(array $history): self
    {
        $catalog = new self();
        $catalog->replay($history);

        return $catalog;
    }

    public function publish(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatus(CatalogStatus::DRAFT, 'publish');

        $this->record(new CatalogPublished(
            eventId: EventId::random(),
            catalogId: $this->id,
            status: CatalogStatus::PUBLISHED,
            occurredAt: $this->validatedOccurredAt($occurredAt),
        ));
    }

    public function withdraw(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatus(CatalogStatus::PUBLISHED, 'withdraw');

        $this->record(new CatalogWithdrawn(
            eventId: EventId::random(),
            catalogId: $this->id,
            status: CatalogStatus::WITHDRAWN,
            occurredAt: $this->validatedOccurredAt($occurredAt),
        ));
    }

    public function changeName(string $name, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();
        $name = self::normalizeText($name, 'name');
        self::assertValidName($name);

        if ($this->name === $name) {
            return;
        }

        $this->record(new CatalogNameChanged(
            eventId: EventId::random(),
            catalogId: $this->id,
            previousName: $this->name,
            newName: $name,
            occurredAt: $this->validatedOccurredAt($occurredAt),
        ));
    }

    public function changeDescription(string $description, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();
        $description = self::normalizeText($description, 'description');
        self::assertValidDescription($description);

        if ($this->description === $description) {
            return;
        }

        $this->record(new CatalogDescriptionChanged(
            eventId: EventId::random(),
            catalogId: $this->id,
            previousDescription: $this->description,
            newDescription: $description,
            occurredAt: $this->validatedOccurredAt($occurredAt),
        ));
    }

    public function changePrice(Money $price, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();
        self::assertValidPrice($price);

        if ($this->price->equals($price)) {
            return;
        }

        $this->record(new CatalogPriceChanged(
            eventId: EventId::random(),
            catalogId: $this->id,
            previousPrice: $this->price,
            newPrice: $price,
            occurredAt: $this->validatedOccurredAt($occurredAt),
        ));
    }

    public function replaceImages(ImageCollection $images, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();
        $images = $images->copy();
        self::assertHasImages($images);

        if ($this->sameImages($this->images, $images)) {
            return;
        }

        $this->record(new CatalogImagesReplaced(
            eventId: EventId::random(),
            catalogId: $this->id,
            images: $images,
            occurredAt: $this->validatedOccurredAt($occurredAt),
        ));
    }

    public function getId(): CatalogId
    {
        return $this->id;
    }

    public function getSku(): Sku
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getCategoryId(): CategoryId
    {
        return $this->categoryId;
    }

    public function getStatus(): CatalogStatus
    {
        return $this->status;
    }

    public function getImages(): ImageCollection
    {
        return $this->images->copy();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getWithdrawnAt(): ?DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    protected function apply(DomainEvent $event): void
    {
        if ($event instanceof CatalogCreated) {
            $this->id = $event->catalogId;
            $this->sku = $event->sku;
            $this->name = $event->name;
            $this->description = $event->description;
            $this->price = $event->price;
            $this->categoryId = $event->categoryId;
            $this->status = $event->status;
            $this->images = $event->images->copy();
            $this->createdAt = $event->occurredAt;
            $this->updatedAt = $event->occurredAt;
            $this->publishedAt = null;
            $this->withdrawnAt = null;

            return;
        }

        if ($event instanceof CatalogPublished) {
            $this->status = $event->status;
            $this->publishedAt = $event->occurredAt;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof CatalogWithdrawn) {
            $this->status = $event->status;
            $this->withdrawnAt = $event->occurredAt;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof CatalogNameChanged) {
            $this->name = $event->newName;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof CatalogDescriptionChanged) {
            $this->description = $event->newDescription;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof CatalogPriceChanged) {
            $this->price = $event->newPrice;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof CatalogImagesReplaced) {
            $this->images = $event->images->copy();
            $this->updatedAt = $event->occurredAt;
        }
    }

    private function assertStatus(CatalogStatus $expected, string $operation): void
    {
        if ($this->status !== $expected) {
            throw new InvalidCatalogStateException(sprintf(
                'Cannot %s catalog in status %s; expected %s.',
                $operation,
                $this->status->value,
                $expected->value,
            ));
        }
    }

    private function assertNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidCatalogStateException(sprintf(
                'Cannot modify catalog in terminal status %s.',
                $this->status->value,
            ));
        }
    }

    private function validatedOccurredAt(DateTimeImmutable $occurredAt): DateTimeImmutable
    {
        $occurredAt = self::utc($occurredAt);

        if ($occurredAt < $this->updatedAt) {
            throw new InvalidCatalogStateException('Occurred at cannot precede the last change.');
        }

        return $occurredAt;
    }

    private static function assertHasImages(ImageCollection $images): void
    {
        if ($images->isEmpty()) {
            throw new EmptyCatalogException('Catalog must contain at least one image.');
        }
    }

    private static function normalizeText(string $value, string $field): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidCatalogStateException(sprintf('Catalog %s must be valid UTF-8.', $field));
        }

        $value = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value);

        if ($value === null || $value === '' || str_contains($value, "\0")) {
            throw new InvalidCatalogStateException(sprintf('Catalog %s is invalid or empty.', $field));
        }

        return $value;
    }

    private static function assertValidName(string $name): void
    {
        if (mb_strlen($name, 'UTF-8') > self::NAME_LIMIT) {
            throw new InvalidCatalogStateException('Catalog name cannot exceed 255 characters.');
        }
    }

    private static function assertValidDescription(string $description): void
    {
        if (mb_strlen($description, 'UTF-8') > self::DESCRIPTION_LIMIT) {
            throw new InvalidCatalogStateException('Catalog description cannot exceed 4000 characters.');
        }
    }

    private static function assertValidPrice(Money $price): void
    {
        if (!$price->isPositive()) {
            throw new InvalidCatalogStateException('Catalog price must be greater than zero.');
        }
    }

    private static function sameImages(ImageCollection $left, ImageCollection $right): bool
    {
        $urls = static fn (ImageCollection $images): array => array_map(
            static fn (Image $image): string => $image->url,
            $images->all(),
        );

        return $urls($left) === $urls($right);
    }

    private static function utc(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
