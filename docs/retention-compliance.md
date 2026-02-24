# Data Retention and Compliance Notes — Audit Compliance Module

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-beta1            |
| Date     | 25-02-2026                               |
| Status   | Beta                                     |
| Audience | Compliance Officers, Administrators      |

---

## 1. Regulatory Context

Audit logging for PBX/telecommunications systems may be subject to various regulatory frameworks depending on jurisdiction and industry:

| Regulation | Requirement | Module Relevance |
|------------|-------------|-----------------|
| GDPR (EU) | Data minimization, right to erasure, lawful basis for processing | Audit logs may contain personal data (usernames, IPs); retention must be justified |
| SOX (US) | Internal controls over financial reporting, audit trail integrity | Immutable audit trail of admin actions supports SOX compliance |
| HIPAA (US) | Access controls, audit controls for systems handling PHI | CDR/recording access logging supports HIPAA audit requirements |
| PCI DSS | Logging and monitoring access to cardholder data environments | Admin action logging on network equipment (PBX) supports PCI Requirement 10 |
| ISO 27001 | A.12.4 — Logging and monitoring | Event logging, protection of log information, administrator/operator logs |
| Local telecom regulations | May require retention of call records and system access logs | Jurisdiction-specific; consult local counsel |

---

## 2. Data Classification

### Data Stored in Audit Events

| Field | Classification | Contains PII | Retention Sensitivity |
|-------|---------------|-------------|----------------------|
| `actor` | Internal | Yes (admin username) | Medium — de-identify after retention period |
| `source_ip` | Internal | Yes (network identifier) | Medium — may be subject to GDPR |
| `module_name`, `action` | Internal | No | Low |
| `object_type`, `object_id` | Internal | Possibly (extension numbers, user IDs) | Medium |
| `change_before/after/changed` | Internal/Restricted | Possibly (config values, extension details) | Medium — redacted sensitive fields |
| `occurred_at_*` timestamps | Internal | No | Low |
| `session_id`, `event_id` | Internal | No | Low |

### Data NOT Stored

| Data Type | Handling |
|-----------|---------|
| Passwords | Replaced with `***REDACTED***` before persistence |
| API keys/tokens | Replaced with `***REDACTED***` before persistence |
| Private keys | Replaced with `***REDACTED***` before persistence |
| Call recordings content | Not captured — only access events logged |
| CDR content | Not captured — only search/view access events logged |

---

## 3. Retention Policy Recommendations

### Default Retention Periods

| Data Type | Recommended Retention | Rationale |
|-----------|----------------------|-----------|
| State-changing admin actions | 3 years | Compliance audit trail, incident investigation |
| Authentication events (login/logout/timeout) | 2 years | Security monitoring, access pattern analysis |
| Authentication failures | 1 year | Security investigation, brute-force analysis |
| Sensitive data read events | 2 years | Privacy compliance, data access auditing |
| Session metadata | 2 years | Correlates with event retention |

### Implementing Retention

The module enforces append-only semantics. Data retention (purging old records) must be performed by a DBA using the break-glass procedure:

#### MariaDB — Partitioned Retention

```sql
-- Recommended: Partition audit_events by month for efficient retention
ALTER TABLE audit_events
PARTITION BY RANGE (occurred_at_unix) (
  PARTITION p_2026_01 VALUES LESS THAN (UNIX_TIMESTAMP('2026-02-01')),
  PARTITION p_2026_02 VALUES LESS THAN (UNIX_TIMESTAMP('2026-03-01')),
  -- ... add monthly partitions ...
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- Drop old partitions (DBA break-glass, requires trigger disable):
-- ALTER TABLE audit_events DROP PARTITION p_2024_01;
```

#### PostgreSQL — Time-Based Retention

```sql
-- Partitioned table approach (PostgreSQL 11+)
CREATE TABLE audit_events_archive (LIKE audit_events INCLUDING ALL)
PARTITION BY RANGE (occurred_at_unix);

-- Retention via partition drop (DBA break-glass):
-- DROP TABLE audit_events_y2024m01;
```

#### Cron-Based Retention Job

```bash
#!/bin/bash
# /etc/cron.monthly/audit-retention.sh
# Requires DBA credentials and trigger disable

RETENTION_DAYS=1095  # 3 years
CUTOFF=$(date -d "-${RETENTION_DAYS} days" +%s)

# IMPORTANT: Disable triggers before deletion
mysql -u dba_user -p auditcompliance <<SQL
DROP TRIGGER IF EXISTS trg_audit_events_no_delete;
DELETE FROM audit_events WHERE occurred_at_unix < ${CUTOFF};
CREATE TRIGGER trg_audit_events_no_delete BEFORE DELETE ON audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit tables are append-only';
SQL
```

---

## 4. GDPR Considerations

### Lawful Basis for Processing

Audit logging of admin actions is justified under GDPR Article 6(1)(f) — **Legitimate interests** (security of processing, accountability).

### Data Subject Rights

| Right | Applicability | Module Response |
|-------|---------------|----------------|
| Right of access (Art. 15) | Admin users may request their audit trail | Export function available via GUI |
| Right to erasure (Art. 17) | May be overridden by legal obligation to retain | Audit logs typically exempt under Art. 17(3)(e) — legal claims defense |
| Right to restriction (Art. 18) | Limited applicability for security logs | Document basis for retention |
| Data portability (Art. 20) | Not applicable for security audit logs | N/A |

### Data Minimization

- Only state-changing actions and sensitive read events are logged
- Credential data is redacted before persistence
- No bulk content capture (call recordings, CDR data itself not stored)
- Payload data truncated to 2048 characters per field

---

## 5. Compliance Verification Checklist

| # | Check | Method | Frequency |
|---|-------|--------|-----------|
| 1 | Audit logging is active | Check recent events in Audit Compliance GUI | Weekly |
| 2 | Immutability triggers are in place | Run `SHOW TRIGGERS` / `SELECT FROM pg_trigger` | Monthly |
| 3 | DB account has only INSERT+SELECT | Run `SHOW GRANTS` / check `information_schema.table_privileges` | Monthly |
| 4 | TLS is enforced on DB connection | Verify DSN contains ssl/sslmode parameters | Monthly |
| 5 | Sensitive fields are redacted | Sample recent events, verify no passwords in clear text | Quarterly |
| 6 | Retention policy is followed | Verify oldest events align with retention period | Quarterly |
| 7 | Export rate limiting is active | Test rapid export requests | Quarterly |
| 8 | RBAC is correctly configured | Verify unauthorized admins cannot access module | Quarterly |
| 9 | Auth failure events are being captured | Review recent failure events, correlate with known failed logins | Monthly |
| 10 | Coverage matrix is up to date | After any module install/upgrade, verify coverage | Per change |

---

## 6. Archival Strategy

For long-term archival beyond the active retention period:

1. **Export to cold storage**: Use the export function or direct SQL dump to archive records before purging
2. **Format**: JSON export preserves all fields and is human-readable; CSV for spreadsheet analysis
3. **Integrity**: Consider adding SHA-256 hash of the export file for tamper detection:
   ```bash
   sha256sum audit-export-2026.json > audit-export-2026.json.sha256
   ```
4. **Storage**: Encrypted at rest (AES-256), with access limited to compliance team
5. **Labeling**: Include retention period end date, data classification, and responsible party in archive metadata
