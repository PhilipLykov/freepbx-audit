# Changelog

All notable changes to the Audit Compliance module for FreePBX/pbxACT are documented in this file.

This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) once a stable release is published.

## [Unreleased]

### Added

- **ODBC database connection support** via `pdo_odbc`. The module can now connect to the audit database through Linux system ODBC data sources (`unixODBC`), enabling centralized driver-level TLS management and compliance with enterprise ODBC-only policies.
- New config key `audit_db_odbc_backend` (`mysql` / `pgsql`) to explicitly specify the database engine behind an ODBC connection when auto-detection is insufficient.
- Automatic ODBC backend detection via `SELECT version()` and `PDO::ATTR_SERVER_VERSION` heuristics, with fallback to `mysql`.
- **Dashboard tab navigation** -- the Dashboard view now includes the standard 4-tab navigation bar consistent with Search, Timeline, and Discovery views.
- `getConfigSafe()` internal helper for resilient config key retrieval with explicit default values.
- **Shutdown capture safety net** -- `register_shutdown_function` captures events when modules call `redirect_standard()` or `exit()` before the audit hook completes (e.g., Trunks, Misc Destinations). Covers both POST and GET state-changing requests with deduplication via `eventCapturedThisRequest` flag.
- **GET-based state-changing action capture** -- modules that trigger deletes, copies, or other state changes via GET requests (e.g., Ring Groups delete via `action=delGRP`) are now audited through `captureGuiGetActionEvent()`. Expanded `STATE_CHANGING_PREFIXES` with `copy`, `duplicate`, and `submit`.
- **Self-referential change baseline** -- the module stores processed POST data (`change_after`) with each event. Subsequent edits to the same object use this as the "before" state for reliable before/after diffs, eliminating dependency on FreePBX DB reads during hook execution.
- **Semantic value normalization** -- `valuesAreDifferent()`, `areBothFalsy()`, and `normalizeListValue()` eliminate false-positive diffs caused by format differences (e.g., `0` vs `""`, `\n`-separated vs `-`-separated lists).
- **Noise key filtering** -- `DIFF_SKIP_KEYS` constant filters out framework fields (`display`, `action`, `Submit`, CSRF tokens, `goto0`-`goto2`, `delete`, `tech`, `orig_account`, `entries`, `module_hook`) from change diffs to show only meaningful changes.
- **23 sensitive-read pages** -- added `calendargroups` (`calendar_credentials_access`) and `logfiles_settings` (`system_log_access`) to the sensitive page registry.
- **Settings GUI** -- full graphical settings page for configuring audit database connection (Direct MySQL/MariaDB, Direct PostgreSQL, ODBC), with connection test, input validation, and CSRF protection.
- **Apply Config event capture** -- multi-layered detection for FreePBX "Apply Config" button presses, including JavaScript interception of `ajax.php?command=reload`.
- **Expanded object ID detection** -- `detectObjectId()` now recognizes `pagenbr`, `announcement_id`, `callrecording_id`, `channel`, `orig_account`, `trunknum`, and additional module-specific ID fields.

### Changed

- **Contactmanager hook renamed** -- `hookContactmanager_addEntry` changed to `hookContactmanager_addEntryByGroupID` to match FreePBX 17 Contactmanager API (`addEntryByGroupID` method).
- **Sipsettings removed from `BEFORE_STATE_READERS`** -- `Sipsettings::getConfig()` is a generic BMO `DB_Helper` method, not a SIP-specific getter; removed to prevent incorrect before-state reads.
- **`eventCapturedThisRequest` property** relocated to class property section with explicit per-request reset in `doConfigPageInit()`.

### Fixed

- **LIMIT/OFFSET MySQL compatibility** -- pagination parameters (`LIMIT`, `OFFSET`) are now inlined as sanitized integers instead of bound PDO parameters. Prevents `SQLSTATE[42000]` syntax errors on MySQL/MariaDB with `PDO::ATTR_EMULATE_PREPARES = true` (the default on PHP < 8.1).
- **PostgreSQL LIKE escape compatibility** -- all `LIKE` clauses in `searchAuditEvents()` and `handleDashboardStatsAjax()` now include an explicit `ESCAPE '\'` clause, required by PostgreSQL when `standard_conforming_strings = on` (default since PostgreSQL 9.1).
- **Dashboard sensitive reads count** -- the `%_access` pattern in the `sensitive_reads_24h` dashboard query now escapes the `_` wildcard (`%\_access`) to prevent false matches where `_` was treated as a single-character SQL wildcard.
- **TLS default when config returns `false`** -- `getConfig()` can return `false` (not `null`) for non-existent keys. The null coalesce operator `??` does not catch `false`, which silently disabled TLS enforcement. All config reads now use the new `getConfigSafe()` helper that handles `null`, `false`, and empty string correctly, defaulting `audit_db_require_tls` to `'1'` (enabled).
- **`setDefaultConfigIfMissing()` false handling** -- the install-time config bootstrapper now checks for `false` in addition to `null` and empty string when deciding whether to set a default value.
- **Indentation consistency** -- corrected indentation in `searchAuditEvents()` filter block and `page.auditcompliance.php` CSRF validation block.
- **PHP 8.2 compatibility** -- `parseDateInput()` correctly handles `DateTime::getLastErrors()` returning `false` instead of an empty array.
- **First-event diff display** -- when no prior baseline exists for an object, the full filtered POST data is shown as "Added Fields" instead of an empty diff.
- **Legacy noise in diffs** -- `filterNoiseKeys()` applied to historical `change_after` data on retrieval, ensuring consistent filtering between old and new states.

### Security

- TLS enforcement can no longer be silently disabled by a `false` return from the FreePBX config store, closing a potential downgrade vector on first-run or config corruption scenarios.
- Settings page uses independent CSRF token generation and `hash_equals()` validation.
- Shutdown capture function uses snapshots of `$_REQUEST` and `$_SERVER` to avoid referencing mutable globals after exit.

## [0.1.0-alpha] - 20-02-2026

### Added

- **Multi-channel event capture** -- GUI POST, universal AJAX interceptor, BMO hooks, auth boundary events.
- **Universal AJAX interceptor** -- Client-side JavaScript patches `XMLHttpRequest` to monitor all `ajax.php` POST/PUT/DELETE calls for any module, closing AJAX coverage gaps without per-module adapters.
- **38 BMO hook handlers** across 10 modules (core, userman, backup, certman, voicemail, timeconditions, contactmanager, ucp, calendar, bulkhandler) for deep method-level write interception.
- **23 sensitive-read page monitors** covering CDR, recordings, user credentials, certificates, voicemail, conferences, contacts, queues, AMI, SIP settings, log files, log file settings, ARI, file store, calendar, calendar groups, fax, PIN sets, Superfecta, XMPP, phonebook, blacklist, and CEL.
- **Immutable append-only storage** with database triggers preventing UPDATE/DELETE on `audit_events` and DELETE on `audit_sessions`.
- **Session-grouped timeline** correlating events by admin session with explicit login, logout, timeout, and auth-failure boundary events.
- **Remote database support** for MariaDB 10.5+ and PostgreSQL 14+ via TLS-enforced PDO connections.
- **Dashboard view** with KPI cards (events today, active sessions, auth failures, sensitive reads, total events), recent activity feed, top actors, and channel breakdown with 30-second auto-refresh.
- **Search view** with quick search bar, collapsible advanced filters, sortable columns, pagination, keyboard navigation, event detail expansion, and presets (e.g., `?preset=failures`).
- **Session Timeline view** with visual timeline connector, expandable session cards, session duration display, and authentication failure banner.
- **Module Discovery view** enumerating installed modules and their audit surfaces with 5-tier coverage classification, summary statistics, and live module filter.
- **Export** in CSV and JSON formats with 5,000-row cap and 10-second rate limiting.
- **RBAC** via FreePBX section-based permission checks on page load and all AJAX endpoints.
- **Sensitive data redaction** using three match strategies (substring, exact, suffix) to mask passwords, tokens, API keys, private keys, PINs, and credentials before persistence.
- **Cross-channel deduplication** within a 3-second window on `(session_id, module_name, action, object_id)` to prevent duplicate event recording when the same action fires through multiple channels.
- **CLI discovery tool** (`tools/discover-pbxact-surfaces.php`) for server-side module surface enumeration with JSON, CSV, and human-readable output.
- **DB role hardening scripts** for MariaDB/MySQL and PostgreSQL (`tests/db-role-hardening-mysql.sql`, `tests/db-role-hardening-pgsql.sql`).

### Security

- All database queries use prepared statements (LIMIT/OFFSET inlined as sanitized integers for MySQL emulated prepare compatibility).
- Sort and filter column names validated against strict allowlists.
- All view output escaped via `htmlspecialchars()` and JavaScript `createTextNode()` / `esc()`.
- TLS enforcement on remote audit database connections via DSN validation.
- IP-based rate limiting on unauthenticated auth-failure recording endpoint (20 per IP per 60-second window).
- Export rate limiting (10-second cooldown per session).
- CSRF protection inherited from FreePBX framework session management.
- No outbound HTTP requests from user-supplied input (SSRF prevention).

### Documentation

- Deployment guide with MariaDB/PostgreSQL setup, CLI/GUI installation, verification, RBAC, and networking.
- STRIDE + OWASP Top 10 threat model.
- Data retention and compliance notes covering GDPR, SOX, HIPAA, PCI DSS, and ISO 27001.
- Data classification and redaction matrix.
- Module coverage matrix with per-module tier classification for 52+ modules.
- Rollback guide with disable/uninstall procedures and data preservation notes.
- Upstream FreePBX analysis of 80 repositories.
- Security test plan with OWASP test cases, AJAX interceptor validation, and coverage gate checks.

[Unreleased]: https://github.com/PhilipLykov/freepbx-audit/compare/v0.1.0-alpha...HEAD
[0.1.0-alpha]: https://github.com/PhilipLykov/freepbx-audit/releases/tag/v0.1.0-alpha
