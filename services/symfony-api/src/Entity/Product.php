<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Stored product, spec docs/specs/products-list.md ("Product model (stored)").
 *
 * List index (spec, Performance): declared here so that migrations:diff knows it, but created by hand
 * in the migration with created_at DESC, which Doctrine mapping cannot express (DBAL does not read DESC
 * back, so there is no drift). The "where" is written as PostgreSQL returns it, or every diff recreates the index.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\Index(name: 'idx_product_published_list', columns: ['created_at', 'id'], options: ['where' => "((status)::text = 'published'::text)"])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 64, unique: true)]
    private string $sku;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 16, enumType: ProductType::class)]
    private ProductType $type;

    /** Minor units (cents). */
    #[ORM\Column]
    private int $price;

    /** ISO 4217, e.g. EUR. */
    #[ORM\Column(length: 3, options: ['fixed' => true])]
    private string $currency;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column(length: 16, enumType: ProductStatus::class)]
    private ProductStatus $status;

    /** Minor units (cents). Admin only. */
    #[ORM\Column]
    private int $costPrice = 0;

    /** Digital products only; never public. */
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $downloadUrl = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $sku,
        string $name,
        string $slug,
        string $description,
        ProductType $type,
        int $price,
        string $currency,
    ) {
        // HUMAN: guards - an invalid product never exists.
        if ('' === $sku) {
            throw new \InvalidArgumentException('sku cannot be empty.');
        }
        if ('' === $name) {
            throw new \InvalidArgumentException('name cannot be empty.');
        }
        if (1 !== preg_match('/^[A-Z]{3}\z/', $currency)) {
            throw new \InvalidArgumentException('Currency must be an ISO 4217 code, e.g. "EUR".');
        }

        $this->id = Uuid::v7();
        $this->sku = $sku;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->type = $type; // HUMAN: Already typed as ProductType in the constructor signature.
        $this->price = $price;
        $this->currency = $currency; // HUMAN: TODO: Multi currency deferred from spec for now.
        $this->imageUrl = null;
        $this->quantity = 0;
        $this->status = ProductStatus::Draft;
        $this->costPrice = 0;
        $this->downloadUrl = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt; // HUMAN: same instant, not 2 now()
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): ProductType
    {
        return $this->type;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getCostPrice(): int
    {
        return $this->costPrice;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function publish(): void
    {
        $this->status = ProductStatus::Published;
        $this->touch();
    }

    public function archive(): void
    {
        $this->status = ProductStatus::Archived;
        $this->touch();
    }

    public function changeQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
        $this->touch();
    }

    public function changeCostPrice(int $costPrice): void
    {
        $this->costPrice = $costPrice;
        $this->touch();
    }

    public function changeImageUrl(?string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
        $this->touch();
    }

    public function changeDownloadUrl(?string $downloadUrl): void
    {
        $this->downloadUrl = $downloadUrl;
        $this->touch();
    }

    /** Spec: physical → quantity > 0; digital → always true. */
    public function isInStock(): bool
    {
        return ProductType::Digital === $this->type || $this->quantity > 0;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
