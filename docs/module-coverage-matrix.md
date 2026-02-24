# Official Module Coverage Matrix

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-beta1            |
| Date     | 25-02-2026                               |
| Status   | Beta                                     |
| Audience | Developers, Compliance Officers          |

---

## Scope and Method

- Source of truth: upstream FreePBX OSS repos + pbxACT commercial module analysis
- Input set: 80+ official FreePBX repos with `release/*` default branch + pbxACT commercial modules
- Coverage objective: ensure audit capture for:
  - state-changing actions (create/update/delete/apply),
  - auth/session events (login/logout/failure/timeout),
  - reads of sensitive/personal data (credentials, PINs, personal contacts, call records, logs).

## Universal Capture Architecture

The audit module implements a **multi-channel capture strategy** that achieves near-universal coverage without modifying any native FreePBX/pbxACT core files:

```
Channel         Mechanism                              Scope
-----------     ------------------------------------   -----------------------------------------
GUI             doConfigPageInit() on POST             All active module pages (dynamic)
GUI (read)      doConfigPageInit() on GET              23 designated sensitive pages
GUI (action)    doConfigPageInit() on GET              State-changing actions (del/copy/submit)
Shutdown        register_shutdown_function             Safety net for early exit()/redirect()
AJAX            Client-side XHR interceptor            ALL ajax.php POST/PUT/DELETE calls
HOOK            module.xml <hooks> declarations        38 methods across 10 modules
AUTH            ensureSessionState() + JS beacon       Login/logout/timeout/failure
```

### Coverage Tiers

| Tier | Coverage Level | Channels | Description |
|------|---------------|----------|-------------|
| **Full** | `HOOK + GUI + AJAX + Read` | All 4 write channels + sensitive read | Modules with explicit hook declarations for deep method-level capture |
| **GUI+AJAX+Read** | `GUI + AJAX + Read` | 3 channels + sensitive read | Modules with sensitive data pages but no hookable processHooks() methods |
| **GUI+AJAX** | `GUI + AJAX` | 2 channels | Baseline coverage for all active modules via dynamic page registration + universal interceptor |

### Deduplication

When the same action is captured by multiple channels (e.g. GUI POST + HOOK fire simultaneously), the `routeEvent()` central router deduplicates via a 3-second time window check on `(session_id, module_name, action, object_id)`.

### Universal AJAX Interceptor

The JS interceptor patches `XMLHttpRequest.prototype.open/send` on every admin page to monitor all `ajax.php` calls. For any POST/PUT/DELETE to a module other than `auditcompliance`, it beacons the metadata (module, command, HTTP method, status) to `recordInterceptedAjax`. This closes the AJAX gap for ALL modules without per-module adapters.

---

## AUTH Channel Coverage Status

| Event Type | Detection Mechanism | Status | Notes |
|---|---|---|---|
| **Login success** | `ensureSessionState()` in `doConfigPageInit` — detects first page load after `gui_auth.php` sets `$_SESSION['AMP_user']`. | **Covered** | Idempotency guard via `SESSION_KEY_LOGIN_RECORDED`. |
| **Logout (explicit)** | Inline JS intercepts `a[href*="logout=true"]` clicks, fires AJAX beacon. | **Covered** | 3-second timeout fallback. |
| **Logout (retroactive)** | `closeStaleActiveSessions()` finds stale active sessions on new login. | **Covered** | Handles missed JS beacons. |
| **Session timeout** | `ensureSessionState()` compares last activity against idle threshold (default 1800s). | **Covered** | Independent of framework `SESSION_TIMEOUT`. |
| **Auth failure** | AJAX handler `recordAuthFailure` with `authenticate=false`. | **Covered (handler ready)** | Login page JS snippet needed for UI integration. |

---

## Sensitive Read Coverage (23 pages)

| Page | Read Type | Sensitive Data | Channel | Status |
|------|-----------|---------------|---------|--------|
| `cdr` | CDR list/search access | Call records, phone numbers | GUI (GET) | **Covered** |
| `recordings` | Recording access/playback | Call recordings | GUI (GET) | **Covered** |
| `userman` | User credentials view | Usernames, passwords, email | GUI (GET) | **Covered** |
| `certman` | Certificate/private key view | TLS certificates, private keys | GUI (GET) | **Covered** |
| `voicemail` | Voicemail settings access | VM passwords, email addresses | GUI (GET) | **Covered** |
| `conferences` | Conference PIN access | User/admin PINs | GUI (GET) | **Covered** |
| `contactmanager` | Contact data access | Personal contact information | GUI (GET) | **Covered** |
| `queues` | Queue credentials access | Queue passwords, agent config | GUI (GET) | **Covered** |
| `manager` | AMI credentials access | AMI user secrets, IP rules | GUI (GET) | **Covered** |
| `sipsettings` | SIP credentials access | TURN passwords, TLS cert paths | GUI (GET) | **Covered** |
| `logfiles` | System log access | Asterisk logs, call data | GUI (GET) | **Covered** |
| `arimanager` | ARI credentials access | ARI user passwords | GUI (GET) | **Covered** |
| `filestore` | Storage credentials access | FTP/SSH/S3/Dropbox passwords/keys | GUI (GET) | **Covered** |
| `calendar` | Calendar credentials access | CalDAV/OAuth credentials | GUI (GET) | **Covered** |
| `fax` | Fax settings access | Fax email addresses (personal) | GUI (GET) | **Covered** |
| `pinsets` | PIN credentials access | PIN set passwords | GUI (GET) | **Covered** |
| `superfecta` | Caller ID config access | Caller ID data, source passwords | GUI (GET) | **Covered** |
| `xmpp` | XMPP credentials access | XMPP passwords | GUI (GET) | **Covered** |
| `phonebook` | Phonebook personal access | Personal contact info (GDPR) | GUI (GET) | **Covered** |
| `blacklist` | Blacklist personal access | Phone numbers (personal data) | GUI (GET) | **Covered** |
| `cel` | CEL data access | Call event log records | GUI (GET) | **Covered** |
| `calendargroups` | Calendar group credentials access | CalDAV/OAuth credentials | GUI (GET) | **Covered** |
| `logfiles_settings` | Log settings access | System log configuration | GUI (GET) | **Covered** |

---

## Hook Coverage (38 methods across 10 modules)

| Target Module | Hooked Method | Action Type |
|---------------|--------------|-------------|
| **core** | processQuickCreate | Create extension |
| **core** | addDevice | Add device |
| **core** | delDevice | Delete device |
| **core** | addUser | Add user |
| **core** | delUser | Delete user |
| **core** | addDID | Add inbound route |
| **core** | delDID | Delete inbound route |
| **userman** | addUserByDirectory | Create directory user |
| **userman** | updateUser | Update user |
| **userman** | deleteUserByID | Delete user |
| **userman** | updateGroup | Update group |
| **userman** | deleteDirectoryByID | Delete directory |
| **backup** | deleteBackup | Delete backup |
| **certman** | updateCertificate | Update certificate |
| **certman** | makeCertDefault | Set default certificate |
| **voicemail** | updateGeneral | Update VM general settings |
| **timeconditions** | addTimeCondition | Create time condition |
| **timeconditions** | editTimeCondition | Edit time condition |
| **timeconditions** | delTimeCondition | Delete time condition |
| **timeconditions** | addTimeGroup | Create time group |
| **timeconditions** | editTimeGroup | Edit time group |
| **timeconditions** | delTimeGroup | Delete time group |
| **contactmanager** | addGroup | Create contact group |
| **contactmanager** | updateGroup | Update contact group |
| **contactmanager** | deleteGroupByID | Delete contact group |
| **contactmanager** | addEntryByGroupID | Create contact entry |
| **contactmanager** | updateEntry | Update contact entry |
| **contactmanager** | deleteEntryByID | Delete contact entry |
| **ucp** | addGroup | Create UCP group |
| **ucp** | updateGroup | Update UCP group |
| **ucp** | delGroup | Delete UCP group |
| **ucp** | addUser | Create UCP user |
| **ucp** | updateUser | Update UCP user |
| **ucp** | delUser | Delete UCP user |
| **calendar** | sync | Calendar sync operation |
| **bulkhandler** | import | Bulk import |
| **bulkhandler** | export | Bulk export |
| **bulkhandler** | validate | Bulk validation |

---

## Tier 1 Module Coverage Detail (10 modules)

| Module | GUI POST | Sensitive Read | AJAX Interceptor | HOOK | AJAX Commands (intercepted) | Status |
|--------|----------|---------------|-------------------|------|---------------------------|--------|
| `core` | Covered | N/A | Covered | 7 hooks | All core AJAX | **Full** |
| `userman` | Covered | `user_credentials_access` | Covered | 5 hooks | All user management AJAX | **Full** |
| `backup` | Covered | N/A | Covered | 1 hook | runBackup, runRestore, delete | **Full** |
| `certman` | Covered | `certificate_access` | Covered | 2 hooks | makeDefault, delete, upload | **Full** |
| `cdr` | Covered | `cdr_access` | Covered | N/A | playback, download | **GUI+AJAX+Read** |
| `recordings` | Covered | `recording_access` | Covered | N/A | save, upload, convert, delete | **GUI+AJAX+Read** |
| `callrecording` | Covered | N/A | Covered | N/A | Via interceptor | **GUI+AJAX** |
| `voicemail` | Covered | `voicemail_access` | Covered | 1 hook | VM settings AJAX | **Full** |
| `firewall` | Covered | N/A | Covered | N/A | ALL firewall AJAX commands | **GUI+AJAX** |
| `framework` | Covered | N/A | Covered | N/A | reload, scheduler, sysupdate | **GUI+AJAX** |

---

## Tier 2 Module Coverage Detail (9 modules)

| Module | GUI POST | Sensitive Read | AJAX Interceptor | HOOK | Key AJAX Commands | Status |
|--------|----------|---------------|-------------------|------|-------------------|--------|
| `queues` | Covered | `queue_credentials_access` | Covered | N/A (hookTabs is UI-only) | getJSON, grid | **GUI+AJAX+Read** |
| `timeconditions` | Covered | N/A | Covered | 6 hooks | getGroups, getJSON | **Full** |
| `contactmanager` | Covered | `contact_data_access` | Covered | 6 hooks | sdgrid, grid, delete, upload | **Full** |
| `conferences` | Covered | `conference_pin_access` | Covered | N/A | N/A (GUI-based) | **GUI+AJAX+Read** |
| `parking` | Covered | N/A | Covered | N/A | getJSON | **GUI+AJAX** |
| `findmefollow` | Covered | N/A | Covered | N/A | toggleFM, getJSON | **GUI+AJAX** |
| `fax` | Covered | `fax_settings_access` | Covered | N/A | N/A (GUI-based) | **GUI+AJAX+Read** |
| `calendar` | Covered | `calendar_credentials_access` | Covered | 1 hook | sync, delevent, events, OAuth | **Full** |
| `ucp` | Covered | N/A | Covered | 6 hooks | Via DashboardHooks dispatch | **Full** |

---

## Tier 3 Module Coverage Detail (39 modules)

### Tier 3A: Modules with sensitive data (have sensitive read + AJAX coverage)

| Module | GUI POST | Sensitive Read | AJAX Interceptor | Key AJAX Commands | Status |
|--------|----------|---------------|-------------------|-------------------|--------|
| `manager` | Covered | `ami_credentials_access` | Covered | list, get, update, delete | **GUI+AJAX+Read** |
| `sipsettings` | Covered | `sip_credentials_access` | Covered | getnetworking | **GUI+AJAX+Read** |
| `logfiles` | Covered | `system_log_access` | Covered | log_file_read, export, settings_set, destroy | **GUI+AJAX+Read** |
| `arimanager` | Covered | `ari_credentials_access` | Covered | grid, get, update, delete | **GUI+AJAX+Read** |
| `filestore` | Covered | `storage_credentials_access` | Covered | grid, testconnection | **GUI+AJAX+Read** |
| `pinsets` | Covered | `pin_credentials_access` | Covered | getJSON | **GUI+AJAX+Read** |
| `superfecta` | Covered | `callerid_config_access` | Covered | sort, copy, update, delete, save | **GUI+AJAX+Read** |
| `xmpp` | Covered | `xmpp_credentials_access` | Covered | N/A (GUI-based) | **GUI+AJAX+Read** |
| `phonebook` | Covered | `phonebook_personal_access` | Covered | getJSON | **GUI+AJAX+Read** |
| `blacklist` | Covered | `blacklist_personal_access` | Covered | add, edit, del, bulkdelete, calllog | **GUI+AJAX+Read** |
| `cel` | Covered | `cel_data_access` | Covered | report, gethtml5, playback | **GUI+AJAX+Read** |

### Tier 3B: Modules with AJAX handlers (GUI + AJAX coverage)

| Module | GUI POST | AJAX Interceptor | Key AJAX Commands | Status |
|--------|----------|-------------------|-------------------|--------|
| `ringgroups` | Covered | Covered | getJSON | **GUI+AJAX** |
| `ivr` | Covered | Covered | savebrowserrecording, upload, getJSON | **GUI+AJAX** |
| `announcement` | Covered | Covered | getData, getJSON | **GUI+AJAX** |
| `paging` | Covered | Covered | getJSON, setDefault | **GUI+AJAX** |
| `callback` | Covered | Covered | getJSON | **GUI+AJAX** |
| `miscapps` | Covered | Covered | rnav | **GUI+AJAX** |
| `miscdests` | Covered | Covered | getJSON | **GUI+AJAX** |
| `setcid` | Covered | Covered | getable | **GUI+AJAX** |
| `featurecodeadmin` | Covered | Covered | fc_list | **GUI+AJAX** |
| `dashboard` | Covered | Covered | deletemessage, resetmessage, saveorder, getcontent | **GUI+AJAX** |
| `soundlang` | Covered | Covered | install, uninstall, upload, delete, savesettings | **GUI+AJAX** |
| `music` | Covered | Covered | deletemusic, save, deleteCategory, upload | **GUI+AJAX** |
| `tts` | Covered | Covered | getJSON | **GUI+AJAX** |
| `customappsreg` | Covered | Covered | getJSON | **GUI+AJAX** |
| `presencestate` | Covered | Covered | getJSON | **GUI+AJAX** |
| `queueprio` | Covered | Covered | priority_list, update, del | **GUI+AJAX** |
| `cxpanel` | Covered | Covered | getUser, checkAuth | **GUI+AJAX** |
| `bulkhandler` | Covered | Covered + 3 hooks | import, direct_import | **Full** |

### Tier 3C: Modules with GUI-only (no AJAX handler, minimal surface)

| Module | GUI POST | AJAX Interceptor | Notes | Status |
|--------|----------|-------------------|-------|--------|
| `donotdisturb` | Covered | N/A (no AJAX) | Toggle via GUI form | **GUI** |
| `callforward` | Covered | N/A (no AJAX) | Settings via GUI form | **GUI** |
| `callwaiting` | Covered | N/A (no AJAX) | Toggle via GUI form | **GUI** |
| `dictation` | Covered | N/A (no AJAX) | Settings via GUI form | **GUI** |
| `restart` | Covered | N/A (no AJAX) | System restart trigger | **GUI** |
| `pm2` | Covered | N/A (no AJAX) | Process manager settings | **GUI** |
| `speeddial` | Covered | N/A (no AJAX) | Legacy functions.inc.php only | **GUI** |
| `weakpasswords` | Covered | N/A (no AJAX) | Password audit scan (read-only) | **GUI** |

---

## Coverage Summary

| Coverage Level | Count | Modules |
|---------------|-------|---------|
| **Full** (hook+gui+ajax) | 10 | core, userman, backup, certman, voicemail, timeconditions, contactmanager, ucp, calendar, bulkhandler |
| **GUI+AJAX+Read** | 13 | cdr, recordings, queues, conferences, fax, manager, sipsettings, logfiles, arimanager, filestore, pinsets, superfecta, xmpp, phonebook, blacklist, cel |
| **GUI+AJAX** | 20 | callrecording, firewall, framework, parking, findmefollow, ringgroups, ivr, announcement, paging, callback, miscapps, miscdests, setcid, featurecodeadmin, dashboard, soundlang, music, tts, customappsreg, presencestate, queueprio, cxpanel |
| **GUI only** | 8 | donotdisturb, callforward, callwaiting, dictation, restart, pm2, speeddial, weakpasswords |

**Total: 51 modules with documented coverage** (remaining modules from the 80-repo set are either meta-packages, deprecated, or do not have admin GUI surfaces)

---

## Residual Gaps

| Gap | Scope | Mitigation | Priority |
|-----|-------|------------|----------|
| Direct GraphQL API calls (backup, certman) | Rare: only programmatic/CLI access, not admin GUI | Document as deployment validation item | Low |
| Auth failure login page JS | Login page pre-BMO rendering | Deployment snippet or framework hook | Medium |
| REST API direct calls (non-GUI) | Server-to-server or CLI tools | Out of GUI audit scope; document | Low |
| Future modules with novel patterns | Unknown modules added post-deployment | Discovery tool + AJAX interceptor provide baseline; revalidate per update | Medium |
| GUI-only modules (no AJAX) | 8 modules with form-only UI | GUI POST capture covers all form submissions; no AJAX to miss | Low |
| AMI direct commands | CLI `asterisk -rx` or AMI raw | Out of web GUI scope | Low |

---

## pbxACT Commercial Module Coverage

Commercial/pbxACT modules are covered by the same universal mechanisms:
- GUI POST capture via dynamic page registration (`myConfigPageInits()`)
- AJAX interceptor captures all `ajax.php` calls regardless of module origin
- Sensitive-read detection if the module page matches the sensitive registry

**Discovery tool**: Run `tools/discover-pbxact-surfaces.php` on the target pbxACT system to enumerate all installed commercial modules and map their surfaces. The built-in Module Discovery view (`?display=auditcompliance&view=discovery`) provides the same information via the GUI.

---

## Redaction Coverage

The following key patterns are automatically redacted in all captured event payloads:

| Pattern | Modules Affected |
|---------|-----------------|
| `password`, `pass` | All (userman, queues, conferences, daynight, pinsets, etc.) |
| `secret` | manager (AMI secrets) |
| `token`, `access_token`, `refresh_token` | calendar (OAuth), filestore |
| `api_key` | Various integrations |
| `private_key`, `privatekey`, `tlskey` | certman, sipsettings |
| `pin`, `userpin`, `adminpin` | conferences, pinsets |
| `fcc_password` | queues, parking |
| `turnpassword` | sipsettings |
| `ampmgrpass` | ucp, framework |
| `credential` | Various |
| `oauth` | calendar |
| `cert` | certman, sipsettings |

---

## Validation and Regression Gate

Before marking a FreePBX/pbxACT update as compatible:

1. Run `fwconsole hooks --refresh` to update hook cache
2. Verify `myConfigPageInits()` returns all active module pages
3. Check `module.xml` hooks resolve correctly for all 10 hooked modules (38 methods)
4. Run the Module Discovery view and confirm no new modules have unexpected surface gaps
5. Validate all 23 sensitive-read pages are detected on GET
6. Execute security test plan scenarios for each coverage tier
7. Update this matrix with pass/fail evidence and timestamp

Last validated: 20-02-2026 (Europe/Chisinau)

---

## Change Tracking Architecture

### Self-Referential Baseline

The module stores the filtered POST data (`change_after`) with each event. On subsequent edits to the same object, this stored data serves as the "before" state for change diffs. This approach:

- Eliminates dependency on FreePBX module-specific API methods for reading current state
- Avoids the hook execution order problem where the target module updates its DB before the audit hook fires
- Provides consistent field formats between before and after states

### Shutdown Capture Safety Net

Some modules (e.g., Trunks, Misc Destinations) call `redirect_standard()` or `exit()` immediately after processing. The audit module registers a `shutdown_function` for all state-changing requests:

1. On every state-changing request (POST or GET with recognized action prefix), `registerShutdownCapture()` is called
2. If the primary capture (`captureGuiPostEvent` or `captureGuiGetActionEvent`) fires successfully, it sets `eventCapturedThisRequest = true`
3. If the request terminates early (exit/redirect), the shutdown function checks the flag and captures the event if it was missed
4. Both capture paths feed through `routeEvent()` for uniform deduplication and persistence
