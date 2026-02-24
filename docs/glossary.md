# Glossary

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-alpha    |
| Date     | 21-02-2026                       |
| Status   | Draft                            |
| Audience | All                              |

---

| Term | Definition |
|------|-----------|
| **Actor** | The administrator username recorded for each audit event. Extracted from `$_SESSION['AMP_user']->username`. Events without a valid session use `unknown`. |
| **AJAX Interceptor** | Client-side JavaScript injected into every admin page that patches `XMLHttpRequest.prototype` to monitor all POST/PUT/DELETE calls to `ajax.php`. Provides universal coverage for AJAX-driven module operations. |
| **Append-Only** | A storage model where new records can be inserted but existing records cannot be modified or deleted. Enforced by database triggers on the `audit_events` table. |
| **BMO** | Basic Module Object. The FreePBX object-oriented module architecture that provides lifecycle hooks (`doConfigPageInit`, `ajaxHandler`, `install`, etc.) and inter-module hook declarations. |
| **Boundary Event** | An audit event marking a session lifecycle transition: `login`, `logout`, `timeout`, or `failure`. These events have `channel = 'auth'` and distinct `session_phase` values. |
| **Break-Glass** | An emergency procedure requiring elevated database privileges (e.g., DBA superuser) to temporarily disable immutability triggers for data retention or recovery operations. Must be documented and audited externally. |
| **Channel** | The capture mechanism that originated an audit event. Values: `gui` (form POST), `ajax` (AJAX interceptor), `hook` (BMO hook), `auth` (session boundary), `rest` (future: REST API). |
| **Coverage Tier** | Classification of how thoroughly a module's actions are audited. Tiers: `full` (hooks + GUI + AJAX + read), `gui_ajax_read`, `gui_read`, `gui_ajax`, `gui_only`. |
| **Cross-Channel Deduplication** | The process of detecting and discarding duplicate events when the same action is captured by multiple channels (e.g., a GUI POST triggers both `doConfigPageInit` and a BMO hook). Uses a 3-second time window on `(session_id, module_name, action, object_id)`. |
| **Dedup Window** | The time interval (default 3 seconds) during which a second event with the same session, module, action, and object is considered a duplicate and discarded. Defined by `DEDUP_WINDOW_SECONDS`. |
| **doConfigPageInit** | A FreePBX BMO method called on every page load for registered module displays. The audit module uses it as the primary GUI capture hook and session boundary detector. |
| **Event** | A single audit record in the `audit_events` table representing one administrator action, sensitive read, or authentication boundary. |
| **Hook** | A FreePBX inter-module integration point declared in `module.xml`. When module A calls a hookable method, module B's registered handler is invoked. The audit module hooks into 38 methods across 10 modules. |
| **getConfigSafe** | Internal helper method wrapping FreePBX's `getConfig()` to handle `null`, `false`, and empty string returns uniformly. Ensures security-critical defaults (e.g., TLS enabled) are applied when the config store returns unexpected values. |
| **Idle Timeout** | The maximum number of seconds of inactivity before a session is considered timed out. Default: 1800 seconds (30 minutes). Configurable via `audit_session_idle_timeout_seconds`. |
| **Immutability Trigger** | A database `BEFORE UPDATE` or `BEFORE DELETE` trigger that raises an error to prevent modification of audit records. Three triggers protect `audit_events` (update + delete) and `audit_sessions` (delete). |
| **myConfigPageInits** | A FreePBX BMO method that returns a list of module display names the module wants to receive `doConfigPageInit` calls for. The audit module registers for all active module pages dynamically. |
| **ODBC** | Open Database Connectivity. A standard API for database access. The audit module supports `pdo_odbc` as an alternative to native `pdo_mysql`/`pdo_pgsql` drivers, with the actual database engine specified via `audit_db_odbc_backend` or auto-detected from the server version string. |
| **Object ID** | An identifier for the entity being acted upon (e.g., extension number, user ID, backup ID). Extracted from `$_REQUEST` using a priority-ordered list of common parameter names. |
| **Object Type** | The category of entity being acted upon. Typically the lowercase module name (e.g., `core`, `userman`, `backup`). |
| **Outcome** | The result of an audited action: `success` or `failure`. |
| **pbxACT** | The commercial distribution of FreePBX published by Sangoma, which includes additional commercial modules. The audit module is compatible with both FreePBX OSS and pbxACT. |
| **RBAC** | Role-Based Access Control. The audit module uses FreePBX's section-based permission system (`checkSection('auditcompliance')`) to restrict access to audit data. |
| **Redaction** | The process of replacing sensitive data values with `***REDACTED***` before persistence. Uses three matching strategies: substring match (e.g., `password`), exact match (e.g., `pin`), and suffix match (e.g., `_secret`). |
| **routeEvent** | The central event routing method in the audit module. All capture channels flow through this method for uniform validation, normalization, deduplication, and persistence. |
| **Sensitive Read** | An audit event recorded when an administrator views a page containing personal or security-sensitive data (credentials, PINs, contact information, call records, logs). Triggered on GET requests to 23 designated pages. |
| **Session** | A logical grouping of audit events corresponding to one administrator login period. Tracked in `audit_sessions` with explicit `login`, `logout`, and `timeout` boundaries. |
| **Session Phase** | The lifecycle stage of an event within its session: `login`, `activity`, `logout`, `timeout`, or `failure`. |
| **STRIDE** | A threat modeling framework covering Spoofing, Tampering, Repudiation, Information disclosure, Denial of service, and Elevation of privilege. Used in the module's threat model. |
| **Self-Referential Baseline** | The change tracking approach where each audit event stores its processed POST data (`change_after`). Subsequent edits to the same object retrieve this stored data as the "before" state for computing diffs, rather than reading the live FreePBX database. This ensures reliable before/after comparisons regardless of hook execution order. |
| **Shutdown Capture** | A safety net using PHP's `register_shutdown_function` to capture audit events from modules that call `redirect_standard()` or `exit()` before the primary audit hook completes. The shutdown function checks the `eventCapturedThisRequest` flag to avoid double-logging, and fires for both POST and GET state-changing requests. |
| **State-Changing Action** | An HTTP request that modifies FreePBX configuration. Detected by matching the `action` parameter against `STATE_CHANGING_PREFIXES` (e.g., `del`, `add`, `edit`, `save`, `copy`, `submit`). Applies to both POST form submissions and GET redirects (e.g., action bar delete buttons). |
| **Noise Key Filtering** | The process of removing framework-internal fields (`display`, `action`, `Submit`, CSRF tokens, `goto0`-`goto2`, etc.) from change diff comparisons via the `DIFF_SKIP_KEYS` constant. Ensures only meaningful configuration changes appear in the audit log. |
| **Semantic Value Normalization** | Intelligent comparison of before/after values using `valuesAreDifferent()`, `areBothFalsy()`, and `normalizeListValue()` to suppress false-positive diffs caused by format differences (e.g., `"0"` vs `""` treated as equivalent, newline-separated lists normalized to hyphen-separated). |
| **Settings GUI** | The graphical interface for configuring the audit database connection, accessible via the Settings tab. Supports Direct MySQL/MariaDB, Direct PostgreSQL, and ODBC connection types with connection testing and CSRF-protected form submission. |
| **Truncation** | The process of limiting field values to their maximum allowed lengths before database insertion. Uses multibyte-safe `mb_substr()` to prevent UTF-8 corruption. |
