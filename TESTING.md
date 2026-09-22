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

**Actual response (HTTP 200):**

```json
{"data":[{"id":1,"code":"physical_count","name":"盘点修正"},{"id":2,"code":"damaged","name":"商品损坏"},{"id":3,"code":"missing","name":"商品丢失"},{"id":4,"code":"data_entry","name":"数据录入修正"}]}
```

Only 4 reasons (ids 1–4). Reason 5 (inactive) and reason 6 (wrong applies_to) are hidden.

---

### Scenario 2 — Create an adjustment (100 → 92)

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":1,"adjustment_reason_id":1,"new_quantity":92,"note":"stocktake found 8 missing"}'
```

**Actual response (HTTP 201 Created):**

```
HTTP/1.1 201 Created
Content-Type: application/json
```

```json
{"data":{"id":1,"old_quantity":100,"new_quantity":92,"quantity_diff":-8,"note":"stocktake found 8 missing","created_at":"2026-09-22T12:30:27+00:00","batch":{"id":1,"batch_no":"B20260922-001","current_quantity":92,"product":{"id":1,"name":"矿泉水550ml","sku":"SKU-WATER-550"},"warehouse":{"id":1,"name":"主仓库","code":"MAIN"}},"reason":{"id":1,"code":"physical_count","name":"盘点修正"}}}
```

Key checks: `old_quantity=100`, `new_quantity=92`, `quantity_diff=-8`, batch `current_quantity` is now **92**.

---

### Scenario 3 — View adjustment detail

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Actual response (HTTP 200):**

```json
{"data":{"id":1,"old_quantity":100,"new_quantity":92,"quantity_diff":-8,"note":"stocktake found 8 missing","created_at":"2026-09-22T12:30:27+00:00","batch":{"id":1,"batch_no":"B20260922-001","current_quantity":92,"product":{"id":1,"name":"矿泉水550ml","sku":"SKU-WATER-550"},"warehouse":{"id":1,"name":"主仓库","code":"MAIN"}},"reason":{"id":1,"code":"physical_count","name":"盘点修正"}}}
```

Nested `batch.product`, `batch.warehouse`, and `reason` all present.

---

### Scenario 4 — Inactive reason is rejected

Use `reason_id=5` (inactive):

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":5,"new_quantity":40}'
```

**Actual response (HTTP 422 Unprocessable Content):**

```
HTTP/1.1 422 Unprocessable Content
```

```json
{"message":"The selected reason is not active or not valid for inventory adjustments.","errors":{"adjustment_reason_id":["The selected reason is not active or not valid for inventory adjustments."]}}
```

Batch 2's quantity remains **50** (unchanged).

---

### Scenario 5 — Non-existent batch is rejected

Use `batch_id=999`:

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":999,"adjustment_reason_id":1,"new_quantity":50}'
```

**Actual response (HTTP 422):**

```json
{"message":"The selected batch id is invalid.","errors":{"batch_id":["The selected batch id is invalid."]}}
```

---

### Scenario 6 — Negative quantity is rejected

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":-5}'
```

**Actual response (HTTP 422):**

```json
{"message":"The new quantity field must be at least 0.","errors":{"new_quantity":["The new quantity field must be at least 0."]}}
```

---

### Scenario 7 — Missing new_quantity is rejected

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1}'
```

**Actual response (HTTP 422):**

```json
{"message":"The new quantity field is required.","errors":{"new_quantity":["The new quantity field is required."]}}
```

---

### Scenario 8 — Non-integer quantity is rejected

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":"abc"}'
```

**Actual response (HTTP 422):**

```json
{"message":"The new quantity field must be an integer.","errors":{"new_quantity":["The new quantity field must be an integer."]}}
```

---

### Scenario 9 — Zero diff is allowed (count matches system)

Batch 2 currently has quantity 50. Set it to 50:

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":50,"note":"count matches system"}'
```

**Actual response (HTTP 201 Created):**

```json
{"data":{"id":2,"old_quantity":50,"new_quantity":50,"quantity_diff":0,"note":"count matches system","created_at":"2026-09-22T12:32:54+00:00","batch":{"id":2,"batch_no":"B20260922-002","current_quantity":50,"product":{"id":2,"name":"红烧牛肉面","sku":"SKU-NOODLE-BR"},"warehouse":{"id":1,"name":"主仓库","code":"MAIN"}},"reason":{"id":1,"code":"physical_count","name":"盘点修正"}}}
```

`quantity_diff = 0` is a valid audit record, not an error.

---

### Scenario 10 — Reason with wrong applies_to is rejected

Use `reason_id=6` (active, but it's for price adjustments, not inventory):

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":6,"new_quantity":40}'
```

**Actual response (HTTP 422):**

```json
{"message":"The selected reason is not active or not valid for inventory adjustments.","errors":{"adjustment_reason_id":["The selected reason is not active or not valid for inventory adjustments."]}}
```

Batch 2's quantity remains **50**.

---

### Scenario 11 — Re-adjust reads current quantity as old

Batch 1 is now **92** (after Scenario 2). Adjust it again to 90:

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":1,"adjustment_reason_id":1,"new_quantity":90,"note":"second recount"}'
```

**Actual response (HTTP 201 Created):**

```json
{"data":{"id":3,"old_quantity":92,"new_quantity":90,"quantity_diff":-2,"note":"second recount","created_at":"2026-09-22T12:33:10+00:00","batch":{"id":1,"batch_no":"B20260922-001","current_quantity":90,"product":{"id":1,"name":"矿泉水550ml","sku":"SKU-WATER-550"},"warehouse":{"id":1,"name":"主仓库","code":"MAIN"}},"reason":{"id":1,"code":"physical_count","name":"盘点修正"}}}
```

Key: `old_quantity = 92` (the current value, not the original 100), `new_quantity = 90`, `quantity_diff = -2`.

---

### Scenario 12 — Non-existent adjustment returns 404

```bash
curl -i http://127.0.0.1:8000/api/inventory-adjustments/999
```

**Actual response (HTTP 404 Not Found):**

```
HTTP/1.1 404 Not Found
Content-Type: application/json
```

```json
{
    "message": "No query results for model [App\\Models\\InventoryAdjustment] 999"
}
```

Clean JSON, no stack trace (`APP_DEBUG=false`).

---

### Scenario 13 — History remains readable after reason is deactivated

First create an adjustment (Scenario 2), then deactivate the reason in `php artisan tinker`:

```bash
php artisan tinker
```

```php
> App\Models\AdjustmentReason::find(1)->update(['is_active' => false]);
= true
```

Then view the historical adjustment:

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Actual response (HTTP 200):**

```json
{"data":{"id":1,"old_quantity":100,"new_quantity":92,"quantity_diff":-8,"note":"stocktake found 8 missing","created_at":"2026-09-22T12:30:27+00:00","batch":{"id":1,"batch_no":"B20260922-001","current_quantity":90,"product":{"id":1,"name":"矿泉水550ml","sku":"SKU-WATER-550"},"warehouse":{"id":1,"name":"主仓库","code":"MAIN"}},"reason":{"id":1,"code":"physical_count","name":"盘点修正"}}}
```

The reason data (`code`, `name`) is still attached to the historical record even though `is_active` is now false.

---

## Done?

Press `Ctrl+C` in the server terminal to stop. That's the end of the manual walkthrough.
