<?php
/**
 * Audit Compliance — early capture bootstrap.
 *
 * FreePBX loads every active module's functions.inc.php from bootstrap.php
 * BEFORE GuiHooks::doConfigPageInits() runs. This guarantees the code below
 * executes before any module's doConfigPageInit — including page-owner modules
 * that call redirect_standard() / exit() (e.g. miscdests) and therefore
 * prevent the Auditcompliance BMO class from ever being instantiated.
 *
 * Two things happen here:
 *   1. $_REQUEST and $_SERVER are snapshotted into $GLOBALS before any module
 *      can modify them.
 *   2. A register_shutdown_function is registered that fires AFTER exit(),
 *      lazy-loads the Auditcompliance BMO class, and captures any
 *      state-changing event that the normal doConfigPageInit path missed.
 */

if (!defined('AUDITCOMPLIANCE_EARLY_CAPTURE_REGISTERED')) {
	define('AUDITCOMPLIANCE_EARLY_CAPTURE_REGISTERED', true);

	$GLOBALS['_AUDITCOMPLIANCE_EARLY_REQUEST'] = $_REQUEST;
	$GLOBALS['_AUDITCOMPLIANCE_EARLY_SERVER'] = array(
		'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? '',
		'REQUEST_URI'    => $_SERVER['REQUEST_URI'] ?? '',
		'REMOTE_ADDR'    => $_SERVER['REMOTE_ADDR'] ?? '',
		'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
	);

	register_shutdown_function(function () {
		if (!empty($GLOBALS['_AUDITCOMPLIANCE_EVENT_CAPTURED'])) {
			return;
		}

		$snapshot = $GLOBALS['_AUDITCOMPLIANCE_EARLY_REQUEST'] ?? null;
		if (!is_array($snapshot)) {
			return;
		}

		if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
			return;
		}

		$server = $GLOBALS['_AUDITCOMPLIANCE_EARLY_SERVER'] ?? array();
		$method = strtoupper((string) ($server['REQUEST_METHOD'] ?? ''));
		$action = strtolower(trim((string) ($snapshot['action'] ?? '')));
		$certAction = strtolower(trim((string) ($snapshot['certaction'] ?? '')));
		if ($certAction !== '') {
			$action = $certAction;
		}
		$display = trim((string) ($snapshot['display'] ?? ''));

		if ($display === '' || $display === 'auditcompliance') {
			return;
		}

		$isStateChanging = false;
		if ($method === 'POST') {
			$handler = strtolower(trim((string) ($snapshot['handler'] ?? '')));
			if ($handler === 'reload' || $handler === 'retrieve_conf') {
				$isStateChanging = false;
			} else {
				$isStateChanging = true;
			}
		}
		if (!$isStateChanging && $method === 'GET' && $action !== '') {
			$getPrefixes = array('del', 'delete', 'remove', 'enable', 'disable', 'toggle', 'reset', 'copy', 'duplicate', 'set', 'assign', 'clear', 'flush', 'purge');
			foreach ($getPrefixes as $prefix) {
				if (strpos($action, $prefix) === 0) {
					$isStateChanging = true;
					break;
				}
			}
		}

		if (!$isStateChanging) {
			return;
		}

		try {
			$fpbx = \FreePBX::create();
			$module = $fpbx->Auditcompliance;
			if (is_object($module) && method_exists($module, 'handleEarlyShutdownCapture')) {
				$effectiveAction = ($action !== '') ? $action : 'update';
				$module->handleEarlyShutdownCapture($display, $effectiveAction, $snapshot, $server);
			}
		} catch (\Throwable $e) {
			// Silent in shutdown context
		}
	});
}
