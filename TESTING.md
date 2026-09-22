# Testing Guide

There are two ways to verify this project: **automated tests** (one command) and **manual curl** (step-by-step).

---

## Option A — Automated tests (recommended, 10 seconds)

No HTTP server needed. Tests run in-process against an in-memory SQLite database.

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan test
```

You should see **15 passed** in under a second. That covers all 13 scenarios below automatically.

---

## Option B — Manual curl (step-by-step, copy-paste)

### 0. Start fresh and start the server

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Keep this terminal open. Open a **second terminal** for the curl commands below.

> **Git Bash on Windows tip:** write the JSON body as a single line (no line breaks inside `-d '...'`), otherwise the shell may mangle the quotes.

After `migrate:fresh --seed`, you can assume:

- `batch_id = 1` → B20260922-001, current quantity = **100**
- `batch_id = 2` → B20260922-002, current quantity = **50**
- `reason_id = 1` → 盘点修正 (active, inventory adjustment)
- `reason_id = 5` → 已停用原因示例 (inactive)
- `reason_id = 6` → 价格调整 (active, but `applies_to = price_adjustment`, NOT inventory)

---

### Scenario 1 — List active reasons only

```bash
curl http://127.0.0.1:8000/api/adjustment-reasons
```

**Expect:** HTTP 200. Only 4 reasons returned (ids 1–4). Reason 5 (inactive) and reason 6 (wrong applies_to) must NOT appear.

---

### Scenario 2 — Create an adjustment (100 → 92)

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":1,"adjustment_reason_id":1,"new_quantity":92,"note":"stocktake found 8 missing"}'
```

**Expect:** HTTP **201 Created**. Response body:

```json
{
  "data": {
    "id": 1,
    "old_quantity": 100,
    "new_quantity": 92,
    "quantity_diff": -8,
    "note": "stocktake found 8 missing",
    "batch": { "id": 1, "batch_no": "B20260922-001", "current_quantity": 92, ... },
    "reason": { "id": 1, "code": "physical_count", ... }
  }
}
```

Key checks: `old_quantity=100`, `new_quantity=92`, `quantity_diff=-8`, batch `current_quantity` is now **92**.

---

### Scenario 3 — View adjustment detail

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Expect:** HTTP 200. The same adjustment from Scenario 2, with nested `batch.product` and `batch.warehouse` and `reason`.

---

### Scenario 4 — Inactive reason is rejected

Use `reason_id=5` (inactive):

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":5,"new_quantity":40}'
```

**Expect:** HTTP **422**. Batch 2's quantity must still be **50** (unchanged).

---

### Scenario 5 — Non-existent batch is rejected

Use `batch_id=999`:

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":999,"adjustment_reason_id":1,"new_quantity":50}'
```

**Expect:** HTTP **422** with error on `batch_id`.

---

### Scenario 6 — Negative quantity is rejected

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":-5}'
```

**Expect:** HTTP **422** with error on `new_quantity`.

---

### Scenario 7 — Missing new_quantity is rejected

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1}'
```

**Expect:** HTTP **422** with error on `new_quantity`.

---

### Scenario 8 — Non-integer quantity is rejected

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":"abc"}'
```

**Expect:** HTTP **422** with error on `new_quantity`.

---

### Scenario 9 — Zero diff is allowed (count matches system)

Batch 2 currently has quantity 50. Set it to 50:

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":50,"note":"count matches system"}'
```

**Expect:** HTTP **201 Created**, `quantity_diff = 0`. This is a valid audit record, not an error.

---

### Scenario 10 — Reason with wrong applies_to is rejected

Use `reason_id=6` (active, but it's for price adjustments, not inventory):

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":6,"new_quantity":40}'
```

**Expect:** HTTP **422**. Batch 2's quantity must still be **50**.

---

### Scenario 11 — Re-adjust reads current quantity as old

Batch 1 is now **92** (after Scenario 2). Adjust it again to 90:

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":1,"adjustment_reason_id":1,"new_quantity":90,"note":"second recount"}'
```

**Expect:** HTTP **201**. `old_quantity = 92` (the current value, not the original 100), `new_quantity = 90`, `quantity_diff = -2`.

---

### Scenario 12 — Non-existent adjustment returns 404

```bash
curl -i http://127.0.0.1:8000/api/inventory-adjustments/999
```

**Expect:** HTTP **404** (clean JSON, no stack trace because `APP_DEBUG=false`).

---

### Scenario 13 — History remains readable after reason is deactivated

First create an adjustment (Scenario 2), then deactivate the reason in `php artisan tinker`:

```bash
php artisan tinker
```

```php
App\Models\AdjustmentReason::find(1)->update(['is_active' => false]);
```

Then view the historical adjustment:

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Expect:** HTTP **200**. The reason data (`code`, `name`) is still attached to the historical record. Deactivating a reason must not break history reads.

---

## Done?

Press `Ctrl+C` in the server terminal to stop. That's the end of the manual walkthrough.
