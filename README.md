# Inventory Adjustment API (Laravel Demo)

A small standalone Laravel API demo for managing batch quantity adjustments with predefined reasons.

## Business Context

A warehouse stores product batches. Each batch has a current quantity. When a physical count does not match the system quantity, an inventory adjustment is created to correct the batch quantity. Every adjustment must use a predefined reason (e.g. physical count, damaged, missing, data entry).

---

## Requirements

- PHP >= 8.2 (developed on 8.3)
- SQLite (no external database needed)
- Composer

---

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Create SQLite database file (Linux/macOS)
touch database/database.sqlite

# 3. Create SQLite database file (Windows)
# type nul > database\database.sqlite

# 4. Run migrations and seed demo data
php artisan migrate:fresh --seed

# 5. Start the dev server
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.

---

## API Endpoints

### 1. List active adjustment reasons

Returns only active reasons that are valid for inventory adjustments.

```bash
curl http://127.0.0.1:8000/api/adjustment-reasons
```

**Response (200):**

```json
{
  "data": [
    { "id": 1, "code": "physical_count", "name": "盘点修正" },
    { "id": 2, "code": "damaged", "name": "商品损坏" },
    { "id": 3, "code": "missing", "name": "商品丢失" },
    { "id": 4, "code": "data_entry", "name": "数据录入修正" }
  ]
}
```

### 2. Create an inventory adjustment

Creates an adjustment record and updates the batch quantity atomically.

```bash
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d '{
    "batch_id": 1,
    "adjustment_reason_id": 1,
    "new_quantity": 92,
    "note": "月末盘点发现少了8箱"
  }'
```

**Request body:**

| Field | Type | Required | Description |
|---|---|---|---|
| `batch_id` | integer | yes | Must exist in `batches` |
| `adjustment_reason_id` | integer | yes | Must exist, be active, and apply to inventory adjustments |
| `new_quantity` | integer | yes | New quantity, must be >= 0 |
| `note` | string | no | Free-text note, max 1000 chars |

**Response (201):**

```json
{
  "data": {
    "id": 1,
    "old_quantity": 100,
    "new_quantity": 92,
    "quantity_diff": -8,
    "note": "月末盘点发现少了8箱",
    "created_at": "2026-09-22T10:00:00+00:00",
    "batch": {
      "id": 1,
      "batch_no": "B20260922-001",
      "current_quantity": 92,
      "product":   { "id": 1, "name": "矿泉水550ml", "sku": "SKU-WATER-550" },
      "warehouse":  { "id": 1, "name": "主仓库", "code": "MAIN" }
    },
    "reason": { "id": 1, "code": "physical_count", "name": "盘点修正" }
  }
}
```

### 3. View an adjustment

Returns the adjustment with its batch, product, warehouse, and reason.

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Response (200):** same shape as the create response above.

---

## Seeded Demo Data

| Table | Rows |
|---|---|
| warehouses | 主仓库 (MAIN), 副仓库 (SECOND) |
| products | 矿泉水550ml, 红烧牛肉面 |
| batches | B20260922-001 (qty 100), B20260922-002 (qty 50) |
| adjustment_reasons | 4 active + 1 inactive (expired_demo) |

Use batch_id=1 and reason_id=1 to reproduce the "100 -> 92" example.

---

## Error Responses

Uses Laravel default format.

| Scenario | Status |
|---|---|
| Validation failed (missing/invalid field) | 422 |
| Reason not active or not applicable | 422 |
| Batch not found (route model binding) | 404 |
| Adjustment not found | 404 |

Example 422:

```json
{
  "message": "The selected adjustment reason id is invalid.",
  "errors": {
    "adjustment_reason_id": ["The selected reason is not active or not valid for inventory adjustments."]
  }
}
```

---

## Design Decisions

| Decision | Why |
|---|---|
| **Database transaction + `lockForUpdate()` on batch row** | Prevents lost updates under concurrency. Two simultaneous adjustments on the same batch cannot read the same old quantity and overwrite each other. |
| **Store `old_quantity`, `new_quantity`, and `quantity_diff`** | `quantity_diff` is redundant (always `new - old`) but stored for fast audit queries and reconciliation without recomputation. It is always calculated server-side; the client never sends it. |
| **Do NOT validate `is_active` / `applies_to` in FormRequest** | These depend on live DB state and must be checked inside the same transaction that reads the batch, avoiding a TOCTOU race (reason could be deactivated between validation and write). |
| **Reason invalid -> 422, batch not found -> 404** | Clear HTTP semantics: 404 means "resource does not exist"; 422 means "request semantically invalid" (e.g. inactive reason chosen). |
| **Allow `quantity_diff = 0`** | A physical count matching the system quantity is a legitimate audit record. There is no business reason to reject it. |
| **No Service class** | The store logic is under 80 lines. A service layer would be over-engineering for this size. If it grows, extract later. |
| **Hand-written routes, not `apiResource`** | Only 3 actions exist. `apiResource` would generate unused index/update/destroy routes. |
| **Default Laravel error format** | No custom exception handler needed. Responses are predictable and standard. |
| **`RESTRICT` foreign key on delete** | Inventory adjustments are audit records. Deleting a warehouse, product, batch, or reason is blocked while records reference it, preserving the audit trail. Reasons are deactivated via `is_active = false`, never physically deleted. |
| **Composite unique `(product_id, batch_no)`** | Batch numbers are product-scoped, not globally unique. |

---

## Scope and Limitations

Intentionally **not** included (out of scope for this demo):

- **No update/delete on adjustments** — inventory adjustments are immutable audit records. If a mistake is made, create a new reversing adjustment.
- **No authentication / authorization** — outside the task scope.
- **No pagination** — reason list is small.
- **No tests** — manual curl verification covers the required scenarios.

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AdjustmentReasonController.php
│   │   └── InventoryAdjustmentController.php
│   ├── Requests/
│   │   └── StoreInventoryAdjustmentRequest.php
│   └── Resources/
│       ├── AdjustmentReasonResource.php
│       └── InventoryAdjustmentResource.php
└── Models/
    ├── Warehouse.php
    ├── Product.php
    ├── Batch.php
    ├── AdjustmentReason.php
    └── InventoryAdjustment.php
database/
├── migrations/
└── seeders/
routes/
└── api.php
```
