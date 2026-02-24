# FreePBX Upstream Analysis

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-beta1            |
| Date     | 25-02-2026                               |
| Status   | Beta                                     |
| Audience | Developers                               |

---

## Snapshot

- Analysis timestamp (Europe/Chisinau, `DD-MM-YYYY HH:MM:SS`): `20-02-2026 16:55:32`
- Source organization: `https://github.com/FreePBX`
- Repositories cloned: `80` (public, non-archived, `release/*` default branch)
- Local source root: `freepbx-audit/upstream/repos/`
- Generated surface inventory: `freepbx-audit/upstream/module-surface-matrix.csv`

## Verified Core References

- `framework/amp_conf/htdocs/admin/libraries/BMO/GuiHooks.class.php`
  - Confirms `doConfigPageInit($display)` execution model and ownership ordering.
- `framework/amp_conf/htdocs/admin/libraries/BMO/Hooks.class.php`
  - Confirms `myConfigPageInits()` discovery and hook registration model.
- `framework/amp_conf/htdocs/admin/libraries/BMO/Database.class.php`
  - Confirms DB abstraction behavior and DSN handling.
- `conferences/Conferences.class.php`
  - Confirms representative module pattern for BMO class structure and prepared statements.

## Communication Surface Findings (80-module snapshot)

- Modules with `Api/Rest` path: `19`
- Modules with `ajaxHandler()`: `67`
- Modules with XML `<hooks>` definitions: `32`
- Modules with `myConfigPageInits()`: `4` (`callrecording`, `fax`, `paging`, `pm2`)

## Important Architecture Implications

- `doConfigPageInit` alone is insufficient for broad coverage.
- The dominant write/event channels are `ajax.php` handlers and module-specific AJAX requests.
- Some modules include API layers (REST/GQL paths), requiring API-side event adapters.
- Hook-based inter-module behavior (`module.xml` `<hooks>`) must be audited because write actions can be triggered indirectly.
- Session-grouped compliance reporting must be modeled as:
  - immutable per-action events,
  - plus session correlation fields to produce timeline summaries (`login` -> `logout/timeout`).

## Confirmed Atypical/High-Priority Modules

- `backup`
  - Has `ajaxRequest()` and `ajaxHandler()` in `Backup.class.php`.
  - Includes API namespace path (`Api/Gql`), indicating additional non-GUI communication paths.
- `certman`
  - Has `ajaxRequest()` and `ajaxHandler()` in `Certman.class.php`.
  - Includes API namespace path (`Api/Gql`), indicating additional non-GUI communication paths.

## Commercial/PBXact Note

- Some pbxACT commercial modules are not fully represented in public FreePBX OSS repositories.
- These modules must be treated as a separate coverage tier during deployment validation on the target pbxACT instance.
