# Audit Compliance Module -- Documentation Index

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-alpha    |
| Date     | 20-02-2026                       |
| Status   | Draft                            |

---

## By Audience

### Administrators

| Document | Description |
|----------|-------------|
| [Administrator Guide](administrator-guide.md) | GUI walkthrough of Dashboard, Search, Timeline, and Discovery views; export procedures |
| [Deployment Guide](deployment-guide.md) | Installation, database setup, configuration, verification, RBAC, networking |
| [Rollback Guide](rollback-guide.md) | Safe module disable/removal and data preservation |
| [Troubleshooting](troubleshooting.md) | Symptom-based diagnostics, known limitations, FAQ |
| [Configuration Reference](configuration-reference.md) | All settings with types, defaults, and examples |

### Operations / Infrastructure

| Document | Description |
|----------|-------------|
| [Operations Runbook](operations-runbook.md) | Health checks, monitoring integration, alerting, incident response, capacity planning |
| [Deployment Guide](deployment-guide.md) | Network, firewall, TLS, upgrade procedures |
| [Database Schema](database-schema.md) | Tables, indexes, triggers, cross-DB differences, sample queries |

### Developers

| Document | Description |
|----------|-------------|
| [Architecture](architecture.md) | System context, component diagrams, data flows, design decisions |
| [API Reference](api-reference.md) | All 7 AJAX endpoints with request/response schemas and examples |
| [Database Schema](database-schema.md) | ER diagram, column definitions, immutability enforcement |
| [Configuration Reference](configuration-reference.md) | Config keys, PHP constants, environment guidance |
| [Module Coverage Matrix](module-coverage-matrix.md) | Per-module audit coverage with tier classification |
| [Glossary](glossary.md) | Definitions of project-specific terms (BMO, hook, channel, dedup, etc.) |
| [Contributing Guide](../CONTRIBUTING.md) | Code standards, Git workflow, PR checklist |

### Compliance / Security Officers

| Document | Description |
|----------|-------------|
| [Threat Model](threat-model.md) | STRIDE analysis with OWASP Top 10 mapping |
| [Data Classification & Redaction](data-classification-redaction.md) | Redaction matrix, event taxonomy, fields never captured |
| [Retention & Compliance](retention-compliance.md) | GDPR, SOX, HIPAA, PCI DSS, ISO 27001 guidance; retention policy; archival strategy |
| [Security Test Plan](../tests/security-test-plan.md) | OWASP test cases, capture validation, DB immutability evidence |
| [Security Policy](../SECURITY.md) | Vulnerability disclosure process and response SLAs |

### All Audiences

| Document | Description |
|----------|-------------|
| [Module README](../module/auditcompliance/README.md) | Quick start, feature summary, universal capture architecture |
| [Changelog](../CHANGELOG.md) | Version history with all changes, fixes, and security notes |
| [License](../LICENSE) | GNU General Public License v3.0 or later |

---

## Suggested Reading Order

1. **Module README** -- understand what the module does and how universal capture works.
2. **Architecture** -- understand the system design before diving into details.
3. **Deployment Guide** -- install and configure the module.
4. **Administrator Guide** -- learn how to use the GUI.
5. **Configuration Reference** -- tune settings for your environment.
6. **Operations Runbook** -- set up monitoring and alerting.
7. **Threat Model** + **Retention & Compliance** -- satisfy auditor requirements.

---

## Upstream Analysis

| Document | Description |
|----------|-------------|
| [Upstream Analysis](upstream-analysis.md) | FreePBX repository scan findings (80 repos, communication surfaces, atypical modules) |
