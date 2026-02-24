# Audit Compliance Module for FreePBX/pbxACT

![Version](https://img.shields.io/badge/version-17.0.0--beta-blue)
![License](https://img.shields.io/badge/license-GPLv3+-green)
![PHP](https://img.shields.io/badge/php-%3E%3D7.4-purple)
![FreePBX](https://img.shields.io/badge/FreePBX-17.x-orange)

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-beta1    |
| Date     | 25-02-2026                       |
| Status   | Beta                             |

Enterprise-grade immutable compliance audit logging for FreePBX 17 / pbxACT Web GUI administrator actions. Provides **universal coverage** across all modules without modifying any native FreePBX/pbxACT core files.

## Quick Start

1. Copy the module to your FreePBX server:
   ```bash
   cp -r auditcompliance /var/www/html/admin/modules/
   ```
2. Install and enable:
   ```bash
   fwconsole ma install auditcompliance
   fwconsole reload
   ```
3. Configure a remote audit database (native PDO or ODBC):
   ```bash
   # Native PDO (MariaDB)
   fwconsole setting AUDITCOMPLIANCE_DB_DSN "mysql:host=audit-db.example.com;port=3306;dbname=auditcompliance;charset=utf8mb4"
   fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
   fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<STRONG_PASSWORD>"
   fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"

   # Or via ODBC (system DSN defined in /etc/odbc.ini)
   fwconsole setting AUDITCOMPLIANCE_DB_DSN "odbc:AuditDB"
   fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
   fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<STRONG_PASSWORD>"
   fwconsole setting AUDITCOMPLIANCE_DB_ODBC_BACKEND "mysql"
   ```
4. Navigate to **Reports > Audit Compliance** in the Web GUI.
5. The Dashboard shows real-time audit activity. Make any admin change and watch it appear.

For detailed setup including database creation, role hardening, TLS configuration, and RBAC,
see the [Deployment Guide](../../docs/deployment-guide.md).

## Universal Capture Architecture

```
Admin --> FreePBX GUI --> Capture Channels --> routeEvent() --> Dedup --> Audit Writer --> Remote DB
                              |
              GUI | AJAX Interceptor | Hook | Auth channels
```

### How Universal Coverage Works

| Channel | Mechanism | Scope |
|---------|-----------|-------|
| **GUI POST** | `doConfigPageInit()` on POST | All active module pages (dynamic enumeration) |
| **GUI GET Action** | `doConfigPageInit()` on GET | State-changing actions (delete, copy, submit) on any module page |
| **GUI Read** | `doConfigPageInit()` on GET | 23 sensitive pages (credentials, personal data, logs) |
| **AJAX** | Client-side XHR interceptor (patches XMLHttpRequest) | **ALL** `ajax.php` POST/PUT/DELETE calls for **any** module |
| **Hook** | `module.xml <hooks>` declarations | 38 methods across 10 modules (core, userman, backup, certman, voicemail, timeconditions, contactmanager, ucp, calendar, bulkhandler) |
| **Auth** | Session state detection + JS logout beacon | Login, logout, timeout, auth failure |
| **Shutdown** | `register_shutdown_function` safety net | Catches events from modules that `exit()` before audit hook completes |

The AJAX interceptor is the key to universal coverage: it monitors all AJAX calls to `ajax.php` from the browser, regardless of which module originates them. This covers firewall operations, backup triggers, recording management, and any future module that uses AJAX.

### Shutdown Safety Net

Some modules (e.g., Trunks, Misc Destinations) call `redirect_standard()` or `exit()` before the audit hook completes. The module registers a `shutdown_function` for all state-changing requests that captures the event if the primary capture was missed, with deduplication to prevent double-logging.

### Before/After Change Diffs

The module tracks exact changes using a self-referential baseline: each event stores the processed POST data, and subsequent edits compare against this stored baseline. This produces reliable "old value → new value" diffs with semantic normalization to suppress false positives.

### Cross-Channel Deduplication

When the same action fires through multiple channels (e.g., a POST that triggers both `doConfigPageInit` and a BMO hook), the `routeEvent()` central router deduplicates within a 3-second window on `(session_id, module_name, action, object_id)`.

## Features

- **52+ modules covered**: Full Tier-1/2/3 coverage across all official FreePBX/pbxACT modules
- **Multi-channel event capture**: GUI POST + GUI GET actions + universal AJAX interceptor + BMO hooks + auth events + shutdown safety net
- **Before/after change diffs**: Self-referential baseline tracking shows exact field-level changes with semantic normalization
- **Append-only immutable storage**: DB triggers prevent UPDATE/DELETE; least-privilege DB role
- **Session-grouped timeline**: Events correlated by admin session with login/logout/timeout boundaries
- **Remote database support**: MariaDB 10.5+ and PostgreSQL 14+ via TLS-enforced PDO or ODBC
- **Settings GUI**: Graphical configuration of audit database connection with connection testing
- **Full search GUI**: Multi-dimensional filtering, sortable columns, pagination
- **Export**: CSV and JSON with rate limiting (5000 row cap, 10s cooldown)
- **Sensitive read auditing**: 23 pages covering CDR, recordings, credentials, PINs, logs, personal data
- **Module Discovery**: Built-in tool to enumerate all installed modules and their audit surfaces
- **RBAC**: Section-based permission check on page and all AJAX endpoints
- **Sensitive data redaction**: Passwords, tokens, API keys automatically masked
- **OWASP Top 10 aligned**: Prepared statements, output escaping, TLS enforcement (fail-safe defaults), allowlisted inputs, explicit LIKE ESCAPE clauses

## Installation

See [docs/deployment-guide.md](../../docs/deployment-guide.md) for full instructions.

```bash
cp -r auditcompliance /var/www/html/admin/modules/
fwconsole ma install auditcompliance
fwconsole reload
```

## Configuration

```bash
fwconsole setting AUDITCOMPLIANCE_DB_DSN "mysql:host=db.example.com;port=3306;dbname=auditcompliance;charset=utf8mb4"
fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<password>"
fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"
```

## pbxACT Commercial Module Support

Run the discovery tool on your target server:

```bash
php tools/discover-pbxact-surfaces.php --json
```

Or use the built-in Module Discovery view in the GUI: **Reports > Audit Compliance > Module Discovery**

## Documentation

| Document | Description |
|----------|-------------|
The full documentation suite is available at [docs/README.md](../../docs/README.md).

### Operational

| Document | Description |
|----------|-------------|
| [Deployment Guide](../../docs/deployment-guide.md) | Installation, database setup, configuration, verification |
| [Administrator Guide](../../docs/administrator-guide.md) | GUI walkthrough of all 5 views and export |
| [Configuration Reference](../../docs/configuration-reference.md) | All settings with types, defaults, examples |
| [Troubleshooting](../../docs/troubleshooting.md) | Symptom-based diagnostics, known limitations, FAQ |
| [Operations Runbook](../../docs/operations-runbook.md) | Health checks, monitoring, alerting, capacity planning |
| [Rollback Guide](../../docs/rollback-guide.md) | Safe module disable/removal and data preservation |

### Technical

| Document | Description |
|----------|-------------|
| [Architecture](../../docs/architecture.md) | System context, component diagrams, data flows, deployment topology |
| [API Reference](../../docs/api-reference.md) | All 7 AJAX endpoints with schemas and examples |
| [Database Schema](../../docs/database-schema.md) | ER diagram, tables, indexes, triggers, cross-DB differences |
| [Module Coverage Matrix](../../docs/module-coverage-matrix.md) | Per-module audit coverage with tier classification |
| [Glossary](../../docs/glossary.md) | Definitions of all project-specific terms |

### Security & Compliance

| Document | Description |
|----------|-------------|
| [Threat Model](../../docs/threat-model.md) | STRIDE analysis + OWASP Top 10 mapping |
| [Data Classification](../../docs/data-classification-redaction.md) | Three-tier redaction matrix, event taxonomy |
| [Retention & Compliance](../../docs/retention-compliance.md) | GDPR, SOX, HIPAA, PCI DSS, ISO 27001 guidance |
| [Security Test Plan](../../tests/security-test-plan.md) | OWASP test cases + universal capture validation |
| [Upstream Analysis](../../docs/upstream-analysis.md) | FreePBX repository scan findings (80 repos) |

### Project

| Document | Description |
|----------|-------------|
| [Changelog](../../CHANGELOG.md) | Version history following Keep a Changelog format |
| [Contributing](../../CONTRIBUTING.md) | Code standards, Git workflow, PR checklist |
| [Security Policy](../../SECURITY.md) | Vulnerability disclosure process and response SLAs |
| [License](../../LICENSE) | GNU General Public License v3.0 or later |

## Date Format

All timestamps: `DD-MM-YYYY HH:MM:SS` with `Europe/Chisinau` local timezone.

## Requirements

- FreePBX 17.x / pbxACT with Framework >= 17.0.1
- PHP 7.4+ (PHP 8.1+ recommended)
- PDO with `pdo_mysql` and/or `pdo_pgsql`, or `pdo_odbc` for ODBC connections
- Remote MariaDB 10.5+ or PostgreSQL 14+ with TLS
- (ODBC only) `unixODBC` and the appropriate database ODBC driver

## License

[GPLv3+](../../LICENSE)
