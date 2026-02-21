# Changelog

All notable changes to the Audit Compliance module for FreePBX/pbxACT are documented in this file.

This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) once a stable release is published.

## [Unreleased]

## [0.1.0-alpha] - 20-02-2026

### Added

- **Multi-channel event capture** -- GUI POST, universal AJAX interceptor, BMO hooks, auth boundary events.
- **Universal AJAX interceptor** -- Client-side JavaScript patches `XMLHttpRequest` to monitor all `ajax.php` POST/PUT/DELETE calls for any module, closing AJAX coverage gaps without per-module adapters.
- **38 BMO hook handlers** across 10 modules (core, userman, backup, certman, voicemail, timeconditions, contactmanager, ucp, calendar, bulkhandler) for deep method-level write interception.
- **21 sensitive-read page monitors** covering CDR, recordings, user credentials, certificates, voicemail, conferences, contacts, queues, AMI, SIP settings, log files, ARI, file store, calendar, fax, PIN sets, Superfecta, XMPP, phonebook, blacklist, and CEL.
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

- All database queries use prepared statements with bound parameters.
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

[Unreleased]: https://github.com/example/freepbx-audit/compare/v0.1.0-alpha...HEAD
[0.1.0-alpha]: https://github.com/example/freepbx-audit/releases/tag/v0.1.0-alpha
