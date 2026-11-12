<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\UuidV7;

/**
 * Product entity and its mapping, against the real test database (migrations applied).
 * Each test runs in a transaction that is rolled back: the database stays empty.
 */
final class ProductTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testNewProductIsADraftWithIdAndTimestamps(): void
    {
        $product = self::product('MG-001', ProductType::Physical);

        self::assertSame(ProductStatus::Draft, $product->getStatus());
        self::assertInstanceOf(UuidV7::class, $product->getId());
        self::assertSame($product->getCreatedAt(), $product->getUpdatedAt());
        self::assertSame(0, $product->getQuantity());
        self::assertSame(0, $product->getCostPrice(), 'cost price is never copied from the sale price');
    }

    #[DataProvider('invalidArgumentsProvider')]
    public function testInvalidArgumentsAreRejected(string $sku, string $name, string $currency): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Product($sku, $name, 'espresso-cup', 'Ceramic.', ProductType::Physical, 1290, $currency);
    }

    /**
     * @return iterable<string, array{string, string, string}> sku, name, currency
     */
    public static function invalidArgumentsProvider(): iterable
    {
        yield 'empty sku' => ['', 'Espresso cup', 'EUR'];
        yield 'empty name' => ['MG-001', '', 'EUR'];
        yield 'lowercase currency' => ['MG-001', 'Espresso cup', 'eur'];
        yield 'symbol currency' => ['MG-001', 'Espresso cup', '€'];
        yield 'empty currency' => ['MG-001', 'Espresso cup', ''];
        yield 'currency with newline' => ['MG-001', 'Espresso cup', "EUR\n"];
    }

    public function testPublishChangesStatusAndUpdatedAt(): void
    {
        $product = self::product('MG-001', ProductType::Physical);
        $createdAt = $product->getCreatedAt();

        $product->publish();

        self::assertSame(ProductStatus::Published, $product->getStatus());
        self::assertGreaterThanOrEqual($createdAt, $product->getUpdatedAt());
        self::assertNotSame($createdAt, $product->getUpdatedAt());
    }

    public function testInStockDependsOnTypeAndQuantity(): void
    {
        $physical = self::product('MG-001', ProductType::Physical);
        $digital = self::product('MG-DL-01', ProductType::Digital);

        self::assertFalse($physical->isInStock());
        self::assertTrue($digital->isInStock(), 'digital: always in stock');

        $physical->changeQuantity(3);
        self::assertTrue($physical->isInStock());
    }

    public function testRoundTripThroughTheDatabase(): void
    {
        $product = self::product('MG-001', ProductType::Digital);
        $product->publish();
        $product->changeCostPrice(400);
        $product->changeDownloadUrl('https://files.example.com/private/guide.pdf');
        $this->em->persist($product);
        $this->em->flush();
        $this->em->clear();

        $loaded = self::getContainer()->get(ProductRepository::class)->find($product->getId());

        self::assertInstanceOf(Product::class, $loaded);
        self::assertNotSame($product, $loaded, 'loaded from the database, not from the identity map');
        self::assertTrue($product->getId()->equals($loaded->getId()));
        self::assertSame(ProductType::Digital, $loaded->getType());
        self::assertSame(ProductStatus::Published, $loaded->getStatus());
        self::assertSame(1290, $loaded->getPrice());
        self::assertSame('EUR', $loaded->getCurrency());
        self::assertSame(400, $loaded->getCostPrice());
        self::assertSame('https://files.example.com/private/guide.pdf', $loaded->getDownloadUrl());
        self::assertNull($loaded->getImageUrl());
    }

    private static function product(string $sku, ProductType $type): Product
    {
        return new Product($sku, 'Espresso cup', strtolower($sku), 'Ceramic cup, 80 ml.', $type, 1290, 'EUR');
    }
}
