# Rollback Guide — Audit Compliance Module

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-beta1            |
| Date     | 25-02-2026                               |
| Status   | Beta                                     |
| Audience | Administrators, Operations               |

---

## When to Roll Back

- Module causes FreePBX GUI errors or performance degradation
- Database connection failures impacting other FreePBX operations
- Compatibility issues with specific pbxACT commercial modules
- Critical security vulnerability discovered in the module

---

## Pre-Rollback Checklist

1. **Verify the issue is caused by the audit module** — Check FreePBX logs:
   ```bash
   grep -i 'auditcompliance\|audit_' /var/log/asterisk/freepbx.log | tail -50
   ```

2. **Document the issue** — Record the error messages, timestamps, and affected functionality before rolling back.

3. **Backup current state** — Even if rolling back, preserve the current module state:
   ```bash
   cp -r /var/www/html/admin/modules/auditcompliance /tmp/auditcompliance-pre-rollback-$(date +%Y%m%d)
   ```

---

## Rollback Procedure

### Step 1: Disable the Module

```bash
fwconsole ma disable auditcompliance
fwconsole reload
```

This immediately stops all hook execution and event capture. The FreePBX GUI returns to normal operation without the audit hooks.

### Step 2: Verify Normal Operation

- Log in to FreePBX Web GUI
- Navigate through several configuration pages
- Confirm no errors related to `auditcompliance` appear
- Check that Apply Config works correctly

### Step 3: (Optional) Uninstall the Module

If disabling is not sufficient or you want a clean removal:

```bash
fwconsole ma uninstall auditcompliance
fwconsole reload
```

### Step 4: (Optional) Remove Module Files

```bash
rm -rf /var/www/html/admin/modules/auditcompliance
```

---

## Data Preservation

**Audit data in the remote database is NOT affected by module rollback.**

The `audit_events` and `audit_sessions` tables on the remote audit DB remain intact and immutable. They can be:

1. **Queried directly** via SQL for compliance reporting
2. **Re-connected** when a fixed version of the module is deployed
3. **Archived** for long-term retention per compliance requirements

### To preserve audit data during uninstall

By default, `fwconsole ma uninstall` may attempt to drop local tables if the module's `uninstall.php` includes drop statements. The module is designed to:
- **Never drop remote database tables** — Only local FreePBX config entries are removed
- **Preserve all audit records** — The remote DB is independent of the FreePBX installation

If using the local DB fallback (development mode), backup before uninstall:
```bash
mysqldump -u root freepbx audit_events audit_sessions > /tmp/audit-backup-$(date +%Y%m%d).sql
```

---

## Restore Previous Version

If you have a backup of a previous module version:

```bash
# Remove current version
rm -rf /var/www/html/admin/modules/auditcompliance

# Restore backup
cp -r /tmp/auditcompliance.bak /var/www/html/admin/modules/auditcompliance

# Reinstall
fwconsole ma install auditcompliance
fwconsole reload
```

---

## Post-Rollback Verification

1. **FreePBX GUI**: All pages load without errors
2. **Apply Config**: Works without audit-related warnings
3. **Admin operations**: Create/edit/delete operations complete normally
4. **Logs**: No `auditcompliance` errors in `/var/log/asterisk/freepbx.log`
5. **Audit DB**: Confirm remote audit database is accessible and data is intact:
   ```sql
   SELECT COUNT(*) FROM audit_events;
   SELECT MAX(occurred_at_local) FROM audit_events;
   ```

---

## Impact Assessment

| Component | Impact of Disabling | Impact of Uninstalling |
|-----------|--------------------|-----------------------|
| Audit event capture | Stops immediately | Stops immediately |
| Existing audit data (remote) | No impact | No impact |
| Existing audit data (local fallback) | No impact | May be dropped — backup first |
| FreePBX GUI performance | Returns to baseline | Returns to baseline |
| BMO hook processing | Hooks deregistered | Hooks deregistered |
| Menu item | Hidden | Removed |
| Module configuration (astdb) | Preserved | Removed |
