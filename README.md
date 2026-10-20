# Extendable Order & Payment Management API

[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![Tests Passing](https://img.shields.io/badge/Tests-31%20Passed-brightgreen.svg)]()
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

An enterprise-grade, clean-architecture RESTful API for Order and Payment Management built with **Laravel 13**, **JWT Authentication**, and an **Extensible Payment Gateway Strategy Pattern**.

Designed to senior software engineering standards with full database normalization, write-through caching, idempotency key safeguards, and 100% automated test coverage.

---

## 🏛️ System Architecture & Design Principles

```
                  ┌───────────────────────────────┐
                  │          HTTP Request         │
                  └──────────────┬────────────────┘
                                 │
                                 ▼
                  ┌───────────────────────────────┐
                  │    JWT Auth & Form Requests   │ (Validation & Auth Guards)
                  └──────────────┬────────────────┘
                                 │
                                 ▼
                  ┌───────────────────────────────┐
                  │      API Controllers (V1)     │ (HTTP Layer / Routing)
                  └──────────────┬────────────────┘
                                 │
                                 ▼
                  ┌───────────────────────────────┐
                  │        Service Layer          │ (Business Rules, Caching,
                  │  (OrderService/PaymentService)│  Idempotency Mutex)
                  └──────┬─────────────────┬──────┘
                         │                 │
                         ▼                 ▼
          ┌──────────────────────┐  ┌─────────────────────────────┐
          │   Repository Layer   │  │   Payment Gateway Strategy  │
          │(Eloquent Repositories│  │ (PaymentGatewayManager / CC,│
          │      & Contracts)    │  │       PayPal, Stripe)       │
          └──────────┬───────────┘  └─────────────────────────────┘
                     │
                     ▼
          ┌──────────────────────┐
          │  Normalized Database │
          │ (Users, Orders, Items│
          │      Payments)       │
          └──────────────────────┘
```

### 1. Clean Architecture Layers
- **Domain Layer (`app/Domain` & `app/Enums`, `app/DTOs`, `app/Exceptions`)**: Pure business models, status enums (`OrderStatus`, `PaymentStatus`, `PaymentMethod`, `UserRole`), strongly-typed DTOs, and custom domain exceptions.
- **Repository Pattern (`app/Repositories`)**: Decouples data access from business logic. All database access goes through interfaces (`OrderRepositoryInterface`, `PaymentRepositoryInterface`, `UserRepositoryInterface`).
- **Service Layer (`app/Services`)**: Orchestrates business rules, transactions, and cache management.
- **Payment Strategy Pattern (`app/Services/Payment`)**: Follows the **Open/Closed Principle (OCP)**. New payment gateways can be registered without altering existing checkout or payment processing logic.
- **API Resource Layer (`app/Http/Resources/V1`)**: Unified JSON transformation with collections, pagination metadata, and HATEOAS-like flags.

---

## 🚀 Key Senior Features Implemented

### 1. Write-Through & Invalidation Caching Strategy
- **Order Details (`orders:detail:{id}`)**: Cached for high read performance.
- **Write-Through Invalidation**:
  1. On update: Evicts old cache entry (`Cache::forget`).
  2. Updates database within an atomic transaction.
  3. Reloads fresh record with relations and stores it back to cache (`Cache::put`).
  4. Bumps list version key (`orders:list_version`) to immediately invalidate stale paginated list queries across the system.

### 2. Idempotency Key Handling
- Supports `Idempotency-Key` / `X-Idempotency-Key` headers on payment endpoints.
- Uses an atomic cache lock to prevent concurrent double-charging.
- Caches successful responses for 24 hours: duplicate requests return the original transaction without re-charging or creating duplicate database records.

### 3. Business Rules Enforced
1. **Payments can ONLY be processed for orders in `confirmed` status**: Attempts to process payments for `pending` or `cancelled` orders fail with HTTP `422 Unprocessable Entity`.
2. **Orders CANNOT be deleted if they have associated payments**: Returns HTTP `422` with a clear domain message protecting financial audit trails.

---

## 💳 Payment Gateway Extensibility (Strategy Pattern)

Adding a new payment gateway takes **only 3 simple steps**:

### Step 1: Create a Gateway Driver
Create a class implementing `App\Services\Payment\Contracts\PaymentGatewayInterface`:

```php
namespace App\Services\Payment\Drivers;

use App\DTOs\PaymentResultDTO;
use App\DTOs\ProcessPaymentDTO;
use App\Services\Payment\Contracts\PaymentGatewayInterface;

class CryptoPaymentGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'crypto';
    }

    public function process(ProcessPaymentDTO $dto): PaymentResultDTO
    {
        // 1. Call your 3rd-party crypto provider API
        // 2. Return PaymentResultDTO::success or PaymentResultDTO::failure
        return PaymentResultDTO::success(
            transactionId: 'btc_tx_' . bin2hex(random_bytes(8)),
            gatewayName: $this->getName(),
            message: 'Crypto transaction confirmed.'
        );
    }
}
```

### Step 2: Register in `config/payments.php`
Add the configuration to `config/payments.php`:

```php
'gateways' => [
    // ... existing gateways
    'crypto' => [
        'name' => 'Cryptocurrency',
        'driver' => \App\Services\Payment\Drivers\CryptoPaymentGateway::class,
        'api_key' => env('CRYPTO_API_KEY'),
    ],
],
```

### Step 3: (Optional) Dynamic Runtime Registration
You can also register custom gateways dynamically at runtime via the `PaymentGatewayManager`:

```php
app(PaymentGatewayManager::class)->extend('vodafone_cash', function ($app) {
    return new VodafoneCashPaymentGateway($app['config']['payments.gateways.vodafone_cash']);
});
```

*Zero modifications to existing order or payment code!*

---

## 🛠️ Setup Instructions

### Prerequisites
- **PHP 8.3+**
- **Composer 2+**
- **SQLite** or MySQL

### 1. Clone & Install Dependencies
```bash
composer install
```

### 2. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret --force
```

### 3. Database Migration & Seeding
```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

This will automatically create:
- **Admin User**: `admin@example.com` / `password123`
- **Customer User**: `user@example.com` / `password123`
- Pre-populated orders with items and sample payments.

### 4. Run the Local Development Server
```bash
php artisan serve
```
The API is available at: `http://127.0.0.1:8000/api/v1`

---

## 🧪 Running Automated Tests

Run the full PHPUnit / Pest test suite:

```bash
php artisan test
```

### Test Coverage Highlights (31 Tests, 108 Assertions):
- **`AuthTest`**: User registration, login, token refresh, profile inspection, JWT rejection.
- **`OrderTest`**: Creation with items calculation, status filtering, cache write-through validation, deletion constraints.
- **`PaymentTest`**: Credit card, PayPal, Stripe processing, failure simulation, idempotency duplicates prevention.
- **`PaymentGatewayStrategyTest`**: Gateway manager resolution, dynamic extensions, exception handling.

---

## 📬 Postman API Collection

A complete Postman collection is exported and located at:
`docs/postman_collection.json`

### To Import:
1. Open Postman.
2. Click **Import** -> Select `docs/postman_collection.json`.
3. The collection includes pre-configured environment variables and auto-updating JWT auth tokens upon login/registration.

### Main Endpoints Summary:

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/v1/auth/register` | Register new user | No |
| `POST` | `/api/v1/auth/login` | Login and receive JWT token | No |
| `GET` | `/api/v1/auth/me` | Authenticated user profile | Yes (Bearer) |
| `POST` | `/api/v1/auth/refresh` | Refresh JWT token | Yes (Bearer) |
| `POST` | `/api/v1/auth/logout` | Invalidate token | Yes (Bearer) |
| `GET` | `/api/v1/orders` | List orders (filters: `?status=pending\|confirmed\|cancelled`) | Yes (Bearer) |
| `POST` | `/api/v1/orders` | Create order with items & calculate total | Yes (Bearer) |
| `GET` | `/api/v1/orders/{id}` | Single order details | Yes (Bearer) |
| `PUT` | `/api/v1/orders/{id}` | Update order (triggers cache write-through) | Yes (Bearer) |
| `DELETE` | `/api/v1/orders/{id}` | Delete order (fails 422 if payments exist) | Yes (Bearer) |
| `GET` | `/api/v1/orders/{id}/payments` | View payments for specific order | Yes (Bearer) |
| `POST` | `/api/v1/payments` | Process payment via gateway strategy (supports `Idempotency-Key`) | Yes (Bearer) |
| `GET` | `/api/v1/payments` | List all payments | Yes (Bearer) |
| `GET` | `/api/v1/payments/{id}` | View single payment | Yes (Bearer) |
| `GET` | `/api/v1/payments/gateways` | View supported payment gateways | Yes (Bearer) |
