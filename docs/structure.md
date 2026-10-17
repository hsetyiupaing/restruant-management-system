# Restaurant Management System: Complete Design Specification

**Assumption:** "Multiple customers" is treated as **multi-tenant**. One platform serves many restaurants, and every business table carries `restaurant_id`. Each restaurant has its own menu, tables, staff, and orders. If you meant a single restaurant with many diners, drop `restaurants` and `restaurant_id`; nothing else changes.

## Contents

1. [User Roles & Permissions](#1-user-roles--permissions)
2. [Status Definitions](#2-status-definitions)
3. [Business Rules](#3-business-rules)
4. [Feature List](#4-feature-list)
5. [ERD](#5-erd)
6. [Sequence Diagrams](#6-sequence-diagrams)
7. [Build Order](#7-suggested-build-order)
8. [Appendix: Activity Flowcharts](#appendix-activity-flowcharts)

---

## 1. User Roles & Permissions

| Role | Who | Scope |
|---|---|---|
| **Guest** | Not logged in | Public |
| **Customer** | Registered diner | Own data only |
| **Staff** | Cashier, kitchen, waiter, driver | One restaurant, operational |
| **Admin** | Owner or manager | One restaurant, full control |
| **Super Admin** | Platform operator | All restaurants (optional) |

### Permission matrix

| Capability | Guest | Customer | Staff | Admin |
|---|:-:|:-:|:-:|:-:|
| View home, menu, table availability | ✅ | ✅ | ✅ | ✅ |
| Place order (pickup/delivery) | ❌ | ✅ | ✅ (on behalf, walk-in) | ✅ |
| Make or cancel own reservation | ❌ | ✅ | ✅ (any) | ✅ (any) |
| View own orders/reservations | ❌ | ✅ | ❌ | ❌ |
| View all orders/reservations | ❌ | ❌ | ✅ | ✅ |
| Update order status | ❌ | ❌ | ✅ (forward only) | ✅ (any, incl. cancel/refund) |
| Check in / seat / no-show reservations | ❌ | ❌ | ✅ | ✅ |
| Assign delivery driver | ❌ | ❌ | ✅ | ✅ |
| Edit menu, prices, availability | ❌ | ❌ | ✅ (toggle sold-out only) | ✅ (full) |
| Edit table layout | ❌ | ❌ | ❌ | ✅ |
| View KPIs and revenue | ❌ | ❌ | ❌ | ✅ |
| Manage staff accounts and settings | ❌ | ❌ | ❌ | ✅ |

**Staff sub-roles** (stored in `restaurant_members.staff_type`): `CASHIER`, `KITCHEN`, `WAITER`, `DRIVER`. Kitchen sees only orders in `CONFIRMED → READY`. Drivers see only their deliveries.

---

## 2. Status Definitions

### 2.1 Order status

| Status | Meaning | Set by | Next |
|---|---|---|---|
| `PLACED` | Customer submitted, awaiting restaurant | Customer | CONFIRMED, REJECTED, CANCELLED |
| `CONFIRMED` | Restaurant accepted, payment OK or COD | Staff/Admin | PREPARING, CANCELLED |
| `PREPARING` | Kitchen is cooking | Kitchen | READY |
| `READY` | Food done; waiting for pickup or driver | Kitchen | COMPLETED (pickup), OUT_FOR_DELIVERY (delivery) |
| `OUT_FOR_DELIVERY` | Driver has the order | Driver/Staff | COMPLETED, DELIVERY_FAILED |
| `COMPLETED` | Handed over / delivered | Staff/Driver | REFUNDED (Admin) |
| `CANCELLED` | Cancelled by customer or restaurant | Customer (only while PLACED) / Admin | terminal |
| `REJECTED` | Restaurant declined (closed, out of stock) | Staff/Admin | terminal |
| `DELIVERY_FAILED` | Couldn't deliver | Driver/Admin | OUT_FOR_DELIVERY (retry), CANCELLED |
| `REFUNDED` | Money returned after completion | Admin | terminal |

```mermaid
stateDiagram-v2
    [*] --> PLACED
    PLACED --> CONFIRMED: Staff accepts
    PLACED --> REJECTED: Staff declines
    PLACED --> CANCELLED: Customer cancels
    CONFIRMED --> PREPARING: Kitchen starts
    CONFIRMED --> CANCELLED: Admin cancels
    PREPARING --> READY: Food done
    READY --> COMPLETED: Pickup handed over
    READY --> OUT_FOR_DELIVERY: Driver picks up
    OUT_FOR_DELIVERY --> COMPLETED: Delivered
    OUT_FOR_DELIVERY --> DELIVERY_FAILED: Failed
    DELIVERY_FAILED --> OUT_FOR_DELIVERY: Retry
    DELIVERY_FAILED --> CANCELLED: Give up
    COMPLETED --> REFUNDED: Admin refund
    REJECTED --> [*]
    CANCELLED --> [*]
    REFUNDED --> [*]
    COMPLETED --> [*]
```

### 2.2 Payment status (separate from order status)

| Status | Meaning |
|---|---|
| `UNPAID` | Not yet paid (COD or awaiting online payment) |
| `PAID` | Payment captured |
| `FAILED` | Online payment failed |
| `REFUNDED` | Fully or partially refunded |

### 2.3 Reservation status

| Status | Meaning | Set by |
|---|---|---|
| `PENDING` | Requested, awaiting approval (only if the restaurant uses manual approval; the slot is held) | Customer |
| `CONFIRMED` | Table locked for the time slot | System/Staff |
| `SEATED` | Customer arrived and was seated | Staff |
| `COMPLETED` | Dining finished, table released | Staff |
| `CANCELLED` | Cancelled by customer or restaurant | Customer/Staff |
| `NO_SHOW` | Didn't arrive within the grace period | Staff/System |
| `EXPIRED` | Pending request not approved within the hold time | System |

```mermaid
stateDiagram-v2
    [*] --> PENDING: Manual-approval mode
    [*] --> CONFIRMED: Auto-confirm mode
    PENDING --> CONFIRMED: Staff approves
    PENDING --> CANCELLED: Staff rejects / customer cancels
    PENDING --> EXPIRED: Hold timeout
    CONFIRMED --> SEATED: Check-in
    CONFIRMED --> CANCELLED: Cancel (before cutoff)
    CONFIRMED --> NO_SHOW: Grace period passed
    SEATED --> COMPLETED: Table cleared
    COMPLETED --> [*]
    CANCELLED --> [*]
    NO_SHOW --> [*]
    EXPIRED --> [*]
```

### 2.4 Table status (live, derived for the visual map)

| Status | Meaning |
|---|---|
| `AVAILABLE` | Free for the selected slot |
| `RESERVED` | Has a CONFIRMED/PENDING reservation in the slot |
| `OCCUPIED` | Currently SEATED |
| `UNAVAILABLE` | Disabled by admin (maintenance, `is_active = false`) |

### 2.5 Delivery status

`UNASSIGNED → ASSIGNED → PICKED_UP → DELIVERED` (or `FAILED`)

---

## 3. Business Rules

### Ordering

1. Order type is `PICKUP` or `DELIVERY`. Delivery requires an address inside the restaurant's delivery zone and a minimum order amount.
2. Prices are **snapshotted** into `order_items.unit_price`, so later menu edits never change old orders.
3. Sold-out (`is_available = false`) items can't be added to the cart.
4. The customer can cancel only while `PLACED`. After that, only staff/admin can cancel.
5. Staff can only move status **forward**; only Admin can go backward, cancel after `PREPARING`, or refund.
6. Every status change writes a row to `order_status_history`.
7. `total = subtotal + delivery_fee + tax − discount`.

### Reservations

1. **Slot length:** 90 minutes (configurable per restaurant).
2. **Lead time:** at least 1 hour ahead; at most 30 days ahead.
3. **Capacity:** `party_size ≤ table.capacity` and `≥ table.min_capacity`.
4. **No double booking:** for the same table, active reservations (`PENDING`, `CONFIRMED`, `SEATED`) must not overlap. The test is `new.start < existing.end AND new.end > existing.start`.
5. **Hold:** a `PENDING` reservation holds the table for 15 minutes and then becomes `EXPIRED`.
6. **Customer cancel cutoff:** up to 2 hours before the start; later, call the restaurant.
7. **Grace period:** 15 minutes after the start time with no check-in, then `NO_SHOW`. After 3 no-shows, the customer is flagged and the restaurant may require approval for their bookings.
8. **Concurrency (multiple customers booking at once):** the insert runs in a transaction with a row lock (or an exclusion constraint on `(table_id, tsrange)` in PostgreSQL), so two customers can't take the same slot.

---

## 4. Feature List

### 4.1 Customer website

| # | Feature | Notes |
|---|---|---|
| C1 | Home page | Info, hours, address, featured dishes (`menu_items.is_featured`) |
| C2 | Register / login | Email or phone |
| C3 | Menu browsing | Categories, search, sold-out badges |
| C4 | Cart & checkout | Notes, item options |
| C5 | Pickup or delivery selection | Pickup time slot or delivery address + fee |
| C6 | Table reservation with visual table map | Pick date/time/party size → map shows table status → select table |
| C7 | My Orders | Status timeline, reorder, cancel (while PLACED) |
| C8 | My Reservations | Status, cancel, modify |
| C9 | Profile & saved addresses | |

### 4.2 Admin dashboard

| # | Feature | Notes |
|---|---|---|
| A1 | KPI dashboard | Revenue, order count, average order value, reservation count, no-show rate, top dishes, date-range filter |
| A2 | Order management | Live queue, filter by status/type, update status, cancel/refund |
| A3 | Menu & price management | CRUD categories/items, price, image, availability toggle |
| A4 | Table layout editor | Drag tables on a floor plan, set capacity, enable/disable |
| A5 | Reservation management | Calendar/list, approve, seat, mark no-show |
| A6 | Pickup & delivery management | Pickup board, delivery list, driver assignment, delivery fee/zone settings |
| A7 | Staff management | Invite staff, assign sub-role |
| A8 | Restaurant settings | Hours, slot length, auto-confirm toggle, tax, fees |

**KPI definitions**

- **Revenue** = sum of `total` where `payment_status = PAID` and status is not CANCELLED/REJECTED/REFUNDED.
- **Orders** = count of orders with status `COMPLETED`.
- **Average order value** = Revenue ÷ completed orders.
- **Reservation KPIs** = total bookings, completion rate (`COMPLETED / total`), no-show rate (`NO_SHOW / total`), table utilization.

---

## 5. ERD

```mermaid
erDiagram
    RESTAURANTS ||--o{ RESTAURANT_MEMBERS : employs
    USERS ||--o{ RESTAURANT_MEMBERS : "works as"
    RESTAURANTS ||--o{ CATEGORIES : has
    CATEGORIES ||--o{ MENU_ITEMS : contains
    RESTAURANTS ||--o{ DINING_TABLES : has
    RESTAURANTS ||--o{ ORDERS : receives
    RESTAURANTS ||--o{ RESERVATIONS : receives
    USERS ||--o{ ORDERS : places
    USERS ||--o{ RESERVATIONS : books
    USERS ||--o{ CUSTOMER_ADDRESSES : saves
    ORDERS ||--|{ ORDER_ITEMS : contains
    MENU_ITEMS ||--o{ ORDER_ITEMS : "ordered as"
    ORDERS ||--o| DELIVERY_DETAILS : "has (if delivery)"
    ORDERS ||--o{ PAYMENTS : "paid by"
    ORDERS ||--o{ ORDER_STATUS_HISTORY : logs
    DINING_TABLES ||--o{ RESERVATIONS : "assigned to"
    RESERVATIONS ||--o{ RESERVATION_STATUS_HISTORY : logs
    RESERVATIONS |o--o{ ORDERS : "dine-in link (optional)"
    USERS ||--o{ DELIVERY_DETAILS : "drives (driver_id)"

    RESTAURANTS {
        bigint id PK
        string name
        string slug UK
        string phone
        string address
        json opening_hours
        int slot_minutes
        boolean auto_confirm_reservations
        decimal delivery_fee
        decimal min_delivery_amount
        decimal tax_rate
        boolean is_active
    }
    USERS {
        bigint id PK
        string email UK
        string phone UK
        string password_hash
        string full_name
        enum global_role "CUSTOMER, STAFF, ADMIN, SUPER_ADMIN"
        int no_show_count
        boolean is_active
        datetime created_at
    }
    RESTAURANT_MEMBERS {
        bigint id PK
        bigint restaurant_id FK
        bigint user_id FK
        enum role "STAFF, ADMIN"
        enum staff_type "CASHIER, KITCHEN, WAITER, DRIVER"
    }
    CUSTOMER_ADDRESSES {
        bigint id PK
        bigint user_id FK
        string label
        string address_line
        string city
        string postal_code
        decimal latitude
        decimal longitude
        boolean is_default
    }
    CATEGORIES {
        bigint id PK
        bigint restaurant_id FK
        string name
        int sort_order
    }
    MENU_ITEMS {
        bigint id PK
        bigint restaurant_id FK
        bigint category_id FK
        string name
        text description
        decimal price
        string image_url
        boolean is_available
        boolean is_featured
        datetime deleted_at
    }
    DINING_TABLES {
        bigint id PK
        bigint restaurant_id FK
        string label
        int capacity
        int min_capacity
        int pos_x
        int pos_y
        enum shape "ROUND, SQUARE, RECT"
        boolean is_active
    }
    ORDERS {
        bigint id PK
        bigint restaurant_id FK
        bigint customer_id FK
        bigint reservation_id FK "nullable"
        string order_number UK
        enum order_type "PICKUP, DELIVERY"
        enum status
        enum payment_status
        decimal subtotal
        decimal delivery_fee
        decimal tax
        decimal discount
        decimal total
        datetime scheduled_for
        text customer_note
        datetime created_at
    }
    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint menu_item_id FK
        string item_name_snapshot
        decimal unit_price
        int quantity
        decimal line_total
        text note
    }
    DELIVERY_DETAILS {
        bigint id PK
        bigint order_id FK "UK"
        bigint driver_id FK "nullable"
        string recipient_name
        string recipient_phone
        string address_line
        decimal latitude
        decimal longitude
        enum delivery_status
        datetime assigned_at
        datetime picked_up_at
        datetime delivered_at
        string failure_reason
    }
    PAYMENTS {
        bigint id PK
        bigint order_id FK
        enum method "CASH, CARD, ONLINE"
        decimal amount
        enum status
        string provider_ref
        datetime paid_at
    }
    RESERVATIONS {
        bigint id PK
        bigint restaurant_id FK
        bigint customer_id FK
        bigint table_id FK
        int party_size
        datetime start_time
        datetime end_time
        enum status
        text special_request
        datetime hold_expires_at
        datetime created_at
    }
    ORDER_STATUS_HISTORY {
        bigint id PK
        bigint order_id FK
        string from_status
        string to_status
        bigint changed_by FK
        datetime changed_at
        string reason
    }
    RESERVATION_STATUS_HISTORY {
        bigint id PK
        bigint reservation_id FK
        string from_status
        string to_status
        bigint changed_by FK
        datetime changed_at
    }
```

### Key relationship decisions

- **One user table, many roles.** A person is a customer everywhere; staff and admin access comes from `restaurant_members`, so one account can work at two restaurants.
- **`order_items` snapshots name and price**, so history stays accurate after menu edits. Menu items are soft-deleted (`deleted_at`).
- **`delivery_details` is 1:0..1 with orders.** The row exists only for `DELIVERY` orders, so pickup orders stay clean.
- **`payments` is separate from orders**, which allows partial payments, retries, and refunds.
- **Indexes to add:** `orders(restaurant_id, status, created_at)`, `reservations(table_id, start_time, end_time)`, `menu_items(restaurant_id, category_id)`.

---

## 6. Sequence Diagrams

Participant icons used in every diagram:

| Icon type | Used for |
|---|---|
| `actor` | People: Customer, Staff, Kitchen, Driver, Admin |
| `boundary` | Screens: Customer Website, Admin Dashboard |
| `control` | Business logic: Order Service, Reservation Service |
| `entity` | Domain objects: Order, Reservation |
| `database` | Persistent storage |
| `collections` | Lists of records: Menu Items, Tables |
| `queue` | Async notifications |

> Requires Mermaid 11.7 or newer for the participant icon syntax (`@{ "type": "..." }`).

### 6.1 Customer places an order (pickup or delivery)

```mermaid
sequenceDiagram
    participant C@{ "type": "actor" } as Customer
    participant UI@{ "type": "boundary" } as Customer Website
    participant OS@{ "type": "control" } as Order Service
    participant MI@{ "type": "collections" } as Menu Items
    participant O@{ "type": "entity" } as Order
    participant DB@{ "type": "database" } as Database
    participant Q@{ "type": "queue" } as Notification Queue
    participant S@{ "type": "actor" } as Staff

    C->>UI: Browse menu
    UI->>MI: Load items by category
    MI-->>UI: Items with availability
    C->>UI: Add items to cart
    C->>UI: Checkout and choose PICKUP or DELIVERY

    alt DELIVERY
        UI->>OS: Validate address, zone, minimum amount
        OS-->>UI: Delivery fee
    else PICKUP
        UI-->>C: Choose pickup time slot
    end

    C->>UI: Choose payment method and submit
    UI->>OS: createOrder(cart, type, payment)
    OS->>MI: Check availability
    MI-->>OS: Availability result

    alt Item sold out
        OS-->>UI: Error: item unavailable
        UI-->>C: Update cart
    else All available
        OS->>O: Create order with status PLACED
        Note over O: Prices snapshotted into order_items
        O->>DB: INSERT orders, order_items, delivery_details
        DB-->>O: Saved
        OS->>Q: Publish ORDER_PLACED
        Q-->>S: New order alert
        OS-->>UI: order_number
        UI-->>C: Order confirmation
    end

    S->>OS: Review order
    alt Staff declines
        OS->>O: status = REJECTED
        O->>DB: UPDATE status, INSERT order_status_history
        OS->>Q: Publish ORDER_REJECTED
        Q-->>C: Notify customer
    else Staff accepts
        OS->>O: status = CONFIRMED
        O->>DB: UPDATE status, INSERT order_status_history
        OS->>Q: Publish ORDER_CONFIRMED
        Q-->>C: Notify customer
    end

    opt Customer cancels while PLACED
        C->>UI: Cancel order
        UI->>OS: cancelOrder(orderId)
        OS->>O: status = CANCELLED
        O->>DB: UPDATE status, INSERT order_status_history
    end
```

### 6.2 Order fulfilment (kitchen, pickup, delivery)

```mermaid
sequenceDiagram
    participant K@{ "type": "actor" } as Kitchen
    participant S@{ "type": "actor" } as Staff
    participant D@{ "type": "actor" } as Driver
    participant C@{ "type": "actor" } as Customer
    participant AD@{ "type": "boundary" } as Admin Dashboard
    participant OS@{ "type": "control" } as Order Service
    participant O@{ "type": "entity" } as Order
    participant DD@{ "type": "entity" } as Delivery Details
    participant DB@{ "type": "database" } as Database
    participant Q@{ "type": "queue" } as Notification Queue

    K->>AD: Start cooking
    AD->>OS: updateStatus(orderId, PREPARING)
    OS->>O: status = PREPARING
    O->>DB: UPDATE status, INSERT order_status_history

    K->>AD: Food is done
    AD->>OS: updateStatus(orderId, READY)
    OS->>O: status = READY
    O->>DB: UPDATE status, INSERT order_status_history
    OS->>Q: Publish ORDER_READY
    Q-->>C: Notify customer

    alt PICKUP
        C->>S: Arrives to collect
        S->>AD: Hand over and mark completed
        AD->>OS: updateStatus(orderId, COMPLETED)
        OS->>O: status = COMPLETED
        O->>DB: UPDATE status, payment_status = PAID
    else DELIVERY
        S->>AD: Assign driver
        AD->>OS: assignDriver(orderId, driverId)
        OS->>DD: delivery_status = ASSIGNED
        DD->>DB: UPDATE delivery_details
        D->>AD: Picked up order
        AD->>OS: updateStatus(orderId, OUT_FOR_DELIVERY)
        OS->>Q: Publish ORDER_OUT_FOR_DELIVERY
        Q-->>C: Driver is on the way

        alt Delivered
            D->>AD: Mark delivered
            AD->>OS: updateStatus(orderId, COMPLETED)
            OS->>DD: delivery_status = DELIVERED
            OS->>O: status = COMPLETED
            O->>DB: UPDATE status, payment_status = PAID
        else Delivery fails
            D->>AD: Mark failed with reason
            AD->>OS: updateStatus(orderId, DELIVERY_FAILED)
            OS->>DD: delivery_status = FAILED
            DD->>DB: UPDATE failure_reason
            S->>AD: Retry or cancel
        end
    end
```

### 6.3 Customer reserves a table (visual map)

```mermaid
sequenceDiagram
    participant C@{ "type": "actor" } as Customer
    participant UI@{ "type": "boundary" } as Reservation Page
    participant RS@{ "type": "control" } as Reservation Service
    participant T@{ "type": "collections" } as Dining Tables
    participant R@{ "type": "entity" } as Reservation
    participant DB@{ "type": "database" } as Database
    participant Q@{ "type": "queue" } as Notification Queue
    participant S@{ "type": "actor" } as Staff

    C->>UI: Select date, time, party size
    UI->>RS: getTableMap(date, time, partySize)
    RS->>T: Load active tables
    RS->>DB: Find overlapping reservations for the 90-min slot
    DB-->>RS: Booked table ids
    RS-->>UI: Tables with status AVAILABLE, RESERVED, OCCUPIED, UNAVAILABLE
    UI-->>C: Show colour-coded table map

    C->>UI: Click an available table
    UI->>UI: Check party size against capacity
    C->>UI: Add special request and confirm
    UI->>RS: createReservation(tableId, start, partySize)

    RS->>DB: BEGIN transaction, lock table row
    RS->>DB: Check overlap: new.start < existing.end AND new.end > existing.start

    alt Slot already taken
        DB-->>RS: Conflict
        RS->>DB: ROLLBACK
        RS-->>UI: Error: just taken
        UI-->>C: Pick another table
    else Slot free
        alt Auto-confirm enabled
            RS->>R: Create with status CONFIRMED
            R->>DB: INSERT reservation, COMMIT
            RS->>Q: Publish RESERVATION_CONFIRMED
            Q-->>C: Confirmation message
        else Manual approval
            RS->>R: Create with status PENDING, hold 15 min
            R->>DB: INSERT reservation, COMMIT
            RS->>Q: Publish RESERVATION_PENDING
            Q-->>S: Approval request

            alt Staff approves in time
                S->>RS: approve(reservationId)
                RS->>R: status = CONFIRMED
                RS->>Q: Publish RESERVATION_CONFIRMED
                Q-->>C: Confirmation message
            else Staff rejects
                S->>RS: reject(reservationId)
                RS->>R: status = CANCELLED
                Q-->>C: Rejection notice
            else Hold expires
                RS->>R: status = EXPIRED
                Q-->>C: Request expired
            end
        end
    end

    opt Customer cancels (at least 2 hours before)
        C->>UI: Cancel reservation
        UI->>RS: cancel(reservationId)
        RS->>R: status = CANCELLED
        R->>DB: UPDATE, INSERT reservation_status_history
    end

    Note over C,S: Day of the booking
    alt Customer arrives
        S->>RS: checkIn(reservationId)
        RS->>R: status = SEATED
        S->>RS: tableCleared(reservationId)
        RS->>R: status = COMPLETED
    else No arrival after 15 min grace
        RS->>R: status = NO_SHOW
        R->>DB: UPDATE, increment users.no_show_count
    end
```

### 6.4 Admin daily operations

```mermaid
sequenceDiagram
    participant A@{ "type": "actor" } as Admin
    participant AD@{ "type": "boundary" } as Admin Dashboard
    participant KS@{ "type": "control" } as KPI Service
    participant OS@{ "type": "control" } as Order Service
    participant MS@{ "type": "control" } as Menu Service
    participant TS@{ "type": "control" } as Table Service
    participant MI@{ "type": "collections" } as Menu Items
    participant T@{ "type": "collections" } as Dining Tables
    participant DB@{ "type": "database" } as Database
    participant Q@{ "type": "queue" } as Notification Queue

    A->>AD: Log in
    AD->>KS: getKpis(dateRange)
    KS->>DB: Aggregate orders, payments, reservations
    DB-->>KS: Revenue, order count, reservation stats
    KS-->>AD: Revenue, orders, average order value, no-show rate
    AD-->>A: Show KPI dashboard

    rect rgb(235, 245, 255)
        Note over A,Q: Order management
        A->>AD: Open live order queue
        AD->>OS: listOrders(status, type)
        OS->>DB: SELECT orders
        DB-->>AD: Orders
        alt Advance status
            A->>AD: Update status
            AD->>OS: updateStatus(orderId, nextStatus)
            OS->>DB: UPDATE order, INSERT order_status_history
            OS->>Q: Notify customer
        else Cancel or refund
            A->>AD: Cancel or refund with reason
            AD->>OS: cancelOrRefund(orderId, reason)
            OS->>DB: UPDATE order, payment = REFUNDED
            OS->>Q: Notify customer
        end
    end

    rect rgb(240, 255, 240)
        Note over A,Q: Menu and price management
        A->>AD: Edit item, price, or availability
        AD->>MS: updateItem(itemId, changes)
        MS->>MI: Apply changes
        MI->>DB: UPDATE menu_items
        Note over MI,DB: Applies to new orders only, old orders keep snapshot prices
        A->>AD: Delete item
        AD->>MS: softDelete(itemId)
        MS->>DB: SET deleted_at
    end

    rect rgb(255, 248, 235)
        Note over A,Q: Table layout and reservations
        A->>AD: Edit table layout
        AD->>TS: saveLayout(tables)
        TS->>DB: Check future reservations on changed tables
        alt Future bookings affected
            DB-->>TS: Conflicting reservations
            TS-->>AD: Warning: reassign or cancel first
            AD-->>A: Show affected bookings
        else No conflicts
            TS->>T: Add, move, or disable tables
            T->>DB: UPDATE dining_tables
            TS-->>AD: Layout saved
        end
    end

    rect rgb(250, 240, 255)
        Note over A,Q: Pickup and delivery settings
        A->>AD: Set delivery fee, zone, minimum amount
        AD->>OS: saveDeliverySettings(settings)
        OS->>DB: UPDATE restaurants
        DB-->>AD: Saved
    end
```

---

## 7. Suggested Build Order

1. Database migrations plus auth and role middleware (every query scoped by `restaurant_id`)
2. Menu CRUD, then customer menu and cart
3. Order creation and status engine (enforce the transition table in one service)
4. Admin order queue and delivery details
5. Table layout editor, then the reservation engine (overlap check and transaction)
6. Visual table map, then KPI dashboard

**Open questions to confirm before building:**

- Do you need online payments at launch or cash only?
- Do you want multi-restaurant (multi-tenant) or one restaurant?
- Should a reservation allow combining several tables for large parties? If so, replace `reservations.table_id` with a `reservation_tables` join table.

---

## Appendix: Activity Flowcharts

Swimlane-style activity views of the same flows, for readers who prefer a decision-flow format over sequence diagrams.

### A.1 Customer: order food (pickup or delivery)

```mermaid
flowchart TD
    subgraph Customer
        A([Open website]) --> B[Browse menu]
        B --> C[Add items to cart]
        C --> D{Logged in?}
        D -- No --> E[Login / Register] --> F
        D -- Yes --> F[Checkout]
        F --> G{Order type}
        G -- Pickup --> H[Choose pickup time]
        G -- Delivery --> I[Select or enter address]
        I --> J{In delivery zone and<br/>above minimum?}
        J -- No --> I
        J -- Yes --> K[See delivery fee]
        H --> L[Choose payment method]
        K --> L
        L --> M[Submit order]
    end

    subgraph System
        M --> N[Validate items and availability]
        N --> O{All available?}
        O -- No --> C
        O -- Yes --> P[Create order: PLACED<br/>snapshot prices]
    end

    subgraph Restaurant
        P --> Q{Staff decision}
        Q -- Decline --> R[REJECTED + notify customer]
        Q -- Accept --> S[CONFIRMED]
        S --> T[PREPARING]
        T --> U[READY]
        U --> V{Type}
        V -- Pickup --> W[Customer collects]
        V -- Delivery --> X[Assign driver]
        X --> Y[OUT_FOR_DELIVERY]
        Y --> Z{Delivered?}
        Z -- Yes --> W
        Z -- No --> FAIL[DELIVERY_FAILED]
        W --> DONE[COMPLETED]
    end

    DONE --> END([Customer sees status in My Orders])
    R --> END
```

### A.2 Customer: reserve a table (visual map)

```mermaid
flowchart TD
    A([Open Reservation page]) --> B[Select date, time, party size]
    B --> C[System loads table map<br/>for 90-min slot]
    C --> D[Tables coloured:<br/>Available / Reserved / Occupied / Unavailable]
    D --> E[Customer clicks an available table]
    E --> F{Party size fits<br/>table capacity?}
    F -- No --> D
    F -- Yes --> G[Enter special requests, confirm]
    G --> H[[Transaction: lock table + check overlap]]
    H --> I{Slot still free?}
    I -- No --> J[Show: just taken, pick another] --> C
    I -- Yes --> K{Auto-confirm<br/>enabled?}
    K -- Yes --> L[Status: CONFIRMED]
    K -- No --> M[Status: PENDING<br/>hold 15 min]
    M --> N{Staff responds<br/>in time?}
    N -- Approve --> L
    N -- Reject --> X[CANCELLED + notify]
    N -- Timeout --> Y[EXPIRED + notify]
    L --> O[Confirmation sent to customer]
    O --> P{Customer arrives?}
    P -- Cancels before cutoff --> X
    P -- Yes, checks in --> Q[SEATED] --> R[COMPLETED]
    P -- No, 15 min passed --> S[NO_SHOW<br/>increment no_show_count]
```

### A.3 Admin: daily operations

```mermaid
flowchart TD
    A([Admin logs in]) --> B[Dashboard: KPIs<br/>revenue, orders, AOV, reservations]
    B --> C{Choose task}

    C --> D[Order management]
    D --> D1[View live order queue]
    D1 --> D2[Open order]
    D2 --> D3{Action}
    D3 -- Advance --> D4[Update status<br/>log to history, notify customer]
    D3 -- Cancel/Refund --> D5[Enter reason, refund payment]
    D3 -- Delivery --> D6[Assign driver, track delivery]

    C --> E[Menu management]
    E --> E1{Action}
    E1 -- Add/Edit --> E2[Edit name, price, image, category]
    E1 -- Toggle --> E3[Mark sold-out / available]
    E1 -- Delete --> E4[Soft delete]
    E2 --> E5[Changes apply to new orders only]
    E3 --> E5
    E4 --> E5

    C --> F[Table and reservation management]
    F --> F1{Action}
    F1 -- Edit layout --> F2[Add/move/disable tables, set capacity]
    F1 -- Reservations --> F3[View calendar]
    F3 --> F4{Reservation action}
    F4 -- Approve/Reject --> F5[PENDING → CONFIRMED / CANCELLED]
    F4 -- Check-in --> F6[CONFIRMED → SEATED → COMPLETED]
    F4 -- No-show --> F7[Mark NO_SHOW]
    F2 --> F8{Future bookings<br/>affected?}
    F8 -- Yes --> F9[Warn admin, require reassign or cancel]
    F8 -- No --> F10[Save layout]

    C --> G[Pickup and delivery management]
    G --> G1[Pickup board: orders READY]
    G --> G2[Delivery list by driver]
    G --> G3[Configure fee, zone, minimum]

    D4 --> H([Done])
    D5 --> H
    D6 --> H
    E5 --> H
    F5 --> H
    F6 --> H
    F7 --> H
    F9 --> H
    F10 --> H
    G1 --> H
    G2 --> H
    G3 --> H
```