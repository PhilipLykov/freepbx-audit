-- =============================================================
-- Audit Compliance Module — PostgreSQL Role Hardening
-- Least-privilege DB role for the audit writer application.
-- =============================================================

-- 1. Create the audit database (run as superuser)
CREATE DATABASE auditcompliance
  ENCODING 'UTF8'
  LC_COLLATE 'en_US.UTF-8'
  LC_CTYPE 'en_US.UTF-8';

\connect auditcompliance;

-- 2. Create a dedicated application role
CREATE ROLE audit_writer WITH
  LOGIN
  PASSWORD '<REPLACE_WITH_STRONG_PASSWORD>'
  NOSUPERUSER
  NOCREATEDB
  NOCREATEROLE
  NOINHERIT
  CONNECTION LIMIT 10;

-- 3. Grant minimal schema access
GRANT USAGE ON SCHEMA public TO audit_writer;

-- 4. Grant SELECT + INSERT only on audit tables (append-only)
GRANT SELECT, INSERT ON audit_events TO audit_writer;
GRANT SELECT, INSERT ON audit_sessions TO audit_writer;

-- 5. No UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER grants

-- 6. Default privileges for future tables created by the module installer
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT ON TABLES TO audit_writer;

-- 7. Enforce SSL connections (in pg_hba.conf, not SQL)
-- Example pg_hba.conf line:
--   hostssl auditcompliance audit_writer 0.0.0.0/0 scram-sha-256

-- 8. Verify grants
SELECT grantee, privilege_type, table_name
FROM information_schema.table_privileges
WHERE grantee = 'audit_writer'
ORDER BY table_name, privilege_type;
-- Expected: SELECT and INSERT on audit_events and audit_sessions only

-- =============================================================
-- IMMUTABILITY TRIGGERS (PostgreSQL version)
-- Created by module install, verified here.
-- =============================================================

-- Verify trigger + function existence
SELECT tgname, tgrelid::regclass, proname
FROM pg_trigger t
JOIN pg_proc p ON t.tgfoid = p.oid
WHERE tgname LIKE 'trg_audit%';

-- Test: Attempt to UPDATE an audit event (must fail)
-- UPDATE audit_events SET action='tampered' WHERE event_id = 'test';
-- Expected: ERROR: Audit tables are append-only

-- Test: Attempt to DELETE an audit event (must fail)
-- DELETE FROM audit_events WHERE event_id = 'test';
-- Expected: ERROR: Audit tables are append-only

-- =============================================================
-- OPTIONAL: Break-glass DBA procedure
-- Requires superuser. Disable triggers temporarily.
-- =============================================================

-- ALTER TABLE audit_events DISABLE TRIGGER trg_audit_events_no_update;
-- ALTER TABLE audit_events DISABLE TRIGGER trg_audit_events_no_delete;
-- -- perform emergency correction here --
-- ALTER TABLE audit_events ENABLE TRIGGER trg_audit_events_no_update;
-- ALTER TABLE audit_events ENABLE TRIGGER trg_audit_events_no_delete;
