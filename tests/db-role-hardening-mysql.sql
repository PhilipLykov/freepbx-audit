-- =============================================================
-- Audit Compliance Module — MariaDB/MySQL Role Hardening
-- Least-privilege DB account for the audit writer application.
-- =============================================================

-- 1. Create the audit database (run as DBA)
CREATE DATABASE IF NOT EXISTS auditcompliance
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Create a dedicated application account with INSERT + SELECT only
CREATE USER IF NOT EXISTS 'audit_writer'@'%' IDENTIFIED BY '<REPLACE_WITH_STRONG_PASSWORD>'
  REQUIRE SSL;

-- 3. Grant minimal privileges — append-only (INSERT) + read (SELECT)
GRANT SELECT, INSERT ON auditcompliance.* TO 'audit_writer'@'%';

-- 4. Explicitly deny schema modifications
-- (No ALTER, DROP, CREATE, UPDATE, DELETE, INDEX, TRIGGER grants)

-- 5. Flush privileges to apply
FLUSH PRIVILEGES;

-- 6. Verify grants
SHOW GRANTS FOR 'audit_writer'@'%';
-- Expected: GRANT SELECT, INSERT ON `auditcompliance`.* TO 'audit_writer'@'%'

-- =============================================================
-- IMMUTABILITY TRIGGERS (created by module install, verified here)
-- These prevent UPDATE/DELETE even if someone escalates to a
-- higher-privilege account that has those grants.
-- =============================================================

-- Verify trigger existence
SHOW TRIGGERS FROM auditcompliance;

-- Test: Attempt to UPDATE an audit event (must fail)
-- UPDATE auditcompliance.audit_events SET action='tampered' WHERE 1=1;
-- Expected: ERROR 1644 (45000): Audit tables are append-only

-- Test: Attempt to DELETE an audit event (must fail)
-- DELETE FROM auditcompliance.audit_events WHERE 1=1;
-- Expected: ERROR 1644 (45000): Audit tables are append-only

-- =============================================================
-- OPTIONAL: Break-glass DBA procedure
-- Only for emergency data correction by authorized DBA.
-- Requires dropping triggers temporarily.
-- =============================================================

-- DELIMITER //
-- DROP TRIGGER IF EXISTS trg_audit_events_no_update//
-- DROP TRIGGER IF EXISTS trg_audit_events_no_delete//
-- -- perform emergency correction here --
-- -- re-create triggers immediately --
-- CREATE TRIGGER trg_audit_events_no_update BEFORE UPDATE ON audit_events
-- FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit tables are append-only'//
-- CREATE TRIGGER trg_audit_events_no_delete BEFORE DELETE ON audit_events
-- FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit tables are append-only'//
-- DELIMITER ;
