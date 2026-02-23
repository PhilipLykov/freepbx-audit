<?php
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$discoveryData = $request['discoveryData'] ?? array();
$modules = $discoveryData['modules'] ?? array();
$summary = $discoveryData['summary'] ?? array();
?>
<style>
.audit-tabs { margin-bottom: 20px; }

.audit-discovery-stats {
	display: flex;
	gap: 14px;
	margin-bottom: 20px;
	flex-wrap: wrap;
}
.audit-disc-stat {
	flex: 1;
	min-width: 120px;
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	padding: 14px 16px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
	text-align: center;
}
.audit-disc-stat-value {
	font-size: 24px;
	font-weight: 700;
	color: #2c3e50;
	line-height: 1.1;
}
.audit-disc-stat-label {
	font-size: 10px;
	text-transform: uppercase;
	letter-spacing: 0.4px;
	color: #6c757d;
	font-weight: 600;
	margin-top: 4px;
}
.stat-blue .audit-disc-stat-value { color: #2980b9; }
.stat-green .audit-disc-stat-value { color: #27ae60; }
.stat-orange .audit-disc-stat-value { color: #e67e22; }
.stat-purple .audit-disc-stat-value { color: #8e44ad; }
.stat-red .audit-disc-stat-value { color: #c0392b; }

.audit-disc-table {
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.audit-disc-table table { margin-bottom: 0; }
.audit-disc-table thead th {
	font-size: 10px;
	text-transform: uppercase;
	letter-spacing: 0.3px;
	color: #6c757d;
	background: #f8f9fa;
	border-bottom: 2px solid #dee2e6;
	padding: 10px 8px;
	white-space: nowrap;
}
.audit-disc-table tbody td {
	font-size: 13px;
	vertical-align: middle;
	padding: 8px;
}
.audit-disc-table tbody tr:hover { background: #f0f7ff; }

.mod-name { font-weight: 600; color: #212529; }
.disc-badge {
	display: inline-block;
	padding: 2px 7px;
	border-radius: 4px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.2px;
}
.disc-yes { background: #e8f5e9; color: #2e7d32; }
.disc-no { background: #f5f5f5; color: #bbb; }
.disc-commercial { background: #fff3e0; color: #e65100; }
.disc-oss { background: #f5f5f5; color: #9e9e9e; }

.disc-coverage-full { background: #e8f5e9; color: #2e7d32; }
.disc-coverage-partial { background: #e3f2fd; color: #1565c0; }
.disc-coverage-basic { background: #fff3e0; color: #e65100; }
.disc-coverage-minimal { background: #f5f5f5; color: #757575; }

.audit-disc-timestamp {
	text-align: right;
	font-size: 11px;
	color: #adb5bd;
	margin-top: 10px;
}
.audit-disc-empty {
	text-align: center;
	padding: 40px 20px;
	color: #adb5bd;
}
.audit-disc-empty i { display: block; font-size: 32px; margin-bottom: 10px; }

.disc-filter-bar {
	display: flex;
	gap: 10px;
	margin-bottom: 12px;
	align-items: center;
}
.disc-filter-bar input {
	max-width: 250px;
}
.disc-filter-bar label {
	font-size: 12px;
	color: #6c757d;
	font-weight: 600;
}
</style>

<div class="container-fluid">
	<h1><?php echo _('Audit Compliance'); ?></h1>

	<ul class="nav nav-tabs audit-tabs">
		<li><a href="?display=auditcompliance&view=dashboard"><i class="fa fa-tachometer"></i> <?php echo _('Dashboard'); ?></a></li>
		<li><a href="?display=auditcompliance&view=search"><i class="fa fa-search"></i> <?php echo _('Search'); ?></a></li>
		<li><a href="?display=auditcompliance&view=timeline"><i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?></a></li>
		<li class="active"><a href="?display=auditcompliance&view=discovery"><i class="fa fa-puzzle-piece"></i> <?php echo _('Module Discovery'); ?></a></li>
		<li><a href="?display=auditcompliance&view=settings"><i class="fa fa-cogs"></i> <?php echo _('Settings'); ?></a></li>
	</ul>

	<div class="display full-border">
		<div class="fpbx-container">

			<?php if (empty($modules)): ?>
				<div class="audit-disc-empty">
					<i class="fa fa-puzzle-piece"></i>
					<?php echo _('No module discovery data available. This view shows installed modules and their audit surface when run on the target FreePBX/pbxACT system.'); ?>
				</div>
			<?php else: ?>

				<div class="audit-discovery-stats">
					<div class="audit-disc-stat stat-blue">
						<div class="audit-disc-stat-value"><?php echo $esc($summary['total'] ?? 0); ?></div>
						<div class="audit-disc-stat-label"><?php echo _('Total Modules'); ?></div>
					</div>
					<div class="audit-disc-stat stat-green">
						<div class="audit-disc-stat-value"><?php echo $esc($summary['has_ajax'] ?? 0); ?></div>
						<div class="audit-disc-stat-label"><?php echo _('With AJAX Handler'); ?></div>
					</div>
					<div class="audit-disc-stat stat-orange">
						<div class="audit-disc-stat-value"><?php echo $esc($summary['has_hooks'] ?? 0); ?></div>
						<div class="audit-disc-stat-label"><?php echo _('With processHooks'); ?></div>
					</div>
					<div class="audit-disc-stat stat-purple">
						<div class="audit-disc-stat-value"><?php echo $esc($summary['has_api'] ?? 0); ?></div>
						<div class="audit-disc-stat-label"><?php echo _('With API/REST'); ?></div>
					</div>
					<div class="audit-disc-stat stat-red">
						<div class="audit-disc-stat-value"><?php echo $esc($summary['commercial'] ?? 0); ?></div>
						<div class="audit-disc-stat-label"><?php echo _('Commercial'); ?></div>
					</div>
				</div>

				<div class="disc-filter-bar">
					<label for="disc-search"><i class="fa fa-filter" style="margin-right:4px;"></i><?php echo _('Filter:'); ?></label>
					<input type="text" id="disc-search" class="form-control input-sm" placeholder="<?php echo $esc(_('Type module name...')); ?>"/>
				</div>

				<div class="audit-disc-table">
					<div class="table-responsive">
						<table class="table table-striped table-condensed table-hover" id="disc-module-table">
							<thead>
								<tr>
									<th><?php echo _('Module'); ?></th>
									<th><?php echo _('Version'); ?></th>
									<th><?php echo _('Type'); ?></th>
									<th><?php echo _('GUI'); ?></th>
									<th><?php echo _('AJAX'); ?></th>
									<th><?php echo _('API'); ?></th>
									<th><?php echo _('Hooks'); ?></th>
									<th><?php echo _('Audit Hook'); ?></th>
									<th><?php echo _('Sens. Read'); ?></th>
									<th><?php echo _('Coverage'); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($modules as $mod): ?>
								<tr>
									<td class="mod-name"><?php echo $esc($mod['name']); ?></td>
									<td><?php echo $esc($mod['version'] ?? ''); ?></td>
									<td>
										<?php if (!empty($mod['commercial'])): ?>
											<span class="disc-badge disc-commercial">COMMERCIAL</span>
										<?php else: ?>
											<span class="disc-badge disc-oss">OSS</span>
										<?php endif; ?>
									</td>
									<td><?php echo (int) ($mod['gui_pages'] ?? 0); ?></td>
									<td><?php echo !empty($mod['has_ajax']) ? '<span class="disc-badge disc-yes">YES</span>' : '<span class="disc-badge disc-no">&mdash;</span>'; ?></td>
									<td><?php echo !empty($mod['has_api']) ? '<span class="disc-badge disc-yes">YES</span>' : '<span class="disc-badge disc-no">&mdash;</span>'; ?></td>
									<td><?php echo !empty($mod['has_process_hooks']) ? '<span class="disc-badge disc-yes">YES</span>' : '<span class="disc-badge disc-no">&mdash;</span>'; ?></td>
									<td><?php echo !empty($mod['has_audit_hook']) ? '<span class="disc-badge disc-yes"><i class="fa fa-check" style="margin-right:2px;"></i>HOOKED</span>' : '<span class="disc-badge disc-no">&mdash;</span>'; ?></td>
									<td><?php echo !empty($mod['has_sensitive_read']) ? '<span class="disc-badge disc-yes"><i class="fa fa-eye" style="margin-right:2px;"></i>YES</span>' : '<span class="disc-badge disc-no">&mdash;</span>'; ?></td>
									<td>
										<?php
										$status = $mod['coverage'] ?? 'unknown';
										if ($status === 'full') {
											echo '<span class="disc-badge disc-coverage-full"><i class="fa fa-check-circle" style="margin-right:2px;"></i>FULL</span>';
										} elseif ($status === 'gui_ajax_read') {
											echo '<span class="disc-badge disc-coverage-partial"><i class="fa fa-eye" style="margin-right:2px;"></i>GUI+AJAX+READ</span>';
										} elseif ($status === 'gui_read') {
											echo '<span class="disc-badge disc-coverage-partial"><i class="fa fa-eye" style="margin-right:2px;"></i>GUI+READ</span>';
										} elseif ($status === 'gui_ajax') {
											echo '<span class="disc-badge disc-coverage-basic"><i class="fa fa-desktop" style="margin-right:2px;"></i>GUI+AJAX</span>';
										} elseif ($status === 'gui_only') {
											echo '<span class="disc-badge disc-coverage-minimal"><i class="fa fa-desktop" style="margin-right:2px;"></i>GUI ONLY</span>';
										} else {
											echo '<span class="disc-badge disc-coverage-minimal">' . $esc($status) . '</span>';
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if (!empty($summary['timestamp'])): ?>
				<div class="audit-disc-timestamp">
					<i class="fa fa-clock-o" style="margin-right:4px;"></i>
					<?php echo _('Discovered:'); ?> <?php echo $esc($summary['timestamp']); ?>
				</div>
				<?php endif; ?>

			<?php endif; ?>

		</div>
	</div>
</div>

<script type="text/javascript">
(function() {
	"use strict";
	var input = document.getElementById("disc-search");
	if (!input) return;
	input.addEventListener("input", function() {
		var q = this.value.toLowerCase();
		var rows = document.querySelectorAll("#disc-module-table tbody tr");
		for (var i = 0; i < rows.length; i++) {
			var name = rows[i].querySelector(".mod-name");
			if (!name) continue;
			rows[i].style.display = (name.textContent.toLowerCase().indexOf(q) !== -1) ? "" : "none";
		}
	});
})();
</script>
