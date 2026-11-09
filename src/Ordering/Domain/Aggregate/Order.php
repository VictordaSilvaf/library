<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Aggregate;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateException;
use App\Ordering\Domain\ValueObject\{
    Product,
    OrderId,
    CustomerId,
    OrderStatus,
    Money,
    ProductCollection
};
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_orders_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Column(name: 'products', type: 'product_collection')]
    private ProductCollection $products;

    /**
     * @var array<int, OrderCreated|OrderConfirmed|OrderCancelled>
     */
    private array $domainEvents = [];

    /**
     * @param ProductCollection $products
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', type: 'order_id', length: 36)]
        private readonly OrderId $id,
        #[ORM\Column(name: 'customer_id', type: 'order_customer_id', length: 36)]
        private readonly CustomerId $customerId,
        ProductCollection $products,
        #[ORM\Column(type: 'string', enumType: OrderStatus::class, length: 32)]
        private OrderStatus $status,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'total_')]
        private Money $total,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        private DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'confirmed_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $confirmedAt = null,
        #[ORM\Column(name: 'cancelled_at', type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $cancelledAt = null,
    ) {
        $this->products = $products->copy();
    }

    public static function create(CustomerId $customerId, ProductCollection $products): self
    {
        /** @var Product[] $items */
        $items = array_values($products->getProducts());

        if ($items === []) {
            throw new EmptyOrderException('Products are required');
        }

        $total = $items[0]->getPrice();

        foreach (array_slice($items, 1) as $product) {
            $total = $total->add($product->getPrice());
        }

        $order = new self(
            OrderId::random(),
            $customerId,
            $products,
            OrderStatus::PENDING,
            $total,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );

        $order->record(new OrderCreated(
            $order->getId(),
            $order->getCustomerId(),
            $order->getProducts(),
            $order->getTotal(),
        ));

        return $order;
    }

    #[ORM\PostLoad]
    public function initializeTransientState(): void
    {
        $this->domainEvents = [];
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getProducts(): ProductCollection
    {
        return $this->products->copy();
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getTotal(): Money
    {
        return $this->total;
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

    /**
     * @return array<int, OrderCreated|OrderConfirmed|OrderCancelled>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public function confirm(): void
    {
        $this->validateConfirm();

        $this->status = OrderStatus::CONFIRMED;
        $this->confirmedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->record(new OrderConfirmed(
            $this->id,
            $this->customerId,
            $this->getProducts(),
            $this->total,
        ));
    }

    public function cancel(): void
    {
        $this->validateCancel();

        $this->status = OrderStatus::CANCELLED;
        $this->cancelledAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->record(new OrderCancelled(
            $this->id,
            $this->customerId,
            $this->getProducts(),
            $this->total,
        ));
    }

    public function addProduct(Product $product): void
    {
        $this->validateAddProduct();

        $items = $this->products->getProducts();
        $items[] = $product;
        $this->products = new ProductCollection($items);
        $this->total = $this->total->add($product->getPrice());
        $this->updatedAt = new DateTimeImmutable();
    }

    private function validateAddProduct(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException('Order is not pending');
        }
    }

    private function validateConfirm(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException('Order is not pending');
        }

        if ($this->products->isEmpty()) {
            throw new EmptyOrderException('Order has no products');
        }
    }

    private function validateCancel(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException('Order is not pending');
        }
    }

    private function record(OrderCreated|OrderConfirmed|OrderCancelled $event): void
    {
        $this->domainEvents[] = $event;
    }
}
