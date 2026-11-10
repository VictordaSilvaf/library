<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Event\CatalogCancelled;
use App\Catalog\Domain\Event\CatalogConfirmed;
use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\ValueObjects\{
    CatalogId,
    CatalogStatus,
    CategoryId,
    Sku,
};
use App\Shared\Domain\Event\RecordsDomainEvents;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Src\Catalog\Domain\ValueObjects\ImageCollection;

#[ORM\Entity]
#[ORM\Table(name: 'catalogs')]
#[ORM\Index(name: 'idx_catalogs_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_catalogs_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Catalog
{
    use RecordsDomainEvents;

    #[ORM\Column(name: 'images', type: 'image_collection')]
    private ImageCollection $images;

    /**
     * @param ImageCollection $images
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', type: 'catalog_id', length: 36)]
        public readonly CatalogId $id,
        #[ORM\Column(name: 'sku', type: 'sku', length: 36)]
        public readonly Sku $sku,
        #[ORM\Column(name: 'name', type: 'string', length: 255)]
        public readonly string $name,
        #[ORM\Column(name: 'description', type: 'text')]
        public readonly string $description,
        #[ORM\Column(name: 'price', type: 'money')]
        public readonly Money $price,
        #[ORM\Column(name: 'category_id', type: 'category_id', length: 36)]
        public readonly CategoryId $categoryId,
        #[ORM\Column(name: 'status', type: 'catalog_status')]
        public readonly CatalogStatus $status,
        ImageCollection $images,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        private DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'confirmed_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $confirmedAt = null,
        #[ORM\Column(name: 'cancelled_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $cancelledAt = null,
    ) {
        $this->images = $images->copy();
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
        return $this->images;
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

    public function create(): void
    {
        $this->record(new CatalogCreated(
            catalogId: $this->id,
            sku: $this->sku,
            name: $this->name,
            description: $this->description,
            price: $this->price,
            categoryId: $this->categoryId,
            status: $this->status,
        ));
    }

    public function confirm(): void
    {
        $this->record(new CatalogConfirmed(
            catalogId: $this->id,
            status: $this->status,
        ));
    }

    public function cancel(): void
    {
        $this->record(new CatalogCancelled(
            catalogId: $this->id,
            status: $this->status,
        ));
    }
}