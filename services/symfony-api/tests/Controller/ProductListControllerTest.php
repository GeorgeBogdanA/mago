<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /v1/products, spec docs/specs/products-list.md, against the fake in-memory catalog
 * (5 published, 1 draft, 1 archived).
 *
 * The spec's "25 published" ACs are tested with the same rule on 5 products and a small limit.
 */
final class ProductListControllerTest extends WebTestCase
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testDefaultListIsPublishedProductsNewestFirst(): void
    {
        $body = $this->getJson('/v1/products');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        // created_at desc; MG-003 and MG-002 share created_at, so id asc decides (AC1, AC5).
        self::assertSame(['MG-003', 'MG-002', 'MG-001', 'MG-DL-01', 'MG-004'], self::skus($body));
        self::assertSame(['page' => 1, 'limit' => 20, 'total' => 5], $body['meta']);
    }

    public function testDraftAndArchivedProductsAreNotListed(): void
    {
        $body = $this->getJson('/v1/products');

        self::assertNotContains('MG-005', self::skus($body), 'draft');
        self::assertNotContains('MG-OLD-01', self::skus($body), 'archived');
    }

    public function testItemExposesOnlyWhitelistedKeys(): void
    {
        $item = self::item($this->getJson('/v1/products'), 'MG-DL-01');

        // Digital product with download_url, cost_price 500, quantity 0, image_url null (AC3).
        self::assertSame(
            ['id', 'sku', 'name', 'slug', 'description', 'type', 'price', 'in_stock', 'image_url'],
            array_keys($item),
        );
        self::assertNull($item['image_url']);
        self::assertIsString($item['id']);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $item['id']);
        self::assertSame(['amount' => 900, 'currency' => 'EUR'], $item['price']);
    }

    public function testInStockDependsOnTypeAndQuantity(): void
    {
        $body = $this->getJson('/v1/products');

        // AC4: physical qty 0, physical qty 3, digital qty 0.
        self::assertFalse(self::item($body, 'MG-003')['in_stock']);
        self::assertTrue(self::item($body, 'MG-002')['in_stock']);
        self::assertTrue(self::item($body, 'MG-DL-01')['in_stock']);
    }

    public function testSameCreatedAtAcrossPageBoundaryIsOrderedById(): void
    {
        // MG-003 and MG-002 share created_at, MG-003 has the lower id (AC5).
        self::assertSame(['MG-003'], self::skus($this->getJson('/v1/products?limit=1')));
        self::assertSame(['MG-002'], self::skus($this->getJson('/v1/products?limit=1&page=2')));
    }

    public function testLimitAboveMaxIsReducedToMax(): void
    {
        $body = $this->getJson('/v1/products?limit=500');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(5, self::items($body));
        self::assertSame(['page' => 1, 'limit' => 100, 'total' => 5], $body['meta']);
    }

    #[DataProvider('limitBelowMinProvider')]
    public function testLimitBelowMinIsRaisedToMin(string $query): void
    {
        $body = $this->getJson('/v1/products?'.$query);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['MG-003'], self::skus($body));
        self::assertSame(['page' => 1, 'limit' => 1, 'total' => 5], $body['meta']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function limitBelowMinProvider(): iterable
    {
        yield 'zero' => ['limit=0'];
        yield 'negative' => ['limit=-5'];
    }

    #[DataProvider('pageBelowMinProvider')]
    public function testPageBelowMinIsRaisedToMin(string $query): void
    {
        $expected = $this->getJson('/v1/products');
        $body = $this->getJson('/v1/products?'.$query);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected['items'], $body['items']);
        self::assertSame(['page' => 1, 'limit' => 20, 'total' => 5], $body['meta']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pageBelowMinProvider(): iterable
    {
        yield 'zero' => ['page=0'];
        yield 'negative' => ['page=-1'];
    }

    public function testLastPageIsPartial(): void
    {
        $body = $this->getJson('/v1/products?limit=2&page=3');

        // AC9: 5 products, 2 per page, page 3 holds only the oldest one.
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['MG-004'], self::skus($body));
        self::assertSame(['page' => 3, 'limit' => 2, 'total' => 5], $body['meta']);
    }

    public function testPagePastTheEndIsEmpty(): void
    {
        $body = $this->getJson('/v1/products?page=2');

        // AC10
        self::assertResponseStatusCodeSame(200);
        self::assertSame([], self::items($body));
        self::assertSame(['page' => 2, 'limit' => 20, 'total' => 5], $body['meta']);
    }

    #[DataProvider('lenientLimitProvider')]
    public function testLimitEmptyLeadingZeroAndRepeated(string $query, int $expectedLimit): void
    {
        $body = $this->getJson('/v1/products?'.$query);

        // AC12
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['page' => 1, 'limit' => $expectedLimit, 'total' => 5], $body['meta']);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function lenientLimitProvider(): iterable
    {
        yield 'empty value: default' => ['limit=', 20];
        yield 'leading zero' => ['limit=05', 5];
        yield 'repeated: last wins' => ['limit=5&limit=10', 10];
    }

    #[DataProvider('invalidQueryProvider')]
    public function testInvalidQueryIsProblemJson400(string $query, string $param, string $rawValue): void
    {
        $body = $this->getJson('/v1/products?'.$query);

        // AC13
        self::assertInvalidQueryProblem($body);
        self::assertIsString($body['detail']);
        self::assertStringContainsString('"'.$param.'"', $body['detail']);

        $logged = self::loggedWarning();
        self::assertStringContainsString($param, $logged);
        self::assertStringNotContainsString($rawValue, $logged, 'the raw value must never be logged');
    }

    /**
     * @return iterable<string, array{string, string, string}> query, param, raw value (must not be logged)
     */
    public static function invalidQueryProvider(): iterable
    {
        yield 'letters' => ['limit=abc', 'limit', 'abc'];
        yield 'decimal' => ['limit=1.5', 'limit', '1.5'];
        yield 'exponent' => ['limit=1e2', 'limit', '1e2'];
        yield 'plus sign' => ['limit=%2B5', 'limit', '+5'];
        yield 'leading space' => ['limit=%205', 'limit', ' 5'];
        yield 'trailing letters' => ['limit=10abc', 'limit', '10abc'];
        yield 'trailing newline' => ['limit=5%0A', 'limit', "5\n"];
        yield 'arabic-indic digit' => ['limit=%D9%A5', 'limit', "\u{0665}"];
        yield 'array' => ['limit[]=zzz', 'limit', 'zzz'];
        yield 'ten digits' => ['limit=1234567890', 'limit', '1234567890'];
        yield 'page letters' => ['page=abc', 'page', 'abc'];
        yield 'page above max' => ['page=10001', 'page', '10001'];
    }

    public function testBothInvalidParamsGiveOne400NamingBoth(): void
    {
        $body = $this->getJson('/v1/products?limit=abc&page=abc');

        // AC14
        self::assertInvalidQueryProblem($body);
        self::assertIsString($body['detail']);
        self::assertStringContainsString('"limit"', $body['detail']);
        self::assertStringContainsString('"page"', $body['detail']);

        $logged = self::loggedWarning();
        self::assertStringContainsString('limit', $logged);
        self::assertStringContainsString('page', $logged);
        self::assertStringNotContainsString('abc', $logged);
    }

    public function testUnknownQueryParamsAreIgnored(): void
    {
        $expected = $this->getRaw('/v1/products');

        self::assertSame($expected, $this->getRaw('/v1/products?foo=bar'));
    }

    public function testAuthorizationHeaderIsIgnored(): void
    {
        $expected = $this->getRaw('/v1/products');
        $actual = $this->getRaw('/v1/products', ['HTTP_AUTHORIZATION' => 'Bearer not-a-token']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected, $actual);
    }

    public function testOtherErrorsAreProblemJsonWithoutTrace(): void
    {
        $this->client->request('POST', '/v1/products');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertResponseHeaderSame('Allow', 'GET');
        self::assertJsonStringEqualsJsonString(
            '{"type":"about:blank","title":"Method Not Allowed","status":405,"detail":"Method Not Allowed."}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    /**
     * @param array<mixed> $body
     */
    private static function assertInvalidQueryProblem(array $body): void
    {
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame('https://mago.example/errors/invalid-query', $body['type'] ?? null);
        self::assertSame('Invalid query parameter', $body['title'] ?? null);
        self::assertSame(400, $body['status'] ?? null);
    }

    /**
     * Asserts exactly one warning was logged by the last request and returns it (message + context) as one string.
     */
    private static function loggedWarning(): string
    {
        // Defined only under when@test in monolog.yaml; PHPStan reads the dev container.
        $handler = self::getContainer()->get('monolog.handler.test'); // @phpstan-ignore symfonyContainer.serviceNotFound
        self::assertInstanceOf(TestHandler::class, $handler);

        $warnings = array_values(array_filter(
            $handler->getRecords(),
            static fn (LogRecord $record): bool => Level::Warning === $record->level,
        ));
        self::assertCount(1, $warnings, 'exactly one warning');

        // print_r, not json_encode: it keeps "\n" and non-ASCII raw, so the "not logged" check is real.
        return $warnings[0]->message.' '.print_r($warnings[0]->context, true);
    }

    /**
     * @param array<string, string> $server
     */
    private function getRaw(string $uri, array $server = []): string
    {
        $this->client->request('GET', $uri, server: $server);

        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * @return array<mixed>
     */
    private function getJson(string $uri): array
    {
        $body = json_decode($this->getRaw($uri), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    /**
     * @param array<mixed> $body
     *
     * @return list<array<mixed>>
     */
    private static function items(array $body): array
    {
        self::assertIsArray($body['items'] ?? null);
        self::assertIsList($body['items']);
        self::assertContainsOnlyArray($body['items']);

        return $body['items'];
    }

    /**
     * @param array<mixed> $body
     *
     * @return list<mixed>
     */
    private static function skus(array $body): array
    {
        return array_column(self::items($body), 'sku');
    }

    /**
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    private static function item(array $body, string $sku): array
    {
        foreach (self::items($body) as $item) {
            if (($item['sku'] ?? null) === $sku) {
                return $item;
            }
        }
        self::fail(\sprintf('No item with sku %s.', $sku));
    }
}
