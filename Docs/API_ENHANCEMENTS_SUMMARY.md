# BarcodeBuddy API Enhancements - Implementation Summary

**Date:** February 7, 2026
**Status:** ✅ Complete

## Overview

Enhanced BarcodeBuddy API with 4 new/improved endpoints to support iOS app integration for managing unresolved barcodes.

---

## Changes Made

### 1. Enhanced GET `/api/system/unknownbarcodes`

**Changes:**
- Now returns **both** "known" (looked up) and "unknown" (not looked up) barcodes
- Excludes tare barcodes (requireWeight=1)
- Enriched response with additional fields
- Compatible with existing `lookup` parameter for OpenFoodFacts integration

**New Response Fields:**
```json
{
  "data": {
    "count": 2,
    "barcodes": [
      {
        "id": 1,                          // NEW: Database ID
        "barcode": "TEST123",
        "amount": 1,
        "name": "Organic Oat Milk",       // NEW: Product name or null
        "possibleMatch": 42,              // NEW: Grocy product ID match
        "isLookedUp": true,               // NEW: Whether lookup succeeded
        "bestBeforeInDays": 7,            // NEW: Best before days
        "price": "2.99",                  // NEW: Price from lookup
        "altNames": null,                 // NEW: Alternative names
        "product_info": {...}             // EXISTING: Optional OpenFoodFacts data (if lookup=true)
      }
    ]
  }
}
```

**Test:**
```bash
curl -s -H "BBUDDY-API-KEY: <your-key>" \
  http://localhost:9280/api/system/unknownbarcodes | python3 -m json.tool

# With OpenFoodFacts lookup
curl -s -H "BBUDDY-API-KEY: <your-key>" \
  "http://localhost:9280/api/system/unknownbarcodes?lookup=true" | python3 -m json.tool
```

---

### 2. New DELETE `/api/system/unknownbarcodes/{id}`

**Purpose:** Remove/dismiss a barcode from the unresolved list using its database ID

**Features:**
- RESTful DELETE endpoint with path parameter
- Validates ID is a positive integer
- Returns 404 if barcode not found
- Returns 400 for invalid ID

**Response:**
```json
{
  "data": {
    "deleted": 1
  },
  "result": {
    "result": "OK",
    "http_code": 200
  }
}
```

**Test:**
```bash
curl -s -X DELETE -H "BBUDDY-API-KEY: <your-key>" \
  http://localhost:9280/api/system/unknownbarcodes/1 | python3 -m json.tool
```

**Note:** This complements the existing `/action/deleteunknown` endpoint which deletes by barcode string.

---

### 3. New POST `/api/system/unknownbarcodes/{id}/associate`

**Purpose:** Associate a barcode with a Grocy product using database ID

**Features:**
- RESTful POST endpoint with path parameter
- Validates barcode exists in BB database
- Validates product exists in Grocy (via `API::getProductInfo()`)
- Adds barcode to Grocy product (via `API::addBarcode()`)
- Deletes barcode from BB database on success
- Returns 500 if Grocy API fails (barcode NOT deleted)

**Request:**
```bash
curl -s -X POST -H "BBUDDY-API-KEY: <your-key>" \
  -d "productId=42" \
  http://localhost:9280/api/system/unknownbarcodes/1/associate | python3 -m json.tool
```

**Response:**
```json
{
  "data": {
    "associated": true,
    "barcodeId": 1,
    "barcode": "TEST123",
    "productId": 42
  },
  "result": {
    "result": "OK",
    "http_code": 200
  }
}
```

**Error Responses:**
- 400: Invalid barcode ID or product ID
- 404: Barcode not found in BB or product not found in Grocy
- 500: Failed to associate (Grocy API error)

**Note:** This complements the existing `/action/associatebarcode` endpoint which associates by barcode string and also adds stock.

---

### 4. New GET `/api/system/barcodelogs`

**Purpose:** Return processed barcode history

**Features:**
- Optional `limit` query parameter (default: 50, min: 1, max: 200)
- Returns logs with both `id` and `log` fields
- Ordered by ID descending (newest first)

**Response:**
```json
{
  "data": {
    "count": 2,
    "logs": [
      {
        "id": 125,
        "log": "Barcode 123456 processed: Added to product Oat Milk"
      },
      {
        "id": 124,
        "log": "Unknown barcode looked up, found name: Organic Oat Milk"
      }
    ]
  }
}
```

**Test:**
```bash
# Get last 10 logs
curl -s -H "BBUDDY-API-KEY: <your-key>" \
  "http://localhost:9280/api/system/barcodelogs?limit=10" | python3 -m json.tool

# Get default 50 logs
curl -s -H "BBUDDY-API-KEY: <your-key>" \
  http://localhost:9280/api/system/barcodelogs | python3 -m json.tool
```

---

## Code Changes

### Files Modified

1. **[api/index.php](../api/index.php)**
   - Enhanced routing system to support RESTful path parameters (`{id}`)
   - Updated `BBuddyApi::execute()` to handle pattern matching
   - Updated `ApiRoute` class with pattern matching and HTTP method filtering
   - Enhanced `/system/unknownbarcodes` route to combine known + unknown barcodes with enriched fields
   - Added DELETE `/system/unknownbarcodes/{id}` route
   - Added POST `/system/unknownbarcodes/{id}/associate` route
   - Added GET `/system/barcodelogs` route

2. **[incl/db.inc.php](../incl/db.inc.php)**
   - Added new method `getLogsWithId(int $limit)`
   - Keeps vanilla `getLogs()` method intact for backward compatibility

3. **[openapi.json](../openapi.json)**
   - Updated `/system/unknownbarcodes` GET schema with enriched fields
   - Added `/system/unknownbarcodes/{id}` DELETE endpoint
   - Added `/system/unknownbarcodes/{id}/associate` POST endpoint
   - Added `/system/barcodelogs` GET endpoint
   - *(Updated by upstream with additional `/action/*` endpoints)*

---

## Architecture Enhancements

### Pattern Matching Router

Enhanced the routing system to support RESTful path parameters without breaking existing exact-match routes:

**New `ApiRoute` Features:**
- `matches(string $url)`: Pattern matching with HTTP method validation
- `extractParams(string $url)`: Extracts path parameters
- Constructor accepts optional HTTP method filter (GET, POST, DELETE, etc.)
- Converts `{param}` placeholders to regex patterns

**Routing Logic:**
1. Try exact path match first (backward compatible)
2. Fall back to pattern matching for parameterized routes
3. Return 404 if no match found

**Example:**
```php
new ApiRoute("/system/unknownbarcodes/{id}", function ($id) {
    // $id is automatically extracted from URL
}, "DELETE")
```

---

## Comparison: RESTful vs Action Endpoints

This implementation adds RESTful endpoints that complement the existing action-based endpoints:

| Feature | RESTful Endpoints (New) | Action Endpoints (Existing) |
|---------|------------------------|----------------------------|
| Identify by | Database ID | Barcode string |
| URL style | `/system/unknownbarcodes/{id}` | `/action/deleteunknown?barcode=...` |
| HTTP methods | Semantic (DELETE, POST) | Always POST/GET |
| Stock handling | No automatic stock add | `/action/associatebarcode` adds stock |
| Product creation | No | `/action/createandassociate` creates product |

**Both approaches are valid** and serve different use cases:
- RESTful endpoints: Better for ID-based operations, cleaner URLs
- Action endpoints: Better for barcode-string operations, more features (stock, product creation)

---

## Testing Checklist

When you have access to a running BarcodeBuddy instance, test these scenarios:

### GET `/api/system/unknownbarcodes`
- [ ] Returns both known and unknown barcodes
- [ ] Excludes tare barcodes
- [ ] All integer fields are actual integers (not strings)
- [ ] `name` is null for unknown barcodes
- [ ] `isLookedUp` is true/false correctly
- [ ] Optional `?lookup=true` parameter adds `product_info` field

### DELETE `/api/system/unknownbarcodes/{id}`
- [ ] Successfully deletes existing barcode
- [ ] Returns 404 for non-existent barcode
- [ ] Returns 400 for invalid ID (non-numeric, zero, negative)

### POST `/api/system/unknownbarcodes/{id}/associate`
- [ ] Successfully associates barcode with Grocy product
- [ ] Deletes barcode from BB after successful association
- [ ] Returns 404 if barcode doesn't exist in BB
- [ ] Returns 404 if product doesn't exist in Grocy
- [ ] Returns 400 for missing/invalid productId
- [ ] Returns 500 if Grocy API fails (barcode preserved)

### GET `/api/system/barcodelogs`
- [ ] Returns logs with id and log fields
- [ ] Defaults to 50 logs
- [ ] Respects limit parameter
- [ ] Enforces max limit of 200
- [ ] Enforces min limit of 1
- [ ] Logs are ordered newest first

---

## Authentication

All endpoints require the `BBUDDY-API-KEY` header:

```bash
curl -H "BBUDDY-API-KEY: your_api_key_here" http://localhost:9280/api/...
```

Or via query parameter:
```bash
curl "http://localhost:9280/api/...?apikey=your_api_key_here"
```

---

## Notes for iOS App Integration

### Workflow

1. **Fetch unresolved barcodes:**
   ```
   GET /api/system/unknownbarcodes
   ```

2. **User selects a barcode and picks a Grocy product:**
   - If `possibleMatch` is set, suggest that product first
   - Use Grocy API directly to search/list products

3. **Associate barcode with product:**
   ```
   POST /api/system/unknownbarcodes/{id}/associate
   productId=<selected_product_id>
   ```

4. **Or dismiss barcode:**
   ```
   DELETE /api/system/unknownbarcodes/{id}
   ```

5. **View processing history:**
   ```
   GET /api/system/barcodelogs?limit=20
   ```

### Data Model Recommendations

**Swift Model for Barcode:**
```swift
struct BBUnknownBarcode: Codable, Identifiable {
    let id: Int
    let barcode: String
    let amount: Int
    let name: String?
    let possibleMatch: Int?
    let isLookedUp: Bool
    let bestBeforeInDays: Int?
    let price: String?
    let altNames: [String]?
    let productInfo: BBProductInfo?  // Optional, only if lookup=true
}
```

---

## Backward Compatibility

✅ All changes are backward compatible:

- Existing API routes unchanged and still functional
- Old `/system/unknownbarcodes` still works (just returns more data)
- New endpoints added alongside existing `/action/*` endpoints
- No database schema changes
- New database method doesn't affect existing code
- OpenAPI spec updated (additive changes only)

---

## Next Steps

1. Deploy to your BarcodeBuddy instance
2. Test all endpoints with actual data
3. Integrate with iOS Grocy-SwiftUI app
4. Monitor for any issues

---

**Questions or Issues?**

If you encounter any problems during testing, check:
- PHP error logs
- API response error messages
- Grocy API connectivity
- Database permissions
