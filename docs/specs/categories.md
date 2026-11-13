# SPEC: Categories (tree, menu, products by category)

- **Service:** symfony-api
- **Week / day:** week 1 / day 5 (2026-11-13)
- **Status:** ready

## Goal
The storefront shows a category menu (a tree) and lists the products of a category,
so a shopper can browse the catalog by topic instead of one flat list.

## Out of scope
- Write endpoints for categories (create, move, reorder, delete): admin spec (`/v1/admin/categories`).
- Store scope: one root per store, per-store-view names/URLs: separate spec.
- Display mode (products only / static block / both), CMS blocks, layout updates, category images.
- Default sort and "available sort by" per category; layered navigation / filters.
- URL rewrites and redirects when a category moves: separate spec.
- SEO meta fields (`meta_title`, `meta_description`): not stored in v1; added with the single-category endpoint (Deferred).

## API / interface

### Category model (stored)
| Field | Type | Public | Notes|
|---|---|---|---|
| `id` | UUID | yes | `entity_id`, but not an auto-increment |
| `parent` | Category \| null | no | `parent_id`; `null` = root. One root only in v1; the root is technical: no `url_key`, never listed, never a filter |
| `path` | string | no | `path`, materialized ids from the root, own id included: root `"<root-id>"`, child `"<root-id>/<id>"`. Subtree query = `path LIKE '<path>/%'` |
| `level` | integer | no | `level`: root = 0. Max level: 3 (root + 3 menu levels) |
| `position` | integer | no | `position`: order among siblings (ties: `name` asc, then `id` asc) |
| `name` | string | yes | `name` |
| `url_key` | string | no | `url_key`: lowercase, `[a-z0-9-]`; unique among siblings (DB unique constraint on `parent_id, url_key`) |
| `url_path` | string | yes | `url_path`: `url_key` for a child of the root, else parent `url_path` + `/` + `url_key`, e.g. `coffee/espresso-machines`; unique (DB constraint) |
| `is_active` | bool | no | `is_active`: an inactive category hides itself and its whole subtree. A category is *visible* only if it and all its ancestors are active |
| `include_in_menu` | bool | no | `include_in_menu`: active but not in the menu (landing pages, promo categories) |
| `is_anchor` | bool | no | `is_anchor`: the category lists the products of all its descendants, not only its own. Default `true` |
| `created_at`, `updated_at` | datetime | no | |

### Product ↔ category
**N-N with `position`**: a product can be in "Espresso machines" and in "Gift ideas"; `position` is the manual
merchandising order inside one category. Join table `category_product` with primary key `(category_id, product_id)`:
a product is linked to a category at most once.

### `GET /v1/categories` (menu tree)
- Public, no token, `Content-Type: application/json`, `Cache-Control: no-store, private` (same rules as products-list
  for v1). Query params and the `Authorization` header are ignored.
- Returns the visible categories with `include_in_menu = true`, as a tree under the root (the root itself is not shown),
  siblings ordered by `position` asc, then `name` asc, then `id` asc (sorted in SQL, not in PHP).
  A category with `include_in_menu = false` hides its children from the menu too.
```json
{
  "items": [
    {
      "id": "0193a7d0-...", "name": "Coffee", "url_path": "coffee",
      "children": [
        { "id": "0193a7d1-...", "name": "Espresso machines", "url_path": "coffee/espresso-machines", "children": [] }
      ]
    }
  ]
}
```

### `GET /v1/products?category=<id>` (products of a category)
- Same response, pagination, validation and order as `GET /v1/products` (spec products-list), filtered by category.
- Anchor category: products of the category and of all its *visible* descendants (a descendant under an inactive
  category is excluded even if its own `is_active` is true), each product once. Non-anchor: only products directly
  in the category. `meta.total` = number of published products in that set.
- Order: same as products-list (`created_at` desc, `id` asc). The category `position` is stored but not used in v1
  (it gets a writer with the admin spec, see Deferred).
- `category` input, same leniency as `limit`/`page`: missing or empty = no filter; repeated = last wins; an array
  (`category[]=x`) is malformed. Accepted format: canonical UUID, 36 chars `8-4-4-4-12` hex, case-insensitive
  (regex `^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z`); anything else is malformed.
- Malformed → `400` exactly as products-list AC13: `application/problem+json`, `Cache-Control: no-store, private`,
  `type` `https://mago.example/errors/invalid-query`, `title` `Invalid query parameter`, `detail` names `category`;
  one warning logged with the param name, never the raw value.
- Order of checks: every param is validated first (one `400` naming all rejected params); only then is the category
  looked up. So `?category=<unknown>&limit=abc` → `400`, not `404`.
- Unknown id, the root id, an inactive category or one under an inactive ancestor → `404`, `application/problem+json`,
  `Cache-Control: no-store, private`, `type` `https://mago.example/errors/category-not-found`, `title`
  `Category not found`, `status` 404, `detail` `No category with this id.` (the requested id is never echoed). Not logged (a normal outcome, not a client bug).

## Acceptance criteria
- [ ] AC1: Given a tree root → Coffee (position 2) and Tea (position 1), Coffee → Espresso machines,
  when `GET /v1/categories`, then `200`, `application/json`, `Cache-Control: no-store, private`, the root is not in
  the response, `items` is `[Tea, Coffee]`, Coffee has one child Espresso machines, and each node has exactly the keys
  `id`, `name`, `url_path`, `children`.
- [ ] AC2: Given Coffee inactive, when `GET /v1/categories`, then neither Coffee nor Espresso machines appear.
- [ ] AC3: Given Coffee with `include_in_menu = false`, when `GET /v1/categories`, then neither Coffee nor its children
  appear; `GET /v1/products?category=<Coffee id>` still answers `200`.
- [ ] AC4: Given Coffee (child of the root) and Espresso machines under Coffee, then their `url_path` are `coffee` and
  `coffee/espresso-machines`; flushing a second child of Coffee with `url_key` `espresso-machines` fails on the DB
  unique constraint (tested at repository level).
- [ ] AC5: Given published products P1, P2, P3, anchor Coffee with P1, Espresso machines (under Coffee) with P2 and P1, and inactive
  Grinders (under Coffee) with active child Hand grinders holding P3, when `GET /v1/products?category=<Coffee id>`,
  then `items` contains P1 and P2, each once, not P3, and `meta.total` = 2.
- [ ] AC6: Given the same tree and published products with Coffee non-anchor, when `GET /v1/products?category=<Coffee id>`,
  then `items` contains only P1.
- [ ] AC7: For each of `category=abc`, `category[]=x`, and `category=<unknown UUID>&limit=abc`, then the `400` above
  naming every rejected param, one warning; for each of an unknown UUID, the root id, an inactive category, and a
  category under an inactive ancestor, then the `404` above.
- [ ] AC8: Given a draft product assigned to an active category, then it never appears in
  `GET /v1/products?category=...` (published-only rule of products-list still applies).

## Deferred
- `GET /v1/categories/{url_path}` (single category with SEO meta fields, breadcrumbs): needs the URL rules first;
  `meta_title`/`meta_description` are added with it.
- Write-side guards (max level 3, `url_key` format, one root only): with the admin spec, which is the first writer.
  Until then, fixtures respect them.
- Admin CRUD and moving a subtree (rewrite `path`, `level`, `url_path` of all descendants in one transaction).
- `children_count` cached column: compute when needed.
- Category-specific product order set by an admin (writes to `position`): admin spec.
- HTTP caching of the menu (it changes rarely: a good ETag candidate, week 4).

## Edge cases
- Edge cases without an AC are still covered by tests ("every feature ships with its tests").
- The root is always active (fixtures guarantee it); its `is_active` is never false.
- Empty tree (only the root, or no root at all on a fresh database): `200` with `items: []`.
- Category with no products: `200` with an empty list and `meta.total` 0.
- Product in two subcategories of an anchor: listed once (AC5).
- Two siblings with the same `position`: `name` asc, then `id` asc decide.
- `category` and `limit`/`page` malformed together (`?category=abc&limit=abc`): one `400` naming every rejected param,
  one warning logged (same rule as products-list AC14).

## Security
- Public, read-only, no token; inactive categories are invisible (AC2, AC7): `404`, never a different error that would
  reveal that the category exists.
- Only whitelisted keys (AC1); `url_key`, `is_active`, `is_anchor`, `include_in_menu`, `path`, `level` are internal.
- `category` validated as a UUID before any query (AC7); the raw value is never logged.

## Performance
- Budget: same as products-list (p95 < 200 ms), with 500 categories and 10,000 products.
- Menu: one query for all active categories, tree built in PHP (no N+1 per level).
- Anchor: a fixed number of queries whatever the tree size (category lookup with ancestor check, subtree by
  `path LIKE '<path>/%'` with an index on `path` using `text_pattern_ops`, products, `COUNT`): no N+1.

## Decisions (mine, not the AI's)
- **Tree model: adjacency (`parent`) + materialized `path` + `level`** (2026-11-13): like Magento; simple writes,
  a whole subtree in one `LIKE` query.
- **Product ↔ category N-N with `position`** (2026-11-13): a product can live in several categories
  (e.g. "Espresso machines" and "Gift ideas"), as in Magento `catalog_category_product`.
- **One root in v1** (2026-11-13): store scope (one root per store) comes with its own spec.
- **`is_anchor` defaults to `true`** (2026-11-13): as in Magento 2; a parent category shows its children's products.
- **Max level 3** (2026-11-13): root + 3 menu levels, enough for a storefront menu. Enforced on write (admin spec).
- **Root id as a filter → `404`** (2026-11-13, proposed by Claude): the root is technical, never shown; "all products"
  is `GET /v1/products` without a filter.
- **SEO meta fields come with the single-category endpoint** (2026-11-13, proposed by Claude): no column without a reader.
  Exception: `category_product.position` is stored now, so the join table does not change shape with the admin spec.
- **Order inside a category = products-list order** (2026-11-13): one order rule and one index for every list;
  `position` has no writer until the admin spec, so sorting by it now would sort by a constant.
- **`include_in_menu = false` hides its children from the menu too** (2026-11-13): as in Magento; the category
  itself still answers on `GET /v1/products?category=...` (AC3).

## Done when
- [ ] Tests green (command + output pasted in the PR/commit)
- [ ] PHPStan clean
- [ ] `reviewer` agent: PASS
