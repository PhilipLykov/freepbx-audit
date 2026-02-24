# Security Test Plan — Audit Compliance Module

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-beta1            |
| Date     | 25-02-2026                               |
| Status   | Beta                                     |
| Audience | Developers, QA, Security Officers        |

---

## Scope

Covers OWASP Top 10 controls applied to the `auditcompliance` module for FreePBX 17/pbxACT.
Includes universal capture architecture validation (AJAX interceptor, deduplication, sensitive reads).

## Test Matrix

### A01 — Broken Access Control

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| AC-01 | Unauthenticated access to `page.auditcompliance.php` | Direct URL without session | `No direct script access allowed` or login redirect | Design: `FREEPBX_IS_AUTH` guard |
| AC-02 | Authenticated user without `auditcompliance` section permission | Login as restricted admin, navigate to module | `Access denied` message, no data returned | Design: `checkSection('auditcompliance')` in page + AJAX |
| AC-03 | AJAX `searchEvents` without permission | POST to `ajax.php?module=auditcompliance&command=searchEvents` as restricted user | 403 `ajaxRequest declined` | Design: `hasAuditViewPermission()` in `ajaxRequest` |
| AC-04 | AJAX `exportEvents` without permission | POST as restricted user | 403 `ajaxRequest declined` | Design: `hasAuditViewPermission()` in `ajaxRequest` |
| AC-05 | Export rate limiting | Rapid-fire export requests (< 10s interval) | Second request blocked with rate limit message | Design: `checkExportRateLimit()` 10s cooldown |
| AC-06 | `recordAuthFailure` accepts unauthenticated requests | POST without session | Allowed (by design — `authenticate=false`) but validates input | Design: validates `username` not empty |
| AC-07 | `recordInterceptedAjax` requires authenticated session | POST without session | Rejected (requires authentication, default) | Design: standard BMO auth |

### A02 — Cryptographic Failures

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| CF-01 | TLS required for remote audit DB | Configure DSN without ssl/sslmode | Exception thrown before connection | Design: `validateDsnSecurity()` |
| CF-02 | No secrets in audit event payloads | Submit form with password fields | `***REDACTED***` in stored `change_changed` JSON | Design: `redactSensitiveData()` |
| CF-03 | Password never stored in session keys | Inspect `$_SESSION` after module operations | No credential data in audit session keys | Design: only UUIDs and timestamps |

### A03 — Injection

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| INJ-01 | SQL injection via actor filter | `?actor=' OR 1=1 --` in search | No error, zero results (parameterized query) | Design: all queries use prepared statements |
| INJ-02 | SQL injection via search_text | `search_text=%'; DROP TABLE audit_events; --` | No error, LIKE search treats as literal | Design: `str_replace` escapes `%` and `_`, prepared statement |
| INJ-03 | SQL injection via sort field | `sort=occurred_at_unix; DROP TABLE` | Ignored — falls back to default sort | Design: `allowedSort` allowlist check |
| INJ-04 | SQL injection via getFilterValues column | `column=actor; DROP TABLE` | Empty array returned | Design: `$allowed` allowlist |
| INJ-05 | XSS via intercepted AJAX target_module | `target_module=<script>alert(1)</script>` | Stored truncated, displayed escaped | Design: `htmlspecialchars` in all views |
| INJ-06 | XSS via search results in GUI | Malicious values in event fields | All output escaped via `esc()` helper and DOM text nodes | Design: `htmlspecialchars` + JS `createTextNode` |

### A05 — Security Misconfiguration

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| MC-01 | DB error messages not exposed to UI | Force DB connection failure | Generic error in logs, no stack trace in UI | Design: `try/catch` with `debugLog()` |
| MC-02 | Default audit DB falls back to local | No `audit_db_dsn` configured | Uses FreePBX internal DB (development only) | Design: `getAuditDb()` fallback |

### A07 — Identification and Authentication Failures

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| ID-01 | Actor attribution from session | Perform action as known user | `actor` field = `$_SESSION['AMP_user']->username` | Design: `getActor()` |
| ID-02 | Unknown actor handling | Edge case with missing session data | `actor` = `unknown`, event still recorded | Design: `getActor()` fallback |
| ID-03 | Auth failure without valid session | Call `recordAuthFailure` AJAX | Event created with `authfail_` session ID | Design: `authenticate=false` |

### A09 — Security Logging and Monitoring Failures

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| LM-01 | All events have timestamp + actor + outcome | Query all event types | Every row has non-null fields | Design: mandatory in `routeEvent()` |
| LM-02 | Failed audit writes are logged | Force DB write failure | `debugLog()` records with timestamp | Design: `try/catch` blocks |
| LM-03 | Audit events cannot be modified | `UPDATE audit_events SET ...` | `SQLSTATE 45000: Audit tables are append-only` | Design: `trg_audit_events_no_update` |
| LM-04 | Audit events cannot be deleted | `DELETE FROM audit_events` | `SQLSTATE 45000: Audit tables are append-only` | Design: `trg_audit_events_no_delete` |
| LM-05 | Session records cannot be deleted | `DELETE FROM audit_sessions` | `SQLSTATE 45000: Audit tables are append-only` | Design: `trg_audit_sessions_no_delete` |

### A10 — Server-Side Request Forgery

| # | Test Case | Method | Expected Result | Status |
|---|-----------|--------|-----------------|--------|
| SSRF-01 | No URL fetch from user input | Review all code paths | Module never fetches external URLs from user-supplied data | Design: no HTTP client on user input |

## Universal Capture Architecture Tests

### AJAX Interceptor Validation

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| AI-01 | Firewall AJAX operation (`addnetworktozone`) | Click "Add Network" in Firewall GUI | Event recorded with `channel=ajax`, `module_name=firewall`, `action=addnetworktozone` |
| AI-02 | Backup AJAX operation (`runBackup`) | Trigger backup from GUI | Event recorded with `channel=ajax`, `module_name=backup`, `action=runBackup` |
| AI-03 | Recordings AJAX save | Save recording via GUI | Event recorded with `channel=ajax`, `module_name=recordings`, `action=save` |
| AI-04 | Framework AJAX reload | Apply Config | Event recorded with `channel=ajax`, `module_name=framework`, `action=reload` |
| AI-05 | Self-exclusion | Our own AJAX calls | NOT recorded (module=auditcompliance filtered out) |
| AI-06 | GET requests ignored | AJAX GET to any module | NOT recorded (only POST/PUT/DELETE intercepted) |
| AI-07 | Blacklist add via AJAX | Add a number to blacklist | Event with `channel=ajax`, `module_name=blacklist`, `action=add` |
| AI-08 | Superfecta update via AJAX | Save Superfecta scheme | Event with `channel=ajax`, `module_name=superfecta`, `action=update_scheme` |
| AI-09 | Sound language install via AJAX | Install language pack | Event with `channel=ajax`, `module_name=soundlang`, `action=install` |
| AI-10 | Music category delete via AJAX | Delete MOH category | Event with `channel=ajax`, `module_name=music`, `action=deleteCategory` |
| AI-11 | Logfile read via AJAX | Read log file from GUI | Event with `channel=ajax`, `module_name=logfiles`, `action=log_file_read` |
| AI-12 | ARI manager update via AJAX | Update ARI user | Event with `channel=ajax`, `module_name=arimanager`, `action=update` |
| AI-13 | Dashboard content save via AJAX | Reorder dashboard widgets | Event with `channel=ajax`, `module_name=dashboard`, `action=saveorder` |
| AI-14 | Calendar event delete via AJAX | Delete calendar event | Event with `channel=ajax`, `module_name=calendar`, `action=delevent` |

### Deduplication Validation

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| DD-01 | GUI POST + Hook fire simultaneously | Edit extension (triggers doConfigPageInit + hookCore_addDevice) | Only ONE event recorded (dedup window 3s) |
| DD-02 | Sequential distinct actions | Edit extension, then edit trunk | Both events recorded (different module/action) |
| DD-03 | Same action after dedup window | Edit same extension, wait 5s, edit again | Both events recorded (outside 3s window) |

### Sensitive Read Validation (23 pages)

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| SR-01 | CDR page access | Navigate to CDR module | Event with `action=cdr_access`, `channel=gui` |
| SR-02 | Recordings page access | Navigate to recordings | Event with `action=recording_access`, `channel=gui` |
| SR-03 | User manager page access | Navigate to userman | Event with `action=user_credentials_access`, `channel=gui` |
| SR-04 | Certificate manager page access | Navigate to certman | Event with `action=certificate_access`, `channel=gui` |
| SR-05 | Non-sensitive page access (GET) | Navigate to any non-sensitive module | NO event recorded (not in sensitive registry) |
| SR-06 | Voicemail page access | Navigate to voicemail | Event with `action=voicemail_access`, `channel=gui` |
| SR-07 | Conference page access | Navigate to conferences | Event with `action=conference_pin_access`, `channel=gui` |
| SR-08 | Contact manager page access | Navigate to contactmanager | Event with `action=contact_data_access`, `channel=gui` |
| SR-09 | Queue page access | Navigate to queues | Event with `action=queue_credentials_access`, `channel=gui` |
| SR-10 | AMI Manager page access | Navigate to manager | Event with `action=ami_credentials_access`, `channel=gui` |
| SR-11 | SIP Settings page access | Navigate to sipsettings | Event with `action=sip_credentials_access`, `channel=gui` |
| SR-12 | Log Files page access | Navigate to logfiles | Event with `action=system_log_access`, `channel=gui` |
| SR-13 | ARI Manager page access | Navigate to arimanager | Event with `action=ari_credentials_access`, `channel=gui` |
| SR-14 | File Store page access | Navigate to filestore | Event with `action=storage_credentials_access`, `channel=gui` |
| SR-15 | Calendar page access | Navigate to calendar | Event with `action=calendar_credentials_access`, `channel=gui` |
| SR-16 | Fax settings page access | Navigate to fax | Event with `action=fax_settings_access`, `channel=gui` |
| SR-17 | PIN Sets page access | Navigate to pinsets | Event with `action=pin_credentials_access`, `channel=gui` |
| SR-18 | Superfecta page access | Navigate to superfecta | Event with `action=callerid_config_access`, `channel=gui` |
| SR-19 | XMPP page access | Navigate to xmpp | Event with `action=xmpp_credentials_access`, `channel=gui` |
| SR-20 | Phonebook page access | Navigate to phonebook | Event with `action=phonebook_personal_access`, `channel=gui` |
| SR-21 | Blacklist page access | Navigate to blacklist | Event with `action=blacklist_personal_access`, `channel=gui` |
| SR-22 | CEL page access | Navigate to cel | Event with `action=cel_data_access`, `channel=gui` |
| SR-23 | Day/Night page access | Navigate to daynight | Event with `action=daynight_credentials_access`, `channel=gui` |
| SR-24 | Calendar Groups page access | Navigate to calendargroups | Event with `action=calendar_credentials_access`, `channel=gui` |
| SR-25 | Log Files Settings page access | Navigate to logfiles_settings | Event with `action=system_log_access`, `channel=gui` |

### GET-Based State-Changing Action Tests

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| GA-01 | Delete Ring Group via action bar | Click delete on a Ring Group | Event recorded with `channel=gui`, `action=delGRP`, `request_method=GET` |
| GA-02 | Delete Announcement via action bar | Click delete on an Announcement | Event recorded with `channel=gui`, state-changing action detected |
| GA-03 | Delete Misc Destination | Click delete on Misc Destination | Event recorded (may fire via shutdown capture if module exits early) |
| GA-04 | Delete Trunk | Delete a trunk via action bar | Event recorded (shutdown capture safety net) |
| GA-05 | Copy/duplicate action | Use copy/duplicate feature where available | Event recorded with action prefix `copy` or `duplicate` |

### Shutdown Capture Safety Net Tests

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| SC-01 | Module that calls redirect_standard() | Edit/delete a Trunk | Event captured via shutdown function |
| SC-02 | Module that calls exit() early | Edit/delete a Misc Destination | Event captured via shutdown function |
| SC-03 | Normal module (no early exit) | Edit an Extension | Event captured via primary handler (NOT shutdown); `eventCapturedThisRequest=true` prevents double-log |
| SC-04 | Deduplication between primary and shutdown | Edit normally | Only ONE event recorded, not two |

### Change Diff Validation Tests

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| CD-01 | First edit of new object | Create a Ring Group, then edit it | First event shows "Added Fields" (no prior baseline); second event shows specific changes |
| CD-02 | Subsequent edit shows diff | Edit same Ring Group twice | Second edit shows "Changed" fields with old → new values |
| CD-03 | Noise keys filtered | Edit any object | `display`, `action`, `Submit`, CSRF tokens not in diff output |
| CD-04 | Semantic normalization | Change value from empty to `0` | NOT shown as a change (both are "falsy") |
| CD-05 | Sensitive fields redacted in diffs | Change a password field | `***REDACTED***` in diff, not the actual password |

## DB Immutability Evidence

### MariaDB/MySQL

```sql
SHOW TRIGGERS LIKE 'audit_%';

-- Must fail:
UPDATE audit_events SET action = 'tampered' WHERE event_id = 'evt_test';
-- Expected: ERROR 1644 (45000): Audit tables are append-only

DELETE FROM audit_events WHERE event_id = 'evt_test';
-- Expected: ERROR 1644 (45000): Audit tables are append-only

DELETE FROM audit_sessions WHERE session_id = 'sess_test';
-- Expected: ERROR 1644 (45000): Audit tables are append-only

-- Must succeed:
INSERT INTO audit_events (...) VALUES (...);
-- Expected: success
```

### PostgreSQL

```sql
SELECT tgname, tgrelid::regclass FROM pg_trigger WHERE tgname LIKE 'trg_audit%';

-- Must fail:
UPDATE audit_events SET action = 'tampered' WHERE event_id = 'evt_test';
DELETE FROM audit_events WHERE event_id = 'evt_test';
DELETE FROM audit_sessions WHERE session_id = 'sess_test';
-- Expected: ERROR: Audit tables are append-only
```

## Redaction Evidence

Fields are redacted (`***REDACTED***`) based on three matching rules applied to field key names:

**Substring match** (key contains):
- `password`, `passwd`, `secret`, `api_key`, `private_key`, `access_token`, `refresh_token`, `credential`, `privatekey`, `tlskey`, `tlsprivate`, `ampmgrpass`, `fcc_password`, `turnpassword`

**Exact match** (key equals):
- `pass`, `pin`, `userpin`, `adminpin`, `token`, `oauth_secret`, `oauth_token`, `cert_key`, `tls_cert_key`

**Suffix match** (key ends with):
- `_pass`, `_pin`, `_secret`, `_token`, `_key`, `_cert_pem`, `_private`, `_privkey`

This avoids false positives on keys like `cert_id`, `pinsets_id`, `oauth_provider`, `certificate_name` which are non-sensitive metadata.

Scalar values truncated to 2048 characters.

## Coverage Gate Checks

| # | Gate Check | Method | Pass Criteria |
|---|-----------|--------|---------------|
| CG-01 | myConfigPageInits returns all pages | Call method, compare with active module list | All active module display names included |
| CG-02 | module.xml hooks resolve | `fwconsole hooks --list` | All 38 hook methods across 10 modules listed |
| CG-03 | AJAX interceptor active on all pages | Inspect page source on 5 different module pages | JS interceptor present |
| CG-04 | Discovery tool runs clean | `php discover-pbxact-surfaces.php --json` | Valid JSON output, all modules enumerated |
| CG-05 | No new uncovered modules | Discovery output | No module with unexpected surface gaps |
| CG-06 | All 23 sensitive read pages | Navigate to each page in the sensitive registry | GET events recorded for all 23 pages |
| CG-07 | Tier-2 hook integration | Perform CRUD on timeconditions, contactmanager, UCP | Events recorded with `channel=hook` |

### Tier-2 Hook Integration Tests

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| T2-01 | Add time condition | Create a time condition via GUI | Event with `channel=hook`, `module_name=timeconditions`, `action=addTimeCondition` |
| T2-02 | Edit time condition | Modify a time condition | Event with `action=editTimeCondition` |
| T2-03 | Delete time condition | Delete a time condition | Event with `action=delTimeCondition` |
| T2-04 | Add contact group | Create contact group via contact manager | Event with `channel=hook`, `module_name=contactmanager`, `action=addGroup` |
| T2-05 | Add contact entry | Create contact entry | Event with `action=addEntryByGroupID` |
| T2-06 | Delete contact entry | Delete a contact entry | Event with `action=deleteEntryByID` |
| T2-07 | Add UCP user | Create UCP user | Event with `channel=hook`, `module_name=ucp`, `action=addUser` |
| T2-08 | Update UCP group | Modify UCP group | Event with `action=updateGroup` |
| T2-09 | Calendar sync | Trigger calendar sync | Event with `channel=hook`, `module_name=calendar`, `action=sync` |
| T2-10 | Bulk import | Run bulk handler import | Event with `channel=hook`, `module_name=bulkhandler`, `action=import` |

### Cross-Database Compatibility Tests

| # | Test Case | Method | Expected Result |
|---|-----------|--------|-----------------|
| DB-01 | LIMIT/OFFSET on MySQL emulated prepares | Search events with PHP < 8.1 and `ATTR_EMULATE_PREPARES = true` | Pagination works without `SQLSTATE[42000]` errors |
| DB-02 | LIKE ESCAPE on PostgreSQL | Search for text containing `%` or `_` characters on PostgreSQL | Results correctly filter; wildcards treated as literals |
| DB-03 | Sensitive reads count accuracy | Dashboard `sensitive_reads_24h` on PostgreSQL and MySQL | Count matches actual events with `action` ending in literal `_access` |
| DB-04 | Config store returns `false` | Temporarily remove `audit_db_require_tls` config key | TLS defaults to enabled; connection to remote DB enforces TLS |
| DB-05 | Config store returns `false` for ODBC backend | Temporarily remove `audit_db_odbc_backend` key when using ODBC | Auto-detection runs; falls back to `mysql` gracefully |

## Execution Notes

- Tests executed on staging FreePBX 17 / pbxACT instance.
- DB immutability requires direct SQL access.
- RBAC tests require admin accounts with varying permissions.
- All timestamps in `DD-MM-YYYY HH:MM:SS` format, timezone `Europe/Chisinau`.
- AJAX interceptor tests require browser with JS enabled (standard admin workflow).
- Cross-database tests require both MySQL/MariaDB and PostgreSQL staging environments.
