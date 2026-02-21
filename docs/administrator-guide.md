# Administrator Guide

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-alpha    |
| Date     | 20-02-2026                       |
| Status   | Draft                            |
| Audience | Administrators                   |

---

## Accessing the Module

Navigate to **Reports > Audit Compliance** in the FreePBX Web GUI. The module requires the
`auditcompliance` section permission -- admins without this permission will see an "Access
denied" message.

The module has four views, accessible via tabs:

1. **Dashboard** -- At-a-glance overview (default landing page)
2. **Search** -- Multi-dimensional event search and export
3. **Session Timeline** -- Chronological session-grouped view
4. **Module Discovery** -- Installed module audit coverage report

---

## 1. Dashboard

The Dashboard is the default view and provides a real-time overview of audit activity.

### KPI Cards

Five cards are displayed across the top:

| Card | Metric | Data Source |
|------|--------|-------------|
| Events Today | Total audit events since midnight (Europe/Chisinau) | All channels |
| Active Sessions | Currently open admin sessions | Sessions with `end_reason = 'active'` |
| Auth Failures (24h) | Failed login attempts in the last 24 hours | Events with `session_phase = 'failure'` |
| Sensitive Reads (24h) | Sensitive page views in the last 24 hours | GUI events with actions ending in `_access` |
| Total Audit Events | Lifetime event count | All events in the database |

If Auth Failures > 0, the card gains a red left border as a visual alert.

### Recent Activity Feed

Displays the 15 most recent events with:

- Channel icon (color-coded by channel type)
- Actor name, action, and outcome badge
- Module name and channel label
- Relative timestamp (e.g., "2m ago") with full timestamp on hover

### Sidebar Panels

- **Top Actors Today** -- The 5 most active administrators today, ranked by event count.
- **Channels Today** -- Horizontal bar chart showing the distribution of events by capture
  channel (GUI, AJAX, Hook, Auth).
- **Quick Actions** -- Links to Search, Session Timeline, Auth Failures preset, and Module
  Discovery.

### Auto-Refresh

The Dashboard refreshes automatically every **30 seconds**. No manual action is required.

---

## 2. Search

The Search view provides full-text and filtered searching across all audit events.

### Quick Search Bar

The top bar provides immediate access to the most common filters:

- **Search** -- Free-text search across module name, action, actor, object type, and object ID.
- **From / To** -- Date range filter (calendar date pickers).
- **Search button** -- Executes the search.
- **Reset button** (X icon) -- Clears all filters and reloads.

### Advanced Filters

Click **Advanced Filters** to expand the detailed filter panel:

| Filter | Description | Type |
|--------|-------------|------|
| Actor | Exact match on admin username | Text input |
| Module | Filter by module name | Dropdown (auto-populated) |
| Action | Exact match on action name | Text input |
| Channel | Filter by capture channel | Dropdown: GUI, AJAX, REST, Hook, Auth |
| Outcome | Filter by result | Dropdown: Success, Failure |
| Phase | Filter by session phase | Dropdown: Login, Activity, Logout, Timeout, Failure |
| Source IP | Exact match on client IP | Text input |

### Presets

Access presets via URL parameters:

- `?display=auditcompliance&view=search&preset=failures` -- Pre-fills Phase=Failure and
  Outcome=Failure; opens Advanced Filters automatically.

### Sorting

Click any column header to sort. Click again to reverse direction. Sortable columns:

- Time (default, descending)
- Actor
- Channel
- Module
- Action

The active sort column and direction are indicated by an arrow icon.

### Event Detail

Click any row to expand its detail panel, showing:

- Event ID, Session ID, Route, HTTP Method, Request URI, UTC Time
- **Change Detail** section with color-coded labels:
  - Orange: **Changed** fields
  - Green: **Added** fields
  - Red: **Removed** fields
- JSON payloads are formatted for readability.

### Keyboard Navigation

| Key | Action |
|-----|--------|
| `Enter` | Execute search (when a filter field is focused) |
| `Arrow Up` / `Arrow Down` | Navigate between result rows |
| `Space` | Toggle detail panel for the selected row |

### Pagination

Results are displayed 50 per page. Use the Previous/Next buttons or the page indicator
("Page 1 of 5 (1-50 of 234)") to navigate.

---

## 3. Session Timeline

The Session Timeline groups events by admin session, providing a narrative view of
"who did what when."

### Timeline Layout

Sessions are displayed as expandable cards along a vertical timeline. Each card shows:

- **Actor** -- Admin username with user icon
- **Login time** -- Local timestamp of session start
- **Source IP** -- Client IP address
- **Duration** -- Session length (e.g., "1h 23m"), displayed for closed sessions
- **Event count** badge -- Number of events in the session
- **Status badge** -- Active (green), Logout (blue), or Timeout (orange)

### Expanding Sessions

Click a session card header to expand/collapse its event list. By default:

- If 3 or fewer sessions are displayed, all are expanded automatically.
- If more than 3 sessions, only the first (most recent) is expanded.

### Event List

Within each session, events are listed chronologically with:

- Colored dot indicating phase (green=login, blue=logout, orange=timeout, red=failure,
  grey=activity)
- Timestamp
- Module name and action (for activity events)
- Channel badge and outcome badge
- Boundary events (login/logout/timeout) are highlighted with a colored left border

### Session Footer

Closed sessions show a footer with the end timestamp and total duration.

### Actor Filter

Use the filter bar above the timeline to filter by actor username. Type a name and click
**Filter**. Click **Clear** to remove the filter.

### Authentication Failures

If auth failures exist, a red banner appears above the timeline showing each failure with
its timestamp, attempted username, and source IP.

---

## 4. Module Discovery

The Module Discovery view shows all installed FreePBX/pbxACT modules and their audit
coverage level.

### Summary Statistics

Five cards at the top show:

| Card | Description |
|------|-------------|
| Total Modules | Count of all active modules |
| With AJAX Handler | Modules that have an `ajaxHandler()` method |
| With processHooks | Modules that call `processHooks()` |
| With API/REST | Modules with REST or GraphQL API paths |
| Commercial | Modules identified as commercial/pbxACT |

### Module Table

Each row shows:

| Column | Description |
|--------|-------------|
| Module | Module raw name |
| Version | Installed version |
| Type | OSS or COMMERCIAL badge |
| GUI | Number of GUI pages |
| AJAX | Whether the module has an AJAX handler |
| API | Whether the module has REST/GraphQL APIs |
| Hooks | Whether the module calls processHooks() |
| Audit Hook | Whether our module has explicit hooks for it |
| Sens. Read | Whether the module page is monitored for sensitive reads |
| Coverage | Tier badge: FULL, GUI+AJAX+READ, GUI+READ, GUI+AJAX, or GUI ONLY |

### Coverage Tiers

| Tier | Meaning |
|------|---------|
| **FULL** | Explicit BMO hooks + GUI + AJAX interceptor + sensitive read (if applicable) |
| **GUI+AJAX+READ** | GUI POST capture + AJAX interceptor + sensitive read monitoring |
| **GUI+READ** | GUI POST capture + sensitive read monitoring (no AJAX handler) |
| **GUI+AJAX** | GUI POST capture + AJAX interceptor |
| **GUI ONLY** | GUI POST capture only (no AJAX handler, no sensitive data) |

### Live Filter

Type in the filter bar to search for modules by name. The table updates instantly.

### Timestamp

The bottom-right shows when the discovery scan was performed.

---

## 5. Export

### Formats

- **CSV** -- Comma-separated values with quoted fields. Columns: local time, UTC time, actor,
  channel, module, action, outcome, phase, object type, object ID, source IP, session ID,
  event ID, route, method, request URI.
- **JSON** -- Array of event objects with all fields.

### Limits

- Maximum **5,000 rows** per export.
- **10-second cooldown** between export requests.

### Procedure

1. Set desired filters in the Search view.
2. Click **CSV** or **JSON** in the export bar.
3. The browser downloads the file automatically.
4. Filename format: `audit_events_YYYY-MM-DD_HHMMSS.csv` (or `.json`).

---

## Date and Time Format

All timestamps in the module use the format:

```
DD-MM-YYYY HH:MM:SS
```

Local time zone: **Europe/Chisinau** (UTC+2 / UTC+3 depending on DST).

Both UTC and local timestamps are stored for every event and session.
