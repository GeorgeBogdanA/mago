<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\InvalidQueryException;
use App\Http\ProblemResponse;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public catalog list, spec docs/specs/products-list.md.
 *
 * Temporary: products are fake in-memory data until the Doctrine entity exists.
 *
 * @phpstan-type StoredProduct array{
 *     id: string, sku: string, name: string, slug: string, description: string,
 *     type: 'physical'|'digital', price: int, currency: string, image_url: string|null,
 *     quantity: int, status: 'draft'|'published'|'archived', cost_price: int,
 *     download_url: string|null, created_at: string, updated_at: string
 * }
 */
final class ProductListController extends AbstractController
{
    public const int LIMIT_DEFAULT = 20;
    public const int LIMIT_MIN = 1;
    public const int LIMIT_MAX = 100;
    public const int PAGE_DEFAULT = 1;
    public const int PAGE_MIN = 1;
    public const int PAGE_MAX = 10000;

    private const string INVALID_QUERY_TYPE = 'https://mago.example/errors/invalid-query';
    private const string INVALID_QUERY_TITLE = 'Invalid query parameter';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[Route('/v1/products', name: 'product_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            // all(), not get(): get() throws on "limit[]=zzz" before we can answer with our 400.
            ['page' => $page, 'limit' => $limit] = $this->resolvePagination($request->query->all());
        } catch (InvalidQueryException $e) {
            // Param names only: they come from our code, never from the client (no log injection).
            $names = array_keys($e->errors);
            $this->logger->warning('Invalid query parameters: '.implode(', ', $names), ['params' => $names]);

            return ProblemResponse::create(400, self::INVALID_QUERY_TYPE, self::INVALID_QUERY_TITLE, implode(' ', $e->errors));
        }

        $published = array_values(array_filter(
            self::fakeProducts(),
            static fn (array $product): bool => 'published' === $product['status'],
        ));
        // created_at desc, then id asc (unique tie-breaker: stable pages).
        usort($published, static fn (array $a, array $b): int => [$b['created_at'], $a['id']] <=> [$a['created_at'], $b['id']]);

        $items = array_map(
            self::toPublicItem(...),
            \array_slice($published, ($page - 1) * $limit, $limit),
        );

        $response = new JsonResponse([
            'items' => $items,
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => \count($published)],
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * @param array<string, mixed> $query raw query params ($request->query->all()): a value can be
     *                                    a string, an array ("limit[]=zzz") or missing
     *
     * @return array{page: int, limit: int} the values actually used, after defaults and clamping
     *
     * @throws InvalidQueryException naming every rejected param
     */
    private function resolvePagination(array $query): array
    {
        $errors = [];
        $limit_raw = $query['limit'] ?? null;
        $page_raw = $query['page'] ?? null;

        // LIMIT
        if (null === $limit_raw || '' === $limit_raw) {
            $limit = self::LIMIT_DEFAULT;
        } elseif (!\is_string($limit_raw) || 1 !== preg_match('/^-?[0-9]{1,9}\z/', $limit_raw)) {
            $errors['limit'] = '"limit" must be an integer.';
            $limit = self::LIMIT_DEFAULT;
        } else {
            $limit = (int) $limit_raw;
            if ($limit > self::LIMIT_MAX || $limit < self::LIMIT_MIN) {
                $limit = max(self::LIMIT_MIN, min(self::LIMIT_MAX, $limit));
            }
        }

        // PAGE
        if (null === $page_raw || '' === $page_raw) {
            $page = self::PAGE_DEFAULT;
        } elseif (!\is_string($page_raw) || 1 !== preg_match('/^-?[0-9]{1,9}\z/', $page_raw)) {
            $errors['page'] = '"page" must be an integer.';
            $page = self::PAGE_DEFAULT;
        } else {
            $page = (int) $page_raw;
            if ($page > self::PAGE_MAX) {
                $errors['page'] = '"page" out of range.';
            }
            $page = max(self::PAGE_MIN, min(self::PAGE_MAX, $page));
        }

        if ([] !== $errors) {
            throw new InvalidQueryException($errors);
        }

        return ['page' => $page, 'limit' => $limit];
    }

    /**
     * Whitelist: only these keys are public (spec, "Public item").
     *
     * @param StoredProduct $product
     *
     * @return array<string, mixed>
     */
    private static function toPublicItem(array $product): array
    {
        return [
            'id' => $product['id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'description' => $product['description'],
            'type' => $product['type'],
            'price' => ['amount' => $product['price'], 'currency' => $product['currency']],
            'in_stock' => 'digital' === $product['type'] || $product['quantity'] > 0,
            'image_url' => $product['image_url'],
        ];
    }

    /**
     * 5 published (as in the spec example: total 5, page 2 of 2 = MG-001, MG-DL-01), 1 draft, 1 archived.
     * MG-003 and MG-002 share created_at: id asc decides their order.
     * Stored deliberately out of order (MG-002 before MG-003, oldest first): the sort must do the work.
     *
     * @return list<StoredProduct>
     */
    private static function fakeProducts(): array
    {
        return [
            [
                'id' => '0193a7c0-7e6d-7c5b-9a48-3f2e1d0c9b8a', 'sku' => 'MG-004', 'name' => 'Coffee scale',
                'slug' => 'coffee-scale', 'description' => 'Digital scale with timer, 0.1 g.', 'type' => 'physical',
                'price' => 2990, 'currency' => 'EUR', 'image_url' => 'https://cdn.example.com/mg-004.jpg',
                'quantity' => 7, 'status' => 'published', 'cost_price' => 1300, 'download_url' => null,
                'created_at' => '2026-11-01T10:00:00Z', 'updated_at' => '2026-11-01T10:00:00Z',
            ],
            [
                'id' => '0193a7c5-0000-7000-8000-000000000001', 'sku' => 'MG-005', 'name' => 'Grinder (coming soon)',
                'slug' => 'grinder', 'description' => 'Manual burr grinder.', 'type' => 'physical',
                'price' => 4900, 'currency' => 'EUR', 'image_url' => null, 'quantity' => 10, 'status' => 'draft',
                'cost_price' => 2500, 'download_url' => null,
                'created_at' => '2026-11-06T09:00:00Z', 'updated_at' => '2026-11-06T09:00:00Z',
            ],
            [
                'id' => '0193a7c4-1a2b-7c3d-8e4f-5a6b7c8d9e02', 'sku' => 'MG-002', 'name' => 'Moka pot, 3 cups',
                'slug' => 'moka-pot-3-cups', 'description' => 'Aluminium, for gas and electric hobs.', 'type' => 'physical',
                'price' => 3200, 'currency' => 'EUR', 'image_url' => 'https://cdn.example.com/mg-002.jpg',
                'quantity' => 3, 'status' => 'published', 'cost_price' => 1500, 'download_url' => null,
                'created_at' => '2026-11-05T09:00:00Z', 'updated_at' => '2026-11-05T09:00:00Z',
            ],
            [
                'id' => '0193a7c1-0f3d-7a21-8c44-2b9e5d7f1a03', 'sku' => 'MG-DL-01', 'name' => 'Coffee brewing guide (PDF)',
                'slug' => 'coffee-brewing-guide', 'description' => '40-page guide to espresso and filter coffee.',
                'type' => 'digital', 'price' => 900, 'currency' => 'EUR', 'image_url' => null, 'quantity' => 0,
                'status' => 'published', 'cost_price' => 500,
                'download_url' => 'https://files.example.com/private/brewing-guide.pdf',
                'created_at' => '2026-11-02T10:00:00Z', 'updated_at' => '2026-11-02T10:00:00Z',
            ],
            [
                'id' => '0193a7c4-1a2b-7c3d-8e4f-5a6b7c8d9e01', 'sku' => 'MG-003', 'name' => 'Milk frother',
                'slug' => 'milk-frother', 'description' => 'Stainless steel, 350 ml.', 'type' => 'physical',
                'price' => 2490, 'currency' => 'EUR', 'image_url' => 'https://cdn.example.com/mg-003.jpg',
                'quantity' => 0, 'status' => 'published', 'cost_price' => 1100, 'download_url' => null,
                'created_at' => '2026-11-05T09:00:00Z', 'updated_at' => '2026-11-05T09:00:00Z',
            ],
            [
                'id' => '0193a7b0-0000-7000-8000-000000000002', 'sku' => 'MG-OLD-01', 'name' => 'Paper filters (old pack)',
                'slug' => 'paper-filters-old', 'description' => 'Discontinued.', 'type' => 'physical',
                'price' => 390, 'currency' => 'EUR', 'image_url' => null, 'quantity' => 50, 'status' => 'archived',
                'cost_price' => 100, 'download_url' => null,
                'created_at' => '2026-10-01T10:00:00Z', 'updated_at' => '2026-10-20T10:00:00Z',
            ],
            [
                'id' => '0193a7c2-5b1e-7c3a-9f2d-4e8b1a6c0d55', 'sku' => 'MG-001', 'name' => 'Espresso cup',
                'slug' => 'espresso-cup', 'description' => 'Ceramic cup, 80 ml.', 'type' => 'physical',
                'price' => 1290, 'currency' => 'EUR', 'image_url' => 'https://cdn.example.com/mg-001.jpg',
                'quantity' => 12, 'status' => 'published', 'cost_price' => 400, 'download_url' => null,
                'created_at' => '2026-11-03T10:00:00Z', 'updated_at' => '2026-11-03T10:00:00Z',
            ],
        ];
    }
}
