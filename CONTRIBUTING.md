# Contributing to Audit Compliance Module

Thank you for your interest in contributing. This document describes the development standards,
workflow, and review process for the project.

## Prerequisites

- PHP 7.4+ (8.1+ recommended) with `pdo_mysql` and/or `pdo_pgsql` extensions.
- A FreePBX 17.x development instance for integration testing.
- Git for version control.
- Familiarity with the FreePBX BMO (Basic Module Object) pattern.

## Code Standards

### PHP

- Follow **PSR-12** coding style.
- Use tabs for indentation (FreePBX convention).
- All database queries must use **prepared statements** with bound parameters. Direct string
  interpolation in SQL is not permitted.
- Filter/sort column names must be validated against **explicit allowlists**.
- All view output must be escaped with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Sensitive data (passwords, tokens, keys) must be passed through `redactSensitiveData()` before
  persistence.
- All timestamps must use the `Europe/Chisinau` timezone in `DD-MM-YYYY HH:MM:SS` format.
- Log entries must include a timestamp prefix via `debugLog()`.
- Error handling: wrap external calls in `try/catch(\Throwable $e)` -- audit failures must never
  crash the FreePBX GUI.

### JavaScript

- Use strict mode (`"use strict"`) in all IIFE wrappers.
- Escape dynamic content with `createTextNode()` or the `esc()` helper -- never use `innerHTML`
  with unescaped data.
- Prefix audit-specific CSS classes with `audit-` to avoid collisions with FreePBX themes.

### XML

- `module.xml` hook entries must have matching PHP handler methods in
  `Auditcompliance.class.php`.
- All hook methods must follow the naming convention `hookModuleName_methodName()`.

## Git Workflow

1. Create a feature branch from `main`: `git checkout -b feature/short-description`.
2. Make atomic commits with clear messages following the format:
   ```
   <type>: <short summary>

   <optional body explaining why, not what>
   ```
   Types: `feat`, `fix`, `refactor`, `docs`, `test`, `security`, `chore`.
3. Ensure all PHP files pass `php -l` syntax checks.
4. Open a pull request against `main` with a description referencing any related issues.

## Pull Request Checklist

Before submitting a PR, verify:

- [ ] All PHP files pass `php -l` syntax validation.
- [ ] `module.xml` hooks and PHP handler methods are 1:1 consistent.
- [ ] No secrets, credentials, or sensitive data in committed code.
- [ ] New AJAX endpoints are registered in both `ajaxRequest()` and `ajaxHandler()`.
- [ ] New filter/sort parameters are added to the relevant allowlist.
- [ ] New sensitive fields are covered by redaction patterns.
- [ ] Relevant documentation is updated (README, API reference, coverage matrix).
- [ ] Security test plan updated for new attack surface.
- [ ] CHANGELOG.md updated under `[Unreleased]`.

## Testing

### Syntax Validation

```bash
find module/auditcompliance -name '*.php' -exec php -l {} \;
```

### Security Test Plan

Follow the test cases in [tests/security-test-plan.md](tests/security-test-plan.md). All OWASP
test cases, AJAX interceptor validations, and coverage gate checks must pass on a staging
FreePBX instance before merging.

### Integration Testing

1. Install the module on a FreePBX 17.x staging instance.
2. Verify all 5 GUI views load without errors (Dashboard, Search, Timeline, Discovery, Settings).
3. Perform CRUD operations on hooked modules and confirm events appear.
4. Run the Module Discovery view and confirm no unexpected gaps.
5. Verify export (CSV/JSON) produces valid output.
6. Test RBAC by accessing the module as a restricted admin.

## Architecture References

Before making changes, familiarise yourself with:

- [Architecture Overview](docs/architecture.md) -- System context, components, data flows.
- [API Reference](docs/api-reference.md) -- AJAX endpoint contracts.
- [Database Schema](docs/database-schema.md) -- Tables, indexes, triggers.
- [Configuration Reference](docs/configuration-reference.md) -- All settings and constants.

## Reporting Issues

- For bugs, open a GitHub issue with reproduction steps and FreePBX/PHP version.
- For security vulnerabilities, follow the [Security Policy](SECURITY.md).
