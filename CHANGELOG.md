# Changelog

All notable changes to the Audit Compliance module for FreePBX/pbxACT are documented in this file.

This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) once a stable release is published.

## [Unreleased]

### Added

- **Create/delete action clarity** -- POST events that have no prior audit baseline and use a generic action (`update`, `save`, `submit`) are automatically labeled `create`, making entity creation events easily distinguishable from edits.
- **Entity name resolution for all actions** -- `resolveObjectId()` resolves opaque IDs (numeric, UUID, or composite) to human-readable names for ALL event types (create, edit, delete, sensitive reads), not just create/add actions. Session-based entity cache (`ENTITY_CACHE_MAP`) covers 33 modules: Userman, IVR, Time Conditions, Announcements, Trunks, Contact Manager, Certman, Backup, Calendar, Calendar Groups, Custom Destinations, Custom Extensions, Ring Groups, Queues, Conferences, Paging, Parking, Misc Destinations, Misc Applications, Day/Night (Call Flow Control), PIN Sets, Languages, Music on Hold, Call Recording, DISA, Outbound Routes, Inbound Routes (with composite key support), Callback, CID Lookup, System Recordings, Text to Speech, Set CallerID, and Superfecta. Cache is populated on every non-state-changing GET page view and after create events.
- **Create-event name resolution** -- POST events labeled `create` or `add` now refresh the entity cache and resolve the object ID to its canonical (DB-stored) name, ensuring consistent naming between create and subsequent delete/edit events.
- **Destination selector noise filtering** -- `filterNoiseKeys()` now strips FreePBX destination selector fields (e.g., `Announcements0`, `Ring Groups0`, `Extensions0`) by pattern-matching keys that start with an uppercase letter and end with a digit.
- **Hook noise suppression** -- hooks that fire as internal sub-operations (e.g. `core::addUser`, `core::addDevice`, `contactmanager::addEntryByGroupID` during extension creation) are now suppressed when the primary GUI or AJAX event is already captured. During `config.php` requests hooks are fully suppressed (the GUI channel provides complete change details); during `ajax.php` requests only the first hook per PHP request is recorded.
- **Cross-channel AJAX dedup** -- `hasRecentHookEventForModule()` prevents the JS AJAX interceptor from recording a duplicate event when a BMO hook has already captured the same operation within the dedup window. Apply Config events are exempted from this check.
- **Expanded core AJAX read-only list** -- added `getextensiongrid`, `getdevicegrid`, `getusergrid`, `getnpanxxjson`, and `populatenpanxx` to the `AJAX_READ_ONLY_COMMANDS` for the `core` module, verified against FreePBX 17 `Core.class.php` source.
- **ODBC database connection support** via `pdo_odbc`. The module can now connect to the audit database through Linux system ODBC data sources (`unixODBC`), enabling centralized driver-level TLS management and compliance with enterprise ODBC-only policies.
- New config key `audit_db_odbc_backend` (`mysql` / `pgsql`) to explicitly specify the database engine behind an ODBC connection when auto-detection is insufficient.
- Automatic ODBC backend detection via `SELECT version()` and `PDO::ATTR_SERVER_VERSION` heuristics, with fallback to `mysql`.
- **Dashboard tab navigation** -- the Dashboard view now includes the standard 4-tab navigation bar consistent with Search, Timeline, and Discovery views.
- `getConfigSafe()` internal helper for resilient config key retrieval with explicit default values.
- **Universal shutdown safety net** -- `register_shutdown_function` registered in the constructor (before any module's `doConfigPageInit` runs) captures events from modules that call `redirect_standard()` / `exit()` (e.g. Misc Destinations, Misc Applications) or nullify `$_REQUEST['action']` after processing (e.g. Misc Applications). The constructor also saves a snapshot of `$_REQUEST` and the original `action` value before any module can modify them. Covers both POST and GET state-changing requests with deduplication via `eventCapturedThisRequest` flag.
- **GET-based state-changing action capture** -- modules that trigger deletes, copies, or other state changes via GET requests (e.g., Ring Groups delete via `action=delGRP`) are now audited through `captureGuiGetActionEvent()`. Expanded `STATE_CHANGING_PREFIXES` with `copy`, `duplicate`, and `submit`.
- **Self-referential change baseline** -- the module stores processed POST data (`change_after`) with each event. Subsequent edits to the same object use this as the "before" state for reliable before/after diffs, eliminating dependency on FreePBX DB reads during hook execution.
- **Semantic value normalization** -- `valuesAreDifferent()`, `areBothFalsy()`, and `normalizeListValue()` eliminate false-positive diffs caused by format differences (e.g., `0` vs `""`, `\n`-separated vs `-`-separated lists).
- **Noise key filtering** -- `DIFF_SKIP_KEYS` constant filters out framework fields (`display`, `action`, `Submit`, CSRF tokens, `goto0`-`goto2`, `delete`, `tech`, `orig_account`, `entries`, `module_hook`) from change diffs to show only meaningful changes.
- **20 sensitive-read pages** -- covers CDR, recordings, CEL, Userman, Certman, AMI Manager, ARI Manager, Filestore, XMPP, Calendar, Calendar Groups, Voicemail, Conferences, PIN Sets, DISA, Contact Manager, Phonebook, Log Files, Log File Settings, and Superfecta.
- **Settings GUI** -- full graphical settings page for configuring audit database connection (Direct MySQL/MariaDB, Direct PostgreSQL, ODBC), with connection test, input validation, and CSRF protection.
- **Apply Config event capture** -- multi-layered detection for FreePBX "Apply Config" button presses, including JavaScript interception of `ajax.php?command=reload`.
- **Expanded object ID detection** -- `detectObjectId()` now recognizes `pagenbr`, `announcement_id`, `callrecording_id`, `channel`, `orig_account`, `trunknum`, `destid`, `custom_exten`, `old_custom_exten`, `language_id`, `page_group`, `route_id`, `disa_id`, `number`, `oldval`, `speeddial`, `callback_id`, `cidlookup_id`, `cid_id`, `priority_id`, and additional module-specific ID fields. All three ID extraction methods (GUI, shutdown, AJAX) use the constructor-time `$_REQUEST` snapshot so IDs survive modules that nullify `$_REQUEST` after processing.
- **Misc Destinations & Misc Applications support** -- Both modules are now fully audited. Miscdests uses `redirect_standard()` → `exit()` before our hook fires; captured by the universal constructor-level shutdown safety net. Miscapps nullifies `$_REQUEST['action']` after processing; the original action is preserved from the constructor-time snapshot. BMO method hooks for `Miscdests::add/del/update` remain as CLI/cron fallback.
- **Misc Applications entity cache** -- `Miscapps::listApps()` added to `ENTITY_CACHE_MAP` with `miscapps_id` in all object ID candidate lists.
- **Extended entity cache for delete visibility** -- added Callback (`listCallbacks`), CID Lookup (`getList`), System Recordings (`getAll`), TTS (`listTTS`), Set CallerID (`getAll`), and Superfecta (`getAllSchemes`) to `ENTITY_CACHE_MAP` (now 33 modules), ensuring delete events for these modules display human-readable names instead of raw numeric IDs.
- **Allowlist/Blacklist AJAX enrichment** -- JS interceptor now extracts `number`, `description`, `oldval`, and `numbers` (JSON array for bulkdelete) from AJAX POST bodies. PHP `buildAjaxChangePayload()` includes these fields in change details.
- **Feature code noise reduction** -- `filterNoiseKeys()` detects the `fc[module][feature][...]` nested array from Feature Code Admin and flattens it via `flattenFeatureCodes()`, showing only enabled or customized codes instead of the full 50+ entry form submission.
- **`ALWAYS_UPDATE_MODULES` list** -- modules that always modify existing global/system settings (featurecodeadmin, advancedsettings, sipsettings, iaxsettings, dahdiconfig, soundlang, pm2, sysadmin) are never falsely relabeled as `create` when no prior baseline exists.

### Changed

- **`resolveNumericObjectId` renamed to `resolveObjectId`** -- now resolves any opaque ID (numeric, UUID, or typed name), using the session entity cache with case-insensitive value matching for consistent naming.
- **Entity caching moved to `doConfigPageInit`** -- caching now triggers on every non-state-changing GET page view, not just sensitive read pages, ensuring entity names are available for subsequent delete events.
- **Contactmanager hook renamed** -- `hookContactmanager_addEntry` changed to `hookContactmanager_addEntryByGroupID` to match FreePBX 17 Contactmanager API (`addEntryByGroupID` method).
- **Sipsettings removed from `BEFORE_STATE_READERS`** -- `Sipsettings::getConfig()` is a generic BMO `DB_Helper` method, not a SIP-specific getter; removed to prevent incorrect before-state reads.
- **`eventCapturedThisRequest` property** relocated to class property section with explicit per-request reset in `doConfigPageInit()`.

### Fixed

- **LIMIT/OFFSET MySQL compatibility** -- pagination parameters (`LIMIT`, `OFFSET`) are now inlined as sanitized integers instead of bound PDO parameters. Prevents `SQLSTATE[42000]` syntax errors on MySQL/MariaDB with `PDO::ATTR_EMULATE_PREPARES = true` (the default on PHP < 8.1).
- **PostgreSQL LIKE escape compatibility** -- all `LIKE` clauses in `searchAuditEvents()` and `handleDashboardStatsAjax()` now include an explicit `ESCAPE '\'` clause, required by PostgreSQL when `standard_conforming_strings = on` (default since PostgreSQL 9.1).
- **Dashboard sensitive reads count** -- the `%_access` pattern in the `sensitive_reads_24h` dashboard query now escapes the `_` wildcard (`%\_access`) to prevent false matches where `_` was treated as a single-character SQL wildcard.
- **TLS default when config returns `false`** -- `getConfig()` can return `false` (not `null`) for non-existent keys. The null coalesce operator `??` does not catch `false`, which silently disabled TLS enforcement. All config reads now use the new `getConfigSafe()` helper that handles `null`, `false`, and empty string correctly, defaulting `audit_db_require_tls` to `'1'` (enabled).
- **`setDefaultConfigIfMissing()` false handling** -- the install-time config bootstrapper now checks for `false` in addition to `null` and empty string when deciding whether to set a default value.
- **Indentation consistency** -- corrected indentation in `searchAuditEvents()` filter block, `page.auditcompliance.php` CSRF validation block, and shutdown capture closure.
- **Duplicate `trunkid` in object ID candidates** -- removed duplicate `trunkid` entries from `detectObjectId()` and `registerShutdownCapture()` candidate arrays.
- **Dead code removal** -- removed unused `$keepCurrentPassword` variable in `parseSettingsInput()`.
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
- CSS class names derived from DB values (`session_phase`, `channel`) sanitized with `preg_replace('/[^a-z0-9_-]/', '', ...)` for defense-in-depth XSS prevention.
- DISA module added to `SENSITIVE_READ_PAGES` as `disa_pin_access` (contains access PINs).
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
