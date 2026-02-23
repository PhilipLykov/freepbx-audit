<?php
if (!defined('FREEPBX_IS_AUTH')) {
	die('No direct script access allowed');
}

if (!isset($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user']) || !$_SESSION['AMP_user']->checkSection('auditcompliance')) {
	echo '<div class="alert alert-danger">' . _('Access denied. You do not have permission to view the Audit Compliance module.') . '</div>';
	return;
}

$request = $_REQUEST;
$view = $request['view'] ?? 'dashboard';
$actorFilter = $request['actor'] ?? '';
$csrfSessionKey = 'auditcompliance_settings_csrf';

if (empty($_SESSION[$csrfSessionKey])) {
	$_SESSION[$csrfSessionKey] = bin2hex(random_bytes(16));
}
$settingsNotice = null;

if ($view === 'settings' && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
	if (!FreePBX::Auditcompliance()->canManageSettings()) {
		$settingsNotice = array(
			'status' => false,
			'message' => _('Access denied. Settings management requires administrator privileges.')
		);
	} else {
	$submittedCsrf = (string) ($_POST['auditcompliance_csrf'] ?? '');
	$sessionCsrf = (string) ($_SESSION[$csrfSessionKey] ?? '');
	if ($submittedCsrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $submittedCsrf)) {
		$settingsNotice = array(
			'status' => false,
			'message' => _('Security token validation failed. Refresh the page and try again.')
		);
	} else {
		$action = (string) ($_POST['settings_action'] ?? 'save');
		if ($action === 'test') {
			$settingsNotice = FreePBX::Auditcompliance()->testSettingsConnectionFromUi($_POST);
		} else {
			$settingsNotice = FreePBX::Auditcompliance()->saveSettingsFromUi($_POST);
		}
	}
	}
}

switch ($view) {
	case 'timeline':
		$timeline = FreePBX::Auditcompliance()->getRecentSessionTimeline(25, 0, $actorFilter);
		$authFailures = FreePBX::Auditcompliance()->getRecentAuthFailures(25, 0, $actorFilter);
		$timelineReadError = FreePBX::Auditcompliance()->getLastStorageErrorMessage();
		echo load_view(__DIR__ . '/views/grid.php', array(
			'request' => $request,
			'timeline' => $timeline,
			'authFailures' => $authFailures,
			'actorFilter' => $actorFilter,
			'timelineReadError' => $timelineReadError
		));
		break;
	case 'discovery':
		$discoveryData = FreePBX::Auditcompliance()->discoverModuleSurfaces();
		echo load_view(__DIR__ . '/views/discovery.php', array(
			'request' => array_merge($request, array('discoveryData' => $discoveryData))
		));
		break;
	case 'search':
		echo load_view(__DIR__ . '/views/search.php', array(
			'request' => $request
		));
		break;
	case 'settings':
		if (!FreePBX::Auditcompliance()->canManageSettings()) {
			echo '<div class="alert alert-danger">' . _('Access denied. Settings management requires administrator privileges.') . '</div>';
			break;
		}
		$settingsForForm = FreePBX::Auditcompliance()->getSettingsSnapshot();
		if ($settingsNotice !== null && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
			$settingsForForm = array_merge($settingsForForm, array(
				'audit_connection_type' => (string) ($_POST['audit_connection_type'] ?? $settingsForForm['audit_connection_type']),
				'audit_db_host' => (string) ($_POST['audit_db_host'] ?? ''),
				'audit_db_port' => (string) ($_POST['audit_db_port'] ?? ''),
				'audit_db_name' => (string) ($_POST['audit_db_name'] ?? ''),
				'audit_odbc_dsn_name' => (string) ($_POST['audit_odbc_dsn_name'] ?? ''),
				'audit_db_user' => (string) ($_POST['audit_db_user'] ?? ''),
				'audit_db_require_tls' => !empty($_POST['audit_db_require_tls']) ? '1' : '0',
				'audit_db_odbc_backend' => (string) ($_POST['audit_db_odbc_backend'] ?? ''),
				'audit_require_external_db' => !empty($_POST['audit_require_external_db']) ? '1' : '0',
				'audit_session_idle_timeout_seconds' => (string) ($_POST['audit_session_idle_timeout_seconds'] ?? '1800')
			));
		}
		echo load_view(__DIR__ . '/views/settings.php', array(
			'request' => $request,
			'settings' => $settingsForForm,
			'storageStatus' => FreePBX::Auditcompliance()->getAuditStorageStatus(),
			'settingsNotice' => $settingsNotice,
			'csrfToken' => (string) ($_SESSION[$csrfSessionKey] ?? '')
		));
		break;
	case 'dashboard':
	default:
		echo load_view(__DIR__ . '/views/dashboard.php', array(
			'request' => $request
		));
		break;
}
