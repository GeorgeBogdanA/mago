# SPEC: Products list (`GET /v1/products`)

- **Service:** symfony-api
- **Week / day:** week 1 / day 2 (2026-11-10)
- **Status:** ready

## Goal
The storefront shows the public catalog page by page: a shopper sees the published products,
with name, price and availability, in a stable order.

## Out of scope
- Search, filters and sorting chosen by the client: separate spec (semantic search lives in `ai-python`, weeks 8-11).
- Single product (`GET /v1/products/{id}`): separate spec.
- Configurable attributes (Magento-style EAV) and website > store > store view: separate specs.
- Admin list (`/v1/admin/products`, admin-scoped token): separate spec.
- go-sync price/stock writes (own endpoints or events): separate spec.
- Any write endpoint (create, update, delete), including enforcing "plain text, no HTML" in `description`.
- Rate limiting (limits, `429`, headers): separate spec.
- HTTP caching (ETag, `Cache-Control` other than `no-store`): separate spec.
- API-wide concerns: security headers/CSP, OpenAPI document. Separate cross-cutting spec, for all endpoints.

## API / interface

### Product model (stored)
| Field | Type | Public | Notes |
|---|---|---|---|
| `id` | UUID | yes | Public identifier, not an auto-increment (no enumeration of the catalog) |
| `sku` | string | yes | Unique |
| `name` | string | yes | |
| `slug` | string | yes | Unique, for storefront URLs |
| `description` | string | yes | Plain text, no HTML (enforced on write, out of scope here) |
| `type` | `physical` \| `digital` | yes | |
| `price` | integer, minor units (cents) | yes | Exposed as `{ "amount": 1999, "currency": "EUR" }` |
| `currency` | ISO 4217 string | yes | Inside `price` |
| `image_url` | string \| null | yes | Main image only |
| `quantity` | integer | **no** | Exposed only as `in_stock` (see below) |
| `status` | `draft` \| `published` \| `archived` | **no** | Only `published` products are listed |
| `cost_price` | integer, minor units | **no** | Admin only |
| `download_url` | string \| null | **no** | Digital products: only after purchase, never in the catalog |
| `created_at`, `updated_at` | datetime | **no** | |

`in_stock`: for `physical` products `quantity > 0`; for `digital` products always `true`.

### Public item (whitelist: no other key may appear)
```json
{
  "id": "0193a7c2-5b1e-7c3a-9f2d-4e8b1a6c0d55",
  "sku": "MG-001",
  "name": "Espresso cup",
  "slug": "espresso-cup",
  "description": "Ceramic cup, 80 ml.",
  "type": "physical",
  "price": { "amount": 1290, "currency": "EUR" },
  "in_stock": true,
  "image_url": "https://cdn.example.com/mg-001.jpg"
}
```

### Query and response
- Pagination: `page` + `limit` query params, `page` starts at 1.
- `limit`: default `20`, min `1`, max `100`. `page`: default `1`, min `1`, max `10000`.
- **Valid integer** (for `limit` and `page`): matches `^-?[0-9]{1,9}\z` (optional leading `-`, 1 to 9 ASCII digits,
  anchored at the absolute end; leading zeros allowed: `05` = 5). Anything else is **malformed**: e.g. `1.5`, `1e2`,
  `+5`, ` 5`, `10abc`, `0x10`, a trailing newline (`5\n`), non-ASCII digits (`٥`), an array (`limit[]=zzz`),
  more than 9 digits.
- **Out of range** (valid integer outside min/max): clamped, except `page` above `10000`, which is rejected with `400`
  like malformed input.
- Empty value (`?limit=`) = param not sent (default). Repeated param (`?limit=5&limit=10`): the last value wins.
- Response: `200`, `Content-Type: application/json`, `Cache-Control: no-store`,
  body `{ "items": [...], "meta": { "page", "limit", "total" } }`. `meta.page` and `meta.limit` are the values
  actually used (after defaults and clamping); `total` = number of published products.
- Order: `created_at` desc, then `id` asc (unique tie-breaker, so pages are stable).
- `Authorization` header: ignored (public endpoint, never `401`).
- Error body: RFC 9457, `Content-Type: application/problem+json`, `Cache-Control: no-store`, with `type`, `title`,
  `status`, `detail`. For malformed query params and `page` above `10000` `type` and `title` are fixed: `https://mago.example/errors/invalid-query` and
  `Invalid query parameter`; `detail` names every rejected param. One warning is logged per request, naming every
  rejected param.
- Any other error on this route (e.g. `405`, `500`) also uses problem+json and never contains a stack trace
  (no AC in v1).

Example: `GET /v1/products?page=2&limit=2`
```http
HTTP/1.1 200 OK
Content-Type: application/json
Cache-Control: no-store
```
```json
{
  "items": [
    {
      "id": "0193a7c2-5b1e-7c3a-9f2d-4e8b1a6c0d55",
      "sku": "MG-001",
      "name": "Espresso cup",
      "slug": "espresso-cup",
      "description": "Ceramic cup, 80 ml.",
      "type": "physical",
      "price": { "amount": 1290, "currency": "EUR" },
      "in_stock": true,
      "image_url": "https://cdn.example.com/mg-001.jpg"
    },
    {
      "id": "0193a7c1-0f3d-7a21-8c44-2b9e5d7f1a03",
      "sku": "MG-DL-01",
      "name": "Coffee brewing guide (PDF)",
      "slug": "coffee-brewing-guide",
      "description": "40-page guide to espresso and filter coffee.",
      "type": "digital",
      "price": { "amount": 900, "currency": "EUR" },
      "in_stock": true,
      "image_url": null
    }
  ],
  "meta": { "page": 2, "limit": 2, "total": 5 }
}
```

Example: `GET /v1/products?limit=abc`
```http
HTTP/1.1 400 Bad Request
Content-Type: application/problem+json
Cache-Control: no-store
```
```json
{
  "type": "https://mago.example/errors/invalid-query",
  "title": "Invalid query parameter",
  "status": 400,
  "detail": "\"limit\" must be an integer."
}
```

## Acceptance criteria
<!-- Products in a Given have different `created_at` values unless stated otherwise. -->
- [ ] AC1: Given 25 published products,
  when `GET /v1/products`,
  then `200`, media type `application/json`, `Cache-Control: no-store`, `items` are the 20 newest products
  ordered by `created_at` desc, and `meta` = `{ "page": 1, "limit": 20, "total": 25 }`.
- [ ] AC2: Given 3 published, 2 draft and 2 archived products,
  when `GET /v1/products`,
  then `200`, `items` has the 3 published products only, and `meta.total` = 3.
- [ ] AC3: Given 1 published digital product with `download_url`, `cost_price` = 500, `quantity` = 0,
  `price` = 900 EUR and `image_url` null,
  when `GET /v1/products`,
  then the keys of `items[0]` are exactly `id`, `sku`, `name`, `slug`, `description`, `type`, `price`, `in_stock`,
  `image_url`; `image_url` is present with value `null`; `id` is a UUID string; the keys of `items[0].price` are
  exactly `amount`, `currency`; `price.amount` is the integer 900 and `price.currency` = `"EUR"`.
- [ ] AC4: Given 3 published products: physical with `quantity` 0, physical with `quantity` 3, digital with `quantity` 0,
  when `GET /v1/products`,
  then `in_stock` is `false`, `true`, `true` respectively.
- [ ] AC5: Given 3 published products with the same `created_at` and ids `A < B < C`,
  when `GET /v1/products?limit=2` and then `GET /v1/products?limit=2&page=2`,
  then page 1 is `[A, B]` and page 2 is `[C]`.
- [ ] AC6: Given 25 published products,
  when `GET /v1/products?limit=500`,
  then `200`, `items` has 25 elements and `meta.limit` = 100.
- [ ] AC7: Given 25 published products,
  for each of `GET /v1/products?limit=0` and `GET /v1/products?limit=-5`,
  then `200`, `items` has 1 element and `meta.limit` = 1.
- [ ] AC8: Given 25 published products,
  for each of `GET /v1/products?page=0` and `GET /v1/products?page=-1`,
  then `200`, `meta.page` = 1 and `items` is the same as for `GET /v1/products`.
- [ ] AC9: Given 25 published products,
  when `GET /v1/products?page=2`,
  then `200`, `items` are the 5 oldest products and `meta` = `{ "page": 2, "limit": 20, "total": 25 }`.
- [ ] AC10: Given 25 published products,
  when `GET /v1/products?page=3`,
  then `200`, `items` is empty and `meta` = `{ "page": 3, "limit": 20, "total": 25 }`.
- [ ] AC11: Given no products,
  when `GET /v1/products`,
  then `200`, `items` is empty and `meta.total` = 0.
- [ ] AC12: Given 25 published products,
  `GET /v1/products?limit=` gives `meta.limit` = 20, `?limit=05` gives `meta.limit` = 5,
  and `?limit=5&limit=10` gives `meta.limit` = 10.
- [ ] AC13: Given 25 published products,
  for each of `limit=abc`, `limit=1.5`, `limit=1e2`, `limit=%2B5`, `limit=%205`, `limit=10abc`, `limit=5%0A`,
  `limit=%D9%A5`, `limit[]=zzz`, `limit=1234567890`, `page=abc`, `page=10001`,
  then `400`, media type `application/problem+json`, `Cache-Control: no-store`,
  `type` = `https://mago.example/errors/invalid-query`, `title` = `Invalid query parameter`, `status` = 400,
  `detail` names the param; exactly one warning is logged, whose message and context contain the param name
  and not the raw value (e.g. no `abc`, no `zzz`).
- [ ] AC14: Given 25 published products,
  when `GET /v1/products?limit=abc&page=abc`,
  then `400`, `detail` names both `limit` and `page`, and exactly one warning is logged, naming both.
- [ ] AC15: Given 25 published products,
  when `GET /v1/products?foo=bar`,
  then the response is the same as for `GET /v1/products`.
- [ ] AC16: Given 25 published products,
  when `GET /v1/products` with header `Authorization: Bearer not-a-token`,
  then the response is the same as without the header (`200`, never `401`).

## Edge cases
- Empty catalog: `200` with an empty item list (AC11).
- Malformed `limit` or `page`: `400` with the error body, warning logged (AC13); both malformed: one `400` naming both (AC14).
- `limit` above the max: reduced to `100`, `200` (AC6).
- `limit` `0` or negative: reduced to `1`, `200` (AC7).
- `page` `0` or negative: reduced to `1`, `200` (AC8).
- `page` above `10000` (out of range, not malformed): `400` like malformed input (AC13), so the database never gets
  a huge `OFFSET`.
- Partial last page and page past the end: `200` with the remaining items, or an empty list (AC9, AC10).
- Empty value, leading zeros, repeated param (AC12).
- Same `created_at` across a page boundary: `id` asc decides (AC5).
- Unknown query params: ignored, in every environment (AC15).
- `Authorization` header: ignored (AC16).

## Security
- Public endpoint, read-only, no token; an `Authorization` header is ignored (AC16).
- Only `published` products (AC2); only the whitelisted keys above (AC3). Admin fields live on `/v1/admin/products`.
- Malformed input and `page` above `10000` are rejected with `400` and the error body (AC13); other out-of-range
  valid integers are clamped, not errors. Rejected input never causes a redirect and never shows a stack trace.
  (Symfony's own trailing-slash redirect, `/v1/products/` → `/v1/products`, is left as is.)
- Logs contain the name of a malformed param, never its raw value (no log injection). Exception, accepted:
  in dev, Symfony's router logs the request URI (URL-encoded, so no line injection) at `info` level.
- `page` max `10000`: bounds the `OFFSET`, so one request cannot force a very slow query.

## Performance
- Budget: p95 < 200 ms with 10,000 published products, any valid `page` and `limit`. Measured with k6 before
  week 4 closes; the k6 scenario (users, duration, environment) is defined then.
- Index (PostgreSQL, partial): `CREATE INDEX ... ON product (created_at DESC, id ASC) WHERE status = 'published'`.
  It matches the list query, so rows are read already in order; checked by hand with `EXPLAIN ANALYZE`
  when the migration is written (no AC).
- `meta.total` is a `COUNT(*)` on published products (second query per request): acceptable at 10k rows.
- No HTTP cache in v1: `Cache-Control: no-store` (AC1).

## Decisions (mine, not the AI's)
- **One endpoint per client, not one shared endpoint** (2026-11-10). `GET /v1/products` serves the storefront only:
  public, read-only, published products, whitelisted fields. Why: admin data never goes through the public code
  path, and the public response is the same for every caller (it can be cached later). Admin and go-sync get their
  own endpoints, tokens and limits in their own specs.
- **Search is a separate feature** (2026-11-10): keeps v1 small; search belongs to `ai-python`.
- **Product has a `status` field** (2026-11-10): only `published` products are public.
- **Field list proposed by Claude, confirmed by me** (2026-11-10): generic store of physical and digital products;
  `in_stock` instead of `quantity` so stock levels cannot be scraped; `download_url` never public.
- **Digital products are always in stock** (2026-11-10): a file does not run out.
- **Clamp out-of-range values, reject malformed ones** (2026-11-10): `limit` above 100 → 100; `limit`/`page` `0` or
  negative → 1; not a valid integer → `400`. `limit` and `page` follow the same rules, except above the max:
  `limit` is clamped, `page` is rejected (next decision).
- **One warning per rejected request** (2026-11-10), naming every rejected param: one request = one log line.
- **`Cache-Control: no-store` on every response of this endpoint** (2026-11-10), `400` included: one rule only.
- **`page` max 10000** (2026-11-10): bounds the `OFFSET` (`400` above, not clamped: a page that far is a client bug).
- **Lenient on harmless input** (2026-11-10): empty value = default, leading zeros allowed, last repeated value wins
  (PHP's default behaviour).
- **Invalid input → `400`** (2026-11-10), not `404` and not a redirect to the homepage: the API has no homepage,
  and `404` means "resource not found".
- **Pagination with `page` + `limit`** (2026-11-10), not a cursor: simple, and the storefront can jump to a page.
- **`limit` default 20, max 100** (2026-11-10): one storefront page; the max bounds the query cost.
- **Unknown query params are ignored** (2026-11-10), the same in every environment: adding a param later
  (e.g. `?sort=`) does not break old clients.
- **`Authorization` header ignored** (2026-11-10): the endpoint is public; a stale token in the storefront must not
  break the catalog.
- **Envelope with `items` and `meta`** (2026-11-10): easy for Laravel to read, can grow without breaking clients.
- **Order `created_at` desc, then `id` asc** (2026-11-10): newest first; `id` makes the order unique, so a product
  never appears on two pages; `asc` matches the index.
- **Partial index on published products, proposed by Claude** (2026-11-10): matches the only query
  this endpoint runs (`WHERE status = 'published' ORDER BY created_at DESC, id ASC`) and stays small.
- **No HTTP cache in v1, proposed by Claude** (2026-11-10): the index already meets the budget at 10k
  products; caching needs a decision on how fast price/stock changes from go-sync must show up, so it gets its own spec.
- **Errors in RFC 9457 problem+json** (2026-11-10): a standard format, reused by every service (README: one error format everywhere).
- **Warnings of the `app` channel always written in prod** (2026-11-11, proposed by Claude): Monolog's default
  `fingers_crossed` handler drops warnings unless an error follows, so the 400 warnings go to stderr (JSON) through
  their own handler.
- **Raw URI in the dev log accepted** (2026-11-11): Symfony's router logs it at `info`; dev only, URL-encoded.
- **AC11 tested when the catalog can be replaced** (2026-11-11): with the temporary fake in-memory data the catalog
  is never empty; the test comes with the Doctrine entity / injected catalog (week 1, Thursday).
- **Deferred to their own specs** (2026-11-10): rate limiting, HTTP caching, security headers/CSP, OpenAPI, k6 scenario.

## Done when
- [x] Tests green (command + output pasted in the PR/commit)
- [x] PHPStan clean
- [ ] `reviewer` agent: PASS
