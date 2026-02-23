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
.audit-settings-tabs { margin-bottom: 20px; }
.audit-settings-panel {
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	padding: 20px;
	max-width: 960px;
}
.audit-settings-actions {
	display: flex;
	gap: 8px;
	align-items: center;
	margin-top: 12px;
	flex-wrap: wrap;
}
.audit-settings-note {
	font-size: 12px;
	color: #6c757d;
	margin-top: 8px;
}
.audit-direct-only,
.audit-odbc-only { display: none; }
</style>

<div class="container-fluid">
	<h1><?php echo _('Audit Compliance'); ?></h1>

	<ul class="nav nav-tabs audit-settings-tabs">
		<li><a href="?display=auditcompliance&view=dashboard"><i class="fa fa-tachometer"></i> <?php echo _('Dashboard'); ?></a></li>
		<li><a href="?display=auditcompliance&view=search"><i class="fa fa-search"></i> <?php echo _('Search'); ?></a></li>
		<li><a href="?display=auditcompliance&view=timeline"><i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?></a></li>
		<li><a href="?display=auditcompliance&view=discovery"><i class="fa fa-puzzle-piece"></i> <?php echo _('Module Discovery'); ?></a></li>
		<li class="active"><a href="?display=auditcompliance&view=settings"><i class="fa fa-cogs"></i> <?php echo _('Settings'); ?></a></li>
	</ul>

	<?php if ($settingsNotice): ?>
		<?php
			$noticeClass = 'alert-danger';
			if (!empty($settingsNotice['status'])) {
				$noticeClass = !empty($settingsNotice['warning']) ? 'alert-warning' : 'alert-success';
			}
		?>
		<div class="alert <?php echo $noticeClass; ?>">
			<?php echo $esc($settingsNotice['message'] ?? ''); ?>
		</div>
	<?php endif; ?>
	<?php if ($showStorageStatus): ?>
		<div class="alert <?php echo !empty($storageStatus['status']) ? 'alert-info' : 'alert-warning'; ?>">
			<?php echo $esc($storageStatus['message'] ?? ''); ?>
			<?php if (!empty($storageStatus['driver'])): ?>
				<br><small><?php echo $esc(_('Driver') . ': ' . (string) $storageStatus['driver']); ?></small>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="audit-settings-panel">
		<form method="post" action="?display=auditcompliance&view=settings" autocomplete="off">
			<input type="hidden" name="auditcompliance_csrf" value="<?php echo $esc($csrfToken); ?>">
			<input type="hidden" name="keep_current_password" value="1">

			<div class="form-group">
				<label for="audit_connection_type"><?php echo _('Connection Type'); ?></label>
				<select class="form-control" id="audit_connection_type" name="audit_connection_type">
					<?php $connectionType = (string) ($settings['audit_connection_type'] ?? 'mysql'); ?>
					<option value="mysql" <?php echo $connectionType === 'mysql' ? 'selected' : ''; ?>><?php echo _('Direct MySQL/MariaDB (PDO)'); ?></option>
					<option value="pgsql" <?php echo $connectionType === 'pgsql' ? 'selected' : ''; ?>><?php echo _('Direct PostgreSQL (PDO)'); ?></option>
					<option value="odbc" <?php echo $connectionType === 'odbc' ? 'selected' : ''; ?>><?php echo _('ODBC connection'); ?></option>
				</select>
				<p class="help-block"><?php echo _('Choose how this module connects to your remote audit database.'); ?></p>
			</div>

			<div id="audit-direct-fields" class="audit-direct-only">
				<div class="row">
					<div class="col-sm-4">
						<div class="form-group">
							<label for="audit_db_host"><?php echo _('Hostname'); ?></label>
							<input type="text" class="form-control" id="audit_db_host" name="audit_db_host" value="<?php echo $esc($settings['audit_db_host'] ?? ''); ?>" placeholder="db.example.com">
						</div>
					</div>
					<div class="col-sm-4">
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
				<p class="help-block" id="audit-direct-help"><?php echo _('Direct connection uses native PDO driver for selected database engine.'); ?></p>
			</div>

			<div id="audit-odbc-fields" class="audit-odbc-only">
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
					<p class="help-block"><?php echo _('Use DSN name from /etc/odbc.ini (example: AuditDB).'); ?></p>
				</div>
			</div>

			<div class="row">
				<div class="col-sm-6">
					<div class="form-group">
						<label for="audit_db_user"><?php echo _('Audit DB User'); ?></label>
						<input type="text" class="form-control" id="audit_db_user" name="audit_db_user" value="<?php echo $esc($settings['audit_db_user'] ?? ''); ?>">
					</div>
				</div>
				<div class="col-sm-6">
					<div class="form-group">
						<label for="audit_db_password"><?php echo _('Audit DB Password'); ?></label>
						<input type="password" class="form-control" id="audit_db_password" name="audit_db_password" value="">
						<p class="help-block">
							<?php
								$hasPassword = !empty($settings['audit_db_password_set']);
								echo $hasPassword
									? _('Password is already set. Leave blank to keep current value.')
									: _('Password is not set.');
							?>
						</p>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-sm-3">
					<div class="form-group">
						<label for="audit_require_external_db"><?php echo _('Require External DB'); ?></label>
						<div class="checkbox" style="margin-top:0;">
							<label>
								<input type="checkbox" id="audit_require_external_db" name="audit_require_external_db" value="1" <?php echo (($settings['audit_require_external_db'] ?? '1') === '1') ? 'checked' : ''; ?>>
								<?php echo _('Do not use local FreePBX DB fallback'); ?>
							</label>
						</div>
					</div>
				</div>
				<div class="col-sm-3">
					<div class="form-group">
						<label for="audit_db_require_tls"><?php echo _('Require TLS'); ?></label>
						<div class="checkbox" style="margin-top:0;">
							<label>
								<input type="checkbox" id="audit_db_require_tls" name="audit_db_require_tls" value="1" <?php echo (($settings['audit_db_require_tls'] ?? '1') === '1') ? 'checked' : ''; ?>>
								<?php echo _('Enforce encrypted connection (recommended)'); ?>
							</label>
						</div>
					</div>
				</div>
				<div class="col-sm-3 audit-odbc-only" id="audit-odbc-backend-wrap">
					<div class="form-group">
						<label for="audit_db_odbc_backend"><?php echo _('ODBC target engine'); ?></label>
						<select class="form-control" id="audit_db_odbc_backend" name="audit_db_odbc_backend">
							<?php $odbcBackend = (string) ($settings['audit_db_odbc_backend'] ?? ''); ?>
							<option value="mysql" <?php echo $odbcBackend === 'mysql' ? 'selected' : ''; ?>><?php echo _('MySQL / MariaDB'); ?></option>
							<option value="pgsql" <?php echo $odbcBackend === 'pgsql' ? 'selected' : ''; ?>><?php echo _('PostgreSQL'); ?></option>
						</select>
						<p class="help-block"><?php echo _('Used for SQL dialect selection when using ODBC.'); ?></p>
					</div>
				</div>
				<div class="col-sm-3">
					<div class="form-group">
						<label for="audit_session_idle_timeout_seconds"><?php echo _('Session Idle Timeout (seconds)'); ?></label>
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
			</div>

			<div class="audit-settings-note">
				<?php echo _('Settings are available from both GUI and CLI (fwconsole setting AUDITCOMPLIANCE_*).'); ?>
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
	var directHelpEl = document.getElementById("audit-direct-help");
	var odbcWrap = document.getElementById("audit-odbc-backend-wrap");

	var odbcDsnEl = document.getElementById("audit_odbc_dsn_name");
	var prevType = (typeEl && typeEl.value) ? typeEl.value : "mysql";

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
		if (!directHelpEl) {
			return;
		}
		if (t === "pgsql") {
			if (portEl && !portEl.value) {
				portEl.value = "5432";
			}
			directHelpEl.textContent = "Direct PostgreSQL mode: enter Hostname, Port, and Database Name.";
			return;
		}
		if (t === "odbc") {
			return;
		}
		if (portEl && !portEl.value) {
			portEl.value = "3306";
		}
		directHelpEl.textContent = "Direct MySQL/MariaDB mode: enter Hostname, Port, and Database Name.";
	}

	if (typeEl) {
		typeEl.addEventListener("change", function() { applyConnectionTypeUi(true); });
	}
	applyConnectionTypeUi(false);
})();
</script>
