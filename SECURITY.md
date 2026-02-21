# Security Policy

## Supported Versions

| Version          | Supported |
|------------------|-----------|
| 0.1.0-alpha      | Yes       |

Only the latest release receives security updates.

## Reporting a Vulnerability

If you discover a security vulnerability in the Audit Compliance module, **do not open a public issue**.

### Reporting Process

1. Send an encrypted email to the project maintainer at the address listed in `module.xml` `<publisher>` with:
   - A clear description of the vulnerability.
   - Steps to reproduce or a proof-of-concept.
   - The affected version(s).
   - Your assessment of severity (Critical / High / Medium / Low).
2. If possible, encrypt your report using the PGP key published at the project repository (key fingerprint to be published with stable release).
3. You will receive an acknowledgement within **48 hours**.

### Response Timeline

| Stage                          | SLA           |
|--------------------------------|---------------|
| Acknowledgement                | 48 hours      |
| Initial triage and severity    | 5 business days |
| Patch development              | 14 business days (critical: 72 hours) |
| Public disclosure (coordinated)| After patch release, or 90 days max |

### Scope

The following components are in scope:

- `Auditcompliance.class.php` and all PHP module code.
- `module.xml` hook declarations.
- JavaScript injected by `injectAuditScripts()` (logout interceptor, AJAX interceptor).
- Database schema, triggers, and SQL queries.
- CLI tool `tools/discover-pbxact-surfaces.php`.

Out of scope:

- FreePBX framework vulnerabilities (report to [FreePBX Security](https://www.freepbx.org/security/)).
- Database server configuration issues.
- Operating system and network infrastructure.

### Recognition

Security researchers who report valid vulnerabilities will be credited in the CHANGELOG under the **Security** section (unless anonymity is requested).

## Security Design References

- [Threat Model](docs/threat-model.md) -- STRIDE analysis and OWASP Top 10 mapping.
- [Security Test Plan](tests/security-test-plan.md) -- OWASP test cases and universal capture validation.
- [Data Classification](docs/data-classification-redaction.md) -- Redaction matrix and data handling.
