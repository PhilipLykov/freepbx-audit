# Troubleshooting Guide

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-alpha    |
| Date     | 21-02-2026                       |
| Status   | Draft                            |
| Audience | Administrators, Operations       |

---

## Symptom-Based Diagnostics

### No events appearing in the Search view

| Check | Command / Action | Expected |
|-------|-----------------|----------|
| Module is enabled | `fwconsole ma list \| grep auditcompliance` | Status: `Enabled` |
| Tables exist | `mysql -u audit_writer -p auditcompliance -e "SELECT COUNT(*) FROM audit_events;"` | Returns a number (0 or more) |
| DB connection works | Check FreePBX log for `auditcompliance` errors: `grep auditcompliance /var/log/asterisk/freepbx.log \| tail -20` | No connection errors |
| DSN is configured | `fwconsole setting AUDITCOMPLIANCE_DB_DSN` | Non-empty DSN string (or empty for local DB) |
| RBAC allows access | Admin > Administrators > check `auditcompliance` section permission | Enabled for your account |
| Perform a test action | Make a change in any module (e.g., edit an extension), then search | Event appears |

### Dashboard shows all zeros

| Cause | Fix |
|-------|-----|
| Module just installed, no events yet | Perform admin actions and check again |
| AJAX `getDashboardStats` failing | Open browser Developer Tools > Network tab > check for errors on `ajax.php?...getDashboardStats` |
| DB connection error | Check logs: `grep 'Dashboard stats query failed' /var/log/asterisk/freepbx.log` |

### Export fails or produces empty file

| Cause | Fix |
|-------|-----|
| Rate limited (too quick) | Wait 10 seconds and try again |
| No matching events | Adjust filters to match existing data |
| Browser blocks download | Check browser download settings / popup blocker |
| AJAX error | Open browser Developer Tools > Console for JavaScript errors |

### Database connection error on startup

| Symptom | Cause | Fix |
|---------|-------|-----|
| `TLS is required for MySQL/MariaDB audit DB connections` | DSN missing SSL parameters | Add SSL parameters to DSN or set `AUDITCOMPLIANCE_DB_REQUIRE_TLS` to `0` (dev only) |
| `TLS is required for PostgreSQL audit DB connections` | DSN missing `sslmode=` | Add `sslmode=require` (or stricter) to DSN |
| `SQLSTATE[HY000] [2002] Connection refused` | DB server unreachable | Check network, firewall, DB server status |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong credentials | Verify `AUDITCOMPLIANCE_DB_USER` and `AUDITCOMPLIANCE_DB_PASSWORD` |

### Module not loading or blank page

| Check | Command | Expected |
|-------|---------|----------|
| PHP syntax errors | `php -l /var/www/html/admin/modules/auditcompliance/Auditcompliance.class.php` | `No syntax errors detected` |
| FreePBX error log | `tail -50 /var/log/asterisk/freepbx.log` | No fatal errors from auditcompliance |
| Module file permissions | `ls -la /var/www/html/admin/modules/auditcompliance/` | Readable by web server user (asterisk) |
| Framework version | `fwconsole framework --version` | >= 17.0.1 |

### Immutability triggers missing

| Check | Command |
|-------|---------|
| MariaDB/MySQL | `mysql -u root -p auditcompliance -e "SHOW TRIGGERS LIKE 'audit_%';"` |
| PostgreSQL | `psql -U postgres auditcompliance -c "SELECT tgname FROM pg_trigger WHERE tgname LIKE 'trg_audit%';"` |

If triggers are missing, the module will recreate them on the next request. Force it by
temporarily renaming the `audit_events` table (DBA action) or reinstalling the module.

### Session timeline shows no events inside sessions

| Cause | Fix |
|-------|-----|
| Events exist but `session_id` does not match | Check if multiple session IDs are generated per login (session regeneration issue) |
| Events in database but not displayed | Verify `getSessionEventsBatch()` query runs: check for DB errors in logs |

---

## Diagnostic Commands

### Check module status

```bash
fwconsole ma list | grep auditcompliance
```

### Check audit database tables

```bash
# MariaDB
mysql -u audit_writer -p -h audit-db.example.com auditcompliance -e "SHOW TABLES;"

# PostgreSQL
psql -U audit_writer -h audit-db.example.com auditcompliance -c "\dt"
```

### Count events and sessions

```bash
mysql -u audit_writer -p auditcompliance -e "
  SELECT 'events' AS type, COUNT(*) AS cnt FROM audit_events
  UNION ALL
  SELECT 'sessions', COUNT(*) FROM audit_sessions
  UNION ALL
  SELECT 'active_sessions', COUNT(*) FROM audit_sessions WHERE end_reason='active';
"
```

### Check recent errors in FreePBX log

```bash
grep -i 'auditcompliance\|audit_' /var/log/asterisk/freepbx.log | tail -30
```

### Verify DB user permissions

```bash
# MariaDB
mysql -u root -p -e "SHOW GRANTS FOR 'audit_writer'@'%';"

# PostgreSQL
psql -U postgres auditcompliance -c "
  SELECT privilege_type FROM information_schema.table_privileges
  WHERE grantee='audit_writer' AND table_name='audit_events';
"
```

---

## Recently Resolved Issues

### Code review round 1 (21-02-2026)

| Issue | Impact | Resolution |
|-------|--------|------------|
| LIMIT/OFFSET as PDO bound params | `SQLSTATE[42000]` syntax error on MySQL with `ATTR_EMULATE_PREPARES = true` (PHP < 8.1) | Pagination values inlined as sanitized integers |
| Missing LIKE ESCAPE clause | Free-text search `\%` / `\_` escapes ignored by PostgreSQL (`standard_conforming_strings = on`) | Explicit `ESCAPE '\'` added to all LIKE clauses |
| Dashboard `_access` wildcard | `_` in `%_access` treated as SQL single-char wildcard, potentially over-counting sensitive reads | Escaped to `%\_access` with `ESCAPE '\'` |
| TLS silently disabled | `getConfig()` returning `false` bypassed null coalesce, yielding `requireTls = false` | New `getConfigSafe()` helper defaults to TLS enabled |
| Dashboard missing tab nav | Dashboard was the only view without the 4-tab navigation bar | Tab bar added, consistent with all other views |
| `setDefaultConfigIfMissing` | `false` return from `getConfig()` not checked, preventing install-time defaults | Added `false` check to conditional |

### FreePBX 17 conformance and code review round 2 (20-02-2026)

| Issue | Impact | Resolution |
|-------|--------|------------|
| Contactmanager `addEntry` hook mismatch | Hook for `addEntry` would never fire on FreePBX 17 (method renamed to `addEntryByGroupID`) | Renamed hook to `hookContactmanager_addEntryByGroupID` in both `module.xml` and PHP |
| Sipsettings in `BEFORE_STATE_READERS` | `Sipsettings::getConfig()` is a generic BMO helper, not a SIP-specific getter | Removed from `BEFORE_STATE_READERS` |
| Missing GET-based delete capture | Ring Group deletes (and similar) triggered via GET redirects were not audited | Added `captureGuiGetActionEvent()` for state-changing GET actions |
| Modules calling `exit()` before audit | Trunks, Misc Destinations call `redirect_standard()`/`exit()` before audit hook completes | `register_shutdown_function` safety net with deduplication flag |
| `eventCapturedThisRequest` property placement | Property declared between docblock and method, could lead to stale state if BMO cached | Moved to class property section with explicit per-request reset |
| Indentation inconsistency in `searchAuditEvents` | Filter block used one-tab indent inside a two-tab `try` block | Corrected to consistent two-tab indentation |
| Indentation inconsistency in CSRF check | `page.auditcompliance.php` CSRF block missing one indent level | Corrected nesting indentation |
| Missing `submit` in `STATE_CHANGING_PREFIXES` | Paging module uses `submit` as action name, not captured as state-changing | Added `submit`, `copy`, `duplicate` to prefixes |
| Missing sensitive-read pages | `calendargroups` and `logfiles_settings` pages not monitored | Added to `SENSITIVE_READ_PAGES` |
| PHP 8.2 `DateTime::getLastErrors()` returning `false` | `parseDateInput()` could fail on PHP 8.2+ | Added `false` check alongside empty array check |

---

## Known Limitations

| Limitation | Description | Mitigation |
|-----------|-------------|------------|
| REST/GraphQL API calls not captured | Direct API calls (not through admin GUI) bypass all capture channels | Document as out of GUI audit scope; use API gateway logging |
| Auth failure recording requires login page JS | The `recordAuthFailure` endpoint handler exists but login page integration requires a deployment snippet | Deploy the login page JS snippet per deployment guide |
| Session idle timeout is approximate | The idle timeout is checked on the next page load, not in real-time | Acceptable for compliance; real-time enforcement is a framework responsibility |
| Stale session close uses login time | `closeStaleActiveSessions()` compares against `login_at_unix`, not last activity (which is only in PHP session, not DB) | Sessions are correctly closed; the only impact is timeout vs. logout classification |
| GUI-only modules (no AJAX) have limited capture | 8 modules with form-only UI have no AJAX interceptor coverage | All GUI POST submissions are captured; these modules simply have no AJAX surface |
| Maximum 5,000 rows per export | Enforced to prevent browser/server overload | Use direct SQL queries for larger exports |
| CLI discovery tool `gui_pages` approximation | Counts `<menuitems>` XML tags (typically 1 per module) rather than individual menu item children | In-module `discoverModuleSurfaces()` uses the correct `count($modData['items'])` |

---

## Frequently Asked Questions

### Q: Does this module modify any FreePBX core files?

No. The module uses only standard BMO hooks, `doConfigPageInit()`, and `module.xml` hook
declarations. No native FreePBX/pbxACT files are modified.

### Q: Will disabling the module affect FreePBX operation?

No. Disabling or uninstalling the module immediately stops all audit capture but has no
impact on FreePBX functionality. See [Rollback Guide](rollback-guide.md).

### Q: Is audit data deleted when the module is uninstalled?

No. Remote audit database data is preserved. Only local FreePBX config entries are removed.
See [Rollback Guide](rollback-guide.md) for data preservation details.

### Q: Can administrators delete or modify audit records?

No. Database triggers prevent UPDATE and DELETE on `audit_events`. DELETE is also blocked on
`audit_sessions`. The database user account only has INSERT and SELECT permissions.

### Q: How much storage does the audit log consume?

Approximately 1-2 KB per event. At 100 admin actions per day, expect ~50-75 MB per year.
See [Operations Runbook](operations-runbook.md) for capacity planning.

### Q: What happens if the audit database is unreachable?

The module catches all database errors in try/catch blocks. Audit write failures are logged
to the FreePBX error log but do not crash or slow down the FreePBX GUI. Events occurring
during a database outage are lost.

### Q: Are passwords stored in the audit log?

No. All sensitive fields (passwords, tokens, API keys, private keys, PINs) are replaced
with `***REDACTED***` before persistence. See [Data Classification](data-classification-redaction.md).
