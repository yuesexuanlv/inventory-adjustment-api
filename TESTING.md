# Testing Guide

There are two ways to verify this project: **automated tests** (recommended) and **manual curl**.

---

## Option A — Automated tests (recommended)

No HTTP server needed. Tests run in-process against an in-memory SQLite database.

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan test
```

**Expected output:**
```
   PASS  Tests\Feature\InventoryAdjustmentApiTest
  ✓ lists active reasons only
  ✓ creates adjustment and updates batch quantity
  ✓ rejects inactive reason with 422
  ✓ rejects nonexistent batch with 422
  ✓ rejects negative quantity with 422
  ✓ missing new quantity is rejected
  ✓ returns 404 for nonexistent adjustment
  ✓ view adjustment detail with relations

  Tests:    10 passed (24 assertions)
  Duration:  0.60s
```

That's it. No server to start, no port to occupy, no process to kill.

---

## Option B — Manual curl (optional, for exploring the API)

### Start the server

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Keep this terminal open. Use a second terminal for the curl commands.

> Remember to press `Ctrl+C` to stop the server when done.

### Test 1 — List active adjustment reasons

```bash
curl http://127.0.0.1:8000/api/adjustment-reasons
```

**Expected:** 4 active reasons. The inactive reason must not appear.

### Test 2 — Create an inventory adjustment (100 → 92)

```bash
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":1,\"adjustment_reason_id\":1,\"new_quantity\":92,\"note\":\"stocktake found 8 missing\"}"
```

**Expected (201):**
- `old_quantity` = 100
- `new_quantity` = 92
- `quantity_diff` = -8
- `batch.current_quantity` = 92 (updated in the same transaction)

### Test 3 — View adjustment detail

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Expected (200):** nested `batch`, `batch.product`, `batch.warehouse`, `reason`.

### Error cases

```bash
# Inactive reason -> 422
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":2,\"adjustment_reason_id\":5,\"new_quantity\":40}"

# Non-existent batch -> 422
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":999,\"adjustment_reason_id\":1,\"new_quantity\":50}"

# Non-existent adjustment -> 404
curl http://127.0.0.1:8000/api/inventory-adjustments/999
```

---

## Test Coverage Summary

| # | Scenario | Expected |
|---|---|---|
| 1 | List active reasons only | 200, inactive hidden |
| 2 | Create adjustment 100→92 | 201, diff=-8, batch updated |
| 3 | View adjustment detail | 200, relations loaded |
| 4 | Inactive reason rejected | 422, batch unchanged |
| 5 | Non-existent batch rejected | 422 |
| 6 | Negative quantity rejected | 422 |
| 7 | Missing new_quantity rejected | 422 |
| 8 | Non-existent adjustment | 404 |
