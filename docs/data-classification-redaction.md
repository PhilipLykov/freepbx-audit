# Data Classification and Redaction Matrix

| Field    | Value                                    |
|----------|------------------------------------------|
| Module   | auditcompliance v17.0.0-alpha            |
| Date     | 21-02-2026                               |
| Status   | Draft                                    |
| Audience | Developers, Compliance Officers          |

---

## Redacted Fields

The audit module automatically replaces the value of any request field whose key matches
configured patterns with `***REDACTED***` before persistence.

Redaction uses **three match strategies** applied in sequence to each array key. A key is
considered sensitive if it matches any of the three:

### 1. Substring Match (case-insensitive)

The key is redacted if it contains the pattern as a substring anywhere in the key name.

| Pattern | Example Matches | Rationale |
|---------|-----------------|-----------|
| `password` | `password`, `user_password`, `sip_password`, `devinfo_password` | Credential protection |
| `secret` | `secret`, `ami_secret`, `shared_secret` | Credential protection |
| `private_key` | `private_key`, `ssl_private_key` | Certificate private key protection |
| `access_token` | `access_token`, `bearer_access_token` | OAuth token protection |
| `api_key` | `api_key`, `tts_api_key` | API key protection |

### 2. Exact Match (case-insensitive)

The key is redacted only if it matches the pattern exactly (after lowercasing).

| Pattern | Matches | Does NOT Match | Rationale |
|---------|---------|----------------|-----------|
| `pass` | `pass` | `passthrough`, `bypass` | Prevents false positives from substring match |
| `pin` | `pin` | `pinsets_id`, `opinionate` | PIN values without over-matching IDs |
| `token` | `token` | `token_type`, `tokenize` | Bare token fields |

### 3. Suffix Match (case-insensitive)

The key is redacted if it ends with the given suffix.

| Suffix | Example Matches | Does NOT Match | Rationale |
|--------|-----------------|----------------|-----------|
| `_pass` | `vm_pass`, `sip_pass` | `passthrough` | Short password field names |
| `_secret` | `client_secret`, `app_secret` | `secret_name` | Secret suffix convention |
| `_token` | `api_token`, `refresh_token` | `token_type` | Token suffix convention |
| `_key` | `enc_key`, `signing_key` | `key_name` | Key suffix convention |

## Redaction Implementation

```
Location:   Auditcompliance.class.php → redactSensitiveData()
Trigger:    Applied to all change payloads (before/after/changed) and hook arguments
Method:     Three-tier matching: substring → exact → suffix (case-insensitive)
Result:     Value replaced with string "***REDACTED***"
Recursion:  Applied recursively to nested arrays
Non-scalar: Objects and resources are silently excluded from the output (request data is always string/array)
```

## Data Truncation

| Context | Max Length | Rationale |
|---------|-----------|-----------|
| Scalar values in payloads | 2048 chars | Prevent storage bloat from large config values |
| Module name | 128 chars | Reasonable upper bound for module identifiers |
| Action | 128 chars | Reasonable upper bound for action names |
| Object ID | 256 chars | Allows composite IDs |
| Route / Request URI | DB column limit | Stored as-is (typically < 500 chars) |

## Event Taxonomy

### Channels

| Channel | Source | Capture Method |
|---------|--------|---------------|
| `gui` | FreePBX Web GUI form submissions and state-changing GET actions | `doConfigPageInit()` on POST requests and GET requests with recognized action prefixes (del, add, edit, copy, submit, etc.) |
| `hook` | BMO cross-module hook calls | `module.xml <hooks>` + `captureHookEvent()` |
| `ajax` | AJAX-driven module operations | Universal JS `XMLHttpRequest` interceptor + `recordInterceptedAjax` AJAX endpoint |
| `rest` | REST API calls | Future: API middleware adapter |
| `auth` | Authentication boundary events | `ensureSessionState()` + AJAX handlers |

### Session Phases

| Phase | Meaning | When Recorded |
|-------|---------|---------------|
| `login` | User authenticated and started session | First page load with valid `AMP_user` |
| `activity` | User performed a state-changing action | POST form submission or hook event |
| `logout` | User explicitly logged out | JavaScript intercept of logout link |
| `timeout` | Session expired due to inactivity | Detected on next page load (30min idle) |
| `failure` | Authentication attempt failed | AJAX `recordAuthFailure` endpoint |

### Outcomes

| Outcome | Meaning |
|---------|---------|
| `success` | Action completed without error |
| `failure` | Action failed or authentication was rejected |

## Fields Never Captured

| Data Type | Reason |
|-----------|--------|
| Call recording audio content | Privacy; only access events are logged |
| CDR record contents | Privacy; only search/view access events logged |
| File upload binary data | Size/relevance; only metadata captured |
| HTTP response bodies | Size; only request context captured |
| Internal framework debug data | Noise; only user-initiated actions captured |
