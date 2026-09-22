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
  ✓ allows zero diff when count matches
  ✓ returns 404 for nonexistent adjustment
  ✓ view adjustment detail with relations
  ✓ rejects reason with wrong applies to
  ✓ re adjust reads current quantity as old
  ✓ rejects non integer quantity
  ✓ history still readable when reason later deactivated

  Tests:    15 passed (39 assertions)
  Duration:  0.7s
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

> **Note:** In Git Bash on Windows, write the JSON body as a single line to avoid quote-escaping issues.

### Test 1 — List active adjustment reasons

```bash
curl http://127.0.0.1:8000/api/adjustment-reasons
```

**Expected:** 4 active reasons. The inactive reason and the price-adjust reason must not appear.

### Test 2 — Create an inventory adjustment (100 → 92)

```bash
curl -i -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":1,"adjustment_reason_id":1,"new_quantity":92,"note":"stocktake found 8 missing"}'
```

**Expected (HTTP 201 Created):**
- `old_quantity` = 100
- `new_quantity` = 92
- `quantity_diff` = -8
- `batch.current_quantity` = 92 (updated in the same transaction)

### Test 3 — View adjustment detail

```bash
curl http://127.0.0.1:8000/api/inventory-adjustments/1
```

**Expected (HTTP 200):** nested `batch`, `batch.product`, `batch.warehouse`, `reason`.

### Error cases

```bash
# Inactive reason -> 422
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":5,"new_quantity":40}'

# Reason with wrong applies_to (price_adjust) -> 422
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":6,"new_quantity":40}'

# Non-existent batch -> 422
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":999,"adjustment_reason_id":1,"new_quantity":50}'

# Negative quantity -> 422
curl -X POST http://127.0.0.1:8000/api/inventory-adjustments -H "Content-Type: application/json" -d '{"batch_id":2,"adjustment_reason_id":1,"new_quantity":-5}'

# Non-existent adjustment -> 404
curl http://127.0.0.1:8000/api/inventory-adjustments/999
```

---

## Test Coverage Summary

| # | Scenario | Expected |
|---|---|---|
| 1 | List active reasons only | 200, inactive and wrong-applies-to hidden |
| 2 | Create adjustment 100→92 | 201, diff=-8, batch updated |
| 3 | View adjustment detail | 200, nested relations loaded |
| 4 | Inactive reason rejected | 422, batch unchanged |
| 5 | Non-existent batch rejected | 422 |
| 6 | Negative quantity rejected | 422 |
| 7 | Missing new_quantity rejected | 422 |
| 8 | Non-integer quantity rejected | 422 |
| 9 | Zero diff (count matches system) | 201, diff=0 |
| 10 | Reason with wrong applies_to rejected | 422, batch unchanged |
| 11 | Re-adjust reads current qty as old | 201, old = previous new |
| 12 | Non-existent adjustment | 404 |
| 13 | History still readable after reason deactivated | 200, reason data intact |
