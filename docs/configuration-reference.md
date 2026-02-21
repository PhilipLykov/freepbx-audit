# Configuration Reference

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-alpha    |
| Date     | 20-02-2026                       |
| Status   | Draft                            |
| Audience | Administrators, Developers       |

---

## FreePBX Config Store Settings

These settings are stored in the FreePBX key-value store (`astdb`) and can be set via
`fwconsole setting` or the FreePBX Advanced Settings GUI.

### `audit_db_dsn`

| Property | Value |
|----------|-------|
| Type | String |
| Default | `''` (empty -- uses FreePBX internal database) |
| Set via | `fwconsole setting AUDITCOMPLIANCE_DB_DSN "<dsn>"` |
| Security | Must include TLS parameters when `audit_db_require_tls` is enabled |

**Description**: PDO Data Source Name for the remote audit database. When empty, the module
falls back to the FreePBX internal database (development/testing only -- not recommended for
production).

**Examples**:

```bash
# MariaDB/MySQL
fwconsole setting AUDITCOMPLIANCE_DB_DSN "mysql:host=audit-db.example.com;port=3306;dbname=auditcompliance;charset=utf8mb4"

# PostgreSQL
fwconsole setting AUDITCOMPLIANCE_DB_DSN "pgsql:host=audit-db.example.com;port=5432;dbname=auditcompliance;sslmode=require"
```

---

### `audit_db_user`

| Property | Value |
|----------|-------|
| Type | String |
| Default | `''` (empty) |
| Set via | `fwconsole setting AUDITCOMPLIANCE_DB_USER "<username>"` |
| Security | Use a dedicated least-privilege account with INSERT + SELECT only |

**Description**: Username for the remote audit database connection.

---

### `audit_db_password`

| Property | Value |
|----------|-------|
| Type | String |
| Default | `''` (empty) |
| Set via | `fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<password>"` |
| Security | Never commit to version control; stored encrypted in FreePBX astdb |

**Description**: Password for the remote audit database connection.

---

### `audit_db_require_tls`

| Property | Value |
|----------|-------|
| Type | String (boolean-like) |
| Default | `'1'` (enabled) |
| Set via | `fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"` |
| Validation | DSN must contain `ssl` (MySQL) or `sslmode=` (PostgreSQL) when enabled |

**Description**: When enabled (`'1'`), the module validates that the DSN includes TLS
parameters before establishing the connection. If the DSN lacks TLS configuration, an
exception is thrown and the connection is refused.

**Recommendation**: Always leave enabled in production. Disable only for local development
with a non-networked database.

---

### `audit_session_idle_timeout_seconds`

| Property | Value |
|----------|-------|
| Type | String (integer-like) |
| Default | `'1800'` (30 minutes) |
| Set via | `fwconsole setting AUDITCOMPLIANCE_SESSION_IDLE_TIMEOUT_SECONDS "1800"` |
| Validation | Must be > 0; falls back to 1800 if invalid |

**Description**: Number of seconds of inactivity after which a session is considered timed
out. When a timed-out session is detected on the next page load, a `timeout` event is
recorded and the session is closed.

---

## PHP Constants

These are defined in `Auditcompliance.class.php` and cannot be changed at runtime.

| Constant | Value | Description |
|----------|-------|-------------|
| `SESSION_KEY_ID` | `'auditcompliance_session_uuid'` | PHP `$_SESSION` key for the current audit session ID |
| `SESSION_KEY_LAST_ACTIVITY` | `'auditcompliance_last_activity_unix'` | PHP `$_SESSION` key for the last activity timestamp |
| `SESSION_KEY_LOGIN_RECORDED` | `'auditcompliance_login_recorded'` | PHP `$_SESSION` key for login idempotency guard |
| `SESSION_IDLE_TIMEOUT_SECONDS` | `1800` | Default idle timeout (overridden by config) |
| `DEDUP_WINDOW_SECONDS` | `3` | Cross-channel deduplication window in seconds |

---

## Environment-Specific Guidance

### Development

```bash
# Use local DB (no remote DSN), TLS not required
fwconsole setting AUDITCOMPLIANCE_DB_DSN ""
fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "0"
```

### Production

```bash
# Remote MariaDB with TLS
fwconsole setting AUDITCOMPLIANCE_DB_DSN "mysql:host=audit-db.prod.internal;port=3306;dbname=auditcompliance;charset=utf8mb4"
fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<STRONG_PASSWORD>"
fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"
fwconsole setting AUDITCOMPLIANCE_SESSION_IDLE_TIMEOUT_SECONDS "1800"
```

### High-Security

Consider reducing the idle timeout for stricter session control:

```bash
fwconsole setting AUDITCOMPLIANCE_SESSION_IDLE_TIMEOUT_SECONDS "900"  # 15 minutes
```
