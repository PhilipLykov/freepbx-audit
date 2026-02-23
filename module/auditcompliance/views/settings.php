<?php
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$settings = is_array($settings ?? null) ? $settings : array();
$storageStatus = is_array($storageStatus ?? null) ? $storageStatus : null;
$settingsNotice = is_array($settingsNotice ?? null) ? $settingsNotice : null;
$csrfToken = (string) ($csrfToken ?? '');
$showStorageStatus = $storageStatus !== null;
if ($showStorageStatus && $settingsNotice) {
	$showStorageStatus = empty($settingsNotice['status']);
}
if ($showStorageStatus && $settingsNotice && isset($settingsNotice['message'], $storageStatus['message'])) {
	$showStorageStatus = ((string) $settingsNotice['message']) !== ((string) $storageStatus['message']);
}
?>
<style>
.audit-tabs { margin-bottom: 20px; }
.audit-settings-panel {
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	padding: 0;
	max-width: 960px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
	overflow: hidden;
}
.audit-settings-section {
	padding: 20px 24px 16px;
	border-bottom: 1px solid #f0f0f0;
}
.audit-settings-section:last-child { border-bottom: none; }
.audit-section-title {
	font-size: 13px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.4px;
	color: #495057;
	margin: 0 0 14px;
	display: flex;
	align-items: center;
	gap: 8px;
}
.audit-section-title i { color: #6c757d; font-size: 14px; }
.audit-settings-actions {
	padding: 16px 24px;
	background: #f8f9fa;
	display: flex;
	gap: 8px;
	align-items: center;
	flex-wrap: wrap;
	border-top: 1px solid #f0f0f0;
}
.audit-settings-note {
	font-size: 12px;
	color: #6c757d;
	margin-left: auto;
}
.audit-direct-only,
.audit-odbc-only { display: none; }
.audit-notice { margin-bottom: 16px; border-radius: 6px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 10px; }
.audit-notice i.notice-icon { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
.audit-notice-success { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; }
.audit-notice-warning { background: #fff8e1; border: 1px solid #ffe082; color: #e65100; }
.audit-notice-danger { background: #ffebee; border: 1px solid #ef9a9a; color: #c62828; }
.audit-notice-info { background: #e3f2fd; border: 1px solid #90caf9; color: #1565c0; }
.audit-conn-type-hint {
	font-size: 12px;
	color: #6c757d;
	margin-top: 6px;
	padding: 6px 10px;
	background: #f8f9fa;
	border-radius: 4px;
	border-left: 3px solid #dee2e6;
}
</style>

<div class="container-fluid">
	<h1><?php echo _('Audit Compliance'); ?></h1>

	<ul class="nav nav-tabs audit-tabs">
		<li><a href="?display=auditcompliance&view=dashboard"><i class="fa fa-tachometer"></i> <?php echo _('Dashboard'); ?></a></li>
		<li><a href="?display=auditcompliance&view=search"><i class="fa fa-search"></i> <?php echo _('Search'); ?></a></li>
		<li><a href="?display=auditcompliance&view=timeline"><i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?></a></li>
		<li><a href="?display=auditcompliance&view=discovery"><i class="fa fa-puzzle-piece"></i> <?php echo _('Module Discovery'); ?></a></li>
		<li class="active"><a href="?display=auditcompliance&view=settings"><i class="fa fa-cogs"></i> <?php echo _('Settings'); ?></a></li>
	</ul>

	<?php if ($settingsNotice): ?>
		<?php
			$noticeClass = 'audit-notice-danger';
			$noticeIcon = 'fa-times-circle';
			if (!empty($settingsNotice['status'])) {
				if (!empty($settingsNotice['warning'])) {
					$noticeClass = 'audit-notice-warning';
					$noticeIcon = 'fa-exclamation-triangle';
				} else {
					$noticeClass = 'audit-notice-success';
					$noticeIcon = 'fa-check-circle';
				}
			}
		?>
		<div class="audit-notice <?php echo $noticeClass; ?>">
			<i class="fa <?php echo $noticeIcon; ?> notice-icon"></i>
			<span><?php echo $esc($settingsNotice['message'] ?? ''); ?></span>
		</div>
	<?php endif; ?>
	<?php if ($showStorageStatus): ?>
		<div class="audit-notice <?php echo !empty($storageStatus['status']) ? 'audit-notice-info' : 'audit-notice-warning'; ?>">
			<i class="fa <?php echo !empty($storageStatus['status']) ? 'fa-database' : 'fa-exclamation-triangle'; ?> notice-icon"></i>
			<span>
				<?php echo $esc($storageStatus['message'] ?? ''); ?>
				<?php if (!empty($storageStatus['driver'])): ?>
					<br><small><?php echo $esc(_('Driver') . ': ' . (string) $storageStatus['driver']); ?></small>
				<?php endif; ?>
			</span>
		</div>
	<?php endif; ?>

	<div class="audit-settings-panel">
		<form method="post" action="?display=auditcompliance&view=settings" autocomplete="off">
			<input type="hidden" name="auditcompliance_csrf" value="<?php echo $esc($csrfToken); ?>">
			<input type="hidden" name="keep_current_password" value="1">

			<div class="audit-settings-section">
				<h4 class="audit-section-title"><i class="fa fa-plug"></i> <?php echo _('Database Connection'); ?></h4>

				<div class="form-group">
					<label for="audit_connection_type"><?php echo _('Connection Type'); ?></label>
					<select class="form-control" id="audit_connection_type" name="audit_connection_type">
						<?php $connectionType = (string) ($settings['audit_connection_type'] ?? 'mysql'); ?>
						<option value="mysql" <?php echo $connectionType === 'mysql' ? 'selected' : ''; ?>><?php echo _('Direct MySQL / MariaDB'); ?></option>
						<option value="pgsql" <?php echo $connectionType === 'pgsql' ? 'selected' : ''; ?>><?php echo _('Direct PostgreSQL'); ?></option>
						<option value="odbc" <?php echo $connectionType === 'odbc' ? 'selected' : ''; ?>><?php echo _('ODBC'); ?></option>
					</select>
				</div>

				<div id="audit-conn-hint" class="audit-conn-type-hint"></div>

				<div id="audit-direct-fields" class="audit-direct-only" style="margin-top: 12px;">
					<div class="row">
						<div class="col-sm-5">
							<div class="form-group">
								<label for="audit_db_host"><?php echo _('Hostname'); ?></label>
								<input type="text" class="form-control" id="audit_db_host" name="audit_db_host" value="<?php echo $esc($settings['audit_db_host'] ?? ''); ?>" placeholder="db.example.com">
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-group">
								<label for="audit_db_port"><?php echo _('Port'); ?></label>
								<input type="number" class="form-control" id="audit_db_port" name="audit_db_port" value="<?php echo $esc($settings['audit_db_port'] ?? ''); ?>" min="1" max="65535" step="1">
							</div>
						</div>
						<div class="col-sm-4">
							<div class="form-group">
								<label for="audit_db_name"><?php echo _('Database Name'); ?></label>
								<input type="text" class="form-control" id="audit_db_name" name="audit_db_name" value="<?php echo $esc($settings['audit_db_name'] ?? ''); ?>" placeholder="auditcompliance">
							</div>
						</div>
					</div>
				</div>

				<div id="audit-odbc-fields" class="audit-odbc-only" style="margin-top: 12px;">
					<div class="form-group">
						<label for="audit_odbc_dsn_name"><?php echo _('ODBC DSN Name'); ?></label>
						<input
							type="text"
							class="form-control"
							id="audit_odbc_dsn_name"
							name="audit_odbc_dsn_name"
							value="<?php echo $esc($settings['audit_odbc_dsn_name'] ?? ''); ?>"
							placeholder="AuditDB"
						>
						<p class="help-block"><?php echo _('System DSN name configured in /etc/odbc.ini'); ?></p>
					</div>
				</div>
			</div>

			<div class="audit-settings-section">
				<h4 class="audit-section-title"><i class="fa fa-key"></i> <?php echo _('Authentication'); ?></h4>
				<div class="row">
					<div class="col-sm-6">
						<div class="form-group">
							<label for="audit_db_user"><?php echo _('Username'); ?></label>
							<input type="text" class="form-control" id="audit_db_user" name="audit_db_user" value="<?php echo $esc($settings['audit_db_user'] ?? ''); ?>" placeholder="audit_writer">
						</div>
					</div>
					<div class="col-sm-6">
						<div class="form-group">
							<label for="audit_db_password"><?php echo _('Password'); ?></label>
							<input type="password" class="form-control" id="audit_db_password" name="audit_db_password" value="">
							<p class="help-block">
								<?php
									$hasPassword = !empty($settings['audit_db_password_set']);
									echo $hasPassword
										? '<i class="fa fa-check-circle" style="color:#27ae60;margin-right:3px;"></i>' . _('Password is set. Leave blank to keep current.')
										: '<i class="fa fa-info-circle" style="color:#adb5bd;margin-right:3px;"></i>' . _('No password configured.');
								?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div class="audit-settings-section">
				<h4 class="audit-section-title"><i class="fa fa-shield"></i> <?php echo _('Security & Behavior'); ?></h4>
				<div class="row">
					<div class="col-sm-4">
						<div class="form-group">
							<label><?php echo _('External DB Only'); ?></label>
							<div class="checkbox" style="margin-top:0;">
								<label>
									<input type="checkbox" id="audit_require_external_db" name="audit_require_external_db" value="1" <?php echo (($settings['audit_require_external_db'] ?? '1') === '1') ? 'checked' : ''; ?>>
									<?php echo _('Require remote audit database'); ?>
								</label>
							</div>
							<p class="help-block"><?php echo _('When disabled, falls back to the local FreePBX DB if remote is unavailable.'); ?></p>
						</div>
					</div>
					<div class="col-sm-4">
						<div class="form-group">
							<label><?php echo _('Encryption'); ?></label>
							<div class="checkbox" style="margin-top:0;">
								<label>
									<input type="checkbox" id="audit_db_require_tls" name="audit_db_require_tls" value="1" <?php echo (($settings['audit_db_require_tls'] ?? '1') === '1') ? 'checked' : ''; ?>>
									<?php echo _('Require TLS for DB connection'); ?>
								</label>
							</div>
							<p class="help-block"><?php echo _('Enforces encrypted transport (recommended for production).'); ?></p>
						</div>
					</div>
					<div class="col-sm-4">
						<div class="form-group">
							<label for="audit_session_idle_timeout_seconds"><?php echo _('Session Idle Timeout'); ?></label>
							<div class="input-group">
								<input
									type="number"
									min="60"
									max="86400"
									step="1"
									class="form-control"
									id="audit_session_idle_timeout_seconds"
									name="audit_session_idle_timeout_seconds"
									value="<?php echo $esc($settings['audit_session_idle_timeout_seconds'] ?? '1800'); ?>"
								>
								<span class="input-group-addon"><?php echo _('seconds'); ?></span>
							</div>
							<p class="help-block"><?php echo _('Auto-close sessions after this idle period (60-86400).'); ?></p>
						</div>
					</div>
				</div>

				<div class="audit-odbc-only" id="audit-odbc-backend-wrap" style="margin-top: 4px;">
					<div class="row">
						<div class="col-sm-4">
							<div class="form-group">
								<label for="audit_db_odbc_backend"><?php echo _('ODBC Target Engine'); ?></label>
								<select class="form-control" id="audit_db_odbc_backend" name="audit_db_odbc_backend">
									<?php $odbcBackend = (string) ($settings['audit_db_odbc_backend'] ?? ''); ?>
									<option value="mysql" <?php echo $odbcBackend === 'mysql' ? 'selected' : ''; ?>><?php echo _('MySQL / MariaDB'); ?></option>
									<option value="pgsql" <?php echo $odbcBackend === 'pgsql' ? 'selected' : ''; ?>><?php echo _('PostgreSQL'); ?></option>
								</select>
								<p class="help-block"><?php echo _('SQL dialect for schema creation over ODBC.'); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="audit-settings-actions">
				<button type="submit" name="settings_action" value="save" class="btn btn-primary">
					<i class="fa fa-save"></i> <?php echo _('Save Settings'); ?>
				</button>
				<button type="submit" name="settings_action" value="test" class="btn btn-default">
					<i class="fa fa-plug"></i> <?php echo _('Test Connection'); ?>
				</button>
				<span class="audit-settings-note">
					<i class="fa fa-terminal" style="margin-right:3px;"></i>
					<?php echo _('CLI: fwconsole setting AUDITCOMPLIANCE_*'); ?>
				</span>
			</div>
		</form>
	</div>
</div>
<script type="text/javascript">
(function() {
	"use strict";
	var typeEl = document.getElementById("audit_connection_type");
	var hostEl = document.getElementById("audit_db_host");
	var portEl = document.getElementById("audit_db_port");
	var dbNameEl = document.getElementById("audit_db_name");
	var directFields = document.getElementById("audit-direct-fields");
	var odbcFields = document.getElementById("audit-odbc-fields");
	var odbcWrap = document.getElementById("audit-odbc-backend-wrap");
	var odbcDsnEl = document.getElementById("audit_odbc_dsn_name");
	var hintEl = document.getElementById("audit-conn-hint");
	var prevType = (typeEl && typeEl.value) ? typeEl.value : "mysql";

	var hints = {
		mysql: "Uses PHP PDO mysql driver. Enter the remote server hostname, port, and database name below.",
		pgsql: "Uses PHP PDO pgsql driver. Enter the remote server hostname, port, and database name below.",
		odbc: "Uses PHP PDO ODBC driver. Enter the system DSN name configured in /etc/odbc.ini on the FreePBX server."
	};
	var defaultPorts = { mysql: "3306", pgsql: "5432" };

	function applyConnectionTypeUi(isUserChange) {
		var t = (typeEl && typeEl.value) ? typeEl.value : "mysql";
		var isDirect = (t === "mysql" || t === "pgsql");
		if (directFields) {
			directFields.style.display = isDirect ? "block" : "none";
		}
		if (odbcFields) {
			odbcFields.style.display = (t === "odbc") ? "block" : "none";
		}
		if (odbcWrap) {
			odbcWrap.style.display = (t === "odbc") ? "block" : "none";
		}
		if (hintEl) {
			hintEl.textContent = hints[t] || "";
			hintEl.style.display = hints[t] ? "block" : "none";
		}
		if (isUserChange && prevType !== t) {
			if (isDirect && odbcDsnEl) {
				odbcDsnEl.value = "";
			}
			if (t === "odbc") {
				if (hostEl) { hostEl.value = ""; }
				if (portEl) { portEl.value = ""; }
				if (dbNameEl) { dbNameEl.value = ""; }
			}
			prevType = t;
		}
		if (portEl) {
			portEl.placeholder = defaultPorts[t] || "";
		}
	}

	if (typeEl) {
		typeEl.addEventListener("change", function() { applyConnectionTypeUi(true); });
	}
	applyConnectionTypeUi(false);
})();
</script>
