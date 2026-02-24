# Deployment Guide — Audit Compliance Module

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-alpha            |
| Date     | 21-02-2026                               |
| Status   | Draft                                    |
| Audience | Administrators, Operations               |

---

## Prerequisites

- FreePBX 17.x / pbxACT with Framework ≥ 17.0.1
- PHP 7.4+ (PHP 8.1+ recommended)
- PDO extension with `pdo_mysql` and/or `pdo_pgsql` driver, or `pdo_odbc` for ODBC connections
- Remote audit database server (MariaDB 10.5+ or PostgreSQL 14+)
- TLS certificates for database connections
- (ODBC only) `unixODBC` and the appropriate ODBC driver for your database engine

---

## 1. Prepare the Remote Audit Database

### MariaDB/MySQL

```bash
# On the DB server, create the database and user:
mysql -u root -p <<'SQL'
CREATE DATABASE auditcompliance
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'audit_writer'@'%' IDENTIFIED BY '<STRONG_PASSWORD>'
  REQUIRE SSL;

GRANT SELECT, INSERT ON auditcompliance.* TO 'audit_writer'@'%';
FLUSH PRIVILEGES;
SQL
```

### PostgreSQL

```bash
# On the DB server:
sudo -u postgres psql <<'SQL'
CREATE DATABASE auditcompliance ENCODING 'UTF8';
\connect auditcompliance
CREATE ROLE audit_writer WITH LOGIN PASSWORD '<STRONG_PASSWORD>'
  NOSUPERUSER NOCREATEDB NOCREATEROLE CONNECTION LIMIT 10;
GRANT USAGE ON SCHEMA public TO audit_writer;
SQL
```

> Table creation, indexes, and immutability triggers are automatically applied by the module on first load. The `audit_writer` role needs `CREATE` on schema during initial install only, or tables can be created by a DBA role first and then `INSERT + SELECT` granted.

---

## 2. Install the Module

### Option A: FreePBX Module Admin (GUI)

1. Package the module: `tar czf auditcompliance.tar.gz -C freepbx-audit/module auditcompliance`
2. Navigate to **Admin → Module Admin → Upload Modules**
3. Upload `auditcompliance.tar.gz`
4. Click **Install** and then **Apply Config**

### Option B: CLI Installation

```bash
# Copy module to FreePBX modules directory
cp -r freepbx-audit/module/auditcompliance /var/www/html/admin/modules/

# Install via fwconsole
fwconsole ma install auditcompliance
fwconsole reload
```

---

## 3. Configure the Remote Database Connection

After installation, configure the database connection via one of three methods:

- **Settings GUI** (recommended): Navigate to **Reports > Audit Compliance > Settings** and use the graphical interface to configure connection type, hostname, port, database name, credentials, and TLS settings. Includes a connection test button.
- **CLI**: Use `fwconsole setting` commands as shown below.
- **FreePBX Advanced Settings**: Set the config keys via the advanced settings GUI.

```bash
# MariaDB example
fwconsole setting AUDITCOMPLIANCE_DB_DSN "mysql:host=audit-db.example.com;port=3306;dbname=auditcompliance;charset=utf8mb4"
fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<STRONG_PASSWORD>"
fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"

# PostgreSQL example
fwconsole setting AUDITCOMPLIANCE_DB_DSN "pgsql:host=audit-db.example.com;port=5432;dbname=auditcompliance;sslmode=require"
fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<STRONG_PASSWORD>"
fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"
```

### Option C: ODBC Connection

ODBC allows the audit module to connect via system-level ODBC data sources, which is useful
when:
- Your organization mandates a centralized ODBC layer for all database access.
- You need driver-level TLS/encryption configuration managed by the system administrator.
- You are connecting to a database that requires a specific ODBC driver (e.g., MySQL
  Connector/ODBC, MariaDB ODBC, psqlODBC).

#### Step 1: Install unixODBC and the Database Driver

```bash
# Debian/Ubuntu -- MariaDB ODBC driver
apt-get install unixodbc unixodbc-dev odbc-mariadb

# Debian/Ubuntu -- PostgreSQL ODBC driver
apt-get install unixodbc unixodbc-dev odbc-postgresql

# RHEL/CentOS/SNG7 -- MariaDB ODBC driver
yum install unixODBC mariadb-connector-odbc

# RHEL/CentOS/SNG7 -- PostgreSQL ODBC driver
yum install unixODBC postgresql-odbc
```

Verify the driver is registered:

```bash
odbcinst -q -d
# Expected output includes [MariaDB Unicode] or [PostgreSQL Unicode]
```

#### Step 2: Configure the System DSN

Edit `/etc/odbc.ini` to define the data source:

```ini
# /etc/odbc.ini -- MariaDB example
[AuditDB]
Driver      = MariaDB Unicode
Server      = audit-db.example.com
Port        = 3306
Database    = auditcompliance
Charset     = utf8mb4
SSLVERIFY   = 1
SSLCA       = /etc/ssl/certs/ca-certificates.crt
```

```ini
# /etc/odbc.ini -- PostgreSQL example
[AuditDB]
Driver      = PostgreSQL Unicode
Server      = audit-db.example.com
Port        = 5432
Database    = auditcompliance
SSLMode     = require
```

Test connectivity:

```bash
isql -v AuditDB audit_writer '<PASSWORD>'
# Should show "Connected!" and a SQL> prompt
```

#### Step 3: Configure the Module

```bash
fwconsole setting AUDITCOMPLIANCE_DB_DSN "odbc:AuditDB"
fwconsole setting AUDITCOMPLIANCE_DB_USER "audit_writer"
fwconsole setting AUDITCOMPLIANCE_DB_PASSWORD "<STRONG_PASSWORD>"
fwconsole setting AUDITCOMPLIANCE_DB_REQUIRE_TLS "1"
fwconsole setting AUDITCOMPLIANCE_DB_ODBC_BACKEND "mysql"   # or "pgsql"
```

The `AUDITCOMPLIANCE_DB_ODBC_BACKEND` setting is required so the module knows which SQL
dialect to use for table creation, triggers, and indexes. If omitted, the module attempts
auto-detection via `SELECT version()`, but explicit configuration is recommended.

> **TLS with ODBC**: Encryption is configured at the ODBC driver level (`SSLVERIFY`,
> `SSLCA`, `SSLMode` in `odbc.ini`), not in the PDO DSN string. The module's
> `audit_db_require_tls` setting does not validate ODBC DSNs but should remain enabled
> as a policy signal.

---

Alternatively, configure via the module's settings in the FreePBX config store:

```php
// These are stored in FreePBX's astdb/kvstore:
// audit_db_dsn, audit_db_user, audit_db_password, audit_db_require_tls,
// audit_db_odbc_backend
```

> **Security**: Never store credentials in configuration files committed to version control. Use the FreePBX config store or environment variables.

---

## 4. Verify Installation

### Check Module Status

```bash
fwconsole ma list | grep auditcompliance
# Expected: auditcompliance | 17.0.0alpha1 | Enabled | GPLv3+
```

### Check Database Tables

```bash
# MariaDB
mysql -u audit_writer -p -h audit-db.example.com auditcompliance -e "SHOW TABLES;"
# Expected: audit_events, audit_sessions

# PostgreSQL
psql -U audit_writer -h audit-db.example.com auditcompliance -c "\dt"
# Expected: audit_events, audit_sessions
```

### Check Immutability Triggers

```bash
# MariaDB
mysql -u root -p auditcompliance -e "SHOW TRIGGERS;"
# Expected: trg_audit_events_no_update, trg_audit_events_no_delete,
#           trg_audit_sessions_no_delete

# PostgreSQL
psql -U postgres auditcompliance -c "SELECT tgname FROM pg_trigger WHERE tgname LIKE 'trg_audit%';"
```

### Verify Audit Logging

1. Log in to FreePBX Web GUI
2. Navigate to any configuration page and make a change
3. Go to **Reports → Audit Compliance**
4. Verify that the login event and configuration change appear in the search results

---

## 5. Set Permissions (RBAC)

By default, only administrators with access to the `auditcompliance` section can view audit data.

To restrict access:
1. Go to **Admin → Administrators**
2. Edit the desired admin account
3. Under **Module Permissions**, set `auditcompliance` to the desired access level
4. Only users with explicit section access can view/search/export audit data

---

## 6. Network and Firewall Configuration

Ensure the FreePBX server can reach the audit database server:

```bash
# Test connectivity
telnet audit-db.example.com 3306  # MariaDB
telnet audit-db.example.com 5432  # PostgreSQL

# If using firewall rules, allow:
# Source: FreePBX server IP
# Destination: Audit DB server IP, port 3306 or 5432
# Protocol: TCP
```

Ensure TLS is configured on the database server:
- **MariaDB**: Verify `have_ssl = YES` with `SHOW VARIABLES LIKE 'have_ssl';`
- **PostgreSQL**: Verify `ssl = on` in `postgresql.conf`

---

## 7. Monitoring

### Health Checks

- Monitor the FreePBX error log for entries containing `auditcompliance`:
  ```bash
  grep auditcompliance /var/log/asterisk/freepbx.log
  ```
- Monitor DB connection health by checking for failed write events in the log
- Set up alerts for high auth failure event volume (potential brute-force indicator)

### Capacity Planning

- Each audit event uses approximately 1-2 KB of storage
- Estimate: 100 admin actions/day × 365 days = ~36,500 events/year ≈ 50-75 MB/year
- Plan for 3-5 years of retention: 150-375 MB total
- Session table is much smaller (one row per admin session)

---

## 8. Upgrade Procedure

```bash
# Backup current module
cp -r /var/www/html/admin/modules/auditcompliance /tmp/auditcompliance.bak

# Deploy new version
cp -r freepbx-audit/module/auditcompliance /var/www/html/admin/modules/

# Run module upgrade
fwconsole ma upgrade auditcompliance
fwconsole reload
```

> Audit data is never modified during upgrades. Schema migrations are additive only.
