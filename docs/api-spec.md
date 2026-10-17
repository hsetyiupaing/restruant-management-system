# Restaurant Management API v1

Base URL: `/api/v1`. All responses are JSON. The schema source of truth is
`database/migrations/`; the design rationale is in
[api-implementation-plan.md](api-implementation-plan.md).

---

## 1. Authentication and CSRF

Authentication is **Laravel Sanctum SPA cookie auth**, reusing the existing Fortify `web`
session guard. There are no API keys or tokens in the browser flow.

1. `GET /sanctum/csrf-cookie` — issues the `XSRF-TOKEN` cookie.
2. Send every request with `withCredentials: true` and the `X-XSRF-TOKEN` header (axios does this
   automatically once the cookie exists).
3. `POST /api/v1/auth/login` — starts the session.
4. `POST /api/v1/auth/logout` — ends it.

Requirements:

| Setting | Value |
|---|---|
| `SESSION_DRIVER` | `cookie`, `database` or `redis` (not `array`) |
| `SANCTUM_STATEFUL_DOMAINS` | hosts of the SPA, e.g. `localhost:5173` |
| `FRONTEND_URL` | SPA origin used by `config/cors.php` |
| `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | set to match the deployment topology |
| `APP_URL` | must match the API host |

`supports_credentials => true` in `config/cors.php`; CORS only allows the origin from
`FRONTEND_URL`. Nothing session-related, no secrets and no password hashes are ever returned.

Authentication failures on `/api/*` return `401 {"message": "Unauthenticated."}`.

---

## 2. Error format

Validation (`422`), business-rule (`409`/`422`) and authorization (`403`) errors share one shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items": ["One or more items are unavailable."]
  }
}
```

| Status | Meaning |
|---|---|
| `200` | OK |
| `201` | Created |
| `204` | No content (logout, deletes without a body) |
| `401` | Not authenticated |
| `403` | Authenticated but not allowed (policy or membership) |
| `404` | Not found, **or** an ID that belongs to another restaurant |
| `409` | Conflict — category still referenced, table just booked |
| `422` | Validation or business-rule violation (illegal status transition, minimum order, closed restaurant…) |
| `429` | Rate limited (`Retry-After` header set) |

---

## 3. Pagination, filtering, sorting

Every index endpoint returns Laravel's paginator envelope:

```json
{
  "data": [],
  "links": { "first": "…", "last": "…", "prev": null, "next": null },
  "meta": {
    "current_page": 1, "from": 1, "last_page": 3, "per_page": 15,
    "path": "…", "to": 15, "total": 42
  }
}
```

`per_page` is clamped to **1–100** (default 15). Query strings are preserved in the paginator links.

| Endpoint | Filters |
|---|---|
| `GET /restaurants` | `search`, `per_page` |
| `GET /restaurants/{slug}/menu-items` | `category_id`, `category` (name), `featured=1`, `search`, `per_page` |
| `GET /orders` | `status`, `per_page` |
| `GET /reservations` | `status`, `upcoming=1`, `per_page` |
| `GET /admin/restaurants` | `search`, `is_active`, `per_page` |
| `GET .../menu-items` | `category_id`, `is_available`, `is_featured`, `search`, `per_page` (includes soft-deleted rows, flagged by `deleted_at`) |
| `GET .../orders` | `status`, `order_type`, `payment_status`, `from`, `to`, `active=1`, `per_page` |
| `GET .../reservations` | `status`, `date`, `from`, `to`, `table_id`, `per_page` |
| `GET .../deliveries` | `delivery_status`, `active=1`, `driver_id`, `per_page` |
| `GET .../members` | `role`, `per_page` |

Lists that are inherently bounded and small are not paginated: categories, floor-plan tables,
an order's payments and status history.

---

## 4. Enums

| Enum | Values |
|---|---|
| `user.role` | `CUSTOMER`, `STAFF`, `ADMIN`, `SUPER_ADMIN` |
| `restaurant_members.role` | `STAFF`, `ADMIN` |
| `restaurant_members.staff_type` | `CASHIER`, `KITCHEN`, `WAITER`, `DRIVER`, `null` |
| `table.shape` | `ROUND`, `SQUARE`, `RECT` |
| `order.order_type` | `PICKUP`, `DELIVERY` |
| `order.status` | `PLACED`, `CONFIRMED`, `PREPARING`, `READY`, `OUT_FOR_DELIVERY`, `COMPLETED`, `CANCELLED`, `REJECTED`, `DELIVERY_FAILED`, `REFUNDED` |
| `order.payment_status` | `UNPAID`, `PAID`, `FAILED`, `REFUNDED` |
| `payment.method` | `CASH`, `CARD`, `ONLINE` |
| `payment.status` | `UNPAID`, `PAID`, `FAILED`, `REFUNDED` |
| `delivery.status` | `UNASSIGNED`, `ASSIGNED`, `PICKED_UP`, `DELIVERED`, `FAILED` |
| `reservation.status` | `PENDING`, `CONFIRMED`, `SEATED`, `COMPLETED`, `CANCELLED`, `NO_SHOW`, `EXPIRED` |

### Status transitions

**Orders** (staff; "admin" is an extra set for restaurant/admin platform administrators)

| From | Staff → | Admin additionally → |
|---|---|---|
| `PLACED` | `CONFIRMED`, `REJECTED`, `CANCELLED` | — |
| `CONFIRMED` | `PREPARING` | `CANCELLED` |
| `PREPARING` | `READY` | `CANCELLED` |
| `READY` | `COMPLETED` (pickup) / `OUT_FOR_DELIVERY` (delivery) | `CANCELLED` |
| `OUT_FOR_DELIVERY` | `COMPLETED`, `DELIVERY_FAILED` | `CANCELLED` |
| `DELIVERY_FAILED` | `OUT_FOR_DELIVERY` | `CANCELLED` |
| `COMPLETED` | — | `REFUNDED` |
| `CANCELLED`, `REJECTED`, `REFUNDED` | terminal | — |

Customers may cancel only their own `PLACED` orders.

**Reservations**: `PENDING → CONFIRMED | CANCELLED | EXPIRED`;
`CONFIRMED → SEATED | CANCELLED | NO_SHOW`; `SEATED → COMPLETED | CANCELLED`; everything else
terminal. Customers may cancel `PENDING`/`CONFIRMED` more than 2 hours before `start_time`.

**Deliveries**: `UNASSIGNED → ASSIGNED`; `ASSIGNED → PICKED_UP | FAILED`;
`PICKED_UP → DELIVERED | FAILED`. `PICKED_UP`/`DELIVERED`/`FAILED` mirror onto the order
(`OUT_FOR_DELIVERY`/`COMPLETED`/`DELIVERY_FAILED`).

Every transition writes a history row. Illegal transitions return `422`.

---

## 5. Endpoints

### 5.1 Auth

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/register` | — | throttle 6/min; always creates `CUSTOMER` |
| POST | `/auth/login` | — | throttle 10/min + 5 failed attempts/min per email+IP |
| POST | `/auth/logout` | session | `204` |
| GET | `/auth/me` | session | |
| PATCH | `/auth/me` | session | `name`, `email` only; changing email clears verification |

```http
POST /api/v1/auth/register
{ "name": "Ada Lovelace", "email": "ada@example.test",
  "password": "password", "password_confirmation": "password" }
```

`201` with the user resource (`role` is always `CUSTOMER`; a `role` field in the body is ignored).

### 5.2 Public catalogue

No authentication. Only `is_active` restaurants, only available non-deleted menu items.

| Method | Path | Query |
|---|---|---|
| GET | `/restaurants` | `search`, `per_page` |
| GET | `/restaurants/{slug}` | — |
| GET | `/restaurants/{slug}/categories` | — (includes `menu_items_count` of available items) |
| GET | `/restaurants/{slug}/menu-items` | `category_id`, `category`, `featured`, `search`, `per_page` |
| GET | `/restaurants/{slug}/menu-items/{menuItem}` | — (404 if it belongs elsewhere or is unavailable) |
| GET | `/restaurants/{slug}/availability` | `date=Y-m-d`, `time=H:i`, `party_size` |

Availability response:

```json
{ "data": {
    "restaurant": { "id": 1, "slug": "ember-and-oak", "slot_minutes": 90 },
    "start_time": "2026-10-20T18:00:00+00:00",
    "end_time":   "2026-10-20T19:30:00+00:00",
    "tables": [
      { "id": 2, "label": "E2", "capacity": 4, "min_capacity": 2, "shape": "SQUARE",
        "pos_x": 320, "pos_y": 140, "status": "AVAILABLE", "fits_party": true }
    ] },
  "meta": { "available_count": 3 } }
```

`status` is `AVAILABLE`, `RESERVED`, `OCCUPIED` (a seated reservation) or `UNAVAILABLE`
(party does not fit, or table inactive — inactive tables are omitted).

### 5.3 Customer orders

| Method | Path | Notes |
|---|---|---|
| GET | `/orders` | own orders; `status`, `per_page` |
| POST | `/orders` | throttle 20/min; optional `Idempotency-Key` header |
| GET | `/orders/{order}` | owner, restaurant member, or platform admin |
| POST | `/orders/{order}/cancel` | own `PLACED` order only |

```http
POST /api/v1/orders
Idempotency-Key: 5f0c2b1e-...
{
  "restaurant_id": 1,
  "order_type": "DELIVERY",
  "items": [
    { "menu_item_id": 4, "quantity": 2, "note": "no onion" }
  ],
  "customer_note": "Leave at the door",
  "scheduled_for": null,
  "reservation_id": null,
  "delivery": {
    "recipient_name": "Ada Lovelace",
    "recipient_phone": "555-0100",
    "address_line": "1 Compiler Way",
    "latitude": 51.5074,
    "longitude": -0.1278
  }
}
```

Validation rules: `restaurant_id` required and existing; `order_type` enum; 1–100 lines with
`distinct` menu item ids and quantity 1–99; `delivery.*` required when `order_type = DELIVERY`;
`scheduled_for` must be in the future; `reservation_id` must belong to the caller and the
restaurant. **Prices, tax, fees, totals and `payment_status` are not accepted** — anything
resembling them in the body is ignored.

Server-side calculation:

```
subtotal     = Σ(menu_item.price × quantity)          (prices read from the database)
delivery_fee = restaurant.delivery_fee   if DELIVERY, else 0
tax          = round(subtotal × restaurant.tax_rate, 2)
discount     = 0                                       (no promotion schema)
total        = round(subtotal + delivery_fee + tax − discount, 2)
```

Rejections: items from another restaurant, soft-deleted items, unavailable items, subtotal below
`min_delivery_amount` for delivery, inactive restaurant (`422`); missing delivery block (`422`);
duplicate order numbers are retried up to 5 times inside the transaction.

Response `201` is the order resource described in §5.3.1. Sending the same `Idempotency-Key`
again within 24 hours returns the original order with `201`.

#### 5.3.1 Order resource

```json
{ "data": {
    "id": 12, "order_number": "ORD-20261018-7Q4ZK2M8",
    "order_type": "DELIVERY", "status": "PLACED", "payment_status": "UNPAID",
    "restaurant": { "id": 1, "name": "Ember & Oak", "slug": "ember-and-oak" },
    "customer": { "id": 4, "name": "Ada Lovelace", "email": "ada@example.test" },
    "reservation": null,
    "items": [
      { "id": 30, "menu_item_id": 4, "name": "Ember Burger",
        "unit_price": "17.50", "quantity": 2, "line_total": "35.00", "note": "no onion" }
    ],
    "delivery": { "id": 3, "driver_id": null, "delivery_status": "UNASSIGNED", "...": "…" },
    "payments": [ { "id": 9, "method": "CASH", "amount": "38.50", "status": "PAID", "paid_at": "…" } ],
    "status_history": [ { "from_status": null, "to_status": "PLACED", "changed_by": null, "changed_at": "…" } ],
    "totals": { "subtotal": "35.00", "delivery_fee": "3.50", "tax": "3.50", "discount": "0.00", "total": "42.00" },
    "scheduled_for": null, "customer_note": "Leave at the door",
    "created_at": "2026-10-18T18:04:11+00:00", "updated_at": "2026-10-18T18:04:11+00:00"
  } }
```

Money is emitted as strings to avoid float drift. Related collections only appear when the
endpoint eager-loads them.

### 5.4 Customer reservations

| Method | Path | Notes |
|---|---|---|
| GET | `/reservations` | own; `status`, `upcoming=1`, `per_page` |
| POST | `/reservations` | throttle 10/min |
| GET | `/reservations/{reservation}` | owner, member, or platform admin |
| POST | `/reservations/{reservation}/cancel` | cutoff rules apply |

```http
POST /api/v1/reservations
{ "restaurant_id": 1, "table_id": 2, "party_size": 2,
  "start_time": "2026-10-20 19:00:00", "special_request": "Window seat" }
```

Rules: restaurant must be active; start time 1 hour – 30 days from now; inside `opening_hours`;
party size within `min_capacity`–`capacity`; no overlap with `PENDING`/`CONFIRMED`/`SEATED`
reservations on the same table (`new.start < existing.end AND new.end > existing.start`), checked
inside a transaction holding a row lock on the table → `409` on conflict. `end_time` is
`start_time + restaurants.slot_minutes`.

The resulting status is `CONFIRMED` when `restaurants.auto_confirm_reservations` is true, otherwise
`PENDING` with a 15-minute `hold_expires_at`. Expired holds are released automatically when
availability or a booking is next evaluated.

### 5.5 Restaurant administration

Base: `/api/v1/admin/restaurants/{restaurant}`. Every request requires a session **and** a
`restaurant_members` row (platform administrators bypass membership). Child IDs are resolved
inside the restaurant: a customer/category/menu item/table/order from another restaurant returns
`404`.

| Method | Path | Required |
|---|---|---|
| GET | `/admin/restaurants` | platform admin |
| POST | `/admin/restaurants` | platform admin |
| PATCH | `/admin/restaurants/{restaurant}` | restaurant admin |
| POST | `/admin/restaurants/{restaurant}/activate` | platform admin |
| POST | `/admin/restaurants/{restaurant}/deactivate` | platform admin |
| GET | `/admin/restaurants/{restaurant}/settings` | membership |

`opening_hours` accepts `{"mon":{"open":"11:30","close":"22:30"},…}` keyed `mon`–`sun`, or
`null` for "always open". `slot_minutes` 15–480, `tax_rate` 0–1, `delivery_fee` and
`min_delivery_amount` ≥ 0.

**Members** (restaurant admin)

| Method | Path | Body |
|---|---|---|
| GET | `.../members` | `role` filter |
| POST | `.../members` | `user_id` (unique per restaurant), `role`, `staff_type` |
| PATCH | `.../members/{member}` | `role`, `staff_type` |
| DELETE | `.../members/{member}` | `204` |

**Categories** (restaurant admin)

`GET|POST .../categories`, `PATCH|DELETE .../categories/{category}` — body `name`, `sort_order`.
`DELETE` returns `409` while menu items still reference the category (the FK is `RESTRICT`).

**Menu items** (restaurant admin)

| Method | Path | Notes |
|---|---|---|
| GET | `.../menu-items` | includes soft-deleted; `deleted_at` marks them |
| POST | `.../menu-items` | body below |
| GET | `.../menu-items/{menuItem}` | |
| PATCH | `.../menu-items/{menuItem}` | partial |
| PATCH | `.../menu-items/{menuItem}/availability` | `{"is_available": false}` |
| DELETE | `.../menu-items/{menuItem}` | soft delete, `204`; snapshots in order items survive |

```json
{ "category_id": 3, "name": "Ember Burger", "description": "…",
  "price": 17.5, "image_url": null, "is_available": true, "is_featured": false }
```

`category_id` must belong to the same restaurant (`422` otherwise).

**Dining tables** (restaurant admin)

`GET|POST .../tables`, `PATCH|DELETE .../tables/{table}` — `label` (unique per restaurant),
`capacity`, `min_capacity` (≤ capacity), `pos_x`, `pos_y`, `shape`, `is_active`. The list is
returned unpaginated so the floor-plan editor can render it whole. `DELETE` returns `204` for a
table without reservations, and `200` with the deactivated table when reservations exist.

**Orders** (member)

| Method | Path | Notes |
|---|---|---|
| GET | `.../orders` | queue filters |
| GET | `.../orders/{order}` | full detail |
| PATCH | `.../orders/{order}/status` | `{"status":"PREPARING","reason":"…"}` |
| GET | `.../orders/{order}/payments` | payment list |
| POST | `.../orders/{order}/payments` | see §5.6 |

**Reservations** (member)

`GET .../reservations`, `GET .../reservations/{reservation}`,
`PATCH .../reservations/{reservation}/status` with `{"status":"SEATED"}`. Marking `NO_SHOW`
increments `users.no_show_count` for the customer.

**Deliveries** (member)

| Method | Path | Notes |
|---|---|---|
| GET | `.../deliveries` | `delivery_status`, `active`, `driver_id` |
| GET | `.../deliveries/{delivery}` | |
| PATCH | `.../deliveries/{delivery}` | `driver_id`, `delivery_status`, `failure_reason` |

The driver must be a member of the restaurant (or a platform administrator) — `422` otherwise.
`failure_reason` is required when moving to `FAILED`. Delivery transitions drive the order
status through the same transition engine, so history rows are written.

**KPIs** (restaurant admin)

`GET .../kpis?from=2026-09-19&to=2026-10-18` (defaults: last 30 days).

```json
{ "data": {
    "range": { "from": "2026-09-19", "to": "2026-10-18" },
    "revenue": 1840.5, "revenue_today": 210.0, "orders_today": 6,
    "order_count": 52, "completed_order_count": 41,
    "average_order_value": 44.89, "pending_orders": 4,
    "pickup_orders": 30, "delivery_orders": 22,
    "reservation_count": 18, "completed_reservations": 12,
    "no_show_reservations": 2, "no_show_rate": 11.11,
    "active_deliveries": 3,
    "revenue_series": [ { "date": "2026-09-19", "revenue": 0 } ],
    "order_series":   [ { "date": "2026-09-19", "count": 0 } ],
    "recent_orders":  [ { "id": 52, "order_number": "…", "status": "PLACED",
                          "order_type": "PICKUP", "payment_status": "PAID",
                          "total": "24.00", "created_at": "…" } ]
  } }
```

Revenue = `SUM(total)` where `payment_status = PAID` and the status is not
`CANCELLED`/`REJECTED`/`REFUNDED`. Unpaid and cash-on-delivery orders are not revenue.
Average order value = revenue ÷ paid order count. Series always cover every day in the range,
including zero days.

### 5.6 Payments

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/restaurants/{restaurant}/orders/{order}/payments` | member |
| POST | `/admin/restaurants/{restaurant}/orders/{order}/payments` | member |
| POST | `/admin/payments/{payment}/refund` | restaurant admin or platform admin |

```http
POST /api/v1/admin/restaurants/1/orders/12/payments
{ "method": "CASH", "amount": 38.50, "status": "PAID", "provider_ref": null }
```

`status` may only be `PAID` or `FAILED` — recording a *failed* attempt is supported and does not
mark the order paid. The amount is capped at the order's outstanding balance
(`total − Σ PAID payments`) → `422` otherwise; the order's `payment_status` is then recomputed
from its payment rows. Terminal orders reject new payments.

Refunds flip a `PAID` payment to `REFUNDED` and recompute the order status; a non-captured payment
returns `422`.

**No client can mark an order paid directly.** There is no gateway integration, so `PAID` is only
reachable through this authenticated staff endpoint. When a real provider is added, its signed
webhook result (not client input) must drive the payment status.

---

## 6. Authorization rules

| Actor | Allowed |
|---|---|
| Guest | Public catalogue, availability, register/login |
| Customer | Own profile, orders and reservations; place orders; cancel own `PLACED` order; cancel own reservation before the 2-hour cutoff |
| Staff (`restaurant_members.role = STAFF`) | Their restaurant only: settings read, order queue and forward transitions, reservation status changes, delivery updates, recording payments |
| Restaurant admin (`restaurant_members.role = ADMIN`) | Staff abilities plus restaurant settings, members, categories/menu/tables, administrator-only order transitions (`CANCELLED` after `PREPARING`, `COMPLETED → REFUNDED`), refunds, KPIs |
| Platform admin (`users.role = ADMIN`/`SUPER_ADMIN`) | Everything, including any restaurant; restaurant creation and activation |

Enforcement is server-side only: `restaurant.member` middleware for the tenant boundary, then
Laravel policies (`RestaurantPolicy`, `OrderPolicy`, `ReservationPolicy`, `DeliveryDetailPolicy`,
`PaymentPolicy`) for the action. Frontend route guards are a convenience, never the control.

Note the two meanings of "admin": `users.role` is a **platform** role, while
`restaurant_members.role` is the **per-restaurant** role. A user with `users.role = ADMIN` but no
membership row bypasses membership checks; a restaurant manager normally has
`users.role = CUSTOMER` (or `STAFF`) plus an `ADMIN` membership.

---

## 7. Rate limiting

| Endpoint | Limit |
|---|---|
| `POST /auth/register` | 6/min per IP |
| `POST /auth/login` | 10/min per IP, plus 5 failed attempts/min per email+IP |
| `POST /orders` | 20/min per user |
| `POST /reservations` | 10/min per user |

Exceeding a limit returns `429` with `Retry-After`.

---

## 8. Business rules

1. **Tenant scoping** — every business row carries `restaurant_id`; admin routes nest under the
   restaurant and child IDs are re-checked (`404` when foreign).
2. **Price snapshots** — `order_items` stores `item_name_snapshot`, `unit_price`, `quantity`,
   `line_total`; later menu edits never rewrite history.
3. **Server totals** — see §5.3; no client-supplied amount is trusted.
4. **Transactions** — checkout, status transitions, seat booking and payments run inside
   `DB::transaction`.
5. **Overlap prevention** — row-lock on the dining table, then the interval test; one table can
   never hold overlapping live reservations.
6. **Status validation** — explicit transition maps; illegal moves are `422`.
7. **History** — every order/reservation status change inserts a history row with actor and time.
8. **Cancellation rules** — customers lose cancel rights once work has started (order past
   `PLACED`) or inside the 2-hour reservation window.
9. **Soft deletes** — menu items are soft-deleted so order history stays readable.
10. **Secrets** — `User` is `#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]`
    and resources never expose them.
11. **Eager loading** — list endpoints constrain relations and paginate; detail endpoints load
    exactly what the resource renders.
12. **Date validation** — reservations respect opening hours, lead time and inactive restaurants;
    the public API never exposes inactive restaurants.
13. **Capacity** — party size must satisfy `min_capacity ≤ party_size ≤ capacity`.
14. **Duplicate submissions** — `Idempotency-Key` (24 h, per user) plus unique order numbers with
    in-transaction retry.
15. **Payment truth** — payment status is derived from payment rows, never from client input.

---

## 9. Environment

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SESSION_DRIVER=database
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000,127.0.0.1:8000
DB_CONNECTION=mysql
DB_DATABASE=restaurant_management
```

`SANCTUM_STATEFUL_DOMAINS` must list the SPA host:port exactly, or cookie auth is silently
treated as a token request and every call returns `401`. In production set
`SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=none` only if the SPA is on a different
registrable domain (with `Secure` over HTTPS).

---

## 10. Development seed accounts

`php artisan db:seed --class=DemoDataSeeder` is idempotent and skips production via
`DatabaseSeeder`. All accounts use the fake password **`password`**.

| Email | Role | Access |
|---|---|---|
| `super.admin@example.test` | SUPER_ADMIN | Everything |
| `admin@example.test` | ADMIN | Platform admin across restaurants |
| `ember.manager@example.test` | CUSTOMER + ADMIN membership @ Ember & Oak | Restaurant admin |
| `ember.cashier@example.test` | STAFF + STAFF membership | Cashier |
| `ember.kitchen@example.test` | STAFF + STAFF membership | Kitchen |
| `ember.driver@example.test` | STAFF + STAFF membership (DRIVER) | Delivery |
| `saffron.manager@example.test` | CUSTOMER + ADMIN membership @ Saffron House | Restaurant admin |
| `alice@example.test` | CUSTOMER (1 no-show) | Customer |
| `bob@example.test` | CUSTOMER | Customer |

Seeded data: 2 restaurants, 6 categories, 12 menu items, 7 tables, 3 reservations,
3 orders (pickup + delivery, completed and in-flight), payments, and 15 history rows.

---

## 11. Frontend integration notes (Vue 3 + Vite)

```ts
// src/lib/api.ts
import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL, // http://localhost:8000/api/v1
  withCredentials: true,
  headers: { Accept: 'application/json' },
});

// Call once before the first mutating request (and after each login).
export const csrf = () =>
  axios.get(`${import.meta.env.VITE_BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  });

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // redirect to /login — do not retry blindly
    }
    return Promise.reject(error);
  },
);
```

- `VITE_API_URL=http://localhost:8000/api/v1`, `VITE_BACKEND_URL=http://localhost:8000`.
- Use the `419`/`XSRF-TOKEN` pair: after any login/logout, refresh the CSRF cookie.
- Map `422.errors` directly onto form fields; `meta` drives pagination controls.
- Poll or re-fetch the order queue / KPI dashboard instead of caching statuses locally.
