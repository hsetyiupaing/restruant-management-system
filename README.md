# Restaurant Management System

A Laravel-based restaurant operations platform for multi-tenant restaurant management, ordering, reservations, delivery coordination, and KPI reporting.

## Overview

This application models a multi-restaurant platform where each restaurant has its own staff, menu, tables, orders, reservations, and metrics. It is designed around a public catalogue for customers, authenticated staff/admin flows, and a REST API that supports both operational workflows and front-end integrations.

The project is structured around:

- Laravel 12-style backend with API routes and Fortify-based session authentication
- Role-aware authorization by restaurant membership and global user role
- Restaurant-specific order, reservation, and delivery logic
- KPI aggregation for revenue, reservation completion, and operational activity
- API resources, validation requests, and service-layer business rules

## Project structure

- `app/Actions/` - Fortify action classes for account creation and password resets
- `app/Enums/` - typed enums for order, reservation, payment, role, and status states
- `app/Http/Controllers/Api/` - public and authenticated API controllers
- `app/Http/Controllers/Api/Admin/` - restaurant admin and platform admin endpoints
- `app/Http/Requests/Api/` - request validation for API payloads
- `app/Http/Resources/` - JSON response shaping for API consumers
- `app/Models/` - Eloquent models for restaurants, orders, menu items, tables, and reservations
- `app/Policies/` - authorization rules for restaurants, orders, reservations, and deliveries
- `app/Services/` - order creation, reservation management, and KPI aggregation logic
- `database/migrations/` - schema source of truth for the domain model
- `database/seeders/` - seed data for demo restaurants, menu items, and sample records
- `routes/api.php` - API route registration
- `docs/` - design and API specification documents
- `tests/` - Pest feature and unit coverage

## Domain model

The core business model is built around these concepts:

- Restaurants: each restaurant is a tenant with its own settings, staff, menu, and tables
- Menu items: categorized, priced, sold-out capable, and associated with a restaurant
- Dining tables: live table inventory with shape, capacity, and operating state
- Orders: pickup/delivery orders containing item snapshots and payment data
- Reservations: time-based bookings linked to a table and a customer
- Deliveries: assigned delivery detail records for orders with driver tracking
- Members: restaurant staff/admin assignments with role and staff type metadata

## API summary

The application exposes API endpoints under `/api/v1` using JSON responses and Laravel session-based Sanctum authentication.

### Public catalogue

- `GET /api/v1/restaurants`
- `GET /api/v1/restaurants/{slug}`
- `GET /api/v1/restaurants/{slug}/categories`
- `GET /api/v1/restaurants/{slug}/menu-items`
- `GET /api/v1/restaurants/{slug}/menu-items/{menuItem}`
- `GET /api/v1/restaurants/{slug}/availability`

### Auth and customer flows

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `PATCH /api/v1/auth/me`
- `PUT /api/v1/auth/me/food-restrictions`
- `GET /api/v1/orders`
- `POST /api/v1/orders`
- `GET /api/v1/orders/{order}`
- `POST /api/v1/orders/{order}/cancel`
- `GET /api/v1/reservations`
- `POST /api/v1/reservations`
- `GET /api/v1/reservations/{reservation}`

### Admin flows

- `GET /api/v1/admin/restaurants`
- `POST /api/v1/admin/restaurants`
- `PATCH /api/v1/admin/restaurants/{restaurant}`
- `GET /api/v1/admin/restaurants/{restaurant}/settings`
- `POST /api/v1/admin/restaurants/{restaurant}/activate`
- `POST /api/v1/admin/restaurants/{restaurant}/deactivate`
- `GET /api/v1/admin/restaurants/{restaurant}/orders`
- `GET /api/v1/admin/restaurants/{restaurant}/reservations`
- `GET /api/v1/admin/restaurants/{restaurant}/menu-items`
- `GET /api/v1/admin/restaurants/{restaurant}/members`
- `GET /api/v1/admin/restaurants/{restaurant}/kpis`

### Super Admin only

- `GET /api/v1/admin/users`
- `PATCH /api/v1/admin/users/{user}/role`

## Setup

1. Install dependencies:
   `composer install`
2. Install front-end dependencies if needed:
   `npm install`
3. Configure environment variables using `.env` and your local database settings.
4. Run migrations and seed demo data:
   `php artisan migrate --seed`
5. Start the app:
   `php artisan serve`

### Demo accounts

> **Development only.** These credentials are published here, so any
> deployment that contains them is compromised. Never run
> `DemoAccountSeeder` against a production database; the seeder itself refuses
> to run when `APP_ENV=production`, and the passwords below must never be
> reused for real accounts. Change or delete every demo account before any
> environment is exposed to the internet.

`php artisan db:seed --class=DemoAccountSeeder` (also part of `db:seed`)
creates one account per role:

| Role        | Email                  | Password             |
| ----------- | ---------------------- | -------------------- |
| Super Admin | `superadmin@mori.demo` | `MoriSuperAdmin123!` |
| Admin       | `admin@mori.demo`      | `MoriAdmin123!`      |
| Staff       | `staff@mori.demo`      | `MoriStaff123!`      |
| Customer    | `customer@mori.demo`   | `MoriCustomer123!`   |

The seeder is safe to re-run: accounts are matched by email, an existing
account's password is never reset, memberships and the demo customer's
restrictions (peanuts, shellfish, vegetarian) are upserted rather than
duplicated.

### Roles

| Role          | Can do                                                            |
| ------------- | ----------------------------------------------------------------- |
| `CUSTOMER`    | Storefront, own orders and reservations, own dietary restrictions |
| `STAFF`       | Operational tasks for a restaurant they are a member of           |
| `ADMIN`       | Manage a restaurant, its menu, team and settings                  |
| `SUPER_ADMIN` | Everything above, plus platform accounts and role assignment      |

Public registration always creates a `CUSTOMER`; a `role` sent in the request
body or form is ignored. Only a Super Admin can change another account's role,
and the last remaining Super Admin cannot be demoted (or demote themselves).

## Testing

Run the project test suite with:

`php artisan test`

For a smaller verification pass on an API area, use the Pest test files under `tests/Feature` and filter by a feature name.

## Documentation

This project has its design and endpoint contract in the `docs/` folder:

- `docs/structure.md` — high-level architecture and domain design
- `docs/api-spec.md` — endpoint contract, enums, status transitions, and validation rules
- `docs/api-implementation-plan.md` — implementation plan and rollout guidance

## Notes

- Customer-facing endpoints intentionally expose only active restaurants and available menu items.
- Order totals and prices are recalculated server-side instead of trusting client input.
- Reservation and order transitions are validated against business rules and recorded to status history tables.
- The system uses role and restaurant membership policies to prevent cross-tenant access.
