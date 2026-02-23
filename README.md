# FreePBX Audit Compliance (Simple Overview)

This project adds an **Audit Compliance** module to FreePBX/pbxACT.

In simple words: it records **who changed what and when** in the PBX admin web interface, and lets you search those records later.

## What problem it solves

If an administrator changes a route, extension, certificate, backup job, or other critical setting, you can see:

- who did it
- when it happened
- what module/action was involved
- session context (login/activity/logout/timeout)

This helps with compliance, incident investigations, and internal control.

## Key points (non-technical)

- **Immutable audit log**: records are append-only by design
- **Remote database support**: works with MariaDB/PostgreSQL (including ODBC mode)
- **Built-in GUI**: dashboard, search, session timeline, module discovery, settings
- **Security-focused**: sensitive values are redacted, TLS can be enforced
- **Works with FreePBX and pbxACT**: designed for production PBX environments

## Where to start

- Main module guide: `module/auditcompliance/README.md`
- Full docs index: `docs/README.md`

## Quick install (server side)

```bash
cp -r auditcompliance /var/www/html/admin/modules/
fwconsole ma install auditcompliance
fwconsole reload
```

After install, open:

**Reports -> Audit Compliance**
