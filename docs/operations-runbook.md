# Operations Runbook

| Field    | Value                            |
|----------|----------------------------------|
| Module   | auditcompliance v17.0.0-beta1   |
| Date     | 25-02-2026                       |
| Status   | Beta                             |
| Audience | Operations, Infrastructure       |

---

## 1. Health Check Procedures

### Manual Health Checks

| Check | Command | Frequency | Pass Criteria |
|-------|---------|-----------|---------------|
| Module enabled | `fwconsole ma list \| grep auditcompliance` | Daily | Status: `Enabled` |
| Recent events exist | `mysql -u audit_writer -p auditcompliance -e "SELECT COUNT(*) FROM audit_events WHERE occurred_at_unix >= UNIX_TIMESTAMP() - 3600;"` | Daily | Count > 0 during business hours |
| Active sessions reasonable | `mysql -u audit_writer -p auditcompliance -e "SELECT COUNT(*) FROM audit_sessions WHERE end_reason='active';"` | Daily | Matches expected admin count |
| Triggers in place | `mysql -u root -p auditcompliance -e "SHOW TRIGGERS;"` | Weekly | 3 triggers present |
| DB grants correct | `mysql -u root -p -e "SHOW GRANTS FOR 'audit_writer'@'%';"` | Monthly | Only INSERT + SELECT |
| TLS enforced | `fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS` | Monthly | Returns `1` |
| No errors in log | `grep -i 'auditcompliance.*error\|auditcompliance.*fail' /var/log/asterisk/freepbx.log \| tail -10` | Daily | No recent errors |

### Automated Health Check Script

```bash
#!/bin/bash
# /usr/local/bin/audit-healthcheck.sh
# Exit codes: 0=OK, 1=WARNING, 2=CRITICAL

DB_HOST="audit-db.example.com"
DB_USER="audit_writer"
DB_PASS="<password>"
DB_NAME="auditcompliance"

# Check 1: Table exists and is accessible
EVENT_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" \
  -sNe "SELECT COUNT(*) FROM audit_events WHERE occurred_at_unix >= UNIX_TIMESTAMP() - 86400;" 2>/dev/null)

if [ $? -ne 0 ]; then
  echo "CRITICAL: Cannot connect to audit database"
  exit 2
fi

if [ "${EVENT_COUNT:-0}" -eq 0 ]; then
  echo "WARNING: No audit events in last 24 hours"
  exit 1
fi

# Check 2: Triggers present
TRIGGER_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" \
  -sNe "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$DB_NAME' AND TRIGGER_NAME LIKE 'trg_audit%';" 2>/dev/null)

if [ "${TRIGGER_COUNT:-0}" -lt 3 ]; then
  echo "CRITICAL: Immutability triggers missing (found ${TRIGGER_COUNT}/3)"
  exit 2
fi

# Check 3: Auth failure spike
FAILURES=$(mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" \
  -sNe "SELECT COUNT(*) FROM audit_events WHERE session_phase='failure' AND occurred_at_unix >= UNIX_TIMESTAMP() - 3600;" 2>/dev/null)

if [ "${FAILURES:-0}" -gt 50 ]; then
  echo "WARNING: High auth failure rate (${FAILURES} in last hour)"
  exit 1
fi

echo "OK: ${EVENT_COUNT} events (24h), ${TRIGGER_COUNT} triggers, ${FAILURES} failures (1h)"
exit 0
```

---

## 2. Monitoring Integration

### Nagios / Icinga

```cfg
define command {
    command_name    check_audit_health
    command_line    /usr/local/bin/audit-healthcheck.sh
}

define service {
    use                 generic-service
    host_name           freepbx-server
    service_description Audit Compliance Health
    check_command       check_audit_health
    check_interval      15
    retry_interval      5
    max_check_attempts  3
}
```

### Zabbix

Create a UserParameter:

```
UserParameter=audit.health,/usr/local/bin/audit-healthcheck.sh; echo $?
UserParameter=audit.events.24h,mysql -u audit_writer -p<pass> -h audit-db auditcompliance -sNe "SELECT COUNT(*) FROM audit_events WHERE occurred_at_unix >= UNIX_TIMESTAMP() - 86400;"
UserParameter=audit.failures.1h,mysql -u audit_writer -p<pass> -h audit-db auditcompliance -sNe "SELECT COUNT(*) FROM audit_events WHERE session_phase='failure' AND occurred_at_unix >= UNIX_TIMESTAMP() - 3600;"
```

### PRTG

Use an EXE/Script Advanced sensor pointing to the health check script. Map exit codes:
0=OK, 1=Warning, 2=Error.

---

## 3. Alerting Rules

| Alert | Condition | Severity | Action |
|-------|-----------|----------|--------|
| Auth failure spike | >50 failures in 1 hour | Warning | Investigate source IPs; check for brute-force |
| Auth failure flood | >200 failures in 1 hour | Critical | Block source IP(s) at firewall |
| No events in 24h | `COUNT(*) = 0` for last 24 hours | Warning | Check module status, DB connectivity |
| DB connection failure | Health check returns CRITICAL | Critical | Check DB server, network, credentials |
| Triggers missing | Trigger count < 3 | Critical | Immutability compromised; investigate immediately |
| Storage approaching limit | DB size > 80% of allocated | Warning | Implement retention policy or expand storage |

---

## 4. Incident Response

### Scenario 1: Audit Module Failure (DB unreachable)

1. **Detect**: Health check alert or zero events in Dashboard.
2. **Assess**: Check `freepbx.log` for connection errors.
3. **Mitigate**: FreePBX continues to operate normally (audit writes fail silently).
4. **Resolve**: Fix DB connectivity (network, credentials, DB server restart).
5. **Verify**: Perform a test action and confirm events appear.
6. **Document**: Note the gap in audit coverage with start/end timestamps.

### Scenario 2: Suspected Audit Tampering

1. **Detect**: Missing triggers, unexpected data modifications.
2. **Assess**: Check trigger status and DB user grants.
3. **Preserve evidence**: Export current audit data immediately.
4. **Investigate**: Review DB server access logs for `ALTER TRIGGER`, `DROP TRIGGER`, or
   direct `UPDATE`/`DELETE` statements.
5. **Restore**: Recreate triggers (module will do this on next request if table structure
   is intact; otherwise, use DDL from [Database Schema](database-schema.md)).
6. **Escalate**: Follow organizational incident response procedures.

### Scenario 3: High Auth Failure Volume

1. **Detect**: Alert from monitoring or Dashboard KPI showing elevated failures.
2. **Assess**: Search for failures in the Search view filtered by `session_phase=failure`.
3. **Identify**: Check source IPs -- single source indicates brute-force; multiple sources
   indicate credential stuffing.
4. **Mitigate**: Block offending IP(s) at the firewall or FreePBX Intrusion Detection module.
5. **Review**: Check if any successful login followed the failures (account compromise).

---

## 5. Capacity Planning

### Storage Estimation Formula

```
Annual storage (MB) = (daily_admin_actions * 365 * avg_event_size_kb) / 1024
```

| Scenario | Daily Actions | Avg Event Size | Annual Storage |
|----------|---------------|----------------|----------------|
| Small office (1-2 admins) | 20-50 | 1.5 KB | ~10-25 MB |
| Medium enterprise (5-10 admins) | 100-500 | 1.5 KB | ~55-270 MB |
| Large enterprise (20+ admins) | 500-2000 | 1.5 KB | ~270 MB - 1 GB |

### Retention Impact

| Retention Period | Small | Medium | Large |
|-----------------|-------|--------|-------|
| 1 year | 10-25 MB | 55-270 MB | 270 MB - 1 GB |
| 3 years | 30-75 MB | 165-810 MB | 810 MB - 3 GB |
| 5 years | 50-125 MB | 275 MB - 1.3 GB | 1.3 - 5 GB |

### Database Server Recommendations

| Deployment Size | vCPU | RAM | Disk | Notes |
|-----------------|------|-----|------|-------|
| Small | 1 | 1 GB | 10 GB | Shared DB server acceptable |
| Medium | 2 | 4 GB | 50 GB | Dedicated DB instance recommended |
| Large | 4 | 8 GB | 100 GB | Dedicated instance with SSD; consider partitioning |

---

## 6. Backup Procedures

### Audit Database Backup

```bash
#!/bin/bash
# /etc/cron.daily/audit-backup.sh
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/audit"
mkdir -p "$BACKUP_DIR"

# MariaDB
mysqldump -u backup_user -p<pass> -h audit-db.example.com \
  --single-transaction --routines --triggers \
  auditcompliance > "${BACKUP_DIR}/audit_${TIMESTAMP}.sql"

# Compress and add checksum
gzip "${BACKUP_DIR}/audit_${TIMESTAMP}.sql"
sha256sum "${BACKUP_DIR}/audit_${TIMESTAMP}.sql.gz" > "${BACKUP_DIR}/audit_${TIMESTAMP}.sql.gz.sha256"

# Retain 30 days of backups
find "$BACKUP_DIR" -name "audit_*.sql.gz" -mtime +30 -delete
find "$BACKUP_DIR" -name "audit_*.sha256" -mtime +30 -delete
```

### PostgreSQL Variant

```bash
pg_dump -U backup_user -h audit-db.example.com -Fc \
  auditcompliance > "${BACKUP_DIR}/audit_${TIMESTAMP}.dump"
```

### Backup Verification

Monthly verification procedure:

1. Restore the latest backup to a test database.
2. Verify row counts match production.
3. Verify trigger presence.
4. Document verification with timestamp.

---

## 7. Log Rotation

The audit module logs to the standard FreePBX log file (`/var/log/asterisk/freepbx.log`).
FreePBX log rotation configuration typically resides at `/etc/logrotate.d/freepbx` or
`/etc/logrotate.d/asterisk`.

No additional log rotation configuration is needed for the audit module.

---

## 8. Performance Baseline

### Expected Query Times

| Query | Expected Time | Index Used |
|-------|---------------|------------|
| Dashboard stats (8 queries) | < 200 ms total | Multiple single-column indexes |
| Search with filters (50 rows) | < 100 ms | Composite indexes |
| Session timeline (25 sessions + events) | < 300 ms | `idx_audit_sessions_login_at_unix`, `idx_audit_events_session_id` |
| Deduplication check | < 10 ms | `idx_audit_events_dedup` |
| Event write (INSERT) | < 5 ms | N/A |

### Performance Tuning

If query times exceed baselines:

1. **Check index existence**: Verify all 9 indexes are present.
2. **Analyze table statistics**: `ANALYZE TABLE audit_events;` (MariaDB) or
   `ANALYZE audit_events;` (PostgreSQL).
3. **Consider partitioning**: For tables exceeding 1M rows, partition by
   `occurred_at_unix` range (monthly partitions). See [Retention & Compliance](retention-compliance.md).
4. **Connection pooling**: If using PostgreSQL, consider PgBouncer to reduce connection overhead.
