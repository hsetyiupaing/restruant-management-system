# API Implementation Plan

Status: implemented (see [api-spec.md](api-spec.md) for the endpoint reference).
Source of truth for schema: the files in `database/migrations/`.

## 1. Repository inspection summary

| Area | Finding |
|---|---|
| Framework | Laravel 13.36, PHP 8.5.4 |
| Existing auth | Laravel Fortify 1.37 (session guard `web`, 2FA, passkeys) with Inertia/Vue starter kit |
| API layer | **Not installed** at inspection time: no `routes/api.php`, no `api` middleware group, no Sanctum |
| Database | MySQL 8.4 (`DB_CONNECTION=mysql`); tests run on SQLite `:memory:` per `phpunit.xml` |
| Timezone | `config('app.timezone')` = UTC; `restaurants` has no timezone column |
| Policies / services | None existed; only starter-kit settings controllers |

### Installed during this phase

- `php artisan install:api --without-migration-prompt` — added `laravel/sanctum ^4.0`,
  `routes/api.php`, the `api` route file registration in `bootstrap/app.php`, and the
  `create_personal_access_tokens_table` migration (applied, non-destructive).
- `php artisan config:publish cors` — added `config/cors.php`.

## 2. Actual schema summary

All tables are InnoDB / `utf8mb4_unicode_ci`.

| Table | Key columns | Enums | Notes |
|---|---|---|---|
| `users` | `name`, `email` UK, `password`, `role`, `no_show_count`, 2FA cols, `email_verified_at` | `role`: CUSTOMER, STAFF, ADMIN, SUPER_ADMIN | `name` is the display name; **no `phone` column** |
| `restaurants` | `name`, `slug` UK, `phone`, `address`, `opening_hours` JSON, `slot_minutes` (90), `auto_confirm_reservations`, `delivery_fee`, `min_delivery_amount`, `tax_rate`, `is_active` | — | Cascades to menu/tables/reservations/members; orders `RESTRICT` |
| `restaurant_members` | `restaurant_id`, `user_id`, `role`, `staff_type` | `role`: STAFF, ADMIN; `staff_type`: CASHIER, KITCHEN, WAITER, DRIVER (nullable) | UNIQUE `(restaurant_id, user_id)` |
| `categories` | `restaurant_id`, `name`, `sort_order` | — | Index `(restaurant_id, sort_order)` |
| `menu_items` | `restaurant_id`, `category_id`, `name`, `description`, `price` 10,2, `image_url`, `is_available`, `is_featured`, `deleted_at` | — | Soft deletes; category FK `RESTRICT` |
| `dining_tables` | `restaurant_id`, `label`, `capacity`, `min_capacity`, `pos_x`, `pos_y`, `shape`, `is_active` | `shape`: ROUND, SQUARE, RECT | UNIQUE `(restaurant_id, label)`; **floor-plan coordinates exist** |
| `reservations` | `restaurant_id`, `customer_id`, `table_id`, `party_size`, `start_time`, `end_time`, `status`, `special_request`, `hold_expires_at` | `status`: PENDING, CONFIRMED, SEATED, COMPLETED, CANCELLED, NO_SHOW, EXPIRED | Index `(table_id, start_time, end_time)` |
| `orders` | `restaurant_id`, `customer_id`, `reservation_id` (nullable), `order_number` UK, `order_type`, `status`, `payment_status`, `subtotal`, `delivery_fee`, `tax`, `discount`, `total`, `scheduled_for`, `customer_note` | `order_type`: PICKUP, DELIVERY; `status`: PLACED, CONFIRMED, PREPARING, READY, OUT_FOR_DELIVERY, COMPLETED, CANCELLED, REJECTED, DELIVERY_FAILED, REFUNDED; `payment_status`: UNPAID, PAID, FAILED, REFUNDED | Index `(restaurant_id, status, created_at)` |
| `order_items` | `order_id`, `menu_item_id` (nullable), `item_name_snapshot`, `unit_price`, `quantity`, `line_total`, `note` | — | `menu_item_id` nulls on delete |
| `delivery_details` | `order_id` UK, `driver_id` (nullable), recipient name/phone/address, `latitude`, `longitude`, `delivery_status`, `assigned_at`, `picked_up_at`, `delivered_at`, `failure_reason` | `delivery_status`: UNASSIGNED, ASSIGNED, PICKED_UP, DELIVERED, FAILED | 1:0..1 with orders |
| `payments` | `order_id`, `method`, `amount`, `status`, `provider_ref`, `paid_at` | `method`: CASH, CARD, ONLINE; `status`: UNPAID, PAID, FAILED, REFUNDED | Order delete `RESTRICT` |
| `order_status_history` | `order_id`, `from_status`, `to_status`, `changed_by`, `changed_at`, `reason` | — | `from_status` nullable for initial row |
| `reservation_status_history` | `reservation_id`, `from_status`, `to_status`, `changed_by`, `changed_at` | — | No `reason` column |

## 3. Authentication decision

**Laravel Sanctum SPA cookie authentication** (installed by `install:api`), reusing the existing
Fortify `web` guard, session driver, and `User` model.

- `/api/v1/*` authenticates via `auth:sanctum`; `statefulApi()` middleware lets first-party
  browser requests reuse the session cookie.
- Fortify remains the authentication engine for the existing Inertia app (password hashing,
  2FA state, passkeys are all reused). The API adds JSON register/login/logout so the separate
  Vue 3 app does not need the Inertia pages.
- CSRF: stateful requests need `GET /sanctum/csrf-cookie` first and `withCredentials` +
  `X-XSRF-TOKEN` on axios.
- Bearer tokens remain available for non-browser clients through the same guard (Sanctum's
  `personal_access_tokens` table), but no token-issuing endpoint is exposed; the SPA must use cookies.
- `cors.php` is configured from `FRONTEND_URL` with `supports_credentials => true`.

## 4. Authorization model

| Actor | Ability |
|---|---|
| Guest | Active restaurants and their **available, non-soft-deleted** menu items only |
| Customer | Own orders/reservations only (policy checks ownership); place orders; cancel own `PLACED` order; cancel own reservation before the cutoff |
| Staff (membership) | Operational access to their restaurant only: order queue, status transitions (forward-only), reservations, deliveries, payment records |
| Restaurant admin (`restaurant_members.role = ADMIN`, or `users.role = ADMIN` with membership) | Everything staff can, plus menu/category/table CRUD, members, status correction (cancel after PREPARING), refunds |
| Super admin (`users.role = SUPER_ADMIN`) | Create restaurants, activate/deactivate, manage any restaurant |

Every nested admin route is scoped through the restaurant in the URL
(`/admin/restaurants/{restaurant}/orders/{order}` resolves the order **inside** that
restaurant), so cross-restaurant IDs return 404 rather than leaking existence.

## 5. Endpoints (implemented)

See [api-spec.md](api-spec.md) for fields, examples and error format.

- **Auth**: `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `PATCH /auth/me`
- **Public**: `GET /restaurants`, `GET /restaurants/{slug}`, `GET /restaurants/{slug}/categories`,
  `GET /restaurants/{slug}/menu-items`, `GET /restaurants/{slug}/menu-items/{menuItem}`,
  `GET /restaurants/{slug}/availability`
- **Customer**: `GET/POST /orders`, `GET /orders/{order}`, `POST /orders/{order}/cancel`,
  `GET/POST /reservations`, `GET /reservations/{reservation}`, `POST /reservations/{reservation}/cancel`
- **Restaurants (platform)**: `GET /admin/restaurants`, `POST /admin/restaurants`,
  `PATCH /admin/restaurants/{restaurant}`, `POST .../activate`, `POST .../deactivate`
- **Restaurant admin**: `GET /admin/restaurants/{restaurant}/settings`; settings are changed with
  `PATCH /admin/restaurants/{restaurant}` (restaurant admins or platform administrators)
- **Members**: `GET/POST /admin/restaurants/{restaurant}/members`, `PATCH/DELETE .../members/{member}`
- **Categories**: `GET/POST .../categories`, `PATCH/DELETE .../categories/{category}`
- **Menu items**: `GET/POST .../menu-items`, `GET/PATCH/DELETE .../menu-items/{menuItem}`, `PATCH .../{menuItem}/availability`
- **Tables**: `GET/POST .../tables`, `PATCH/DELETE .../tables/{table}`
- **Orders**: `GET .../orders`, `GET .../orders/{order}`, `PATCH .../orders/{order}/status`
- **Reservations**: `GET .../reservations`, `PATCH .../reservations/{reservation}/status`
- **Deliveries**: `GET .../deliveries`, `GET .../deliveries/{delivery}`, `PATCH .../deliveries/{delivery}`
- **Payments**: `GET/POST .../orders/{order}/payments`, `POST /admin/payments/{payment}/refund`
  (not nested under a restaurant: it is authorized from the payment's own order)
- **KPIs**: `GET .../kpis`

## 6. Business rules as implemented

- Checkout recalculates every amount from database prices. `tax = round(subtotal * tax_rate, 2)`,
  `delivery_fee` comes from the restaurant for `DELIVERY` orders (0 for pickup), discount defaults
  to 0 (no promotion schema exists), `total = subtotal + delivery_fee + tax - discount`.
- `min_delivery_amount` is enforced against the subtotal for delivery orders.
- Menu item names and unit prices are snapshotted into `order_items`; only available,
  non-deleted items belonging to the restaurant are accepted.
- Order numbers use `ORD-YYYYMMDD-XXXXXXXX` (Crockford-ish uppercase alphabet) and are retried on
  unique-constraint collisions inside the checkout transaction.
- Duplicate submissions: an optional `Idempotency-Key` header is cached for 24h per user+key and
  replays the original order.
- Reservation duration comes from `restaurants.slot_minutes`; there is no separate duration column.
  Lead time 1h–30 days, 15-minute hold for `PENDING`, 2-hour customer cancellation cutoff,
  15-minute no-show grace, all matching section 3 of `structure.md`.
- Cross-table overlap check runs inside a transaction with `lockForUpdate()` on the dining table row;
  active statuses are PENDING, CONFIRMED and SEATED.
- Opening hours JSON format: `{"mon": {"open": "09:00", "close": "22:00"}, ...}` keyed `mon`–`sun`;
  `null` means no restriction. `close` of `00:00` is treated as midnight.
- Status transitions are checked against explicit maps; every change writes a history row.
- Payments are **manual records entered by staff**. No gateway is configured, so `PAID` is only
  reachable through an authenticated staff action; `orders.payment_status` is derived from the
  payment rows.

### Order status transitions

| From | Staff may move to | Restaurant admin may also |
|---|---|---|
| PLACED | CONFIRMED, REJECTED, CANCELLED | — |
| CONFIRMED | PREPARING | CANCELLED |
| PREPARING | READY | CANCELLED |
| READY | COMPLETED (pickup), OUT_FOR_DELIVERY (delivery) | CANCELLED |
| OUT_FOR_DELIVERY | COMPLETED, DELIVERY_FAILED | CANCELLED |
| DELIVERY_FAILED | OUT_FOR_DELIVERY | CANCELLED |
| COMPLETED | — | REFUNDED |
| CANCELLED, REJECTED, REFUNDED | terminal | — |

Customers may only move `PLACED → CANCELLED` on their own orders.

### Reservation status transitions

`PENDING → CONFIRMED | CANCELLED | EXPIRED`, `CONFIRMED → SEATED | CANCELLED | NO_SHOW`,
`SEATED → COMPLETED | CANCELLED`, `PENDING/EXPIRED/CANCELLED/NO_SHOW/COMPLETED` terminal.
Customers may cancel PENDING or CONFIRMED reservations up to 2 hours before `start_time`;
staff/admin may cancel any non-terminal reservation.

### KPI semantics

- **Revenue** = `SUM(total)` of orders with `payment_status = PAID` and status not in
  `CANCELLED, REJECTED, REFUNDED`. Unpaid/COD orders are not counted.
- **Orders** = all orders created in the range; **completed orders** is reported separately.
- **Average order value** = revenue ÷ paid order count, 0 when the count is zero.
- Daily boundaries use `config('app.timezone')`.

## 7. Schema gaps and documentation-only limitations

No destructive or changing migration was required. Limitations:

1. `users` has no `phone` column (structure.md's ERD suggested one). Registration and login are
   email-based. A new migration adding `phone` would be needed for SMS/phone login.
2. `restaurants` has no timezone column. KPI day boundaries and opening-hours validation use the
   application timezone (UTC). A per-restaurant timezone would need a new nullable column.
3. `customer_addresses` was not migrated. Delivery orders accept the recipient name, phone and
   address at checkout and snapshot them into `delivery_details`; there is no address book.
4. There is no order `picked_up_at`/`completed_at`, so completion timestamps are only available
   for delivery orders (via `delivery_details.delivered_at`) and history rows.
5. No promotion table exists, so checkout always stores `discount = 0`.
6. Table labels are unique per restaurant, so a deleted/disabled table's label cannot be reused.
   Tables are therefore deactivated (`is_active = false`) rather than deleted when reservations exist.

## 8. Implementation sequence

1. Plan and inspection (this document).
2. Enums, models, relationships, factories.
3. API routing, Sanctum, CORS, rate limiters, exception shaping.
4. Auth endpoints.
5. Public restaurant/menu endpoints.
6. Restaurant, membership and menu administration.
7. Reservations (availability + booking).
8. Checkout, order management, deliveries.
9. Payments.
10. Customer history and KPI endpoints.
11. Feature tests and `docs/api-spec.md`.
12. Seeders.

## 9. Verification

Run on 2026-10-18 (SQLite `:memory:` for tests, MySQL for the seeded dev database):

```
php artisan test                      # 109 passed, 394 assertions
vendor/bin/pint --test                # passed
vendor/bin/phpstan analyse            # 0 errors (larastan level 7)
php artisan db:seed --class=DemoDataSeeder   # idempotent; run twice, counts unchanged
```

API coverage lives in `tests/Feature/Api/`:

| File | Covers |
|---|---|
| `AuthTest.php` | guest 401s, register (incl. role-escalation attempt), login/logout, profile ownership |
| `PublicMenuTest.php` | inactive restaurant hiding, available-only menu, filters, slug/item cross-checks, pagination |
| `OrderCheckoutTest.php` | server-side pricing, delivery fee/minimum, foreign/unavailable items, quantities, idempotency, ownership, cancellation |
| `RestaurantAuthorizationTest.php` | membership gate, staff vs admin, cross-restaurant 404s, category restrict, soft delete, table deactivation, platform-only actions |
| `ReservationTest.php` | availability map, overlap and back-to-back slots, lead time, opening hours, capacity, cancel cutoff, transitions, no-show counting |
| `AdminOrderPaymentTest.php` | order lifecycle + history, illegal transitions, admin-only transitions, payment cap, failed/refund semantics, delivery→order sync, driver eligibility |
| `KpiTest.php` | scoping, revenue exclusion rules, date range/series, staff denial |

Not exercised automatically: concurrent double-booking under real parallel load (the SQLite
in-memory test client is single-connection — the row lock is verified by inspection, not by a
parallel test), real payment-gateway webhooks (none integrated), and CORS/cookie behaviour
in a real browser (config only).
