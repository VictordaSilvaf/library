<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Aggregate;

use App\Catalog\Domain\Event\CatalogCancelled;
use App\Catalog\Domain\Event\CatalogConfirmed;
use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\Event\CatalogDescriptionChanged;
use App\Catalog\Domain\Event\CatalogImagesReplaced;
use App\Catalog\Domain\Event\CatalogNameChanged;
use App\Catalog\Domain\Event\CatalogPriceChanged;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\Event\RecordsDomainEvents;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final class Catalog
{
    use RecordsDomainEvents;

    private int $version = 1;

    private function __construct(
        private readonly CatalogId $id,
        private readonly Sku $sku,
        private string $name,
        private string $description,
        private Money $price,
        private readonly CategoryId $categoryId,
        private CatalogStatus $status,
        private ImageCollection $images,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $confirmedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {
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
        if ($images->isEmpty()) {
            throw new DomainException('Catalog must contain at least one image.');
        }

        $occurredAt = self::utc($occurredAt);

        $catalog = new self(
            id: $id,
            sku: $sku,
            name: $name,
            description: $description,
            price: $price,
            categoryId: $categoryId,
            status: CatalogStatus::PENDING,
            images: $images,
            createdAt: $occurredAt,
            updatedAt: $occurredAt,
        );

        $catalog->record(new CatalogCreated(
            eventId: self::newEventId(),
            catalogId: $id,
            sku: $sku,
            name: $name,
            description: $description,
            price: $price,
            categoryId: $categoryId,
            images: $images,
            status: CatalogStatus::PENDING,
            occurredAt: $occurredAt,
        ));

        return $catalog;
    }

    public function confirm(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatus(CatalogStatus::PENDING, 'confirm');

        $occurredAt = $this->validatedOccurredAt($occurredAt);

        $this->status = CatalogStatus::CONFIRMED;
        $this->confirmedAt = $occurredAt;
        $this->updatedAt = $occurredAt;
        $this->version++;

        $this->record(new CatalogConfirmed(
            eventId: self::newEventId(),
            catalogId: $this->id,
            status: $this->status,
            occurredAt: $occurredAt,
        ));
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatus(CatalogStatus::CONFIRMED, 'cancel');

        $occurredAt = $this->validatedOccurredAt($occurredAt);

        $this->status = CatalogStatus::CANCELLED;
        $this->cancelledAt = $occurredAt;
        $this->updatedAt = $occurredAt;
        $this->version++;

        $this->record(new CatalogCancelled(
            eventId: self::newEventId(),
            catalogId: $this->id,
            status: $this->status,
            occurredAt: $occurredAt,
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

        $occurredAt = $this->validatedOccurredAt($occurredAt);
        $previous = $this->name;

        $this->name = $name;
        $this->updatedAt = $occurredAt;
        $this->version++;

        $this->record(new CatalogNameChanged(
            eventId: self::newEventId(),
            catalogId: $this->id,
            previousName: $previous,
            newName: $name,
            occurredAt: $occurredAt,
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

        $occurredAt = $this->validatedOccurredAt($occurredAt);
        $previous = $this->description;

        $this->description = $description;
        $this->updatedAt = $occurredAt;
        $this->version++;

        $this->record(new CatalogDescriptionChanged(
            eventId: self::newEventId(),
            catalogId: $this->id,
            previousDescription: $previous,
            newDescription: $description,
            occurredAt: $occurredAt,
        ));
    }

    public function changePrice(Money $price, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();
        self::assertValidPrice($price);

        if ($this->price->equals($price)) {
            return;
        }

        $occurredAt = $this->validatedOccurredAt($occurredAt);
        $previous = $this->price;

        $this->price = $price;
        $this->updatedAt = $occurredAt;
        $this->version++;

        $this->record(new CatalogPriceChanged(
            eventId: self::newEventId(),
            catalogId: $this->id,
            previousPrice: $previous,
            newPrice: $price,
            occurredAt: $occurredAt,
        ));
    }

    public function replaceImages(ImageCollection $images, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();

        $images = $images->copy();
        if ($images->isEmpty()) {
            throw new DomainException('Catalog must contain at least one image.');
        }

        $occurredAt = $this->validatedOccurredAt($occurredAt);

        $this->images = $images;
        $this->updatedAt = $occurredAt;
        $this->version++;

        $this->record(new CatalogImagesReplaced(
            eventId: self::newEventId(),
            catalogId: $this->id,
            images: $images,
            occurredAt: $occurredAt,
        ));
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

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

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function assertStatus(CatalogStatus $expected, string $operation): void
    {
        if ($this->status !== $expected) {
            throw new DomainException(sprintf(
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
            throw new DomainException(sprintf(
                'Cannot modify catalog in terminal status %s.',
                $this->status->value,
            ));
        }
    }

    private function validatedOccurredAt(DateTimeImmutable $occurredAt): DateTimeImmutable
    {
        $occurredAt = self::utc($occurredAt);

        if ($occurredAt < $this->updatedAt) {
            throw new DomainException('Occurred at cannot precede the last change.');
        }

        return $occurredAt;
    }

    private static function normalizeText(string $value, string $field): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new DomainException(sprintf('Catalog %s must be valid UTF-8.', $field));
        }

        $value = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value);

        if ($value === null || $value === '' || str_contains($value, "\0")) {
            throw new DomainException(sprintf('Catalog %s is invalid or empty.', $field));
        }

        return $value;
    }

    private static function assertValidName(string $name): void
    {
        if (mb_strlen($name, 'UTF-8') > 255) {
            throw new DomainException('Catalog name cannot exceed 255 characters.');
        }
    }

    private static function assertValidDescription(string $description): void
    {
        // length limit can be added here if needed
    }

    private static function assertValidPrice(Money $price): void
    {
        if (!$price->isPositive()) {
            throw new DomainException('Catalog price must be greater than zero.');
        }
    }

    private static function utc(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(new DateTimeZone('UTC'));
    }

    private static function newEventId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
