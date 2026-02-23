<?php
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$settings = is_array($settings ?? null) ? $settings : array();
$storageStatus = is_array($storageStatus ?? null) ? $storageStatus : null;
$settingsNotice = is_array($settingsNotice ?? null) ? $settingsNotice : null;
$csrfToken = (string) ($csrfToken ?? '');
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
		<div class="alert <?php echo !empty($settingsNotice['status']) ? 'alert-success' : 'alert-danger'; ?>">
			<?php echo $esc($settingsNotice['message'] ?? ''); ?>
		</div>
	<?php endif; ?>
	<?php if ($storageStatus): ?>
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
				<label for="audit_db_dsn"><?php echo _('Audit DB DSN'); ?></label>
				<input
					type="text"
					class="form-control"
					id="audit_db_dsn"
					name="audit_db_dsn"
					value="<?php echo $esc($settings['audit_db_dsn'] ?? ''); ?>"
					placeholder="mysql:host=audit-db.example.com;port=3306;dbname=auditcompliance;charset=utf8mb4"
				>
				<p class="help-block"><?php echo _('Leave empty to use local FreePBX DB (development only).'); ?></p>
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
				<div class="col-sm-3">
					<div class="form-group">
						<label for="audit_db_odbc_backend"><?php echo _('ODBC Backend'); ?></label>
						<select class="form-control" id="audit_db_odbc_backend" name="audit_db_odbc_backend">
							<?php $odbcBackend = (string) ($settings['audit_db_odbc_backend'] ?? ''); ?>
							<option value="" <?php echo $odbcBackend === '' ? 'selected' : ''; ?>><?php echo _('Auto / Not ODBC'); ?></option>
							<option value="mysql" <?php echo $odbcBackend === 'mysql' ? 'selected' : ''; ?>>mysql</option>
							<option value="pgsql" <?php echo $odbcBackend === 'pgsql' ? 'selected' : ''; ?>>pgsql</option>
						</select>
						<p class="help-block"><?php echo _('Required when DSN starts with odbc:.'); ?></p>
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
