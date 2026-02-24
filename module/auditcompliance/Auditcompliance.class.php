<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\modules;

use BMO;
use FreePBX_Helpers;
use PDO;
use PDOException;

class Auditcompliance extends FreePBX_Helpers implements BMO {
	private const SESSION_KEY_ID = 'auditcompliance_session_uuid';
	private const SESSION_KEY_LAST_ACTIVITY = 'auditcompliance_last_activity_unix';
	private const SESSION_KEY_LOGIN_RECORDED = 'auditcompliance_login_recorded';
	private const SESSION_IDLE_TIMEOUT_SECONDS = 1800;
	private const DEDUP_WINDOW_SECONDS = 3;

	private const SENSITIVE_READ_PAGES = array(
		'cdr' => 'cdr_access',
		'recordings' => 'recording_access',
		'cel' => 'cel_data_access',
		'userman' => 'user_credentials_access',
		'certman' => 'certificate_access',
		'manager' => 'ami_credentials_access',
		'arimanager' => 'ari_credentials_access',
		'filestore' => 'storage_credentials_access',
		'xmpp' => 'xmpp_credentials_access',
		'calendar' => 'calendar_credentials_access',
		'calendargroups' => 'calendar_credentials_access',
		'voicemail' => 'voicemail_access',
		'conferences' => 'conference_pin_access',
		'pinsets' => 'pin_credentials_access',
		'contactmanager' => 'contact_data_access',
		'phonebook' => 'phonebook_personal_access',
		'logfiles' => 'system_log_access',
		'logfiles_settings' => 'system_log_access',
		'superfecta' => 'callerid_config_access'
	);

	/**
	 * AJAX commands that are read-only lookups, validations, or UI helpers
	 * and should NOT be recorded as audit events. Checked in handleInterceptedAjax().
	 * Key = module name (lowercase), value = array of command names (lowercase).
	 * '*' key applies to all modules.
	 */
	private const AJAX_READ_ONLY_COMMANDS = array(
		'*' => array(
			'getjson', 'gethtml', 'grid', 'search', 'list', 'getconfig',
			'getsettings', 'getdata', 'getinfo', 'getstatus',
		),
		'userman' => array(
			'pwdtest', 'validators', 'getguihookinfo', 'getdirectories',
			'getusers', 'getgroups', 'getuserfields', 'getucptemplates',
			'getcallactivitygroups', 'auth', 'checkpasswordreminder',
			'nexttrns', 'setlocales',
		),
		'core' => array(
			'getextensiondetails', 'getdestinations', 'getjson',
			'getextensiongrid', 'getdevicegrid', 'getusergrid',
			'getnpanxxjson', 'populatenpanxx',
		),
		'cdr' => array(
			'gethtml5', 'playback',
		),
		'cel' => array(
			'report', 'gethtml5', 'playback',
		),
		'queues' => array(
			'getjson',
		),
		'contactmanager' => array(
			'sdgrid', 'grid', 'lookup',
		),
		'backup' => array(
			'getbackup', 'getbackups', 'getstorage',
		),
		'dashboard' => array(
			'getcontent', 'getnotifications',
		),
		'filestore' => array(
			'grid', 'testconnection',
		),
		'arimanager' => array(
			'grid', 'get',
		),
		'blacklist' => array(
			'calllog',
		),
		'logfiles' => array(
			'log_file_read',
		),
	);

	private const BEFORE_STATE_READERS = array(
		'extensions' => array(
			array('class' => 'Core', 'methods' => array('getDevice', 'getUser')),
		),
		'ivr' => array(
			array('class' => 'Ivr', 'methods' => array('getDetails')),
		),
		'trunks' => array(
			array('class' => 'Core', 'methods' => array('getTrunkByID', 'getTrunkDetails')),
		),
		'ringgroups' => array(
			array('class' => 'Ringgroups', 'methods' => array('get')),
		),
		'timeconditions' => array(
			array('class' => 'Timeconditions', 'methods' => array('getTimeCondition')),
		),
		'announcement' => array(
			array('class' => 'Announcement', 'methods' => array('getAnnouncementByID')),
		),
		'conferences' => array(
			array('class' => 'Conferences', 'methods' => array('getConference')),
		),
		'parking' => array(
			array('class' => 'Parking', 'methods' => array('getParkingLotByID')),
		),
		'paging' => array(
			array('class' => 'Paging', 'methods' => array('getPageGroupById')),
		),
		'callrecording' => array(
			array('class' => 'Callrecording', 'methods' => array('getRecording')),
		),
		'backup' => array(
			array('class' => 'Backup', 'methods' => array('getBackup')),
		),
		'did' => array(
			array('class' => 'Core', 'methods' => array('getDID')),
		),
		'routing' => array(
			array('class' => 'Core', 'methods' => array('getRoute', 'getRouteByID')),
		),
		'userman' => array(
			array('class' => 'Userman', 'methods' => array('getUserByID')),
		),
		'voicemail' => array(
			array('class' => 'Voicemail', 'methods' => array('getVoicemailBoxByExtension', 'getMailbox')),
		),
		'certman' => array(
			array('class' => 'Certman', 'methods' => array('getCertificateDetails')),
		),
	);

	private const GLOBAL_SETTING_MAP = array(
		'audit_connection_type' => 'AUDITCOMPLIANCE_CONNECTION_TYPE',
		'audit_db_dsn' => 'AUDITCOMPLIANCE_DB_DSN',
		'audit_db_user' => 'AUDITCOMPLIANCE_DB_USER',
		'audit_db_password' => 'AUDITCOMPLIANCE_DB_PASSWORD',
		'audit_db_require_tls' => 'AUDITCOMPLIANCE_DB_REQUIRE_TLS',
		'audit_db_odbc_backend' => 'AUDITCOMPLIANCE_DB_ODBC_BACKEND',
		'audit_require_external_db' => 'AUDITCOMPLIANCE_REQUIRE_EXTERNAL_DB',
		'audit_session_idle_timeout_seconds' => 'AUDITCOMPLIANCE_SESSION_IDLE_TIMEOUT_SECONDS'
	);

	private $FreePBX;
	private $db;
	private $auditDb = null;
	private $schemaReady = false;
	private $auditScriptsInjected = false;
	private $resolvedDriver = null;
	private $lastStorageErrorMessage = '';
	private $eventCapturedThisRequest = false;

	public function __construct($freepbx = null) {
		if ($freepbx === null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}

	public function install() {
		$this->ensureGlobalSettingsDefined();
		$this->setDefaultConfigIfMissing('audit_connection_type', 'mysql');
		$this->setDefaultConfigIfMissing('audit_db_dsn', '');
		$this->setDefaultConfigIfMissing('audit_db_user', '');
		$this->setDefaultConfigIfMissing('audit_db_password', '');
		$this->setDefaultConfigIfMissing('audit_db_require_tls', '1');
		$this->setDefaultConfigIfMissing('audit_db_odbc_backend', '');
		$this->setDefaultConfigIfMissing('audit_require_external_db', '1');
		$this->setDefaultConfigIfMissing('audit_session_idle_timeout_seconds', (string) self::SESSION_IDLE_TIMEOUT_SECONDS);

		try {
			$this->ensureAuditSchema();
		} catch (\Throwable $e) {
			$this->debugLog('Schema bootstrap deferred (install)', array('error' => $e->getMessage()));
		}
		return true;
	}

	public function uninstall() {}

	/**
	 * Register for ALL active module pages so session boundary tracking
	 * and logout interception cover the entire admin GUI.
	 */
	public function myConfigPageInits() {
		$pages = array();
		try {
			$modules = $this->FreePBX->Modules->getActiveModules(false);
			if (is_array($modules)) {
				foreach ($modules as $modName => $modData) {
					if (isset($modData['items']) && is_array($modData['items'])) {
						foreach (array_keys($modData['items']) as $itemKey) {
							$pages[] = (string) $itemKey;
						}
					}
				}
			}
		} catch (\Throwable $e) {
			// Fallback handled below
		}
		$critical = array('core', 'userman', 'backup', 'certman', 'cdr', 'recordings', 'auditcompliance');
		return array_values(array_unique(array_merge($pages, $critical)));
	}

	/**
	 * Main hook: runs on every page load for registered displays.
	 *
	 * 1. Manages auth session boundary events (login/timeout detection).
	 * 2. Injects audit scripts (logout interception + universal AJAX interceptor).
	 * 3. Records state-changing events for POST requests.
	 * 4. Records sensitive-read events for GET requests on designated pages.
	 */
	public function doConfigPageInit($display) {
		$this->eventCapturedThisRequest = false;
		if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
			return;
		}

		try {
			$sessionId = $this->ensureSessionState();
		} catch (\Throwable $e) {
			$this->debugLog('Session boundary check failed', array(
				'error' => $e->getMessage(),
				'display' => (string) $display
			));
			return;
		}

		$this->injectAuditScripts();

		$handler = strtolower(trim((string) ($_REQUEST['handler'] ?? '')));
		if (in_array($handler, array('reload', 'retrieve_conf'), true)) {
			$this->captureApplyConfigEvent($sessionId);
			return;
		}

		if (empty($display)) {
			return;
		}

		$method = strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
		$displayLower = strtolower((string) $display);
		$action = strtolower(trim((string) ($_REQUEST['action'] ?? '')));

		if ($this->isStateChangingAction($action)) {
			$this->registerShutdownCapture($sessionId, $display, $action, $method);
		}

		if ($method === 'post') {
			$this->captureGuiPostEvent($sessionId, $display);
		} elseif ($method === 'get') {
			if ($this->isStateChangingAction($action)) {
				$this->captureGuiGetActionEvent($sessionId, $display, $action);
			} elseif (isset(self::SENSITIVE_READ_PAGES[$displayLower])) {
				$this->captureSensitiveReadEvent($sessionId, $display, $displayLower);
			}
		}
	}

	/**
	 * Safety net for modules that call redirect_standard() or exit() in their
	 * doConfigPageInit before our hook fires (e.g. trunks, miscdests).
	 * register_shutdown_function runs even after exit().
	 */
	private function registerShutdownCapture($sessionId, $display, $action, $method = 'post') {
		$self = $this;
		$requestSnapshot = $_REQUEST;
		$serverSnapshot = array(
			'REQUEST_METHOD' => strtoupper($method),
			'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? ''
		);
		register_shutdown_function(function () use ($self, $sessionId, $display, $action, $requestSnapshot, $serverSnapshot) {
			if ($self->eventCapturedThisRequest) {
				return;
			}
			try {
				$objectId = '';
				$candidates = array(
					'id', 'extdisplay', 'account', 'trunkid', 'user_id',
					'itemid', 'group_id', 'entry_id', 'queue', 'grpnum',
					'ext', 'extension', 'cidnum', 'backup_id', 'tcid',
					'tgid', 'confno', 'pagegrp', 'pagenbr', 'rg', 'ivr_id',
					'faxid', 'calendar_id', 'pinsets_id', 'scheme',
					'announcement_id', 'callrecording_id', 'channel',
					'orig_account', 'trunknum'
				);
				foreach ($candidates as $key) {
					if (!empty($requestSnapshot[$key])) {
						$objectId = (string) $requestSnapshot[$key];
						break;
					}
				}

				$changePayload = array(
					'before' => null, 'after' => null,
					'added' => array(), 'removed' => array(),
					'changed' => array('action' => $action, 'object_id' => $objectId)
				);
				if ($serverSnapshot['REQUEST_METHOD'] === 'POST' && !empty($requestSnapshot)) {
					try {
						$previousPost = $self->getPreviousPostData($display, $objectId);
						$changePayload = $self->buildChangePayload($requestSnapshot, $previousPost);
					} catch (\Throwable $diffErr) {
						// Fall back to generic payload
					}
				}

				$self->routeEvent(array(
					'session_id' => $sessionId,
					'session_phase' => 'activity',
					'channel' => 'gui',
					'module_name' => (string) $display,
					'action' => $action,
					'outcome' => 'success',
					'route' => (string) $display,
					'object_type' => strtolower((string) $display),
					'object_id' => $objectId,
					'request_method' => $serverSnapshot['REQUEST_METHOD'],
					'request_uri' => $serverSnapshot['REQUEST_URI'],
					'request_hash' => md5(serialize($requestSnapshot)),
					'change' => $changePayload
				));
			} catch (\Throwable $e) {
				// Silent — shutdown context
			}
		});
	}

	private function captureGuiPostEvent($sessionId, $display) {
		try {
			$action = $this->normalizeAction($_REQUEST['action'] ?? '', $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN');
			$objectId = $this->detectObjectId();
			$previousPost = $this->getPreviousPostData($display, $objectId);
			$this->routeEvent(array(
				'session_id' => $sessionId,
				'session_phase' => 'activity',
				'channel' => 'gui',
				'module_name' => (string) $display,
				'action' => $action,
				'outcome' => 'success',
				'route' => (string) $display,
				'object_type' => $this->detectObjectType($display),
				'object_id' => $objectId,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
				'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
				'request_hash' => $this->hashRequest($_REQUEST),
				'change' => $this->buildChangePayload($_REQUEST, $previousPost)
			));
			$this->eventCapturedThisRequest = true;
		} catch (\Throwable $e) {
			$this->debugLog('GUI audit write failed', array(
				'error' => $e->getMessage(),
				'display' => (string) $display
			));
		}
	}

	private static $STATE_CHANGING_PREFIXES = array(
		'del', 'delete', 'remove', 'add', 'edit', 'edt', 'update', 'save',
		'create', 'modify', 'enable', 'disable', 'toggle', 'reset',
		'copy', 'duplicate', 'submit'
	);

	private function isStateChangingAction($action) {
		if ($action === '') {
			return false;
		}
		foreach (self::$STATE_CHANGING_PREFIXES as $prefix) {
			if (strpos($action, $prefix) === 0) {
				return true;
			}
		}
		return false;
	}

	private function captureGuiGetActionEvent($sessionId, $display, $action) {
		try {
			$objectId = $this->detectObjectId();
			$this->routeEvent(array(
				'session_id' => $sessionId,
				'session_phase' => 'activity',
				'channel' => 'gui',
				'module_name' => (string) $display,
				'action' => $action,
				'outcome' => 'success',
				'route' => (string) $display,
				'object_type' => $this->detectObjectType($display),
				'object_id' => $objectId,
				'request_method' => 'GET',
				'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
				'request_hash' => $this->hashRequest($_REQUEST),
				'change' => array(
					'before' => null,
					'after' => null,
					'added' => array(),
					'removed' => array(),
					'changed' => array('action' => $action, 'object_id' => $objectId)
				)
			));
			$this->eventCapturedThisRequest = true;
		} catch (\Throwable $e) {
			$this->debugLog('GUI GET action audit failed', array(
				'error' => $e->getMessage(),
				'display' => (string) $display,
				'action' => $action
			));
		}
	}

	private function captureApplyConfigEvent($sessionId) {
		try {
			$this->routeEvent(array(
				'session_id' => $sessionId,
				'session_phase' => 'activity',
				'channel' => 'gui',
				'module_name' => 'framework',
				'action' => 'apply_config',
				'outcome' => 'success',
				'route' => 'config.php?handler=reload',
				'object_type' => 'system',
				'object_id' => '',
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
				'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
				'request_hash' => '',
				'change' => array(
					'before' => null,
					'after' => null,
					'added' => array(),
					'removed' => array(),
					'changed' => array('action' => 'apply_config', 'description' => 'Administrator applied configuration changes to Asterisk')
				)
			));
		} catch (\Throwable $e) {
			$this->debugLog('Apply config audit failed', array('error' => $e->getMessage()));
		}
	}

	private function captureSensitiveReadEvent($sessionId, $display, $displayLower) {
		try {
			$readType = self::SENSITIVE_READ_PAGES[$displayLower];
			$this->routeEvent(array(
				'session_id' => $sessionId,
				'session_phase' => 'activity',
				'channel' => 'gui',
				'module_name' => (string) $display,
				'action' => $readType,
				'outcome' => 'success',
				'route' => (string) $display,
				'object_type' => $this->detectObjectType($display),
				'object_id' => $this->detectObjectId(),
				'request_method' => 'GET',
				'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
				'request_hash' => '',
				'change' => array(
					'before' => null, 'after' => null,
					'added' => array(), 'removed' => array(),
					'changed' => array('view' => $readType)
				)
			));
		} catch (\Throwable $e) {
			$this->debugLog('Sensitive read audit failed', array(
				'error' => $e->getMessage(),
				'display' => (string) $display
			));
		}
	}

	public function getRightNav($request) {
		return '';
	}

	/**
	 * Discovery tool: scans installed modules on the current FreePBX/pbxACT
	 * instance and maps their communication surfaces (GUI, AJAX, API, hooks)
	 * to audit coverage status. Designed for deployment-time validation.
	 */
	public function discoverModuleSurfaces() {
		$result = array('modules' => array(), 'summary' => array(
			'total' => 0, 'has_ajax' => 0, 'has_api' => 0,
			'has_hooks' => 0, 'commercial' => 0,
			'timestamp' => $this->getChisinauTimestamp()
		));

		try {
			$allModules = $this->FreePBX->Modules->getActiveModules(false);
		} catch (\Throwable $e) {
			return $result;
		}

		if (!is_array($allModules)) {
			return $result;
		}

		$hookedModules = $this->getHookedModuleList();
		$modulesDir = $this->FreePBX->Config->get('AMPWEBROOT') . '/admin/modules';

		foreach ($allModules as $rawName => $modData) {
			$rawName = (string) $rawName;
			$modPath = $modulesDir . '/' . $rawName;
			$className = ucfirst($rawName);

			$hasAjax = false;
			$hasApi = false;
			$hasProcessHooks = false;
			$isCommercial = false;
			$guiPages = 0;

			if (isset($modData['items']) && is_array($modData['items'])) {
				$guiPages = count($modData['items']);
			}

			$classFile = $modPath . '/' . $className . '.class.php';
			if (is_file($classFile)) {
				$content = @file_get_contents($classFile);
				if ($content !== false) {
					$hasAjax = (strpos($content, 'function ajaxHandler') !== false);
					$hasProcessHooks = (strpos($content, 'processHooks') !== false);
				}
			}

			$hasApi = is_dir($modPath . '/Api/Rest') || is_dir($modPath . '/Api/Gql');

			$moduleXml = $modPath . '/module.xml';
			if (is_file($moduleXml)) {
				$xmlContent = @file_get_contents($moduleXml);
				if ($xmlContent !== false) {
					$isCommercial = (strpos($xmlContent, '<license>Commercial') !== false)
						|| (strpos($xmlContent, '<commercial>') !== false)
						|| (strpos($xmlContent, 'Sangoma') !== false && strpos($xmlContent, 'commercial') !== false);
				}
			}

			$hasAuditHook = in_array($rawName, $hookedModules, true);

			$hasSensitiveRead = array_key_exists(strtolower($rawName), self::SENSITIVE_READ_PAGES);

			if ($hasAuditHook) {
				$coverage = 'full';
			} elseif ($hasSensitiveRead && $hasAjax) {
				$coverage = 'gui_ajax_read';
			} elseif ($hasSensitiveRead) {
				$coverage = 'gui_read';
			} elseif ($hasAjax) {
				$coverage = 'gui_ajax';
			} else {
				$coverage = 'gui_only';
			}

			$result['modules'][] = array(
				'name' => $rawName,
				'version' => (string) ($modData['version'] ?? ''),
				'commercial' => $isCommercial,
				'gui_pages' => $guiPages,
				'has_ajax' => $hasAjax,
				'has_api' => $hasApi,
				'has_process_hooks' => $hasProcessHooks,
				'has_audit_hook' => $hasAuditHook,
				'has_sensitive_read' => $hasSensitiveRead,
				'coverage' => $coverage
			);

			$result['summary']['total']++;
			if ($hasAjax) { $result['summary']['has_ajax']++; }
			if ($hasApi) { $result['summary']['has_api']++; }
			if ($hasProcessHooks) { $result['summary']['has_hooks']++; }
			if ($isCommercial) { $result['summary']['commercial']++; }
		}

		usort($result['modules'], function ($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		return $result;
	}

	private function getHookedModuleList() {
		return array(
			'core', 'userman', 'backup', 'certman', 'voicemail',
			'timeconditions', 'contactmanager', 'ucp', 'calendar', 'bulkhandler'
		);
	}

	// ----------------------------------------------------------------
	// Unified capture router — all channels flow through here
	// ----------------------------------------------------------------

	/**
	 * Central event routing method. Validates, normalizes, deduplicates,
	 * and persists events from any capture channel.
	 *
	 * Required keys: session_id, session_phase, channel, module_name,
	 * action, outcome, route, object_type, object_id, request_method,
	 * request_uri, change.
	 *
	 * Optional: request_hash (defaults to empty).
	 */
	private function routeEvent(array $data) {
		$sessionId = (string) ($data['session_id'] ?? '');
		$channel = (string) ($data['channel'] ?? 'unknown');
		$moduleName = $this->truncate((string) ($data['module_name'] ?? ''), 128);
		$action = $this->truncate((string) ($data['action'] ?? ''), 128);
		$objectId = $this->truncate((string) ($data['object_id'] ?? ''), 256);

		if ($sessionId === '' || $moduleName === '' || $action === '') {
			return;
		}

		if ($this->isRecentDuplicate($sessionId, $moduleName, $action, $objectId)) {
			return;
		}

		$event = array(
			'event_id' => $this->newEventId(),
			'session_id' => $sessionId,
			'session_phase' => $this->truncate((string) ($data['session_phase'] ?? 'activity'), 16),
			'channel' => $this->truncate($channel, 16),
			'module_name' => $moduleName,
			'action' => $action,
			'outcome' => $this->truncate((string) ($data['outcome'] ?? 'success'), 32),
			'route' => $this->truncate((string) ($data['route'] ?? ''), 1024),
			'object_type' => $this->truncate((string) ($data['object_type'] ?? ''), 128),
			'object_id' => $objectId,
			'actor' => $this->getActor(),
			'source_ip' => $this->getRemoteIp(),
			'request_method' => $this->truncate((string) ($data['request_method'] ?? ''), 16),
			'request_uri' => $this->truncate((string) ($data['request_uri'] ?? ''), 2048),
			'request_hash' => $this->truncate((string) ($data['request_hash'] ?? ''), 128),
			'change' => isset($data['change']) ? $data['change'] : array(
				'before' => null, 'after' => null,
				'added' => array(), 'removed' => array(), 'changed' => array()
			),
			'occurred_at_unix' => time(),
			'occurred_at_utc' => gmdate('d-m-Y H:i:s'),
			'occurred_at_local' => $this->getChisinauTimestamp()
		);

		$this->appendAuditEvent($event);
		$this->incrementSessionEventCount($sessionId);
		$this->markSessionActivity();
	}

	/**
	 * Cross-channel deduplication. Prevents the same logical action from being
	 * recorded twice when captured by multiple channels (e.g. GUI + hook, or
	 * GUI + AJAX interceptor) within a short time window.
	 */
	private function isRecentDuplicate($sessionId, $moduleName, $action, $objectId) {
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$cutoff = time() - self::DEDUP_WINDOW_SECONDS;
			$sql = "SELECT COUNT(*) FROM audit_events WHERE session_id = ? AND module_name = ? AND action = ? AND object_id = ? AND occurred_at_unix >= ?";
			$sth = $pdo->prepare($sql);
			$sth->execute(array($sessionId, $moduleName, $action, $objectId, $cutoff));
			return ((int) $sth->fetchColumn()) > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	// ----------------------------------------------------------------
	// BMO hook handlers for cross-module write interception
	// ----------------------------------------------------------------

	/**
	 * Generic hook handler for cross-module write interception.
	 *
	 * In web context (REQUEST_URI set) hooks are suppressed entirely because
	 * the GUI channel (doConfigPageInit) and AJAX interceptor already capture
	 * every admin action with user-facing names and full change details.
	 * Hooks only fire in non-web contexts (CLI fwconsole, cron) where GUI/AJAX
	 * channels are unavailable.
	 */
	public function captureHookEvent($callerModule, $callerMethod) {
		if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
			return;
		}

		if (($_SERVER['REQUEST_URI'] ?? '') !== '') {
			return;
		}

		try {
			$sessionId = $_SESSION[self::SESSION_KEY_ID] ?? null;
			if (empty($sessionId)) {
				return;
			}
			$args = func_get_args();
			$hookArgs = array_slice($args, 2);
			$this->routeEvent(array(
				'session_id' => (string) $sessionId,
				'session_phase' => 'activity',
				'channel' => 'hook',
				'module_name' => (string) $callerModule,
				'action' => (string) $callerMethod,
				'outcome' => 'success',
				'route' => $callerModule . '::' . $callerMethod,
				'object_type' => strtolower((string) $callerModule),
				'object_id' => $this->extractObjectIdFromArgs($hookArgs),
				'request_method' => 'CLI',
				'request_uri' => '',
				'change' => array(
					'before' => null, 'after' => null,
					'added' => array(), 'removed' => array(),
					'changed' => $this->flattenHookArgs($hookArgs)
				)
			));
		} catch (\Throwable $e) {
			$this->debugLog('Hook audit failed', array(
				'error' => $e->getMessage(),
				'module' => (string) $callerModule,
				'method' => (string) $callerMethod
			));
		}
	}

	public function hookCore_processQuickCreate() {
		$this->captureHookEvent('core', 'processQuickCreate', ...func_get_args());
	}

	public function hookCore_addDevice() {
		$this->captureHookEvent('core', 'addDevice', ...func_get_args());
	}

	public function hookCore_delDevice() {
		$this->captureHookEvent('core', 'delDevice', ...func_get_args());
	}

	public function hookCore_addUser() {
		$this->captureHookEvent('core', 'addUser', ...func_get_args());
	}

	public function hookCore_delUser() {
		$this->captureHookEvent('core', 'delUser', ...func_get_args());
	}

	public function hookCore_addDID() {
		$this->captureHookEvent('core', 'addDID', ...func_get_args());
	}

	public function hookCore_delDID() {
		$this->captureHookEvent('core', 'delDID', ...func_get_args());
	}

	public function hookUserman_addUserByDirectory() {
		$this->captureHookEvent('userman', 'addUserByDirectory', ...func_get_args());
	}

	public function hookUserman_updateUser() {
		$this->captureHookEvent('userman', 'updateUser', ...func_get_args());
	}

	public function hookUserman_deleteUserByID() {
		$this->captureHookEvent('userman', 'deleteUserByID', ...func_get_args());
	}

	public function hookUserman_updateGroup() {
		$this->captureHookEvent('userman', 'updateGroup', ...func_get_args());
	}

	public function hookUserman_deleteDirectoryByID() {
		$this->captureHookEvent('userman', 'deleteDirectoryByID', ...func_get_args());
	}

	public function hookBackup_deleteBackup() {
		$this->captureHookEvent('backup', 'deleteBackup', ...func_get_args());
	}

	public function hookCertman_updateCertificate() {
		$this->captureHookEvent('certman', 'updateCertificate', ...func_get_args());
	}

	public function hookCertman_makeCertDefault() {
		$this->captureHookEvent('certman', 'makeCertDefault', ...func_get_args());
	}

	public function hookVoicemail_updateGeneral() {
		$this->captureHookEvent('voicemail', 'updateGeneral', ...func_get_args());
	}

	// Tier 2: Time Conditions hooks
	public function hookTimeconditions_addTimeCondition() {
		$this->captureHookEvent('timeconditions', 'addTimeCondition', ...func_get_args());
	}

	public function hookTimeconditions_editTimeCondition() {
		$this->captureHookEvent('timeconditions', 'editTimeCondition', ...func_get_args());
	}

	public function hookTimeconditions_delTimeCondition() {
		$this->captureHookEvent('timeconditions', 'delTimeCondition', ...func_get_args());
	}

	public function hookTimeconditions_addTimeGroup() {
		$this->captureHookEvent('timeconditions', 'addTimeGroup', ...func_get_args());
	}

	public function hookTimeconditions_editTimeGroup() {
		$this->captureHookEvent('timeconditions', 'editTimeGroup', ...func_get_args());
	}

	public function hookTimeconditions_delTimeGroup() {
		$this->captureHookEvent('timeconditions', 'delTimeGroup', ...func_get_args());
	}

	// Tier 2: Contact Manager hooks
	public function hookContactmanager_addGroup() {
		$this->captureHookEvent('contactmanager', 'addGroup', ...func_get_args());
	}

	public function hookContactmanager_updateGroup() {
		$this->captureHookEvent('contactmanager', 'updateGroup', ...func_get_args());
	}

	public function hookContactmanager_deleteGroupByID() {
		$this->captureHookEvent('contactmanager', 'deleteGroupByID', ...func_get_args());
	}

	public function hookContactmanager_addEntryByGroupID() {
		$this->captureHookEvent('contactmanager', 'addEntryByGroupID', ...func_get_args());
	}

	public function hookContactmanager_updateEntry() {
		$this->captureHookEvent('contactmanager', 'updateEntry', ...func_get_args());
	}

	public function hookContactmanager_deleteEntryByID() {
		$this->captureHookEvent('contactmanager', 'deleteEntryByID', ...func_get_args());
	}

	// Tier 2: UCP hooks
	public function hookUcp_addGroup() {
		$this->captureHookEvent('ucp', 'addGroup', ...func_get_args());
	}

	public function hookUcp_updateGroup() {
		$this->captureHookEvent('ucp', 'updateGroup', ...func_get_args());
	}

	public function hookUcp_delGroup() {
		$this->captureHookEvent('ucp', 'delGroup', ...func_get_args());
	}

	public function hookUcp_addUser() {
		$this->captureHookEvent('ucp', 'addUser', ...func_get_args());
	}

	public function hookUcp_updateUser() {
		$this->captureHookEvent('ucp', 'updateUser', ...func_get_args());
	}

	public function hookUcp_delUser() {
		$this->captureHookEvent('ucp', 'delUser', ...func_get_args());
	}

	// Tier 2: Calendar hooks
	public function hookCalendar_sync() {
		$this->captureHookEvent('calendar', 'sync', ...func_get_args());
	}

	// Tier 3: Bulk Handler hooks
	public function hookBulkhandler_import() {
		$this->captureHookEvent('bulkhandler', 'import', ...func_get_args());
	}

	public function hookBulkhandler_export() {
		$this->captureHookEvent('bulkhandler', 'export', ...func_get_args());
	}

	public function hookBulkhandler_validate() {
		$this->captureHookEvent('bulkhandler', 'validate', ...func_get_args());
	}

	// ----------------------------------------------------------------
	// AJAX handlers for logout and auth failure capture
	// ----------------------------------------------------------------

	public function ajaxRequest($req, &$setting) {
		switch ($req) {
			case 'recordLogout':
				$setting['changesession'] = true;
				return true;
			case 'recordAuthFailure':
				$setting['authenticate'] = false;
				return true;
			case 'recordInterceptedAjax':
				return true;
			case 'searchEvents':
			case 'getFilterValues':
			case 'getDashboardStats':
				if (!$this->hasAuditViewPermission()) {
					return false;
				}
				return true;
			case 'exportEvents':
				if (!$this->hasAuditViewPermission()) {
					return false;
				}
				return true;
		}
		return false;
	}

	public function ajaxHandler() {
		$command = $_REQUEST['command'] ?? '';
		switch ($command) {
			case 'recordLogout':
				return $this->handleLogoutAjax();
			case 'recordAuthFailure':
				return $this->handleAuthFailureAjax();
			case 'recordInterceptedAjax':
				return $this->handleInterceptedAjax();
			case 'searchEvents':
				return $this->handleSearchAjax();
			case 'exportEvents':
				return $this->handleExportAjax();
			case 'getFilterValues':
				return $this->handleFilterValuesAjax();
			case 'getDashboardStats':
				return $this->handleDashboardStatsAjax();
		}
		return false;
	}

	/**
	 * Returns current DB-related settings for the settings UI.
	 */
	public function getSettingsSnapshot() {
		$password = (string) $this->getConfigSafe('audit_db_password', '');
		$odbcBackend = (string) $this->getConfigSafe('audit_db_odbc_backend', '');
		$storedType = strtolower(trim((string) $this->getConfigSafe('audit_connection_type', '')));
		$dsn = (string) $this->getConfigSafe('audit_db_dsn', '');

		if (!in_array($storedType, array('mysql', 'pgsql', 'odbc'), true)) {
			$storedType = $this->deriveConnectionTypeFromDsn($dsn, $odbcBackend);
		}
		if ($storedType === 'odbc') {
			$dsn = $this->normalizeOdbcDsnInput($dsn, $odbcBackend);
		}

		$ui = $this->extractConnectionUiValues($dsn, $storedType);
		return array(
			'audit_connection_type' => $storedType,
			'audit_db_dsn' => $dsn,
			'audit_odbc_dsn_name' => $ui['odbc_dsn_name'],
			'audit_db_host' => $ui['host'],
			'audit_db_port' => $ui['port'],
			'audit_db_name' => $ui['db_name'],
			'audit_db_user' => (string) $this->getConfigSafe('audit_db_user', ''),
			'audit_db_password' => '',
			'audit_db_password_set' => ($password !== ''),
			'audit_db_require_tls' => ((string) $this->getConfigSafe('audit_db_require_tls', '1') === '1') ? '1' : '0',
			'audit_db_odbc_backend' => $odbcBackend,
			'audit_require_external_db' => ((string) $this->getConfigSafe('audit_require_external_db', '1') === '1') ? '1' : '0',
			'audit_session_idle_timeout_seconds' => (string) $this->getConfigSafe('audit_session_idle_timeout_seconds', (string) self::SESSION_IDLE_TIMEOUT_SECONDS)
		);
	}

	public function getAuditStorageStatus() {
		$dsn = trim((string) $this->getConfigSafe('audit_db_dsn', ''));
		$requireExternal = ($this->getConfigSafe('audit_require_external_db', '1') === '1');
		if ($dsn === '' && $requireExternal) {
			return array(
				'status' => false,
				'message' => 'External audit DB is required, but DSN is not configured.',
				'driver' => '',
				'remote' => true
			);
		}

		try {
			$pdo = $this->getAuditDb();
			$this->ensureAuditSchema();
			return array(
				'status' => true,
				'message' => ($pdo === $this->db) ? 'Connected to local FreePBX database.' : 'Connected to remote audit database.',
				'driver' => (string) $this->getDriverName($pdo),
				'remote' => ($pdo !== $this->db)
			);
		} catch (\Throwable $e) {
			return array(
				'status' => false,
				'message' => $this->truncate($e->getMessage(), 250),
				'driver' => '',
				'remote' => false
			);
		}
	}

	public function canManageSettings() {
		return $this->hasAuditAdminPermission();
	}

	/**
	 * Validate and persist settings from the GUI.
	 *
	 * Settings are always persisted after input validation passes.
	 * Connection test is performed after saving; its result is returned
	 * as a warning but does not block persistence.
	 */
	public function saveSettingsFromUi(array $input) {
		if (!$this->hasAuditAdminPermission()) {
			return array('status' => false, 'message' => 'Access denied');
		}
		$parsed = $this->parseSettingsInput($input, true);
		if (!$parsed['status']) {
			return $parsed;
		}

		$values = $parsed['values'];
		$previousModuleValues = array(
			'audit_connection_type' => (string) $this->getConfigSafe('audit_connection_type', 'mysql'),
			'audit_db_dsn' => (string) $this->getConfigSafe('audit_db_dsn', ''),
			'audit_db_user' => (string) $this->getConfigSafe('audit_db_user', ''),
			'audit_db_password' => (string) $this->getConfigSafe('audit_db_password', ''),
			'audit_db_require_tls' => ((string) $this->getConfigSafe('audit_db_require_tls', '1') === '1') ? '1' : '0',
			'audit_db_odbc_backend' => (string) $this->getConfigSafe('audit_db_odbc_backend', ''),
			'audit_require_external_db' => ((string) $this->getConfigSafe('audit_require_external_db', '1') === '1') ? '1' : '0',
			'audit_session_idle_timeout_seconds' => (string) $this->getConfigSafe('audit_session_idle_timeout_seconds', (string) self::SESSION_IDLE_TIMEOUT_SECONDS)
		);
		try {
			$this->setConfig('audit_connection_type', $values['audit_connection_type']);
			$this->setConfig('audit_db_dsn', $values['audit_db_dsn']);
			$this->setConfig('audit_db_user', $values['audit_db_user']);
			$this->setConfig('audit_db_password', $values['audit_db_password']);
			$this->setConfig('audit_db_require_tls', $values['audit_db_require_tls']);
			$this->setConfig('audit_db_odbc_backend', $values['audit_db_odbc_backend']);
			$this->setConfig('audit_require_external_db', $values['audit_require_external_db']);
			$this->setConfig('audit_session_idle_timeout_seconds', $values['audit_session_idle_timeout_seconds']);

			$this->setGlobalConfigValues(array(
				'audit_connection_type' => $values['audit_connection_type'],
				'audit_db_dsn' => $values['audit_db_dsn'],
				'audit_db_user' => $values['audit_db_user'],
				'audit_db_password' => $values['audit_db_password'],
				'audit_db_require_tls' => $values['audit_db_require_tls'],
				'audit_db_odbc_backend' => $values['audit_db_odbc_backend'],
				'audit_require_external_db' => $values['audit_require_external_db'],
				'audit_session_idle_timeout_seconds' => $values['audit_session_idle_timeout_seconds']
			));

			$this->auditDb = null;
			$this->resolvedDriver = null;
			$this->schemaReady = false;
		} catch (\Throwable $e) {
			$this->debugLog('Settings save failed', array('error' => $e->getMessage()));
			$this->restoreModuleConfigValues($previousModuleValues);
			$this->setGlobalConfigValues($previousModuleValues);
			return array('status' => false, 'message' => 'Failed to save settings: ' . $this->truncate($e->getMessage(), 200));
		}

		$connectivityCheck = $this->testConnectionWithValues($values);
		if (empty($connectivityCheck['status'])) {
			return array(
				'status' => true,
				'message' => 'Settings saved. Warning: ' . ($connectivityCheck['message'] ?? 'connection test failed'),
				'warning' => true
			);
		}

		return array('status' => true, 'message' => 'Settings saved and connection verified successfully.');
	}

	/**
	 * Validate connection settings without persisting.
	 */
	public function testSettingsConnectionFromUi(array $input) {
		if (!$this->hasAuditAdminPermission()) {
			return array('status' => false, 'message' => 'Access denied');
		}
		$parsed = $this->parseSettingsInput($input, false);
		if (!$parsed['status']) {
			return $parsed;
		}
		return $this->testConnectionWithValues($parsed['values']);
	}

	private function testConnectionWithValues(array $values) {
		$dsn = trim((string) ($values['audit_db_dsn'] ?? ''));
		$user = (string) ($values['audit_db_user'] ?? '');
		$password = (string) ($values['audit_db_password'] ?? '');
		if ($dsn === '') {
			return array('status' => true, 'message' => 'Using local FreePBX database (fallback mode).');
		}

		try {
			$options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			);
			$pdo = new PDO($dsn, $user, $password, $options);
			$sth = $pdo->query('SELECT 1');
			$sth->fetchColumn();
		} catch (\Throwable $e) {
			$this->debugLog('Settings test connection failed', array('error' => $e->getMessage()));
			return array('status' => false, 'message' => 'Connection test failed: ' . $this->truncate($e->getMessage(), 250));
		}

		return array('status' => true, 'message' => 'Connection successful');
	}

	// ----------------------------------------------------------------
	// Auth boundary event writers (immutable, append-only)
	// ----------------------------------------------------------------

	public function recordLoginEvent($sessionId, $actor) {
		if ($this->isDuplicateBoundaryEvent($sessionId, 'login')) {
			return;
		}
		$event = $this->buildBoundaryEvent($sessionId, 'login', 'login', 'success', $actor);
		$this->appendAuditEvent($event);
		$this->incrementSessionEventCount($sessionId);
		$_SESSION[self::SESSION_KEY_LOGIN_RECORDED] = $sessionId;
	}

	public function recordLogoutEvent($sessionId, $actor) {
		if ($this->isDuplicateBoundaryEvent($sessionId, 'logout')) {
			return;
		}
		$event = $this->buildBoundaryEvent($sessionId, 'logout', 'logout', 'success', $actor);
		$this->appendAuditEvent($event);
		$this->incrementSessionEventCount($sessionId);
		$this->closeSession($sessionId, 'logout');
	}

	public function recordAuthFailureEvent($attemptedUser, $sourceIp) {
		$syntheticSessionId = 'authfail_' . bin2hex(random_bytes(16));
		$event = array(
			'event_id' => $this->newEventId(),
			'session_id' => $syntheticSessionId,
			'session_phase' => 'failure',
			'channel' => 'auth',
			'module_name' => 'framework',
			'action' => 'login',
			'outcome' => 'failure',
			'route' => 'config.php',
			'object_type' => 'auth',
			'object_id' => '',
			'actor' => $this->truncate((string) $attemptedUser, 128),
			'source_ip' => $this->truncate((string) $sourceIp, 64),
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'request_hash' => '',
			'change' => array(
				'before' => null,
				'after' => null,
				'added' => array(),
				'removed' => array(),
				'changed' => array('attempted_user' => $this->truncate((string) $attemptedUser, 128))
			),
			'occurred_at_unix' => time(),
			'occurred_at_utc' => gmdate('d-m-Y H:i:s'),
			'occurred_at_local' => $this->getChisinauTimestamp()
		);
		$this->appendAuditEvent($event);
	}

	// ----------------------------------------------------------------
	// Read-only timeline API for module GUI
	// ----------------------------------------------------------------

	public function getRecentSessionTimeline($limit = 25, $offset = 0, $actor = '') {
		$this->clearStorageError();
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$limit = max(1, min(200, (int) $limit));
			$offset = max(0, (int) $offset);
			$actor = trim((string) $actor);

			$sql = "SELECT session_id, actor, login_at_unix, login_at_utc, login_at_local, end_at_unix, end_at_utc, end_at_local, end_reason, source_ip, user_agent, event_count
				FROM audit_sessions";
			$params = array();
			if ($actor !== '') {
				$sql .= " WHERE actor = ?";
				$params[] = $actor;
			}
			$sql .= " ORDER BY login_at_unix DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

			$sth = $pdo->prepare($sql);
			$sth->execute($params);
			$sessions = $sth->fetchAll(PDO::FETCH_ASSOC);
			if (empty($sessions)) {
				return array();
			}

			$sessionIds = array_column($sessions, 'session_id');
			$allEvents = $this->getSessionEventsBatch($sessionIds);

			$timeline = array();
			foreach ($sessions as $session) {
				$sid = (string) $session['session_id'];
				$timeline[] = array(
					'session' => $session,
					'events' => $allEvents[$sid] ?? array()
				);
			}
			return $timeline;
		} catch (\Throwable $e) {
			$this->setStorageError('Timeline read failed: ' . $e->getMessage());
			$this->debugLog('Timeline read failed', array('error' => $e->getMessage()));
			return array();
		}
	}

	public function getRecentAuthFailures($limit = 25, $offset = 0, $actor = '') {
		$this->clearStorageError();
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$limit = max(1, min(200, (int) $limit));
			$offset = max(0, (int) $offset);
			$actor = trim((string) $actor);

			$sql = "SELECT event_id, session_id, channel, module_name, action, outcome, actor, source_ip,
				occurred_at_unix, occurred_at_utc, occurred_at_local
				FROM audit_events
				WHERE session_phase = ? AND outcome = ?";
			$params = array('failure', 'failure');
			if ($actor !== '') {
				$sql .= " AND actor = ?";
				$params[] = $actor;
			}
			$sql .= " ORDER BY occurred_at_unix DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

			$sth = $pdo->prepare($sql);
			$sth->execute($params);
			return $sth->fetchAll(PDO::FETCH_ASSOC);
		} catch (\Throwable $e) {
			$this->setStorageError('Auth failure read failed: ' . $e->getMessage());
			$this->debugLog('Auth failure read failed', array('error' => $e->getMessage()));
			return array();
		}
	}

	/**
	 * Advanced event search with multiple filter dimensions.
	 * All filtering uses prepared statements. Sort field is allowlisted.
	 */
	public function searchAuditEvents(array $filters, $limit = 50, $offset = 0, $isExport = false) {
		$maxLimit = $isExport ? 5000 : 200;
		$limit = max(1, min($maxLimit, (int) $limit));
		$offset = max(0, (int) $offset);
		$this->clearStorageError();
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();

			$where = array();
			$params = array();

			if (!empty($filters['actor'])) {
				$where[] = "e.actor = ?";
				$params[] = (string) $filters['actor'];
			}
			if (!empty($filters['module_name'])) {
				$where[] = "e.module_name = ?";
				$params[] = (string) $filters['module_name'];
			}
			if (!empty($filters['action'])) {
				$where[] = "e.action = ?";
				$params[] = (string) $filters['action'];
			}
			if (!empty($filters['channel'])) {
				$where[] = "e.channel = ?";
				$params[] = (string) $filters['channel'];
			}
			if (!empty($filters['outcome'])) {
				$where[] = "e.outcome = ?";
				$params[] = (string) $filters['outcome'];
			}
			if (!empty($filters['source_ip'])) {
				$where[] = "e.source_ip = ?";
				$params[] = (string) $filters['source_ip'];
			}
			if (!empty($filters['session_phase'])) {
				$where[] = "e.session_phase = ?";
				$params[] = (string) $filters['session_phase'];
			}
			if (!empty($filters['date_from_unix'])) {
				$where[] = "e.occurred_at_unix >= ?";
				$params[] = (int) $filters['date_from_unix'];
			}
			if (!empty($filters['date_to_unix'])) {
				$where[] = "e.occurred_at_unix <= ?";
				$params[] = (int) $filters['date_to_unix'];
			}
			if (!empty($filters['search_text'])) {
				$term = '%' . str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), (string) $filters['search_text']) . '%';
				$likeEscape = " ESCAPE '!'";
				$where[] = "(e.module_name LIKE ?" . $likeEscape . " OR e.action LIKE ?" . $likeEscape . " OR e.actor LIKE ?" . $likeEscape . " OR e.object_type LIKE ?" . $likeEscape . " OR e.object_id LIKE ?" . $likeEscape . ")";
				$params[] = $term;
				$params[] = $term;
				$params[] = $term;
				$params[] = $term;
				$params[] = $term;
			}

			$allowedSort = array('occurred_at_unix', 'actor', 'module_name', 'action', 'channel');
			$sortField = 'occurred_at_unix';
			if (!empty($filters['sort']) && in_array($filters['sort'], $allowedSort, true)) {
				$sortField = $filters['sort'];
			}
			$sortDir = (!empty($filters['sort_dir']) && strtoupper($filters['sort_dir']) === 'ASC') ? 'ASC' : 'DESC';

			$sql = "SELECT e.event_id, e.session_id, e.session_phase, e.channel, e.module_name, e.action,
				e.outcome, e.route, e.object_type, e.object_id, e.actor, e.source_ip,
				e.request_method, e.request_uri, e.change_before, e.change_after,
				e.change_added, e.change_removed, e.change_changed,
				e.occurred_at_unix, e.occurred_at_utc, e.occurred_at_local
				FROM audit_events e";
			if (!empty($where)) {
				$sql .= " WHERE " . implode(" AND ", $where);
			}
			$sql .= " ORDER BY e." . $sortField . " " . $sortDir . " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

			$sth = $pdo->prepare($sql);
			$sth->execute($params);
			$rows = $sth->fetchAll(\PDO::FETCH_ASSOC);

			$countSql = "SELECT COUNT(*) FROM audit_events e";
			if (!empty($where)) {
				$countSql .= " WHERE " . implode(" AND ", $where);
			}
			$countParams = $params;
			$csth = $pdo->prepare($countSql);
			$csth->execute($countParams);
			$total = (int) $csth->fetchColumn();

			return array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset);
		} catch (\Throwable $e) {
			$errorMessage = 'Search query failed: ' . $e->getMessage();
			$this->setStorageError($errorMessage);
			$this->debugLog('Search query failed', array('error' => $e->getMessage()));
			return array('rows' => array(), 'total' => 0, 'limit' => $limit, 'offset' => $offset, 'error' => $this->truncate($errorMessage, 250));
		}
	}

	/**
	 * Return distinct values for filter dropdowns (bounded for safety).
	 */
	public function getDistinctFilterValues($column) {
		$allowed = array('actor', 'module_name', 'action', 'channel', 'outcome', 'session_phase', 'source_ip');
		if (!in_array($column, $allowed, true)) {
			return array();
		}
		$this->clearStorageError();
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$sql = "SELECT DISTINCT " . $column . " FROM audit_events ORDER BY " . $column . " ASC LIMIT 500";
			$sth = $pdo->prepare($sql);
			$sth->execute();
			return $sth->fetchAll(\PDO::FETCH_COLUMN);
		} catch (\Throwable $e) {
			$this->setStorageError('Filter values read failed: ' . $e->getMessage());
			$this->debugLog('Filter values read failed', array('error' => $e->getMessage()));
			return array();
		}
	}

	// ----------------------------------------------------------------
	// Session state management
	// ----------------------------------------------------------------

	private function ensureSessionState() {
		$actor = $this->getActor();
		$currentUnix = time();
		$idleTimeout = $this->getIdleTimeoutSeconds();
		$existingSessionId = $_SESSION[self::SESSION_KEY_ID] ?? null;
		$lastActivity = (int) ($_SESSION[self::SESSION_KEY_LAST_ACTIVITY] ?? 0);

		if (!empty($existingSessionId) && $lastActivity > 0) {
			if (($currentUnix - $lastActivity) > $idleTimeout) {
				$this->recordTimeoutEvent((string) $existingSessionId, $actor);
				$this->closeSession((string) $existingSessionId, 'timeout');
				unset(
					$_SESSION[self::SESSION_KEY_ID],
					$_SESSION[self::SESSION_KEY_LOGIN_RECORDED]
				);
			} else {
				$_SESSION[self::SESSION_KEY_LAST_ACTIVITY] = $currentUnix;
				return (string) $existingSessionId;
			}
		}

		$this->closeStaleActiveSessions($actor);

		$newSessionId = $this->newSessionId();
		$_SESSION[self::SESSION_KEY_ID] = $newSessionId;
		$_SESSION[self::SESSION_KEY_LAST_ACTIVITY] = $currentUnix;

		$this->appendSessionStart($newSessionId, $actor);
		$this->recordLoginEvent($newSessionId, $actor);

		return $newSessionId;
	}

	private function recordTimeoutEvent($sessionId, $actor) {
		if ($this->isDuplicateBoundaryEvent($sessionId, 'timeout')) {
			return;
		}
		$event = $this->buildBoundaryEvent($sessionId, 'timeout', 'timeout', 'success', $actor);
		$this->appendAuditEvent($event);
		$this->incrementSessionEventCount($sessionId);
	}

	/**
	 * Close any sessions for this actor that are still marked active.
	 * These are from previous logins where the explicit logout event
	 * was missed (e.g. browser tab closed without JS firing).
	 */
	private function closeStaleActiveSessions($actor) {
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$sql = "SELECT session_id, login_at_unix FROM audit_sessions WHERE actor = ? AND end_reason = ?";
		$sth = $pdo->prepare($sql);
		$sth->execute(array($actor, 'active'));
		$stale = $sth->fetchAll(PDO::FETCH_ASSOC);

		$idleTimeout = $this->getIdleTimeoutSeconds();
		$now = time();
		foreach ($stale as $row) {
			$sessionId = (string) $row['session_id'];
			$loginUnix = (int) $row['login_at_unix'];
			$reason = (($now - $loginUnix) > $idleTimeout) ? 'timeout' : 'logout';
			$this->closeSession($sessionId, $reason);
		}
	}

	private function getIdleTimeoutSeconds() {
		$value = (int) $this->getConfigSafe('audit_session_idle_timeout_seconds', (string) self::SESSION_IDLE_TIMEOUT_SECONDS);
		return $value > 0 ? $value : self::SESSION_IDLE_TIMEOUT_SECONDS;
	}

	private function appendSessionStart($sessionId, $actor) {
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$sql = "INSERT INTO audit_sessions (
			session_id, actor, login_at_unix, login_at_utc, login_at_local, end_reason, source_ip, user_agent, event_count, created_at_unix, created_at_utc, created_at_local
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$sth = $pdo->prepare($sql);
		$nowUnix = time();
		$nowUtc = gmdate('d-m-Y H:i:s');
		$nowLocal = $this->getChisinauTimestamp();
		$sth->execute(array(
			$sessionId,
			$actor,
			$nowUnix,
			$nowUtc,
			$nowLocal,
			'active',
			$this->getRemoteIp(),
			$this->truncate((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 1024),
			0,
			$nowUnix,
			$nowUtc,
			$nowLocal
		));
	}

	private function closeSession($sessionId, $reason) {
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$sql = "UPDATE audit_sessions SET end_at_unix = ?, end_at_utc = ?, end_at_local = ?, end_reason = ? WHERE session_id = ? AND end_reason = ?";
		$sth = $pdo->prepare($sql);
		$sth->execute(array(
			time(),
			gmdate('d-m-Y H:i:s'),
			$this->getChisinauTimestamp(),
			$reason,
			$sessionId,
			'active'
		));
	}

	private function incrementSessionEventCount($sessionId) {
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$sql = "UPDATE audit_sessions SET event_count = event_count + 1 WHERE session_id = ?";
		$sth = $pdo->prepare($sql);
		$sth->execute(array($sessionId));
	}

	private function markSessionActivity() {
		$_SESSION[self::SESSION_KEY_LAST_ACTIVITY] = time();
	}

	// ----------------------------------------------------------------
	// AJAX handler implementations
	// ----------------------------------------------------------------

	private function handleLogoutAjax() {
		$sessionId = $_SESSION[self::SESSION_KEY_ID] ?? null;
		if (empty($sessionId)) {
			return array('status' => false, 'message' => 'No active audit session');
		}
		$actor = $this->getActor();
		try {
			$this->recordLogoutEvent((string) $sessionId, $actor);
			unset(
				$_SESSION[self::SESSION_KEY_ID],
				$_SESSION[self::SESSION_KEY_LAST_ACTIVITY],
				$_SESSION[self::SESSION_KEY_LOGIN_RECORDED]
			);
		} catch (\Throwable $e) {
			$this->debugLog('Logout audit write failed', array('error' => $e->getMessage()));
			return array('status' => false, 'message' => 'Audit write failed');
		}
		return array('status' => true, 'message' => 'Logout recorded');
	}

	private function handleAuthFailureAjax() {
		$sourceIp = $this->getRemoteIp();
		if (!$this->checkAuthFailureRateLimit($sourceIp)) {
			return array('status' => false, 'message' => 'Rate limited');
		}
		$attemptedUser = $this->truncate(trim((string) ($_REQUEST['username'] ?? '')), 128);
		if ($attemptedUser === '') {
			return array('status' => false, 'message' => 'No username provided');
		}
		try {
			$this->recordAuthFailureEvent($attemptedUser, $sourceIp);
		} catch (\Throwable $e) {
			$this->debugLog('Auth failure audit write failed', array('error' => $e->getMessage()));
			return array('status' => false, 'message' => 'Audit write failed');
		}
		return array('status' => true, 'message' => 'Auth failure recorded');
	}

	private function checkAuthFailureRateLimit($ip) {
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$window = time() - 60;
			$sth = $pdo->prepare("SELECT COUNT(*) FROM audit_events WHERE session_phase = ? AND source_ip = ? AND occurred_at_unix >= ?");
			$sth->execute(array('failure', $ip, $window));
			return ((int) $sth->fetchColumn()) < 20;
		} catch (\Throwable $e) {
			return true;
		}
	}

	/**
	 * Handles events from the universal JS AJAX interceptor.
	 * The client-side script monitors all XMLHttpRequest/fetch calls to ajax.php
	 * for other modules and beacons the metadata here.
	 */
	private function handleInterceptedAjax() {
		$sessionId = $_SESSION[self::SESSION_KEY_ID] ?? null;
		if (empty($sessionId)) {
			return array('status' => false, 'message' => 'No active audit session');
		}
		$targetModule = $this->truncate(trim((string) ($_REQUEST['target_module'] ?? '')), 128);
		$targetCommand = $this->truncate(trim((string) ($_REQUEST['target_command'] ?? '')), 128);
		$targetMethod = strtoupper(trim((string) ($_REQUEST['target_method'] ?? 'POST')));
		$targetUrl = $this->truncate(trim((string) ($_REQUEST['target_url'] ?? '')), 2048);
		$httpStatus = (int) ($_REQUEST['http_status'] ?? 200);
		$targetBody = $this->truncate(trim((string) ($_REQUEST['target_body'] ?? '')), 4096);

		if ($targetModule === '' || $targetModule === 'auditcompliance') {
			return array('status' => false, 'message' => 'Skipped');
		}

		if ($this->isReadOnlyAjaxCommand($targetModule, $targetCommand)) {
			return array('status' => false, 'message' => 'Skipped read-only');
		}

		$isApplyConfig = ($targetModule === 'framework'
			&& in_array($targetCommand, array('reload', 'retrieve_conf', 'apply_config', ''), true));
		if ($isApplyConfig) {
			$targetCommand = 'apply_config';
		}

		$displayModule = $this->normalizeAjaxModuleName($targetModule, $targetCommand, $targetBody);
		$objectId = $this->extractObjectIdFromAjaxBody($targetBody, $targetUrl);

		try {
			$this->routeEvent(array(
				'session_id' => (string) $sessionId,
				'session_phase' => 'activity',
				'channel' => $isApplyConfig ? 'gui' : 'ajax',
				'module_name' => $displayModule,
				'action' => $targetCommand !== '' ? $targetCommand : 'ajax_action',
				'outcome' => ($httpStatus >= 200 && $httpStatus < 400) ? 'success' : 'failure',
				'route' => $isApplyConfig ? 'config.php?handler=reload' : ('ajax.php?module=' . $targetModule),
				'object_type' => $isApplyConfig ? 'system' : $displayModule,
				'object_id' => $objectId,
				'request_method' => $targetMethod,
				'request_uri' => $targetUrl,
				'change' => array(
					'before' => null, 'after' => null,
					'added' => array(), 'removed' => array(),
					'changed' => $isApplyConfig
						? array('action' => 'apply_config', 'description' => 'Administrator applied configuration changes to Asterisk')
						: $this->buildAjaxChangePayload($targetCommand, $httpStatus, $targetBody)
				)
			));
		} catch (\Throwable $e) {
			$this->debugLog('Intercepted AJAX audit failed', array('error' => $e->getMessage()));
			return array('status' => false, 'message' => 'Audit write failed');
		}
		return array('status' => true, 'message' => 'AJAX action recorded');
	}

	/**
	 * Normalizes the AJAX module name to match the user-facing page/entity name.
	 *
	 * The FreePBX `core` module handles extensions, users, devices, trunks, and
	 * routing. Its AJAX commands use a `type` parameter to distinguish entity
	 * types. This method maps internal module names to user-facing display names
	 * so the audit log reads e.g. "extensions / delete" instead of "core / delete".
	 */
	private function normalizeAjaxModuleName($module, $command, $body) {
		if (strtolower($module) !== 'core' || $body === '') {
			return $module;
		}
		parse_str($body, $params);
		$type = strtolower(trim((string) ($params['type'] ?? '')));

		$typeToModule = array(
			'extensions' => 'extensions',
			'extension' => 'extensions',
			'users' => 'users',
			'devices' => 'devices',
		);

		if (isset($typeToModule[$type])) {
			return $typeToModule[$type];
		}

		$commandLower = strtolower($command);
		if (strpos($commandLower, 'route') !== false || strpos($commandLower, 'trunk') !== false) {
			return $commandLower === 'updatetrunks' ? 'trunks' : 'routing';
		}

		return $module;
	}

	private function isReadOnlyAjaxCommand($module, $command) {
		$moduleLower = strtolower($module);
		$commandLower = strtolower($command);
		if ($commandLower === '') {
			return false;
		}

		if (isset(self::AJAX_READ_ONLY_COMMANDS['*'])) {
			if (in_array($commandLower, self::AJAX_READ_ONLY_COMMANDS['*'], true)) {
				return true;
			}
		}
		if (isset(self::AJAX_READ_ONLY_COMMANDS[$moduleLower])) {
			if (in_array($commandLower, self::AJAX_READ_ONLY_COMMANDS[$moduleLower], true)) {
				return true;
			}
		}

		$readOnlyPrefixes = array('get', 'list', 'check', 'search', 'lookup', 'validate', 'test', 'load', 'fetch', 'query');
		foreach ($readOnlyPrefixes as $prefix) {
			if (strpos($commandLower, $prefix) === 0 && strlen($commandLower) > strlen($prefix)) {
				$nextChar = $commandLower[strlen($prefix)];
				if (ctype_upper($command[strlen($prefix)]) || $nextChar === '_') {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Extracts an object identifier from the AJAX POST body or URL parameters.
	 * The JS interceptor sends a summary of the POST body as `target_body`.
	 */
	private function extractObjectIdFromAjaxBody($body, $url) {
		$candidates = array(
			'id', 'ext', 'extension', 'extdisplay', 'account',
			'user_id', 'trunkid', 'itemid', 'group_id', 'entry_id',
			'queue', 'grpnum', 'cidnum', 'backup_id', 'tcid',
			'tgid', 'confno', 'pagegrp', 'pagenbr', 'rg', 'ivr_id',
			'faxid', 'calendar_id', 'pinsets_id', 'scheme',
			'announcement_id', 'callrecording_id', 'channel',
			'name', 'username',
		);

		if ($body !== '') {
			parse_str($body, $params);
			if (!empty($params)) {
				if (!empty($params['extensions']) && is_array($params['extensions'])) {
					return $this->truncate(implode(',', array_slice($params['extensions'], 0, 10)), 256);
				}
				foreach ($candidates as $key) {
					if (!empty($params[$key])) {
						return $this->truncate((string) $params[$key], 256);
					}
				}
			}
		}

		if ($url !== '') {
			$qPos = strpos($url, '?');
			if ($qPos !== false) {
				parse_str(substr($url, $qPos + 1), $urlParams);
				foreach ($candidates as $key) {
					if (!empty($urlParams[$key])) {
						return $this->truncate((string) $urlParams[$key], 256);
					}
				}
			}
		}

		return '';
	}

	/**
	 * Builds a richer change payload for AJAX events by extracting
	 * identifiers and type info from the intercepted POST body.
	 */
	private function buildAjaxChangePayload($command, $httpStatus, $body) {
		$payload = array('command' => $command, 'http_status' => $httpStatus);
		if ($body !== '') {
			parse_str($body, $params);
			if (!empty($params['type'])) {
				$payload['type'] = $this->truncate((string) $params['type'], 128);
			}
			if (!empty($params['extensions']) && is_array($params['extensions'])) {
				$payload['target_ids'] = array_map(function ($v) {
					return $this->truncate((string) $v, 128);
				}, array_slice($params['extensions'], 0, 20));
			}
			if (!empty($params['name'])) {
				$payload['name'] = $this->truncate((string) $params['name'], 256);
			}
			if (!empty($params['username'])) {
				$payload['username'] = $this->truncate((string) $params['username'], 256);
			}
			if (!empty($params['id'])) {
				$payload['target_id'] = $this->truncate((string) $params['id'], 256);
			}
		}
		return $this->redactSensitiveData($payload);
	}

	private function handleSearchAjax() {
		$filters = array(
			'actor' => trim((string) ($_REQUEST['actor'] ?? '')),
			'module_name' => trim((string) ($_REQUEST['module_name'] ?? '')),
			'action' => trim((string) ($_REQUEST['action_filter'] ?? '')),
			'channel' => trim((string) ($_REQUEST['channel'] ?? '')),
			'outcome' => trim((string) ($_REQUEST['outcome'] ?? '')),
			'source_ip' => trim((string) ($_REQUEST['source_ip'] ?? '')),
			'session_phase' => trim((string) ($_REQUEST['session_phase'] ?? '')),
			'search_text' => trim((string) ($_REQUEST['search_text'] ?? '')),
			'sort' => trim((string) ($_REQUEST['sort'] ?? '')),
			'sort_dir' => trim((string) ($_REQUEST['sort_dir'] ?? ''))
		);
		$this->parseDateFilters($filters);
		$limit = max(1, min(200, (int) ($_REQUEST['limit'] ?? 50)));
		$offset = max(0, (int) ($_REQUEST['offset'] ?? 0));
		return $this->searchAuditEvents($filters, $limit, $offset);
	}

	private function handleExportAjax() {
		if (!$this->checkExportRateLimit()) {
			return array('status' => false, 'message' => 'Export rate limit exceeded, wait 10 seconds');
		}
		$filters = array(
			'actor' => trim((string) ($_REQUEST['actor'] ?? '')),
			'module_name' => trim((string) ($_REQUEST['module_name'] ?? '')),
			'action' => trim((string) ($_REQUEST['action_filter'] ?? '')),
			'channel' => trim((string) ($_REQUEST['channel'] ?? '')),
			'outcome' => trim((string) ($_REQUEST['outcome'] ?? '')),
			'source_ip' => trim((string) ($_REQUEST['source_ip'] ?? '')),
			'session_phase' => trim((string) ($_REQUEST['session_phase'] ?? '')),
			'search_text' => trim((string) ($_REQUEST['search_text'] ?? ''))
		);
		$this->parseDateFilters($filters);
		$result = $this->searchAuditEvents($filters, 5000, 0, true);
		return array('export' => $result['rows'], 'total' => $result['total']);
	}

	private function parseDateFilters(array &$filters) {
		$tz = new \DateTimeZone('Europe/Chisinau');
		$dateFrom = trim((string) ($_REQUEST['date_from'] ?? ''));
		$dateTo = trim((string) ($_REQUEST['date_to'] ?? ''));
		if ($dateFrom !== '') {
			$dt = $this->parseDateInput($dateFrom, $tz);
			if ($dt !== null) {
				$dt->setTime(0, 0, 0);
				$filters['date_from_unix'] = $dt->getTimestamp();
			}
		}
		if ($dateTo !== '') {
			$dt = $this->parseDateInput($dateTo, $tz);
			if ($dt !== null) {
				$dt->setTime(23, 59, 59);
				$filters['date_to_unix'] = $dt->getTimestamp();
			}
		}
	}

	/**
	 * Parse a date string in DD-MM-YYYY format. Falls back to YYYY-MM-DD
	 * for backwards compatibility with existing bookmarks/links.
	 */
	private function parseDateInput($value, \DateTimeZone $tz) {
		$value = trim((string) $value);
		if ($value === '') {
			return null;
		}
		$dt = \DateTime::createFromFormat('d-m-Y', $value, $tz);
		$err = ($dt !== false) ? $dt::getLastErrors() : false;
		if ($dt !== false && ($err === false || !array_sum($err))) {
			return $dt;
		}
		$dt = \DateTime::createFromFormat('Y-m-d', $value, $tz);
		$err = ($dt !== false) ? $dt::getLastErrors() : false;
		if ($dt !== false && ($err === false || !array_sum($err))) {
			return $dt;
		}
		return null;
	}

	private function handleFilterValuesAjax() {
		$column = trim((string) ($_REQUEST['column'] ?? ''));
		$values = $this->getDistinctFilterValues($column);
		return array(
			'values' => $values,
			'error' => $this->getLastStorageErrorMessage()
		);
	}

	private function handleDashboardStatsAjax() {
		$this->clearStorageError();
		$stats = array(
			'events_today' => 0,
			'events_total' => 0,
			'active_sessions' => 0,
			'auth_failures_24h' => 0,
			'sensitive_reads_24h' => 0,
			'top_actors' => array(),
			'channel_breakdown' => array(),
			'recent_events' => array(),
			'timestamp' => $this->getChisinauTimestamp()
		);

		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$now = time();
			$todayStart = (new \DateTime('today midnight', new \DateTimeZone('Europe/Chisinau')))->getTimestamp();
			$last24h = $now - 86400;

			$sth = $pdo->prepare("SELECT COUNT(*) FROM audit_events WHERE occurred_at_unix >= ?");
			$sth->execute(array($todayStart));
			$stats['events_today'] = (int) $sth->fetchColumn();

			$sth = $pdo->prepare("SELECT COUNT(*) FROM audit_events");
			$sth->execute();
			$stats['events_total'] = (int) $sth->fetchColumn();

			$sth = $pdo->prepare("SELECT COUNT(*) FROM audit_sessions WHERE end_reason = ?");
			$sth->execute(array('active'));
			$stats['active_sessions'] = (int) $sth->fetchColumn();

			$sth = $pdo->prepare("SELECT COUNT(*) FROM audit_events WHERE session_phase = ? AND outcome = ? AND occurred_at_unix >= ?");
			$sth->execute(array('failure', 'failure', $last24h));
			$stats['auth_failures_24h'] = (int) $sth->fetchColumn();

			$sth = $pdo->prepare("SELECT COUNT(*) FROM audit_events WHERE channel = ? AND action LIKE ? ESCAPE '!' AND occurred_at_unix >= ?");
			$sth->execute(array('gui', '%!_access', $last24h));
			$stats['sensitive_reads_24h'] = (int) $sth->fetchColumn();

			$sth = $pdo->prepare("SELECT actor, COUNT(*) AS cnt FROM audit_events WHERE occurred_at_unix >= ? AND session_phase = ? GROUP BY actor ORDER BY cnt DESC LIMIT 5");
			$sth->execute(array($todayStart, 'activity'));
			$stats['top_actors'] = $sth->fetchAll(\PDO::FETCH_ASSOC);

			$sth = $pdo->prepare("SELECT channel, COUNT(*) AS cnt FROM audit_events WHERE occurred_at_unix >= ? GROUP BY channel ORDER BY cnt DESC");
			$sth->execute(array($todayStart));
			$stats['channel_breakdown'] = $sth->fetchAll(\PDO::FETCH_ASSOC);

			$sth = $pdo->prepare("SELECT event_id, session_phase, channel, module_name, action, outcome, actor, source_ip, occurred_at_unix, occurred_at_local FROM audit_events ORDER BY occurred_at_unix DESC LIMIT 15");
			$sth->execute();
			$stats['recent_events'] = $sth->fetchAll(\PDO::FETCH_ASSOC);
		} catch (\Throwable $e) {
			$this->setStorageError('Dashboard stats query failed: ' . $e->getMessage());
			$stats['error'] = $this->truncate($e->getMessage(), 250);
			$this->debugLog('Dashboard stats query failed', array('error' => $e->getMessage()));
		}

		return $stats;
	}

	// ----------------------------------------------------------------
	// Audit scripts injection (logout + universal AJAX interceptor)
	// ----------------------------------------------------------------

	private function injectAuditScripts() {
		if ($this->auditScriptsInjected) {
			return;
		}
		$this->auditScriptsInjected = true;
		$js = <<<'JSEOF'
<script type="text/javascript">
(function(){
	"use strict";
	var AUDIT_AJAX="ajax.php?module=auditcompliance&command=";

	// --- Logout interception ---
	var logoutSent=false;
	document.addEventListener("click",function(e){
		var a=e.target.closest('a[href*="logout=true"]');
		if(!a||logoutSent)return;
		logoutSent=true;
		e.preventDefault();
		var x=new XMLHttpRequest();
		x.open("POST",AUDIT_AJAX+"recordLogout",true);
		x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
		x.timeout=3000;
		function go(){window.location.href=a.href;}
		x.onloadend=go;x.ontimeout=go;x.onerror=go;
		x.send("");
	});

	// --- Universal AJAX interceptor ---
	// Monitors all XMLHttpRequest POST/PUT/DELETE calls to ajax.php and
	// config.php (Apply Config / reload) for ANY module except auditcompliance.
	var origOpen=XMLHttpRequest.prototype.open;
	var origSend=XMLHttpRequest.prototype.send;
	XMLHttpRequest.prototype.open=function(method,url){
		this._auditMethod=(method||"").toUpperCase();
		this._auditUrl=String(url||"");
		return origOpen.apply(this,arguments);
	};
	XMLHttpRequest.prototype.send=function(body){
		var self=this;
		var m=self._auditMethod||"";
		var u=self._auditUrl||"";
		var isAjax=(m==="POST"||m==="PUT"||m==="DELETE")&&u.indexOf("ajax.php")!==-1&&u.indexOf("module=auditcompliance")===-1;
		var isReload=(m==="POST")&&u.indexOf("config.php")!==-1&&(u.indexOf("handler=reload")!==-1||u.indexOf("handler=retrieve_conf")!==-1);
		if(isAjax||isReload){
			var mod="",cmd="",bodySnippet="";
			if(isReload){mod="framework";cmd="apply_config";}
			else{try{
				var qIdx=u.indexOf("?");
				if(qIdx>=0){
					var params=new URLSearchParams(u.substring(qIdx));
					mod=params.get("module")||"";
					cmd=params.get("command")||"";
				}
				if(!mod&&body&&typeof body==="string"){
					var bp=new URLSearchParams(body);
					if(!mod)mod=bp.get("module")||"";
					if(!cmd)cmd=bp.get("command")||"";
				}
			}catch(e){}}
			if(body&&typeof body==="string"){try{
				var idKeys=["id","ext","extension","extdisplay","account","user_id","trunkid","name","username","extensions[]"];
				var bp2=new URLSearchParams(body);
				var parts=[];
				for(var ki=0;ki<idKeys.length;ki++){
					var vals=bp2.getAll(idKeys[ki]);
					if(vals.length>0){for(var vi=0;vi<Math.min(vals.length,10);vi++){
						parts.push(idKeys[ki]+"="+vals[vi]);
					}}
				}
				if(bp2.get("type"))parts.push("type="+bp2.get("type"));
				bodySnippet=parts.join("&").substring(0,2048);
			}catch(e){}}
			if(!mod&&cmd==="reload"){mod="framework";cmd="apply_config";}
			if(mod&&mod!=="auditcompliance"){
				self.addEventListener("loadend",function(){
					try{
						var bx=new XMLHttpRequest();
						bx.open("POST",AUDIT_AJAX+"recordInterceptedAjax",true);
						bx.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
						bx.timeout=3000;
						var payload="target_module="+encodeURIComponent(mod)+"&target_command="+encodeURIComponent(cmd)+"&target_method="+encodeURIComponent(m)+"&target_url="+encodeURIComponent(u.substring(0,500))+"&http_status="+encodeURIComponent(self.status||0);
						if(bodySnippet)payload+="&target_body="+encodeURIComponent(bodySnippet);
						bx.send(payload);
					}catch(e){}
				});
			}
		}
		return origSend.apply(this,arguments);
	};
})();
</script>
JSEOF;
		echo $js;
	}

	// ----------------------------------------------------------------
	// Boundary event helpers
	// ----------------------------------------------------------------

	private function buildBoundaryEvent($sessionId, $phase, $action, $outcome, $actor) {
		return array(
			'event_id' => $this->newEventId(),
			'session_id' => (string) $sessionId,
			'session_phase' => (string) $phase,
			'channel' => 'auth',
			'module_name' => 'framework',
			'action' => (string) $action,
			'outcome' => (string) $outcome,
			'route' => 'config.php',
			'object_type' => 'session',
			'object_id' => (string) $sessionId,
			'actor' => (string) $actor,
			'source_ip' => $this->getRemoteIp(),
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'request_hash' => '',
			'change' => array(
				'before' => null,
				'after' => null,
				'added' => array(),
				'removed' => array(),
				'changed' => array()
			),
			'occurred_at_unix' => time(),
			'occurred_at_utc' => gmdate('d-m-Y H:i:s'),
			'occurred_at_local' => $this->getChisinauTimestamp()
		);
	}

	private function isDuplicateBoundaryEvent($sessionId, $phase) {
		if ($phase === 'login') {
			return ($_SESSION[self::SESSION_KEY_LOGIN_RECORDED] ?? null) === $sessionId;
		}
		try {
			$this->ensureAuditSchema();
			$pdo = $this->getAuditDb();
			$sql = "SELECT COUNT(*) FROM audit_events WHERE session_id = ? AND session_phase = ?";
			$sth = $pdo->prepare($sql);
			$sth->execute(array((string) $sessionId, (string) $phase));
			return ((int) $sth->fetchColumn()) > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	// ----------------------------------------------------------------
	// Immutable event writer
	// ----------------------------------------------------------------

	private function appendAuditEvent(array $event) {
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$sql = "INSERT INTO audit_events (
			event_id, session_id, session_phase, channel, module_name, action, outcome, route, object_type, object_id,
			actor, source_ip, request_method, request_uri, request_hash,
			change_before, change_after, change_added, change_removed, change_changed,
			occurred_at_unix, occurred_at_utc, occurred_at_local
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$sth = $pdo->prepare($sql);
		$sth->execute(array(
			$event['event_id'],
			$event['session_id'],
			$event['session_phase'],
			$event['channel'],
			$this->truncate((string) $event['module_name'], 128),
			$this->truncate((string) $event['action'], 128),
			$this->truncate((string) $event['outcome'], 32),
			$this->truncate((string) $event['route'], 1024),
			$this->truncate((string) $event['object_type'], 128),
			$this->truncate((string) $event['object_id'], 256),
			$this->truncate((string) $event['actor'], 128),
			$this->truncate((string) $event['source_ip'], 64),
			$this->truncate((string) $event['request_method'], 16),
			$this->truncate((string) $event['request_uri'], 2048),
			$this->truncate((string) $event['request_hash'], 128),
			$this->safeJsonEncode($event['change']['before']),
			$this->safeJsonEncode($event['change']['after']),
			$this->safeJsonEncode($event['change']['added']),
			$this->safeJsonEncode($event['change']['removed']),
			$this->safeJsonEncode($event['change']['changed']),
			(int) $event['occurred_at_unix'],
			$this->truncate((string) $event['occurred_at_utc'], 19),
			$this->truncate((string) $event['occurred_at_local'], 19)
		));
	}

	// ----------------------------------------------------------------
	// Change payload / redaction
	// ----------------------------------------------------------------

	private function buildChangePayload(array $request, $previousPost = null) {
		$redacted = $this->redactSensitiveData($request);
		$filtered = $this->filterNoiseKeys($redacted);
		if ($previousPost !== null && is_array($previousPost)) {
			$diff = $this->computeChangeDiff($previousPost, $filtered);
			return array(
				'before' => null,
				'after' => $filtered,
				'added' => $diff['added'],
				'removed' => $diff['removed'],
				'changed' => $diff['changed']
			);
		}
		return array(
			'before' => null,
			'after' => $filtered,
			'added' => $filtered,
			'removed' => array(),
			'changed' => array()
		);
	}

	private function filterNoiseKeys(array $data) {
		$skipKeys = array_flip(self::$DIFF_SKIP_KEYS);
		$result = array();
		foreach ($data as $key => $value) {
			if (!isset($skipKeys[$key])) {
				$result[$key] = $value;
			}
		}
		return $result;
	}

	/**
	 * Retrieve the POST data stored from the most recent previous audit
	 * event for the same module and object. This serves as the "before"
	 * baseline: since FreePBX always processes the target module's
	 * doConfigPageInit before other modules' hooks fire, we cannot read
	 * the DB state pre-modification. Instead we compare the current POST
	 * against the previous POST for the same object.
	 */
	private function getPreviousPostData($display, $objectId) {
		if ($objectId === '') {
			return null;
		}
		try {
			$pdo = $this->getAuditDb();
			if ($pdo === null) {
				return null;
			}
			$sql = "SELECT change_after FROM audit_events
				WHERE module_name = ? AND object_id = ? AND channel = 'gui'
				AND change_after IS NOT NULL AND change_after != 'null' AND change_after != '{}'
				ORDER BY occurred_at_unix DESC LIMIT 1";
			$sth = $pdo->prepare($sql);
			$sth->execute(array((string) $display, (string) $objectId));
			$row = $sth->fetch(PDO::FETCH_ASSOC);
			if ($row && !empty($row['change_after'])) {
				$decoded = @json_decode($row['change_after'], true);
				if (is_array($decoded) && !empty($decoded)) {
					return $this->filterNoiseKeys($decoded);
				}
			}
		} catch (\Throwable $e) {
			$this->debugLog('Previous post data lookup failed', array(
				'error' => $e->getMessage(), 'module' => $display, 'object_id' => $objectId
			));
		}
		return null;
	}

	/**
	 * Read the current state of the object being modified via module API.
	 * Note: due to FreePBX hook ordering, this returns post-modification
	 * state for doConfigPageInit events. Kept for AJAX/hook events.
	 */
	private function readBeforeState($display, $objectId) {
		if ($objectId === '') {
			return null;
		}
		$displayLower = strtolower((string) $display);
		$readers = self::BEFORE_STATE_READERS[$displayLower] ?? null;
		if ($readers !== null) {
			foreach ($readers as $reader) {
				$result = $this->tryModuleGetter($reader['class'], $reader['methods'], $objectId);
				if ($result !== null) {
					return $result;
				}
			}
		}
		return $this->readGenericBeforeState($display, $objectId);
	}

	private function tryModuleGetter($className, array $methods, $objectId) {
		try {
			if (!isset($this->FreePBX->$className) || !is_object($this->FreePBX->$className)) {
				return null;
			}
			$module = $this->FreePBX->$className;
			foreach ($methods as $method) {
				if (!method_exists($module, $method)) {
					continue;
				}
				$result = @$module->$method($objectId);
				if ($result !== null && $result !== false) {
					if (is_object($result)) {
						$result = (array) $result;
					}
					if (is_array($result) && !empty($result)) {
						return $this->redactSensitiveData($result);
					}
				}
			}
		} catch (\Throwable $e) {
			$this->debugLog('Before-state read failed', array(
				'class' => $className, 'error' => $e->getMessage()
			));
		}
		return null;
	}

	private function readGenericBeforeState($display, $objectId) {
		$className = ucfirst(strtolower((string) $display));
		try {
			if (!isset($this->FreePBX->$className) || !is_object($this->FreePBX->$className)) {
				return null;
			}
			$module = $this->FreePBX->$className;
			foreach (array('get', 'getDetails', 'getById', 'getConfig') as $method) {
				if (!method_exists($module, $method)) {
					continue;
				}
				$result = @$module->$method($objectId);
				if ($result !== null && $result !== false) {
					if (is_object($result)) {
						$result = (array) $result;
					}
					if (is_array($result) && !empty($result)) {
						return $this->redactSensitiveData($result);
					}
				}
			}
		} catch (\Throwable $e) {
			// Silent — unknown module
		}
		return null;
	}

	private static $DIFF_SKIP_KEYS = array(
		'display', 'action', 'Submit', 'submit', 'view', 'extdisplay',
		'fw_popover_process', 'fw_popover', 'nonce', 'fw_csrf_token',
		'goto0', 'goto1', 'goto2',
		'delete', 'tech', 'orig_account', 'entries',
		'__csrf_token', 'fw_csrf', 'module_hook'
	);

	/**
	 * Compare previous POST data against current POST data.
	 * Keys only in the new POST (not in previous) are reported as added.
	 * Keys in both are compared; differences are reported as changed.
	 */
	private function computeChangeDiff($before, $after) {
		$diff = array('added' => array(), 'removed' => array(), 'changed' => array());
		if (!is_array($before) || !is_array($after)) {
			return $diff;
		}
		foreach ($after as $key => $newVal) {
			if (!array_key_exists($key, $before)) {
				$diff['added'][$key] = $newVal;
				continue;
			}
			$oldVal = $before[$key];
			if ($this->valuesAreDifferent($oldVal, $newVal)) {
				$diff['changed'][$key] = array('old' => $oldVal, 'new' => $newVal);
			}
		}
		foreach ($before as $key => $oldVal) {
			if (!array_key_exists($key, $after)) {
				$diff['removed'][$key] = $oldVal;
			}
		}
		return $diff;
	}

	private function valuesAreDifferent($old, $new) {
		if (is_array($old) || is_array($new)) {
			return json_encode($old) !== json_encode($new);
		}
		$oldStr = trim((string) $old);
		$newStr = trim((string) $new);
		if ($oldStr === $newStr) {
			return false;
		}
		if ($this->areBothFalsy($oldStr, $newStr)) {
			return false;
		}
		$oldNorm = $this->normalizeListValue($oldStr);
		$newNorm = $this->normalizeListValue($newStr);
		return $oldNorm !== $newNorm;
	}

	private function areBothFalsy($a, $b) {
		$falsy = array('' => true, '0' => true, 'no' => true, 'false' => true, 'none' => true, 'null' => true);
		return isset($falsy[strtolower($a)]) && isset($falsy[strtolower($b)]);
	}

	private function normalizeListValue($val) {
		if (strpos($val, '-') !== false || strpos($val, "\n") !== false
			|| strpos($val, ',') !== false || strpos($val, ' ') !== false) {
			$parts = preg_split('/[\-\n\r,\s]+/', $val, -1, PREG_SPLIT_NO_EMPTY);
			sort($parts);
			return implode(',', $parts);
		}
		return $val;
	}

	private function redactSensitiveData(array $input) {
		static $substringPatterns = array(
			'password', 'passwd', 'secret', 'api_key', 'private_key',
			'access_token', 'refresh_token', 'credential',
			'privatekey', 'tlskey', 'tlsprivate', 'ampmgrpass',
			'fcc_password', 'turnpassword'
		);
		static $exactPatterns = array(
			'pass', 'pin', 'userpin', 'adminpin', 'token',
			'oauth_secret', 'oauth_token', 'cert_key', 'tls_cert_key'
		);
		static $suffixPatterns = array(
			'_pass', '_pin', '_secret', '_token', '_key', '_cert_pem',
			'_private', '_privkey'
		);
		$out = array();
		foreach ($input as $key => $value) {
			$k = strtolower((string) $key);
			$isSensitive = false;
			if (in_array($k, $exactPatterns, true)) {
				$isSensitive = true;
			}
			if (!$isSensitive) {
				foreach ($substringPatterns as $s) {
					if (strpos($k, $s) !== false) {
						$isSensitive = true;
						break;
					}
				}
			}
			if (!$isSensitive) {
				foreach ($suffixPatterns as $s) {
					if (substr($k, -strlen($s)) === $s) {
						$isSensitive = true;
						break;
					}
				}
			}
			if ($isSensitive) {
				$out[$key] = '***REDACTED***';
				continue;
			}
			if (is_array($value)) {
				$out[$key] = $this->redactSensitiveData($value);
			} elseif (is_scalar($value) || $value === null) {
				$out[$key] = $this->truncate((string) $value, 2048);
			}
		}
		return $out;
	}

	private function extractObjectIdFromArgs(array $args) {
		foreach ($args as $arg) {
			if (is_scalar($arg) && $arg !== '' && $arg !== null) {
				return $this->truncate((string) $arg, 256);
			}
			if (is_array($arg)) {
				foreach (array('id', 'extension', 'user_id', 'ext', 'name', 'username', 'prevUsername', 'fname', 'lname', 'displayname') as $k) {
					if (isset($arg[$k]) && $arg[$k] !== '') {
						return $this->truncate((string) $arg[$k], 256);
					}
				}
				if (isset($arg['status']) && is_bool($arg['status'])) {
					foreach (array('id', 'user_id', 'username', 'name', 'extension') as $k) {
						if (isset($arg[$k]) && $arg[$k] !== '') {
							return $this->truncate((string) $arg[$k], 256);
						}
					}
				}
			}
		}
		return '';
	}

	private function flattenHookArgs(array $args) {
		$out = array();
		foreach ($args as $i => $arg) {
			if (is_array($arg)) {
				$out['arg_' . $i] = $this->redactSensitiveData($arg);
			} elseif (is_scalar($arg) || $arg === null) {
				$out['arg_' . $i] = $this->truncate((string) $arg, 2048);
			}
		}
		return $out;
	}

	// ----------------------------------------------------------------
	// Schema management
	// ----------------------------------------------------------------

	private function ensureAuditSchema() {
		if ($this->schemaReady) {
			return;
		}
		$pdo = $this->getAuditDb();

		try {
			$sth = $pdo->prepare("SELECT 1 FROM audit_events LIMIT 1");
			$sth->execute();
			$this->schemaReady = true;
			return;
		} catch (\Throwable $e) {
			// Table doesn't exist yet — proceed with full DDL
		}

		$driver = $this->getDriverName($pdo);
		$this->createBaseTables($pdo, $driver);
		$this->createIndexes($pdo, $driver);
		$this->createImmutabilityTriggers($pdo, $driver);
		$this->schemaReady = true;
	}

	private function createBaseTables(PDO $pdo, $driver) {
		if ($driver === 'pgsql') {
			$pdo->exec("CREATE TABLE IF NOT EXISTS audit_sessions (
				session_id VARCHAR(64) PRIMARY KEY,
				actor VARCHAR(128) NOT NULL,
				login_at_unix BIGINT NOT NULL,
				login_at_utc VARCHAR(19) NOT NULL,
				login_at_local VARCHAR(19) NOT NULL,
				end_at_unix BIGINT NULL,
				end_at_utc VARCHAR(19) NULL,
				end_at_local VARCHAR(19) NULL,
				end_reason VARCHAR(32) NOT NULL DEFAULT 'active',
				source_ip VARCHAR(64) NULL,
				user_agent TEXT NULL,
				event_count INTEGER NOT NULL DEFAULT 0,
				created_at_unix BIGINT NOT NULL,
				created_at_utc VARCHAR(19) NOT NULL,
				created_at_local VARCHAR(19) NOT NULL
			)");
			$pdo->exec("CREATE TABLE IF NOT EXISTS audit_events (
				event_id VARCHAR(64) PRIMARY KEY,
				session_id VARCHAR(64) NOT NULL,
				session_phase VARCHAR(16) NOT NULL,
				channel VARCHAR(16) NOT NULL,
				module_name VARCHAR(128) NOT NULL,
				action VARCHAR(128) NOT NULL,
				outcome VARCHAR(32) NOT NULL,
				route VARCHAR(1024) NOT NULL,
				object_type VARCHAR(128) NOT NULL,
				object_id VARCHAR(256) NOT NULL,
				actor VARCHAR(128) NOT NULL,
				source_ip VARCHAR(64) NOT NULL,
				request_method VARCHAR(16) NOT NULL,
				request_uri VARCHAR(2048) NOT NULL,
				request_hash VARCHAR(128) NOT NULL,
				change_before TEXT NULL,
				change_after TEXT NULL,
				change_added TEXT NULL,
				change_removed TEXT NULL,
				change_changed TEXT NULL,
				occurred_at_unix BIGINT NOT NULL,
				occurred_at_utc VARCHAR(19) NOT NULL,
				occurred_at_local VARCHAR(19) NOT NULL
			)");
			return;
		}

		$pdo->exec("CREATE TABLE IF NOT EXISTS audit_sessions (
			session_id VARCHAR(64) PRIMARY KEY,
			actor VARCHAR(128) NOT NULL,
			login_at_unix BIGINT NOT NULL,
			login_at_utc VARCHAR(19) NOT NULL,
			login_at_local VARCHAR(19) NOT NULL,
			end_at_unix BIGINT NULL,
			end_at_utc VARCHAR(19) NULL,
			end_at_local VARCHAR(19) NULL,
			end_reason VARCHAR(32) NOT NULL DEFAULT 'active',
			source_ip VARCHAR(64) NULL,
			user_agent TEXT NULL,
			event_count INT NOT NULL DEFAULT 0,
			created_at_unix BIGINT NOT NULL,
			created_at_utc VARCHAR(19) NOT NULL,
			created_at_local VARCHAR(19) NOT NULL
		)");
		$pdo->exec("CREATE TABLE IF NOT EXISTS audit_events (
			event_id VARCHAR(64) PRIMARY KEY,
			session_id VARCHAR(64) NOT NULL,
			session_phase VARCHAR(16) NOT NULL,
			channel VARCHAR(16) NOT NULL,
			module_name VARCHAR(128) NOT NULL,
			action VARCHAR(128) NOT NULL,
			outcome VARCHAR(32) NOT NULL,
			route VARCHAR(1024) NOT NULL,
			object_type VARCHAR(128) NOT NULL,
			object_id VARCHAR(256) NOT NULL,
			actor VARCHAR(128) NOT NULL,
			source_ip VARCHAR(64) NOT NULL,
			request_method VARCHAR(16) NOT NULL,
			request_uri VARCHAR(2048) NOT NULL,
			request_hash VARCHAR(128) NOT NULL,
			change_before LONGTEXT NULL,
			change_after LONGTEXT NULL,
			change_added LONGTEXT NULL,
			change_removed LONGTEXT NULL,
			change_changed LONGTEXT NULL,
			occurred_at_unix BIGINT NOT NULL,
			occurred_at_utc VARCHAR(19) NOT NULL,
			occurred_at_local VARCHAR(19) NOT NULL
		)");
	}

	private function createIndexes(PDO $pdo, $driver) {
		if ($driver === 'pgsql') {
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_session_id ON audit_events (session_id)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_occurred_at_unix ON audit_events (occurred_at_unix)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_actor ON audit_events (actor)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_module_name ON audit_events (module_name)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_session_phase ON audit_events (session_phase)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_channel ON audit_events (channel)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_events_dedup ON audit_events (session_id, module_name, action, object_id, occurred_at_unix)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_sessions_login_at_unix ON audit_sessions (login_at_unix)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_sessions_actor_end ON audit_sessions (actor, end_reason)");
			return;
		}
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_session_id ON audit_events (session_id)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_occurred_at_unix ON audit_events (occurred_at_unix)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_actor ON audit_events (actor)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_module_name ON audit_events (module_name)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_session_phase ON audit_events (session_phase)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_channel ON audit_events (channel)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_events_dedup ON audit_events (session_id, module_name, action, object_id, occurred_at_unix)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_sessions_login_at_unix ON audit_sessions (login_at_unix)");
		$this->safeExec($pdo, "CREATE INDEX idx_audit_sessions_actor_end ON audit_sessions (actor, end_reason)");
	}

	private function createImmutabilityTriggers(PDO $pdo, $driver) {
		if ($driver === 'pgsql') {
			$pdo->exec("CREATE OR REPLACE FUNCTION audit_deny_modifications() RETURNS trigger AS \$\$
				BEGIN
					RAISE EXCEPTION 'Audit tables are append-only';
				END;
			\$\$ LANGUAGE plpgsql");
			$pdo->exec("DROP TRIGGER IF EXISTS trg_audit_events_no_update ON audit_events");
			$pdo->exec("DROP TRIGGER IF EXISTS trg_audit_events_no_delete ON audit_events");
			$pdo->exec("DROP TRIGGER IF EXISTS trg_audit_sessions_no_delete ON audit_sessions");
			$pdo->exec("CREATE TRIGGER trg_audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW EXECUTE FUNCTION audit_deny_modifications()");
			$pdo->exec("CREATE TRIGGER trg_audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION audit_deny_modifications()");
			$pdo->exec("CREATE TRIGGER trg_audit_sessions_no_delete BEFORE DELETE ON audit_sessions FOR EACH ROW EXECUTE FUNCTION audit_deny_modifications()");
			return;
		}

		$this->safeExec($pdo, "DROP TRIGGER IF EXISTS trg_audit_events_no_update");
		$this->safeExec($pdo, "DROP TRIGGER IF EXISTS trg_audit_events_no_delete");
		$this->safeExec($pdo, "DROP TRIGGER IF EXISTS trg_audit_sessions_no_delete");
		$pdo->exec("CREATE TRIGGER trg_audit_events_no_update BEFORE UPDATE ON audit_events
			FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit tables are append-only'");
		$pdo->exec("CREATE TRIGGER trg_audit_events_no_delete BEFORE DELETE ON audit_events
			FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit tables are append-only'");
		$pdo->exec("CREATE TRIGGER trg_audit_sessions_no_delete BEFORE DELETE ON audit_sessions
			FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit tables are append-only'");
	}

	// ----------------------------------------------------------------
	// RBAC and rate limiting
	// ----------------------------------------------------------------

	private function hasAuditViewPermission() {
		if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
			return false;
		}
		return $_SESSION['AMP_user']->checkSection('auditcompliance');
	}

	private function hasAuditAdminPermission() {
		if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
			return false;
		}
		if (method_exists($_SESSION['AMP_user'], 'getSections')) {
			$sections = $_SESSION['AMP_user']->getSections();
			if (is_array($sections) && in_array('*', $sections, true)) {
				return true;
			}
		}
		return $_SESSION['AMP_user']->checkSection('framework')
			|| $_SESSION['AMP_user']->checkSection('advancedsettings');
	}

	private function checkExportRateLimit() {
		$key = 'auditcompliance_export_last';
		$minInterval = 10;
		$lastExport = (int) ($_SESSION[$key] ?? 0);
		$now = time();
		if (($now - $lastExport) < $minInterval) {
			return false;
		}
		$_SESSION[$key] = $now;
		return true;
	}

	// ----------------------------------------------------------------
	// Database connection
	// ----------------------------------------------------------------

	private function getAuditDb() {
		if ($this->auditDb instanceof PDO) {
			return $this->auditDb;
		}

		$dsn = trim((string) ($this->getConfigSafe('audit_db_dsn', '')));
		$user = (string) ($this->getConfigSafe('audit_db_user', ''));
		$password = (string) ($this->getConfigSafe('audit_db_password', ''));
		$requireTls = ($this->getConfigSafe('audit_db_require_tls', '1')) === '1';
		$requireExternal = ($this->getConfigSafe('audit_require_external_db', '1')) === '1';
		$odbcBackend = strtolower(trim((string) ($this->getConfigSafe('audit_db_odbc_backend', ''))));
		$storedType = strtolower(trim((string) ($this->getConfigSafe('audit_connection_type', ''))));
		$connectionType = in_array($storedType, array('mysql', 'pgsql', 'odbc'), true)
			? $storedType
			: $this->deriveConnectionTypeFromDsn($dsn, $odbcBackend);
		if ($connectionType === 'odbc') {
			$dsn = $this->normalizeOdbcDsnInput($dsn, $odbcBackend);
		}

		if ($dsn === '') {
			if ($requireExternal) {
				throw new \Exception('External audit DB is required but DSN is not configured');
			}
			$this->auditDb = $this->db;
			$this->debugLog('Audit DB local fallback mode is active', array());
			return $this->auditDb;
		}

		$this->validateSupportedDsnFormat($dsn);
		$this->validateDsnSecurity($dsn, $requireTls);
		$options = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		);
		$this->auditDb = new PDO($dsn, $user, $password, $options);

		if ($this->isOdbcDsn($dsn)) {
			$this->resolvedDriver = $this->resolveOdbcBackend($this->auditDb);
		}

		return $this->auditDb;
	}

	/**
	 * Detect whether the configured DSN uses the ODBC PDO driver.
	 */
	private function isOdbcDsn($dsn) {
		return strncasecmp($dsn, 'odbc:', 5) === 0;
	}

	/**
	 * Resolve the actual database engine behind an ODBC connection.
	 *
	 * When connecting via pdo_odbc the PDO driver name is always "odbc",
	 * but we need the real engine (mysql vs pgsql) to choose the correct
	 * SQL dialect for DDL, triggers and indexes.
	 *
	 * Resolution order:
	 *   1. Explicit config key  audit_db_odbc_backend  (mysql | pgsql).
	 *   2. Server version string heuristic via PDO::ATTR_SERVER_INFO /
	 *      PDO::ATTR_SERVER_VERSION and a test query.
	 *   3. Fall back to "mysql" as the safer default (FreePBX ecosystem).
	 */
	private function resolveOdbcBackend(PDO $pdo) {
		$explicit = strtolower(trim($this->getConfigSafe('audit_db_odbc_backend', '')));
		if ($explicit === 'mysql' || $explicit === 'mariadb') {
			return 'mysql';
		}
		if ($explicit === 'pgsql' || $explicit === 'postgresql' || $explicit === 'postgres') {
			return 'pgsql';
		}

		try {
			$version = @$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
			if ($version !== false && $version !== null) {
				$vLower = strtolower((string) $version);
				if (strpos($vLower, 'postgre') !== false) {
					return 'pgsql';
				}
				if (strpos($vLower, 'maria') !== false || strpos($vLower, 'mysql') !== false) {
					return 'mysql';
				}
			}
		} catch (\Throwable $e) {
			// Some ODBC drivers don't support ATTR_SERVER_VERSION
		}

		try {
			$sth = $pdo->query("SELECT version()");
			$row = $sth->fetchColumn();
			if ($row !== false) {
				$rLower = strtolower((string) $row);
				if (strpos($rLower, 'postgre') !== false) {
					return 'pgsql';
				}
				if (strpos($rLower, 'maria') !== false || strpos($rLower, 'mysql') !== false) {
					return 'mysql';
				}
			}
		} catch (\Throwable $e) {
			// version() may not be available
		}

		$this->debugLog('ODBC backend auto-detection inconclusive, defaulting to mysql', array());
		return 'mysql';
	}

	private function validateDsnSecurity($dsn, $requireTls) {
		if (!$requireTls) {
			return;
		}

		if ($this->isOdbcDsn($dsn)) {
			// ODBC: TLS is configured at the driver/DSN level in odbcinst.ini
			// or odbc.ini, not in the PDO DSN string. We cannot validate it
			// here. Log a reminder and trust the system ODBC configuration.
			return;
		}

		$dsnLower = strtolower($dsn);
		if (strpos($dsnLower, 'mysql:') === 0) {
			if (strpos($dsnLower, 'ssl') === false) {
				throw new \Exception('TLS is required for MySQL/MariaDB audit DB connections');
			}
			return;
		}
		if (strpos($dsnLower, 'pgsql:') === 0) {
			if (strpos($dsnLower, 'sslmode=') === false) {
				throw new \Exception('TLS is required for PostgreSQL audit DB connections');
			}
		}
	}

	// ----------------------------------------------------------------
	// Internal read helpers
	// ----------------------------------------------------------------

	private function getSessionEvents($sessionId) {
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$sql = "SELECT event_id, session_phase, channel, module_name, action, outcome, object_type, object_id,
			actor, source_ip, request_method, request_uri, request_hash, change_before, change_after,
			change_added, change_removed, change_changed, occurred_at_unix, occurred_at_utc, occurred_at_local
			FROM audit_events
			WHERE session_id = ?
			ORDER BY occurred_at_unix ASC";
		$sth = $pdo->prepare($sql);
		$sth->execute(array($sessionId));
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	private function getSessionEventsBatch(array $sessionIds) {
		if (empty($sessionIds)) {
			return array();
		}
		$this->ensureAuditSchema();
		$pdo = $this->getAuditDb();
		$placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
		$sql = "SELECT event_id, session_id, session_phase, channel, module_name, action, outcome, object_type, object_id,
			actor, source_ip, request_method, request_uri, request_hash, change_before, change_after,
			change_added, change_removed, change_changed, occurred_at_unix, occurred_at_utc, occurred_at_local
			FROM audit_events
			WHERE session_id IN ({$placeholders})
			ORDER BY occurred_at_unix ASC";
		$sth = $pdo->prepare($sql);
		$sth->execute($sessionIds);
		$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

		$grouped = array();
		foreach ($rows as $row) {
			$grouped[$row['session_id']][] = $row;
		}
		return $grouped;
	}

	// ----------------------------------------------------------------
	// Utilities
	// ----------------------------------------------------------------

	/**
	 * Return the logical driver name for SQL dialect selection.
	 *
	 * For native PDO drivers this returns "mysql" or "pgsql" directly.
	 * For ODBC connections it returns the resolved backend engine so that
	 * DDL, trigger syntax and index creation use the correct dialect.
	 */
	private function getDriverName(PDO $pdo) {
		try {
			$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		} catch (PDOException $e) {
			return '';
		}

		if ($driver === 'odbc' && $this->resolvedDriver !== null) {
			return $this->resolvedDriver;
		}

		return $driver;
	}

	private function getConfigSafe($key, $default = '') {
		$val = $this->getConfig($key);
		if ($val === null || $val === false || $val === '') {
			$globalVal = $this->getGlobalConfig($key);
			if ($globalVal === null || $globalVal === false || $globalVal === '') {
				return $default;
			}
			return (string) $globalVal;
		}
		return (string) $val;
	}

	private function getGlobalConfig($moduleConfigKey) {
		$settingKey = self::GLOBAL_SETTING_MAP[$moduleConfigKey] ?? null;
		if ($settingKey === null || !isset($this->FreePBX->Config) || !is_object($this->FreePBX->Config)) {
			return null;
		}
		try {
			if (method_exists($this->FreePBX->Config, 'conf_setting_exists') && !$this->FreePBX->Config->conf_setting_exists($settingKey)) {
				return null;
			}
			return $this->FreePBX->Config->get($settingKey);
		} catch (\Throwable $e) {
			$this->debugLog('Global config read failed', array(
				'setting' => $settingKey,
				'error' => $e->getMessage()
			));
			return null;
		}
	}

	private function parseSettingsInput(array $input, $persist = false) {
		$dsn = trim((string) ($input['audit_db_dsn'] ?? ''));
		$user = trim((string) ($input['audit_db_user'] ?? ''));
		$keepCurrentPassword = !empty($input['keep_current_password']);
		$providedPassword = (string) ($input['audit_db_password'] ?? '');
		$requireTls = !empty($input['audit_db_require_tls']) ? '1' : '0';
		$requireExternal = !empty($input['audit_require_external_db']) ? '1' : '0';
		$odbcBackend = strtolower(trim((string) ($input['audit_db_odbc_backend'] ?? '')));
		$connectionType = strtolower(trim((string) ($input['audit_connection_type'] ?? '')));
		if ($connectionType === '') {
			$connectionType = $this->deriveConnectionTypeFromDsn($dsn, $odbcBackend);
		}
		if (!in_array($connectionType, array('mysql', 'pgsql', 'odbc'), true)) {
			return array('status' => false, 'message' => 'Connection type must be mysql, pgsql, or odbc');
		}
		try {
			$dsn = $this->buildConnectionDsnFromInput($input, $connectionType, $requireExternal === '1', $requireTls === '1', $odbcBackend, $dsn);
		} catch (\Throwable $e) {
			return array('status' => false, 'message' => $this->truncate($e->getMessage(), 250));
		}
		$dsnScheme = $this->getDsnScheme($dsn);
		$idleTimeout = (int) ($input['audit_session_idle_timeout_seconds'] ?? self::SESSION_IDLE_TIMEOUT_SECONDS);

		if (!in_array($odbcBackend, array('', 'mysql', 'pgsql'), true)) {
			return array('status' => false, 'message' => 'ODBC backend must be empty, mysql, or pgsql');
		}
		if ($connectionType !== 'odbc') {
			$odbcBackend = '';
		}
		if ($idleTimeout < 60 || $idleTimeout > 86400) {
			return array('status' => false, 'message' => 'Idle timeout must be between 60 and 86400 seconds');
		}
		if ($connectionType === 'odbc' && $odbcBackend === '') {
			$odbcBackend = 'mysql';
		}
		if ($dsn === '' && $requireExternal === '1') {
			return array('status' => false, 'message' => 'External audit DB is required. Configure Audit DB DSN.');
		}
		if ($dsn !== '' && $connectionType === 'mysql' && $dsnScheme !== 'mysql') {
			return array('status' => false, 'message' => 'For Direct MySQL/MariaDB connection, DSN must start with mysql:');
		}
		if ($dsn !== '' && $connectionType === 'pgsql' && $dsnScheme !== 'pgsql') {
			return array('status' => false, 'message' => 'For Direct PostgreSQL connection, DSN must start with pgsql:');
		}
		if ($dsn !== '' && $connectionType === 'odbc' && $dsnScheme !== 'odbc') {
			return array('status' => false, 'message' => 'For ODBC connection, DSN must start with odbc: (or be an ODBC DSN name).');
		}

		try {
			$this->validateSupportedDsnFormat($dsn);
			$this->validateDsnSecurity($dsn, $requireTls === '1');
		} catch (\Throwable $e) {
			return array('status' => false, 'message' => $this->truncate($e->getMessage(), 250));
		}

		$password = $providedPassword;
		if ($persist && $keepCurrentPassword && $providedPassword === '') {
			$password = (string) $this->getConfigSafe('audit_db_password', '');
		}
		if (!$persist && $providedPassword === '') {
			$password = (string) $this->getConfigSafe('audit_db_password', '');
		}

		return array(
			'status' => true,
			'values' => array(
				'audit_connection_type' => $connectionType,
				'audit_db_dsn' => $this->truncate($dsn, 2048),
				'audit_db_user' => $this->truncate($user, 256),
				'audit_db_password' => $this->truncate((string) $password, 2048),
				'audit_db_require_tls' => $requireTls,
				'audit_db_odbc_backend' => $odbcBackend,
				'audit_require_external_db' => $requireExternal,
				'audit_session_idle_timeout_seconds' => (string) $idleTimeout
			)
		);
	}

	private function validateSupportedDsnFormat($dsn) {
		$dsn = trim((string) $dsn);
		if ($dsn === '') {
			return;
		}
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*:/', $dsn)) {
			throw new \Exception('Invalid DSN format. Use a PDO DSN like mysql:..., pgsql:..., or odbc:...');
		}
		$scheme = strtolower((string) strstr($dsn, ':', true));
		if (!in_array($scheme, array('mysql', 'pgsql', 'odbc'), true)) {
			throw new \Exception('Unsupported DSN scheme "' . $scheme . '". Supported schemes: mysql, pgsql, odbc.');
		}
	}

	private function normalizeOdbcDsnInput($dsn, $odbcBackend) {
		$dsn = trim((string) $dsn);
		if ($dsn === '') {
			return '';
		}
		if (strpos($dsn, ':') === false) {
			return 'odbc:' . $dsn;
		}
		return $dsn;
	}

	private function buildConnectionDsnFromInput(array $input, $connectionType, $requireExternal, $requireTls, $odbcBackend, $fallbackDsn = '') {
		$connectionType = strtolower(trim((string) $connectionType));
		if ($connectionType === 'odbc') {
			$odbcName = trim((string) ($input['audit_odbc_dsn_name'] ?? ''));
			if ($odbcName === '') {
				$odbcName = trim((string) $fallbackDsn);
			}
			return $this->normalizeOdbcDsnInput($odbcName, $odbcBackend);
		}

		$host = trim((string) ($input['audit_db_host'] ?? ''));
		$dbName = trim((string) ($input['audit_db_name'] ?? ''));
		$portInput = trim((string) ($input['audit_db_port'] ?? ''));
		$port = ctype_digit($portInput) ? (int) $portInput : 0;
		if ($port <= 0) {
			$port = $connectionType === 'pgsql' ? 5432 : 3306;
		}

		if ($host === '' || $dbName === '') {
			if ($requireExternal) {
				throw new \Exception('For direct database connection, Hostname and DB name are required.');
			}
			return '';
		}

		if ($connectionType === 'pgsql') {
			$dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName;
			if ($requireTls) {
				$dsn .= ';sslmode=require';
			}
			return $dsn;
		}

		$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';
		if ($requireTls) {
			$dsn .= ';ssl=true';
		}
		return $dsn;
	}

	private function deriveConnectionTypeFromDsn($dsn, $odbcBackend = '') {
		$dsn = trim((string) $dsn);
		$scheme = '';
		if (preg_match('/^([a-zA-Z][a-zA-Z0-9_]*)\:/', $dsn, $m)) {
			$scheme = strtolower((string) $m[1]);
		}
		if ($scheme === 'mysql') {
			return 'mysql';
		}
		if ($scheme === 'pgsql') {
			return 'pgsql';
		}
		if ($scheme === 'odbc') {
			return 'odbc';
		}
		if ($dsn !== '') {
			return 'odbc';
		}
		return 'mysql';
	}

	private function extractConnectionUiValues($dsn, $connectionType) {
		$out = array(
			'odbc_dsn_name' => '',
			'host' => '',
			'port' => '',
			'db_name' => ''
		);
		$connectionType = strtolower(trim((string) $connectionType));
		$dsn = trim((string) $dsn);
		if ($connectionType === 'odbc') {
			$out['odbc_dsn_name'] = preg_replace('/^odbc\:/i', '', $dsn);
			return $out;
		}

		$pairs = $this->parseDsnPairs($dsn);
		$out['host'] = (string) ($pairs['host'] ?? '');
		$out['port'] = (string) ($pairs['port'] ?? '');
		$out['db_name'] = (string) ($pairs['dbname'] ?? '');
		return $out;
	}

	private function parseDsnPairs($dsn) {
		$pairs = array();
		$dsn = trim((string) $dsn);
		$parts = explode(':', $dsn, 2);
		if (count($parts) !== 2) {
			return $pairs;
		}
		$tail = (string) $parts[1];
		foreach (explode(';', $tail) as $segment) {
			$segment = trim((string) $segment);
			if ($segment === '' || strpos($segment, '=') === false) {
				continue;
			}
			list($k, $v) = explode('=', $segment, 2);
			$key = strtolower(trim((string) $k));
			$pairs[$key] = trim((string) $v);
		}
		return $pairs;
	}

	private function getDsnScheme($dsn) {
		$dsn = trim((string) $dsn);
		if (preg_match('/^([a-zA-Z][a-zA-Z0-9_]*)\:/', $dsn, $m)) {
			return strtolower((string) $m[1]);
		}
		return '';
	}

	private function ensureGlobalSettingsDefined() {
		if (!isset($this->FreePBX->Config) || !is_object($this->FreePBX->Config) || !method_exists($this->FreePBX->Config, 'define_conf_setting')) {
			return;
		}

		$settings = array(
			'AUDITCOMPLIANCE_CONNECTION_TYPE' => array(
				'value' => 'mysql',
				'defaultval' => 'mysql',
				'type' => CONF_TYPE_TEXT,
				'name' => 'Audit Compliance Connection Type',
				'description' => 'Database connection type for audit storage: mysql, pgsql, or odbc.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => false,
				'hidden' => false
			),
			'AUDITCOMPLIANCE_DB_DSN' => array(
				'value' => '',
				'defaultval' => '',
				'type' => CONF_TYPE_TEXT,
				'name' => 'Audit Compliance DB DSN',
				'description' => 'PDO DSN used by the Audit Compliance module to write immutable audit events.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => true,
				'hidden' => false
			),
			'AUDITCOMPLIANCE_DB_USER' => array(
				'value' => '',
				'defaultval' => '',
				'type' => CONF_TYPE_TEXT,
				'name' => 'Audit Compliance DB Username',
				'description' => 'Database username used by Audit Compliance.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => true,
				'hidden' => false
			),
			'AUDITCOMPLIANCE_DB_PASSWORD' => array(
				'value' => '',
				'defaultval' => '',
				'type' => CONF_TYPE_TEXT,
				'name' => 'Audit Compliance DB Password',
				'description' => 'Database password used by Audit Compliance.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => true,
				'hidden' => true
			),
			'AUDITCOMPLIANCE_DB_REQUIRE_TLS' => array(
				'value' => true,
				'defaultval' => true,
				'type' => CONF_TYPE_BOOL,
				'name' => 'Audit Compliance Require TLS',
				'description' => 'Require encrypted DB transport for Audit Compliance remote database connections.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => false,
				'hidden' => false
			),
			'AUDITCOMPLIANCE_DB_ODBC_BACKEND' => array(
				'value' => '',
				'defaultval' => '',
				'type' => CONF_TYPE_TEXT,
				'name' => 'Audit Compliance ODBC Backend',
				'description' => 'Backend behind PDO ODBC DSN: mysql or pgsql.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => true,
				'hidden' => false
			),
			'AUDITCOMPLIANCE_REQUIRE_EXTERNAL_DB' => array(
				'value' => true,
				'defaultval' => true,
				'type' => CONF_TYPE_BOOL,
				'name' => 'Audit Compliance Require External DB',
				'description' => 'Require external audit database DSN and disable local fallback.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => false,
				'hidden' => false
			),
			'AUDITCOMPLIANCE_SESSION_IDLE_TIMEOUT_SECONDS' => array(
				'value' => (int) self::SESSION_IDLE_TIMEOUT_SECONDS,
				'defaultval' => (int) self::SESSION_IDLE_TIMEOUT_SECONDS,
				'type' => CONF_TYPE_INT,
				'options' => '60,86400',
				'name' => 'Audit Compliance Session Idle Timeout Seconds',
				'description' => 'Idle timeout used to close audit sessions when no explicit logout is observed.',
				'category' => 'Audit Compliance',
				'module' => 'auditcompliance',
				'emptyok' => false,
				'hidden' => false
			)
		);

		try {
			foreach ($settings as $key => $definition) {
				$this->FreePBX->Config->define_conf_setting($key, $definition, false);
			}
			if (method_exists($this->FreePBX->Config, 'commit_conf_settings')) {
				$this->FreePBX->Config->commit_conf_settings();
			}
		} catch (\Throwable $e) {
			$this->debugLog('Failed to define global settings', array('error' => $e->getMessage()));
		}
	}

	private function setGlobalConfigValues(array $moduleValues) {
		if (!isset($this->FreePBX->Config) || !is_object($this->FreePBX->Config) || !method_exists($this->FreePBX->Config, 'set_conf_values')) {
			return;
		}

		$updates = array();
		foreach ($moduleValues as $moduleKey => $value) {
			$settingKey = self::GLOBAL_SETTING_MAP[$moduleKey] ?? null;
			if ($settingKey === null) {
				continue;
			}
			if (method_exists($this->FreePBX->Config, 'conf_setting_exists') && !$this->FreePBX->Config->conf_setting_exists($settingKey)) {
				continue;
			}
			if ($moduleKey === 'audit_db_require_tls') {
				$updates[$settingKey] = ($value === '1');
				continue;
			}
			if ($moduleKey === 'audit_require_external_db') {
				$updates[$settingKey] = ($value === '1');
				continue;
			}
			if ($moduleKey === 'audit_session_idle_timeout_seconds') {
				$updates[$settingKey] = (int) $value;
				continue;
			}
			$updates[$settingKey] = (string) $value;
		}

		if (!empty($updates)) {
			$this->FreePBX->Config->set_conf_values($updates, true, true);
		}
	}

	private function restoreModuleConfigValues(array $moduleValues) {
		foreach ($moduleValues as $key => $value) {
			$this->setConfig($key, $value);
		}
	}

	private function clearStorageError() {
		$this->lastStorageErrorMessage = '';
	}

	private function setStorageError($message) {
		$this->lastStorageErrorMessage = $this->truncate((string) $message, 250);
	}

	public function getLastStorageErrorMessage() {
		return (string) $this->lastStorageErrorMessage;
	}

	private function setDefaultConfigIfMissing($key, $value) {
		$current = $this->getConfig($key);
		if ($current === null || $current === false || $current === '') {
			$this->setConfig($key, $value);
		}
	}

	private function normalizeAction($action, $method) {
		$actionLower = strtolower(trim((string) $action));
		if ($actionLower !== '') {
			return $actionLower;
		}
		return strtolower((string) $method) === 'post' ? 'update' : 'view';
	}

	private function detectObjectType($display) {
		return strtolower((string) $display);
	}

	private function detectObjectId() {
		$candidates = array(
			'id', 'extdisplay', 'account', 'trunkid', 'user_id',
			'itemid', 'group_id', 'entry_id', 'queue', 'grpnum',
			'ext', 'extension', 'cidnum', 'backup_id', 'tcid',
			'tgid', 'confno', 'pagegrp', 'pagenbr', 'rg', 'ivr_id',
			'faxid', 'calendar_id', 'pinsets_id', 'scheme',
			'announcement_id', 'callrecording_id', 'channel',
			'orig_account', 'trunknum'
		);
		foreach ($candidates as $key) {
			if (!empty($_REQUEST[$key])) {
				return (string) $_REQUEST[$key];
			}
		}
		return '';
	}

	private function getActor() {
		if (isset($_SESSION['AMP_user']) && is_object($_SESSION['AMP_user']) && isset($_SESSION['AMP_user']->username)) {
			return (string) $_SESSION['AMP_user']->username;
		}
		return 'unknown';
	}

	private function getRemoteIp() {
		return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
	}

	private function hashRequest(array $request) {
		return hash('sha256', $this->safeJsonEncode($this->redactSensitiveData($request)));
	}

	private function safeJsonEncode($value) {
		$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			$this->debugLog('JSON encode failed', array('error' => json_last_error_msg()));
			return '{}';
		}
		return $json;
	}

	private function safeExec(PDO $pdo, $sql) {
		try {
			$pdo->exec($sql);
		} catch (PDOException $e) {
			$msg = strtolower((string) $e->getMessage());
			$ignorable = (strpos($msg, 'already exists') !== false) ||
				(strpos($msg, 'duplicate key name') !== false) ||
				(strpos($msg, 'duplicate') !== false) ||
				(strpos($msg, 'does not exist') !== false);
			if (!$ignorable) {
				throw $e;
			}
		}
	}

	private function truncate($value, $maxLen) {
		$value = (string) $value;
		if (mb_strlen($value, 'UTF-8') <= $maxLen) {
			return $value;
		}
		return mb_substr($value, 0, $maxLen, 'UTF-8');
	}

	private function newSessionId() {
		return 'sess_' . bin2hex(random_bytes(16));
	}

	private function newEventId() {
		return 'evt_' . bin2hex(random_bytes(16));
	}

	private function getChisinauTimestamp() {
		$dt = new \DateTime('now', new \DateTimeZone('Europe/Chisinau'));
		return $dt->format('d-m-Y H:i:s');
	}

	private function debugLog($message, array $context = array()) {
		$prefix = sprintf('[%s] ', $this->getChisinauTimestamp());
		$payload = $prefix . (string) $message . ' ' . $this->safeJsonEncode($context);
		try {
			if (isset($this->FreePBX->Logger) && is_object($this->FreePBX->Logger)) {
				if (method_exists($this->FreePBX->Logger, 'channelLogWrite')) {
					$this->FreePBX->Logger->channelLogWrite('auditcompliance', $payload, array(), 'DEBUG');
					return;
				}
				if (method_exists($this->FreePBX->Logger, 'logWrite')) {
					$this->FreePBX->Logger->logWrite($payload, array(), 'DEBUG');
					return;
				}
				if (method_exists($this->FreePBX->Logger, 'log')) {
					$this->FreePBX->Logger->log('DEBUG', $payload);
					return;
				}
			}
		} catch (\Throwable $e) {
			// Logging must never break audit execution flow.
		}
		@error_log($payload);
	}
}
