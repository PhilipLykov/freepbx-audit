# API Reference -- AJAX Endpoints

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-alpha    |
| Date     | 21-02-2026                       |
| Status   | Draft                            |
| Audience | Developers                       |

---

## Overview

All endpoints are served through the FreePBX AJAX gateway at:

```
POST /admin/ajax.php?module=auditcompliance&command=<command>
```

Content type: `application/x-www-form-urlencoded` (standard FreePBX AJAX convention).
Response type: `application/json`.

### Authentication

| Endpoint | Authentication | RBAC |
|----------|---------------|------|
| `recordLogout` | Session required (`changesession` enabled) | None (any authenticated admin) |
| `recordAuthFailure` | **None** (`authenticate=false`) | IP rate-limited |
| `recordInterceptedAjax` | Session required (standard BMO) | None (any authenticated admin) |
| `searchEvents` | Session required | `checkSection('auditcompliance')` |
| `exportEvents` | Session required | `checkSection('auditcompliance')` + export rate limit |
| `getFilterValues` | Session required | `checkSection('auditcompliance')` |
| `getDashboardStats` | Session required | `checkSection('auditcompliance')` |

---

## 1. `recordLogout`

Records an explicit logout event for the current admin session.

**Trigger**: Called automatically by the injected logout interceptor JavaScript when the admin
clicks a `logout=true` link.

### Request

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| (none) | -- | -- | Session context provides all data |

### Response

```json
{
  "status": true,
  "message": "Logout recorded"
}
```

### Error Responses

| Condition | Response |
|-----------|----------|
| No active audit session | `{"status": false, "message": "No active audit session"}` |
| DB write failure | `{"status": false, "message": "Audit write failed"}` |

---

## 2. `recordAuthFailure`

Records an authentication failure event. This endpoint is unauthenticated by design
(called from the login page before a session exists).

### Request

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `username` | string | Yes | Trimmed, max 128 chars | The attempted username |

### Response

```json
{
  "status": true,
  "message": "Auth failure recorded"
}
```

### Error Responses

| Condition | Response |
|-----------|----------|
| Empty username | `{"status": false, "message": "No username provided"}` |
| Rate limited (>20 failures from same IP in 60s) | `{"status": false, "message": "Rate limited"}` |
| DB write failure | `{"status": false, "message": "Audit write failed"}` |

### Rate Limiting

Maximum **20 auth failure events** per source IP per **60-second** sliding window. Enforced
via database query on `audit_events` where `session_phase = 'failure'`.

---

## 3. `recordInterceptedAjax`

Receives metadata from the universal client-side AJAX interceptor. The interceptor monitors
all `XMLHttpRequest` calls to `ajax.php` for modules other than `auditcompliance`.

### Request

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `target_module` | string | Yes | Trimmed, max 128 chars; must not be `auditcompliance` | The target module name |
| `target_command` | string | No | Trimmed, max 128 chars | The AJAX command |
| `target_method` | string | No | Uppercased; default `POST` | HTTP method (POST/PUT/DELETE) |
| `target_url` | string | No | Trimmed, max 2048 chars | The original request URL |
| `http_status` | integer | No | Cast to int; default 200 | HTTP response status code |

### Response

```json
{
  "status": true,
  "message": "AJAX action recorded"
}
```

### Error Responses

| Condition | Response |
|-----------|----------|
| Empty or self-referencing module | `{"status": false, "message": "Skipped"}` |
| No active audit session | `{"status": false, "message": "No active audit session"}` |

### Notes

- Events recorded through this endpoint have `channel = 'ajax'`.
- The outcome is determined by HTTP status: 2xx/3xx = `success`, 4xx/5xx = `failure`.
- Subject to cross-channel deduplication (3-second window).

---

## 4. `searchEvents`

Searches audit events with multi-dimensional filters, sorting, and pagination.

### Request

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `search_text` | string | No | Max 2048 chars; `%` and `_` escaped for LIKE with explicit `ESCAPE '\'` | Free-text search across module, action, actor, object_type, object_id |
| `actor` | string | No | Exact match | Filter by actor username |
| `module_name` | string | No | Exact match | Filter by module name |
| `action_filter` | string | No | Exact match | Filter by action |
| `channel` | string | No | Exact match | Filter by channel (gui/ajax/hook/auth/rest) |
| `outcome` | string | No | Exact match | Filter by outcome (success/failure) |
| `session_phase` | string | No | Exact match | Filter by phase (login/activity/logout/timeout/failure) |
| `source_ip` | string | No | Exact match | Filter by source IP |
| `date_from` | string | No | Format: `YYYY-MM-DD`; parsed in Europe/Chisinau timezone at 00:00:00 | Start date (inclusive) |
| `date_to` | string | No | Format: `YYYY-MM-DD`; parsed in Europe/Chisinau timezone at 23:59:59 | End date (inclusive) |
| `sort` | string | No | Allowlist: `occurred_at_unix`, `actor`, `module_name`, `action`, `channel` | Sort field (default: `occurred_at_unix`) |
| `sort_dir` | string | No | `ASC` or `DESC` (default: `DESC`) | Sort direction |
| `limit` | integer | No | Range: 1-200 (default: 50); sanitized and inlined into SQL | Page size |
| `offset` | integer | No | Min: 0 (default: 0); sanitized and inlined into SQL | Pagination offset |

### Response

```json
{
  "rows": [
    {
      "event_id": "evt_a1b2c3...",
      "session_id": "sess_d4e5f6...",
      "session_phase": "activity",
      "channel": "gui",
      "module_name": "core",
      "action": "update",
      "outcome": "success",
      "route": "core",
      "object_type": "core",
      "object_id": "1001",
      "actor": "admin",
      "source_ip": "192.168.1.10",
      "request_method": "POST",
      "request_uri": "/admin/config.php?display=extensions&...",
      "change_before": null,
      "change_after": null,
      "change_added": "{}",
      "change_removed": "{}",
      "change_changed": "{\"extension\":\"1001\",\"name\":\"John Doe\",\"password\":\"***REDACTED***\"}",
      "occurred_at_unix": 1740000000,
      "occurred_at_utc": "20-02-2026 10:00:00",
      "occurred_at_local": "20-02-2026 12:00:00"
    }
  ],
  "total": 1,
  "limit": 50,
  "offset": 0
}
```

---

## 5. `exportEvents`

Exports matching events in bulk (up to 5,000 rows). Uses the same filter parameters as
`searchEvents` but without pagination.

### Request

Same filter parameters as `searchEvents` (excluding `sort`, `sort_dir`, `limit`, `offset`).

### Response

```json
{
  "export": [ /* array of event objects, same schema as searchEvents rows */ ],
  "total": 150
}
```

### Rate Limiting

**10-second cooldown** between export requests per session. Enforced via
`$_SESSION['auditcompliance_export_last']`.

### Error Responses

| Condition | Response |
|-----------|----------|
| Rate limited | `{"status": false, "message": "Export rate limit exceeded, wait 10 seconds"}` |

---

## 6. `getFilterValues`

Returns distinct values for a specified column, used to populate filter dropdowns.

### Request

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `column` | string | Yes | Allowlist: `actor`, `module_name`, `action`, `channel`, `outcome`, `session_phase`, `source_ip` | Column to retrieve distinct values for |

### Response

```json
{
  "values": ["admin", "operator", "readonly_admin"]
}
```

### Notes

- Results are limited to **500 distinct values** per column.
- Sorted alphabetically ascending.

---

## 7. `getDashboardStats`

Returns aggregated statistics for the Dashboard KPI cards, recent activity feed, top actors,
and channel breakdown.

### Request

No parameters required.

### Response

```json
{
  "events_today": 42,
  "events_total": 12500,
  "active_sessions": 2,
  "auth_failures_24h": 3,
  "sensitive_reads_24h": 15,
  "top_actors": [
    {"actor": "admin", "cnt": "28"},
    {"actor": "operator", "cnt": "14"}
  ],
  "channel_breakdown": [
    {"channel": "gui", "cnt": "20"},
    {"channel": "ajax", "cnt": "15"},
    {"channel": "hook", "cnt": "5"},
    {"channel": "auth", "cnt": "2"}
  ],
  "recent_events": [
    {
      "event_id": "evt_...",
      "session_phase": "activity",
      "channel": "gui",
      "module_name": "core",
      "action": "update",
      "outcome": "success",
      "actor": "admin",
      "source_ip": "192.168.1.10",
      "occurred_at_unix": 1740000000,
      "occurred_at_local": "20-02-2026 12:00:00"
    }
  ],
  "timestamp": "20-02-2026 12:05:00"
}
```

### Notes

- `events_today` uses midnight in `Europe/Chisinau` timezone as the day boundary.
- `top_actors` returns the top 5 actors by activity event count for today.
- `recent_events` returns the 15 most recent events across all channels.
- The Dashboard auto-refreshes this endpoint every **30 seconds**.
