# Changelog

All notable changes to the Audit Compliance module for FreePBX/pbxACT are documented in this file.

This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) once a stable release is published.

## [Unreleased]

### Added

- **Universal AJAX body forwarding** -- The JavaScript `extractSnippet()` function now forwards ALL request body parameters (truncated to 4096 chars) instead of cherry-picking from a hardcoded `idKeys` list. This eliminates the need to add module-specific parameters to `idKeys` for every new module, and ensures that AJAX events for any module (including firewall zone/network params, soundlang language/format params, logfiles setting params, and all future modules) automatically include full change details. Falls back to raw body substring on URLSearchParams parse failure.
- **Universal AJAX change payload parser** -- `buildAjaxChangePayload()` rewritten to parse ALL body parameters universally instead of checking ~30 hardcoded field names. Uses `AJAX_NOISE_KEYS` (CSRF tokens, routing params) for filtering and `AJAX_ID_FIELDS` for entity ID resolution. JSON-encoded params (`json`, `data`) are automatically decoded. Arrays are preserved (with size limits). All values pass through `redactSensitiveData()` before storage. This replaces ~120 lines of hardcoded parameter extraction.
- **Entity cache for filestore and api modules** -- Added `filestore` and `api` to `ENTITY_CACHE_MAP` (now 39 modules). Filestore uses `listLocations()` with a new `flatten` key to handle its nested `locations[driver][items]` return structure. API uses a new `subproperty` key to navigate the `->applications->getAll()` sub-object pattern. Both `flatten` and `subproperty` are generic mechanisms reusable by any future module with non-standard listing patterns.
- **Sensitive read context enrichment** -- `captureSensitiveReadEvent()` now extracts additional URL parameters (`driver`, `type`, `category`, `section`, `tab`, `appid`, `application_id`, `client_id`) via `SENSITIVE_READ_CONTEXT_KEYS`, providing entity context for sensitive reads (e.g., filestore shows which driver, api shows which application).

### Fixed

- **Edit-vs-create misclassification (vqueue and other modules)** -- The create-vs-edit heuristic now requires `objectId === ''` (empty) in addition to null baseline and generic action. When editing an existing entity, the object ID is always non-empty (e.g., `extdisplay=6`, `id=<uuid>`), so the action is correctly preserved as `edit`/`update`/`submit`. Only truly new entities (with no ID assigned yet) are reclassified to `create`. Applied consistently across all three capture paths: `captureGuiPostEvent`, `registerShutdownCapture`, and `handleEarlyShutdownCapture`.
- **Excessive field display on first edit (filestore and other modules)** -- When no prior baseline exists for an edit action, the change payload no longer shows all submitted fields as "Added Fields". Instead, fields are shown only in the `after` payload for reference, with an empty `added` array. This prevents confusing displays where editing one field in filestore (or any module on first audit) appeared to show every field as newly added. Create events still correctly show all fields as `added`.
- **Sensitive read context `view` key collision** -- Removed `view` from `SENSITIVE_READ_CONTEXT_KEYS` to prevent FreePBX's `$_REQUEST['view']` (page display mode, e.g., `form`) from overwriting the `$context['view']` access type (e.g., `storage_credentials_access`).
- **API subproperty access via `__get`** -- The `cacheModuleEntityNames` subproperty branch now uses try/catch + `is_object()` instead of `isset()` to access sub-objects. `isset()` on PHP magic `__get` properties calls `__isset()` which may return false even when `__get()` would succeed, preventing the API entity cache from populating.
- **Comprehensive module audit (38+ modules verified)** -- Systematic source-code audit of all FreePBX OSS modules across three tiers: Tier-1 (core, queues, ringgroups, IVR, conferences, voicemail, followme, paging, parking, timeconditions), Tier-2 (backup, certman, userman, contactmanager, fax, recordings, music, languages, callrecording, disa, pinsets, daynight, announcement), and Tier-3 (bulkhandler, blacklist, hotelwakeup, superfecta, queueprio, setcid, phonebook, callback, cidlookup, tts, customappsreg, weakpasswords, speeddial). Each module examined for: empty doConfigPageInit, redirect/exit after processing, non-standard action parameters, AJAX command-in-body patterns.
- **Relaxed read-only AJAX prefix matching** -- The `isReadOnlyAjaxCommand` prefix check no longer requires the character after the prefix to be uppercase or underscore. Commands like `gethtml5`, `getsettings`, `gettable`, `checkrecording`, `getsupportedhtml5` are now correctly classified as read-only. Prefixes: `get`, `list`, `check`, `search`, `lookup`, `validate`, `test`, `load`, `fetch`, `query`.
- **AJAX read-only commands for 15 new modules** -- Added explicit read-only command lists for: `recordings` (9 commands), `music` (3), `bulkhandler` (1), `hotelwakeup` (11), `superfecta` (3), `queueprio` (3), `findmefollow` (1), `paging` (1), `phonebook` (1), `callback` (1), `cidlookup` (1), `tts` (1), `parking` (1), `blacklist` (3 total). Also added `gettimeconditions` to timeconditions list.
- **Alternative action parameter detection (certman)** -- Certman uses `certaction` instead of `action`. The constructor, `doConfigPageInit`, `handleEarlyShutdownCapture`, and `functions.inc.php` shutdown handler now check `$_REQUEST['certaction']` and give it precedence over `$_REQUEST['action']` when present, ensuring correct action labels for certificate add/edit/delete/importlocally operations even when the URL contains display-routing `action=view`.
- **Entity cache for 4 new modules** -- Added `ENTITY_CACHE_MAP` entries for `findmefollow`, `phonebook`, `blacklist`, and `queueprio` to resolve opaque IDs to human-readable names (total: 37 modules).
- **Expanded object ID detection** -- Added `tts_id`, `setcid_id`, `scheme_name`, `blockType` to `detectObjectId()` candidates, and `scheme_name`, `blockType`, `tts_id`, `setcid_id`, `key`, `source`, `destination` to `extractObjectIdFromAjaxBody()` candidates.
- **Richer AJAX change payloads** -- (Superseded by universal AJAX parser) Previously captured module-specific params via hardcoded checks; now handled universally.
- **JS interceptor body-snippet expansion** -- (Superseded by universal body forwarding) Previously expanded `idKeys` array; now all params forwarded automatically.
- **Sensitive read pages for backup and admin users** -- Added `backup` (`backup_credentials_access` — shows remote storage credentials) and `ampusers` (`admin_user_access` — shows FreePBX admin accounts) to `SENSITIVE_READ_PAGES`.

### Fixed

- **Duplicate AJAX_READ_ONLY_COMMANDS keys** -- Removed duplicate array keys for `backup`, `userman`, `contactmanager` that were accidentally added in previous iterations. In PHP, duplicate keys silently overwrite earlier entries, which was causing the comprehensive read-only lists to be replaced by smaller subsets, resulting in read-only AJAX operations being logged as state-changing events.
- **Duplicate SENSITIVE_READ_PAGES key** -- Removed duplicate `backup` entry and spurious `bulkhandler` entry from `SENSITIVE_READ_PAGES`.
- **`populatenpanxx` incorrectly classified as read-only** -- Moved `populatenpanxx` from core's read-only list to state-changing (it imports NPA-NXX dial patterns). Similarly, `accesstoken` removed from backup's read-only list (it generates OAuth tokens).
- **`registerShutdownCapture` candidates out of sync** -- The inline object-ID candidates array in `registerShutdownCapture` was missing `tts_id`, `setcid_id`, `scheme_name`, `blockType` that were added to `detectObjectId()`, causing the shutdown safety net to fail to identify object IDs for TTS, SetCID, Superfecta, and Blacklist modules.
- **`functions.inc.php` GET prefix list incomplete** -- The shutdown handler's GET state-changing prefix list was missing `set`, `assign`, `clear`, `flush`, `purge` compared to the class's `GET_STATE_CHANGING_PREFIXES`, which could cause GET actions like `setDefault` to be missed when the owning module exits early.
- **`handleEarlyShutdownCapture` missing `certaction`** -- The method now applies `certaction` precedence consistent with the constructor and `doConfigPageInit`.

### Previously added

- **Fetch API interception** -- The JavaScript AJAX interceptor now patches `window.fetch` in addition to `XMLHttpRequest`. Modules using the modern Fetch API are now fully captured. The `fetch()` wrapper handles `Request` objects, `FormData` bodies, and both resolved/rejected promises, ensuring beacon delivery even on network errors.
- **Reliable beacon delivery via `navigator.sendBeacon()`** -- Audit beacons are now delivered using the Beacon API instead of async `XMLHttpRequest`. This is critical for modules like Firewall that use synchronous AJAX (`async: false`) followed by immediate page reload (`window.location.href = ...`), which would abort an in-flight XHR before it could deliver the audit record. The Beacon API queues data for delivery even during page unload.
- **Firewall module full audit coverage** -- All 37 Firewall AJAX commands are now captured, with proper read-only filtering for 10 data-retrieval operations (`getwhitelist`, `getbannedlist`, `getattackers`, etc.). Firewall page views are logged as sensitive reads (`firewall_config_access`). Both form POST actions (`enablefw`, `disablefw`, `updateservices`, `enablerfw`, `disablerfw`, `saveresponsive`) and AJAX operations (network zone management, blacklist, custom rules, intrusion detection, advanced settings) are covered.
- **Sysadmin module audit coverage** -- Added `sysadmin` to sensitive-read pages (`system_admin_access`).
- **Expanded AJAX read-only commands** -- Added module-specific read-only command lists for `firewall` (10 commands), `logfiles` (7 commands), and `soundlang` (3 commands) to prevent noise from data-retrieval AJAX calls.
- **Expanded state-changing action prefixes** -- `STATE_CHANGING_PREFIXES` now includes 33 prefixes (was 17), adding: `process`, `set`, `apply`, `install`, `uninstall`, `destroy`, `change`, `rename`, `move`, `import`, `export`, `activate`, `deactivate`, `restore`, `revoke`, `grant`, `assign`, `clear`, `flush`, `purge`, `reorder`. This ensures Module Admin operations (`action=process`), repo toggles (`action=setrepo`), and other non-standard action names are properly recognized as state-changing.
- **Log Files Settings detailed change capture** -- The `logfiles_settings` module is 100% AJAX-driven (empty `doConfigPageInit`). All state-changing AJAX commands (`settings_set`, `logfiles_set`, `logfiles_destory`, `log_file_destory`) now capture full change details: for `settings_set` the audit log records the setting name and new value; for `logfiles_set` it records the log file name and the complete configuration object (debug/dtmf/error/fax/notice/verbose/warning/security/disabled flags); for `logfiles_destory` it records the removed log file name. Seven read-only AJAX commands are filtered out to prevent noise.
- **AJAX command body extraction fix** -- The JS `parseModCmd()` now extracts `command` (and `action` as fallback) from the POST body when the URL contains `module=` but not `command=`. Previously the body was only checked when the URL had no `module=` parameter at all, causing AJAX-heavy commercial modules (e.g., VQueue/Virtual Queue) to log events with empty command names. The PHP `handleInterceptedAjax()` also falls back to extracting `command`/`action` from the body snippet when `target_command` is empty.
- **POST-with-empty-action shutdown capture** -- The `functions.inc.php` shutdown handler now treats ALL POST requests to a valid module display as state-changing (except reload/retrieve_conf). Previously, POST requests with an empty `action` parameter were silently skipped, causing missed events for commercial modules that determine the operation from button names, `fw_popover_process`, or body-only parameters instead of `$_REQUEST['action']`.
- **AJAX dialog/popover context** -- `buildAjaxChangePayload()` now captures `action` (sub-action), `dlg_mode` (dialog mode: new/edit), and `fw_popover_process` (popover source module) from the AJAX body, providing richer context for modules using FreePBX's nested-form/popover dialog pattern for create/edit operations.
- **JS interceptor body-snippet enrichment** -- (Superseded by universal body forwarding) Previously captured specific params; now all params forwarded automatically.
- **Create/delete action clarity** -- POST events that have no prior audit baseline, use a generic action (`update`, `save`, `submit`), AND have an empty object ID are automatically labeled `create`. Events with a non-empty object ID are preserved as edits even without a baseline.
- **Entity name resolution for all actions** -- `resolveObjectId()` resolves opaque IDs (numeric, UUID, or composite) to human-readable names for ALL event types (create, edit, delete, sensitive reads), not just create/add actions. Session-based entity cache (`ENTITY_CACHE_MAP`) covers 39 modules: Userman, IVR, Time Conditions, Announcements, Trunks, Contact Manager, Certman, Backup, Calendar, Calendar Groups, Custom Destinations, Custom Extensions, Ring Groups, Queues, Conferences, Paging, Parking, Misc Destinations, Misc Applications, Day/Night (Call Flow Control), PIN Sets, Languages, Music on Hold, Call Recording, DISA, Outbound Routes, Inbound Routes (with composite key support), Callback, CID Lookup, System Recordings, Text to Speech, Set CallerID, Superfecta, Findmefollow, Phonebook, Blacklist, Queueprio, Filestore, and API. Cache is populated on every non-state-changing GET page view and after create events. The `cacheModuleEntityNames` method now supports `flatten` (for nested grouped returns) and `subproperty` (for sub-object access) patterns.
- **Create-event name resolution** -- POST events labeled `create` or `add` now refresh the entity cache and resolve the object ID to its canonical (DB-stored) name, ensuring consistent naming between create and subsequent delete/edit events.
- **Destination selector noise filtering** -- `filterNoiseKeys()` now strips FreePBX destination selector fields (e.g., `Announcements0`, `Ring Groups0`, `Extensions0`) by pattern-matching keys that start with an uppercase letter and end with a digit.
- **Hook noise suppression** -- hooks that fire as internal sub-operations (e.g. `core::addUser`, `core::addDevice`, `contactmanager::addEntryByGroupID` during extension creation) are now suppressed when the primary GUI or AJAX event is already captured. During `config.php` requests hooks are fully suppressed (the GUI channel provides complete change details); during `ajax.php` requests only the first hook per PHP request is recorded.
- **Cross-channel AJAX dedup** -- `hasRecentHookEventForModule()` prevents the JS AJAX interceptor from recording a duplicate event when a BMO hook has already captured the same operation within the dedup window. Apply Config events are exempted from this check.
- **Expanded core AJAX read-only list** -- added `getextensiongrid`, `getdevicegrid`, `getusergrid`, and `getnpanxxjson` to the `AJAX_READ_ONLY_COMMANDS` for the `core` module, verified against FreePBX 17 `Core.class.php` source.
- **ODBC database connection support** via `pdo_odbc`. The module can now connect to the audit database through Linux system ODBC data sources (`unixODBC`), enabling centralized driver-level TLS management and compliance with enterprise ODBC-only policies.
- New config key `audit_db_odbc_backend` (`mysql` / `pgsql`) to explicitly specify the database engine behind an ODBC connection when auto-detection is insufficient.
- Automatic ODBC backend detection via `SELECT version()` and `PDO::ATTR_SERVER_VERSION` heuristics, with fallback to `mysql`.
- **Dashboard tab navigation** -- the Dashboard view now includes the standard 4-tab navigation bar consistent with Search, Timeline, and Discovery views.
- `getConfigSafe()` internal helper for resilient config key retrieval with explicit default values.
- **Early capture via `functions.inc.php`** -- FreePBX loads every active module's `functions.inc.php` from `bootstrap.php` BEFORE `GuiHooks::doConfigPageInits()` runs. The audit module uses this to snapshot `$_REQUEST` and `$_SERVER` into `$GLOBALS` and register a `register_shutdown_function` before any module's `doConfigPageInit` runs. This is critical for modules like Misc Destinations that call `redirect_standard()` / `exit()` in their `doConfigPageInit`, which terminates PHP before the Auditcompliance BMO class is ever lazy-loaded. The shutdown function fires after `exit()`, lazy-loads the module, and captures the event via `handleEarlyShutdownCapture()`. Deduplication uses the `$GLOBALS['_AUDITCOMPLIANCE_EVENT_CAPTURED']` flag set by `routeEvent()`.
- **Constructor-level shutdown safety net** -- `register_shutdown_function` registered in the constructor captures events from modules that nullify `$_REQUEST['action']` after processing (e.g. Misc Applications). The constructor prefers the early `$GLOBALS` snapshot from `functions.inc.php` over live `$_REQUEST`, ensuring the original action is always preserved. Covers both POST and GET state-changing requests.
- **GET-based state-changing action capture** -- modules that trigger deletes, copies, or other state changes via GET requests (e.g., Ring Groups delete via `action=delGRP`) are now audited through `captureGuiGetActionEvent()`. Expanded `STATE_CHANGING_PREFIXES` with `copy`, `duplicate`, and `submit`.
- **Self-referential change baseline** -- the module stores processed POST data (`change_after`) with each event. Subsequent edits to the same object use this as the "before" state for reliable before/after diffs, eliminating dependency on FreePBX DB reads during hook execution. `getPreviousPostData()` now tries both the raw object ID (e.g. `OUT_2`) and the resolved human-readable name (e.g. `FROM-StageA`) when looking up the previous baseline, fixing a mismatch where events were stored with the resolved name but lookups used the raw ID.
- **Semantic value normalization** -- `valuesAreDifferent()`, `areBothFalsy()`, and `normalizeListValue()` eliminate false-positive diffs caused by format differences (e.g., `0` vs `""`, `\n`-separated vs `-`-separated lists).
- **Noise key filtering** -- `DIFF_SKIP_KEYS` constant filters out framework fields (`display`, `action`, `Submit`, CSRF tokens, `goto0`-`goto2`, `delete`, `tech`, `orig_account`, `entries`, `module_hook`) from change diffs to show only meaningful changes.
- **25 sensitive-read pages** -- covers CDR, recordings, CEL, Userman, Certman, AMI Manager, ARI Manager, Filestore, XMPP, Calendar, Calendar Groups, Voicemail, Conferences, PIN Sets, DISA, Contact Manager, Phonebook, Log Files, Log File Settings, Superfecta, API (OAuth2 tokens/client secrets), REST API (tokens/token keys), REST API Report (token logs), Firewall (network/rule configuration), and Sysadmin (system administration).
- **Settings GUI** -- full graphical settings page for configuring audit database connection (Direct MySQL/MariaDB, Direct PostgreSQL, ODBC), with connection test, input validation, and CSRF protection.
- **Apply Config event capture** -- multi-layered detection for FreePBX "Apply Config" button presses, including JavaScript interception of `ajax.php?command=reload`.
- **Expanded object ID detection** -- `detectObjectId()` now recognizes `pagenbr`, `announcement_id`, `callrecording_id`, `channel`, `orig_account`, `trunknum`, `destid`, `custom_exten`, `old_custom_exten`, `language_id`, `page_group`, `route_id`, `disa_id`, `number`, `oldval`, `speeddial`, `callback_id`, `cidlookup_id`, `cid_id`, `priority_id`, and additional module-specific ID fields. All three ID extraction methods (GUI, shutdown, AJAX) use the constructor-time `$_REQUEST` snapshot so IDs survive modules that nullify `$_REQUEST` after processing.
- **Misc Destinations & Misc Applications support** -- Both modules are now fully audited. Miscdests uses `redirect_standard()` → `exit()` before our BMO class is ever instantiated (FreePBX lazy-loads modules); captured by the `functions.inc.php` early shutdown handler. Miscapps nullifies `$_REQUEST['action']` after processing; the original action is preserved from the early `$GLOBALS` snapshot. Dead BMO method hooks for `Miscdests::add/del/update` removed (miscdests does not call `processHooks()` so they never fired).
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
- **First-event diff display** -- (Refined) when no prior baseline exists for a **create** event, the full filtered POST data is shown as "Added Fields". For **edit** events without baseline, fields are shown in `after` only (not `added`) to avoid confusion.
- **Legacy noise in diffs** -- `filterNoiseKeys()` applied to historical `change_after` data on retrieval, ensuring consistent filtering between old and new states.

### Security

- TLS enforcement can no longer be silently disabled by a `false` return from the FreePBX config store, closing a potential downgrade vector on first-run or config corruption scenarios.
- Settings page uses independent CSRF token generation and `hash_equals()` validation.
- Shutdown capture function uses snapshots of `$_REQUEST` and `$_SERVER` to avoid referencing mutable globals after exit.

## [0.1.0-alpha] - 20-02-2026

### Added

- **Multi-channel event capture** -- GUI POST, universal AJAX interceptor, BMO hooks, auth boundary events.
- **Universal AJAX interceptor** -- Client-side JavaScript patches both `XMLHttpRequest` and the **Fetch API** (`window.fetch`) to monitor all `ajax.php` POST/PUT/DELETE calls for any module, closing AJAX coverage gaps without per-module adapters. The dual-transport interception ensures modules using either classic jQuery AJAX or modern `fetch()` are captured.
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
