#!/usr/bin/env php
<?php
/**
 * pbxACT / FreePBX Module Surface Discovery Tool
 *
 * Run this script ON the target FreePBX/pbxACT server to enumerate all
 * installed modules and their communication surfaces (GUI, AJAX, API, hooks).
 *
 * Usage:
 *   php discover-pbxact-surfaces.php [--json] [--csv]
 *
 * Output: module surface inventory for audit coverage mapping.
 *
 * Requirements:
 *   - Must be run on the FreePBX server (needs access to /var/www/html/admin/modules)
 *   - PHP CLI with file system access
 */

$outputJson = in_array('--json', $argv);
$outputCsv = in_array('--csv', $argv);

$modulesDir = '/var/www/html/admin/modules';
if (!is_dir($modulesDir)) {
	$modulesDir = '/var/lib/asterisk/www/html/admin/modules';
}
if (!is_dir($modulesDir)) {
	fwrite(STDERR, "ERROR: Cannot find FreePBX modules directory.\n");
	fwrite(STDERR, "Tried: /var/www/html/admin/modules, /var/lib/asterisk/www/html/admin/modules\n");
	exit(1);
}

$sensitiveReadPages = array(
	'cdr', 'recordings', 'userman', 'certman', 'voicemail', 'conferences',
	'contactmanager', 'queues', 'manager', 'sipsettings', 'logfiles',
	'arimanager', 'filestore', 'calendar', 'fax', 'pinsets', 'superfecta',
	'xmpp', 'phonebook', 'blacklist', 'cel'
);
$hookedModules = array(
	'core', 'userman', 'backup', 'certman', 'voicemail',
	'timeconditions', 'contactmanager', 'ucp', 'calendar', 'bulkhandler'
);

$results = array();
$dirs = scandir($modulesDir);

foreach ($dirs as $entry) {
	if ($entry === '.' || $entry === '..') {
		continue;
	}
	$modPath = $modulesDir . '/' . $entry;
	if (!is_dir($modPath)) {
		continue;
	}

	$className = ucfirst($entry);
	$classFile = $modPath . '/' . $className . '.class.php';
	$moduleXml = $modPath . '/module.xml';

	$mod = array(
		'rawname' => $entry,
		'version' => '',
		'commercial' => false,
		'gui_pages' => 0,
		'has_ajax_handler' => false,
		'has_ajax_request' => false,
		'ajax_commands' => array(),
		'has_api_rest' => is_dir($modPath . '/Api/Rest'),
		'has_api_gql' => is_dir($modPath . '/Api/Gql'),
		'has_process_hooks' => false,
		'has_hooks_xml' => false,
		'hooks_targets' => array(),
		'has_config_page_inits' => false
	);

	if (is_file($moduleXml)) {
		$xml = @file_get_contents($moduleXml);
		if ($xml !== false) {
			if (preg_match('/<version>([^<]+)</', $xml, $m)) {
				$mod['version'] = trim($m[1]);
			}
			$mod['commercial'] = (strpos($xml, '<license>Commercial') !== false)
				|| (strpos($xml, '<commercial>') !== false);
			if (preg_match_all('/<(\w+)\s[^>]*callingMethod="([^"]+)"/', $xml, $hm, PREG_SET_ORDER)) {
				foreach ($hm as $h) {
					$mod['hooks_targets'][] = $h[2];
				}
			}
			$mod['has_hooks_xml'] = (strpos($xml, '<hooks>') !== false);
			$itemCount = substr_count($xml, '<menuitems>');
			$mod['gui_pages'] = max($itemCount, 0);
		}
	}

	if (is_file($classFile)) {
		$content = @file_get_contents($classFile);
		if ($content !== false) {
			$mod['has_ajax_handler'] = (strpos($content, 'function ajaxHandler') !== false);
			$mod['has_ajax_request'] = (strpos($content, 'function ajaxRequest') !== false);
			$mod['has_process_hooks'] = (strpos($content, 'processHooks') !== false);
			$mod['has_config_page_inits'] = (strpos($content, 'myConfigPageInits') !== false);

			if (preg_match_all("/case\s+'([^']+)'/", $content, $cm)) {
				$mod['ajax_commands'] = array_values(array_unique($cm[1]));
			}
		}
	}

	$mod['has_sensitive_read'] = in_array(strtolower($entry), $sensitiveReadPages, true);
	$mod['has_audit_hook'] = in_array(strtolower($entry), $hookedModules, true);

	$hasAjax = $mod['has_ajax_handler'] || $mod['has_api_rest'] || $mod['has_api_gql'];
	if ($mod['has_audit_hook']) {
		$coverage = 'full';
	} elseif ($mod['has_sensitive_read'] && $hasAjax) {
		$coverage = 'gui_ajax_read';
	} elseif ($mod['has_sensitive_read']) {
		$coverage = 'gui_read';
	} elseif ($hasAjax) {
		$coverage = 'gui_ajax';
	} else {
		$coverage = 'gui_only';
	}
	$mod['coverage'] = $coverage;

	$results[] = $mod;
}

usort($results, function ($a, $b) {
	return strcmp($a['rawname'], $b['rawname']);
});

$dt = new DateTime('now', new DateTimeZone('Europe/Chisinau'));
$timestamp = $dt->format('d-m-Y H:i:s');

if ($outputJson) {
	echo json_encode(array(
		'timestamp' => $timestamp,
		'modules_dir' => $modulesDir,
		'total' => count($results),
		'modules' => $results
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	exit(0);
}

if ($outputCsv) {
	$header = array('rawname', 'version', 'commercial', 'gui_pages', 'has_ajax_handler', 'has_api_rest', 'has_api_gql', 'has_process_hooks', 'has_hooks_xml', 'has_audit_hook', 'has_sensitive_read', 'coverage', 'ajax_commands');
	echo implode(',', $header) . "\n";
	foreach ($results as $r) {
		echo implode(',', array(
			$r['rawname'],
			$r['version'],
			$r['commercial'] ? 'yes' : 'no',
			$r['gui_pages'],
			$r['has_ajax_handler'] ? 'yes' : 'no',
			$r['has_api_rest'] ? 'yes' : 'no',
			$r['has_api_gql'] ? 'yes' : 'no',
			$r['has_process_hooks'] ? 'yes' : 'no',
			$r['has_hooks_xml'] ? 'yes' : 'no',
			$r['has_audit_hook'] ? 'yes' : 'no',
			$r['has_sensitive_read'] ? 'yes' : 'no',
			$r['coverage'],
			'"' . implode(';', $r['ajax_commands']) . '"'
		)) . "\n";
	}
	exit(0);
}

fprintf(STDOUT, "=== pbxACT / FreePBX Module Surface Discovery ===\n");
fprintf(STDOUT, "Timestamp: %s (Europe/Chisinau)\n", $timestamp);
fprintf(STDOUT, "Modules dir: %s\n", $modulesDir);
fprintf(STDOUT, "Total modules: %d\n\n", count($results));

$countAjax = 0;
$countApi = 0;
$countHooks = 0;
$countCommercial = 0;

foreach ($results as $r) {
	if ($r['has_ajax_handler']) { $countAjax++; }
	if ($r['has_api_rest'] || $r['has_api_gql']) { $countApi++; }
	if ($r['has_process_hooks']) { $countHooks++; }
	if ($r['commercial']) { $countCommercial++; }
}

fprintf(STDOUT, "Summary:\n");
fprintf(STDOUT, "  With ajaxHandler: %d\n", $countAjax);
fprintf(STDOUT, "  With API paths:   %d\n", $countApi);
fprintf(STDOUT, "  With processHooks: %d\n", $countHooks);
fprintf(STDOUT, "  Commercial:       %d\n\n", $countCommercial);

fprintf(STDOUT, "%-25s %-10s %-5s %-5s %-5s %-5s %-5s %-5s %-14s\n",
	'MODULE', 'VERSION', 'AJAX', 'API', 'HOOKS', 'HOOK', 'READ', 'COMM', 'COVERAGE');
fprintf(STDOUT, "%s\n", str_repeat('-', 95));

foreach ($results as $r) {
	fprintf(STDOUT, "%-25s %-10s %-5s %-5s %-5s %-5s %-5s %-5s %-14s\n",
		$r['rawname'],
		substr($r['version'], 0, 10),
		$r['has_ajax_handler'] ? 'YES' : '-',
		($r['has_api_rest'] || $r['has_api_gql']) ? 'YES' : '-',
		$r['has_process_hooks'] ? 'YES' : '-',
		$r['has_audit_hook'] ? 'YES' : '-',
		$r['has_sensitive_read'] ? 'YES' : '-',
		$r['commercial'] ? 'YES' : '-',
		$r['coverage']
	);
}

fprintf(STDOUT, "\nRun with --json for machine-readable output, --csv for spreadsheet import.\n");
