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

switch ($view) {
	case 'timeline':
		$timeline = FreePBX::Auditcompliance()->getRecentSessionTimeline(25, 0, $actorFilter);
		$authFailures = FreePBX::Auditcompliance()->getRecentAuthFailures(25, 0, $actorFilter);
		echo load_view(__DIR__ . '/views/grid.php', array(
			'request' => $request,
			'timeline' => $timeline,
			'authFailures' => $authFailures,
			'actorFilter' => $actorFilter
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
	case 'dashboard':
	default:
		echo load_view(__DIR__ . '/views/dashboard.php', array(
			'request' => $request
		));
		break;
}
