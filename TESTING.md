# Testing Guide — How to Verify This Project

This guide lets you test the whole API in under 5 minutes. Every command is copy-pasteable.

---

## Prerequisites (one time)

```bash
# From the project root
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

`migrate:fresh --seed` will:
- Drop all tables and recreate them
- Insert demo data: 2 warehouses, 2 products, 2 batches, 5 reasons (4 active + 1 inactive)

---

## Start the server

```bash
php artisan serve
```

You should see:
```
INFO  Server running on [http://127.0.0.1:8000].
```

Keep this terminal open. Open a **second** terminal for the curl commands below.

---

## Test 1 — List active adjustment reasons

**Command:**
```bash
curl http://127.0.0.1:8000/api/adjustment-reasons
```

**Expected (HTTP 200):**
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

✅ **Check:** exactly 4 items. The inactive reason (id=5 "已停用原因示例") must NOT appear.

---

## Test 2 — Create an inventory adjustment (the main scenario)

This reproduces the example from the task: batch 1 has qty=100 in the system, physical count finds 92.

**Command:**
```bash
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":1,\"adjustment_reason_id\":1,\"new_quantity\":92,\"note\":\"stocktake found 8 missing\"}"
```

**Expected (HTTP 201):**
```json
{
  "data": {
    "id": 1,
    "old_quantity": 100,
    "new_quantity": 92,
    "quantity_diff": -8,
    "note": "stocktake found 8 missing",
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

✅ **Check:**
- `old_quantity` = 100 (read from DB before update)
- `new_quantity` = 92 (what you sent)
- `quantity_diff` = **-8** (auto-calculated, negative = stock shrinkage)
- `batch.current_quantity` = **92** (the batch was updated in the same transaction)

---

## Test 3 — View the adjustment detail

**Command:**
```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Expected (HTTP 200):** same JSON shape as Test 2 response.

✅ **Check:** the response includes nested `batch`, `batch.product`, `batch.warehouse`, and `reason` — no extra HTTP calls, all eager-loaded.

---

## Test 4 — Reject an inactive reason (business rule)

Reason id=5 ("已停用原因示例") exists but is `is_active = false`.

**Command:**
```bash
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":2,\"adjustment_reason_id\":5,\"new_quantity\":40}"
```

**Expected (HTTP 422):**
```json
{
  "message": "The selected reason is not active or not valid for inventory adjustments.",
  "errors": {
    "adjustment_reason_id": ["The selected reason is not active or not valid for inventory adjustments."]
  }
}
```

✅ **Check:** the request is rejected inside the transaction. Batch 2 quantity must stay at 50.

---

## Test 5 — Reject a non-existent batch (validation)

**Command:**
```bash
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":999,\"adjustment_reason_id\":1,\"new_quantity\":50}"
```

**Expected (HTTP 422):** Laravel validation error on `batch_id`.

---

## Test 6 — Reject a negative quantity (validation)

**Command:**
```bash
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":2,\"adjustment_reason_id\":1,\"new_quantity\":-5}"
```

**Expected (HTTP 422):** validation error on `new_quantity`.

---

## Test 7 — 404 for non-existent adjustment

**Command:**
```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/999
```

**Expected (HTTP 404):**
```json
{ "message": "No query results for model [App\\Models\\InventoryAdjustment] 999" }
```

---

## Verify the database directly (optional)

To prove the batch quantity was actually updated:

```bash
php artisan tinker
```

Then in tinker:
```php
App\Models\Batch::find(1)->current_quantity;   // should be 92 after Test 2
App\Models\InventoryAdjustment::count();         // should be 1
exit
```

---

## Reset to a clean state anytime

```bash
# Ctrl+C to stop the server, then:
php artisan migrate:fresh --seed
php artisan serve
```

This wipes test data and restores batch 1 to qty=100.

---

## Test Summary Checklist

| # | Test | Expected | Pass? |
|---|---|---|---|
| 1 | List reasons | 4 active reasons, inactive hidden | ☐ |
| 2 | Create adjustment 100→92 | 201, diff=-8, batch updated | ☐ |
| 3 | View adjustment detail | 200, nested relations loaded | ☐ |
| 4 | Inactive reason rejected | 422, batch unchanged | ☐ |
| 5 | Non-existent batch rejected | 422 | ☐ |
| 6 | Negative quantity rejected | 422 | ☐ |
| 7 | Non-existent adjustment | 404 | ☐ |
