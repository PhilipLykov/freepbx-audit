<?php
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$preset = $request['preset'] ?? '';
?>
<style>
.audit-tabs { margin-bottom: 20px; }

.audit-filter-panel {
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	margin-bottom: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
	overflow: hidden;
}
.audit-filter-quick {
	padding: 14px 20px;
	display: flex;
	gap: 12px;
	align-items: flex-end;
	flex-wrap: wrap;
	background: #fff;
}
.audit-filter-quick .form-group { margin-bottom: 0; flex: 1; min-width: 120px; }
.audit-filter-quick label { font-weight: 600; font-size: 11px; text-transform: uppercase; color: #6c757d; letter-spacing: 0.3px; margin-bottom: 3px; display: block; }
.audit-filter-quick .btn-group-actions { display: flex; gap: 6px; align-self: flex-end; flex-shrink: 0; }
.audit-filter-toggle {
	padding: 0 20px 12px;
	border-top: 1px solid #f0f0f0;
	background: #fafbfc;
}
.audit-filter-toggle-btn {
	background: none;
	border: none;
	color: #6c757d;
	font-size: 12px;
	cursor: pointer;
	padding: 8px 0 0;
	font-weight: 500;
}
.audit-filter-toggle-btn:hover { color: #495057; }
.audit-filter-toggle-btn i { margin-right: 4px; transition: transform 0.2s; }
.audit-filter-toggle-btn.open i { transform: rotate(180deg); }
.audit-filter-advanced {
	padding: 0 20px 14px;
	background: #fafbfc;
	display: none;
}
.audit-filter-advanced.visible { display: block; }
.audit-filter-advanced .row { margin-bottom: 0; }
.audit-filter-advanced .form-group { margin-bottom: 8px; }
.audit-filter-advanced label { font-weight: 600; font-size: 11px; text-transform: uppercase; color: #6c757d; letter-spacing: 0.3px; }

.audit-result-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px;
	flex-wrap: wrap;
	gap: 8px;
}
.audit-result-bar .result-count {
	font-weight: 600;
	color: #495057;
	font-size: 13px;
}
.audit-export-bar { display: flex; gap: 6px; }

.audit-result-table { border-collapse: separate; border-spacing: 0; }
.audit-result-table thead th {
	white-space: nowrap;
	cursor: pointer;
	user-select: none;
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.3px;
	color: #6c757d;
	background: #f8f9fa;
	border-bottom: 2px solid #dee2e6;
	padding: 10px 8px;
}
.audit-result-table thead th:hover { background: #e9ecef; color: #495057; }
.audit-result-table thead th .sort-arrow { margin-left: 4px; font-size: 10px; color: #ccc; }
.audit-result-table thead th .sort-arrow.active { color: #495057; }
.audit-result-table td {
	font-size: 13px;
	word-break: break-word;
	max-width: 200px;
	padding: 8px;
	vertical-align: middle;
}
.audit-result-table tbody tr.audit-event-row { transition: background 0.1s; }
.audit-result-table tbody tr.audit-event-row:hover { background: #f0f7ff !important; }
.audit-event-row td:first-child {
	white-space: nowrap;
}
.audit-time-cell {
	display: flex;
	flex-direction: column;
}
.audit-time-abs { font-size: 12px; color: #495057; }
.audit-time-rel { font-size: 10px; color: #adb5bd; }

.audit-badge-sm {
	display: inline-block;
	padding: 3px 7px;
	border-radius: 4px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.3px;
}
.audit-badge-success { background: #e8f5e9; color: #2e7d32; }
.audit-badge-failure { background: #ffebee; color: #c62828; }
.audit-badge-gui { background: #e3f2fd; color: #1565c0; }
.audit-badge-ajax { background: #fff8e1; color: #f57f17; }
.audit-badge-rest { background: #f3e5f5; color: #6a1b9a; }
.audit-badge-hook { background: #e8f5e9; color: #2e7d32; }
.audit-badge-auth { background: #fce4ec; color: #c62828; }
.audit-badge-default { background: #f5f5f5; color: #616161; }

.audit-loading { text-align: center; padding: 40px; color: #adb5bd; }
.audit-no-results { text-align: center; padding: 40px; color: #adb5bd; font-size: 14px; }

.audit-detail-row td { background: #fff !important; padding: 0 !important; }
.audit-detail-inner {
	margin: 0 8px 8px;
	border: 1px solid #e9ecef;
	border-radius: 6px;
	overflow: hidden;
}
.audit-detail-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 0;
}
.audit-detail-field {
	padding: 8px 12px;
	border-bottom: 1px solid #f5f5f5;
	font-size: 12px;
}
.audit-detail-field:nth-child(odd) { background: #fafbfc; }
.audit-detail-label {
	font-weight: 700;
	color: #6c757d;
	text-transform: uppercase;
	font-size: 10px;
	letter-spacing: 0.3px;
	display: block;
	margin-bottom: 2px;
}
.audit-detail-value {
	color: #212529;
	word-break: break-all;
}
.audit-detail-changes {
	padding: 10px 12px;
	border-top: 1px solid #e9ecef;
	background: #f8f9fa;
}
.audit-detail-changes-title {
	font-weight: 700;
	font-size: 10px;
	text-transform: uppercase;
	letter-spacing: 0.3px;
	color: #6c757d;
	margin-bottom: 4px;
}
.audit-detail-json {
	font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
	font-size: 11px;
	white-space: pre-wrap;
	word-break: break-all;
	max-height: 200px;
	overflow-y: auto;
	background: #fff;
	border: 1px solid #e9ecef;
	border-radius: 4px;
	padding: 8px;
	margin-top: 4px;
}
.change-label { font-weight: 700; margin-bottom: 2px; display: block; }
.change-label-changed { color: #e67e22; }
.change-label-added { color: #27ae60; }
.change-label-removed { color: #c0392b; }

.audit-pagination {
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 12px;
	margin-top: 16px;
	padding: 12px 0;
}
.audit-pagination .page-info { font-size: 13px; color: #6c757d; }

.audit-keyboard-hint {
	font-size: 11px;
	color: #adb5bd;
	text-align: center;
	margin-top: 8px;
}
.audit-keyboard-hint kbd {
	background: #f0f0f0;
	border: 1px solid #ddd;
	border-radius: 3px;
	padding: 1px 5px;
	font-size: 10px;
	font-family: inherit;
}
</style>

<div class="container-fluid">
	<h1><?php echo _('Audit Compliance'); ?></h1>

	<ul class="nav nav-tabs audit-tabs">
		<li><a href="?display=auditcompliance&view=dashboard"><i class="fa fa-tachometer"></i> <?php echo _('Dashboard'); ?></a></li>
		<li class="active"><a href="?display=auditcompliance&view=search"><i class="fa fa-search"></i> <?php echo _('Search'); ?></a></li>
		<li><a href="?display=auditcompliance&view=timeline"><i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?></a></li>
		<li><a href="?display=auditcompliance&view=discovery"><i class="fa fa-puzzle-piece"></i> <?php echo _('Module Discovery'); ?></a></li>
		<li><a href="?display=auditcompliance&view=settings"><i class="fa fa-cogs"></i> <?php echo _('Settings'); ?></a></li>
	</ul>

	<div class="display full-border">
		<div class="fpbx-container">

			<div class="audit-filter-panel">
				<form id="audit-search-form" onsubmit="return false;">
					<div class="audit-filter-quick">
						<div class="form-group" style="flex:2; min-width: 200px;">
							<label for="audit-search-text"><i class="fa fa-search" style="margin-right: 3px;"></i> <?php echo _('Search'); ?></label>
							<input type="text" id="audit-search-text" class="form-control input-sm" placeholder="<?php echo $esc(_('Search across module, action, actor, object...')); ?>"/>
						</div>
					<div class="form-group">
						<label for="audit-date-from"><?php echo _('From'); ?></label>
						<input type="text" id="audit-date-from" class="form-control input-sm" placeholder="DD-MM-YYYY" maxlength="10" style="width:120px;"/>
					</div>
					<div class="form-group">
						<label for="audit-date-to"><?php echo _('To'); ?></label>
						<input type="text" id="audit-date-to" class="form-control input-sm" placeholder="DD-MM-YYYY" maxlength="10" style="width:120px;"/>
						</div>
						<div class="btn-group-actions">
							<button type="button" id="audit-btn-search" class="btn btn-primary btn-sm">
								<i class="fa fa-search"></i> <?php echo _('Search'); ?>
							</button>
							<button type="button" id="audit-btn-reset" class="btn btn-default btn-sm" title="<?php echo $esc(_('Reset all filters')); ?>">
								<i class="fa fa-times"></i>
							</button>
						</div>
					</div>
					<div class="audit-filter-toggle">
						<button type="button" class="audit-filter-toggle-btn" id="audit-toggle-advanced">
							<i class="fa fa-chevron-down"></i> <?php echo _('Advanced Filters'); ?>
						</button>
					</div>
					<div class="audit-filter-advanced" id="audit-advanced-panel">
						<div class="row">
							<div class="col-sm-3">
								<div class="form-group">
									<label for="audit-actor"><?php echo _('Actor'); ?></label>
									<input type="text" id="audit-actor" class="form-control input-sm" placeholder="<?php echo $esc(_('e.g. admin')); ?>"/>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<label for="audit-module"><?php echo _('Module'); ?></label>
									<select id="audit-module" class="form-control input-sm">
										<option value=""><?php echo _('All Modules'); ?></option>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="form-group">
									<label for="audit-action"><?php echo _('Action'); ?></label>
									<input type="text" id="audit-action" class="form-control input-sm" placeholder="<?php echo $esc(_('e.g. update')); ?>"/>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="form-group">
									<label for="audit-channel"><?php echo _('Channel'); ?></label>
									<select id="audit-channel" class="form-control input-sm">
										<option value=""><?php echo _('All'); ?></option>
										<option value="gui"><?php echo _('GUI'); ?></option>
										<option value="ajax"><?php echo _('AJAX'); ?></option>
										<option value="rest"><?php echo _('REST'); ?></option>
										<option value="hook"><?php echo _('Hook'); ?></option>
										<option value="auth"><?php echo _('Auth'); ?></option>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="form-group">
									<label for="audit-outcome"><?php echo _('Outcome'); ?></label>
									<select id="audit-outcome" class="form-control input-sm">
										<option value=""><?php echo _('All'); ?></option>
										<option value="success"><?php echo _('Success'); ?></option>
										<option value="failure"><?php echo _('Failure'); ?></option>
									</select>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-sm-2">
								<div class="form-group">
									<label for="audit-phase"><?php echo _('Phase'); ?></label>
									<select id="audit-phase" class="form-control input-sm">
										<option value=""><?php echo _('All'); ?></option>
										<option value="login"><?php echo _('Login'); ?></option>
										<option value="activity"><?php echo _('Activity'); ?></option>
										<option value="logout"><?php echo _('Logout'); ?></option>
										<option value="timeout"><?php echo _('Timeout'); ?></option>
										<option value="failure"><?php echo _('Failure'); ?></option>
									</select>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<label for="audit-source-ip"><?php echo _('Source IP'); ?></label>
									<input type="text" id="audit-source-ip" class="form-control input-sm" placeholder="<?php echo $esc(_('e.g. 192.168.1.1')); ?>"/>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>

			<div class="audit-result-bar">
				<span class="result-count" id="audit-result-count">&nbsp;</span>
				<div class="audit-export-bar">
					<button type="button" id="audit-btn-export-csv" class="btn btn-default btn-xs" disabled>
						<i class="fa fa-download"></i> <?php echo _('CSV'); ?>
					</button>
					<button type="button" id="audit-btn-export-json" class="btn btn-default btn-xs" disabled>
						<i class="fa fa-download"></i> <?php echo _('JSON'); ?>
					</button>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-striped table-condensed table-hover audit-result-table" id="audit-result-table">
					<thead>
						<tr>
							<th data-sort="occurred_at_unix"><?php echo _('Time'); ?> <span class="sort-arrow active">&#9660;</span></th>
							<th data-sort="actor"><?php echo _('Actor'); ?> <span class="sort-arrow">&#9650;</span></th>
							<th data-sort="channel"><?php echo _('Channel'); ?> <span class="sort-arrow">&#9650;</span></th>
							<th data-sort="module_name"><?php echo _('Module'); ?> <span class="sort-arrow">&#9650;</span></th>
							<th data-sort="action"><?php echo _('Action'); ?> <span class="sort-arrow">&#9650;</span></th>
							<th><?php echo _('Outcome'); ?></th>
							<th><?php echo _('Phase'); ?></th>
							<th><?php echo _('Object'); ?></th>
							<th><?php echo _('IP'); ?></th>
						</tr>
					</thead>
					<tbody id="audit-result-body">
						<tr><td colspan="9" class="audit-loading"><i class="fa fa-spinner fa-spin"></i> <?php echo _('Loading recent events...'); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="audit-pagination" id="audit-pagination" style="display:none;">
				<button type="button" id="audit-btn-prev" class="btn btn-default btn-xs" disabled>
					<i class="fa fa-chevron-left"></i> <?php echo _('Previous'); ?>
				</button>
				<span class="page-info" id="audit-page-info"></span>
				<button type="button" id="audit-btn-next" class="btn btn-default btn-xs" disabled>
					<?php echo _('Next'); ?> <i class="fa fa-chevron-right"></i>
				</button>
			</div>

			<div class="audit-keyboard-hint">
				<kbd>Enter</kbd> <?php echo _('to search'); ?> &middot;
				<kbd>&uarr;</kbd><kbd>&darr;</kbd> <?php echo _('navigate rows'); ?> &middot;
				<kbd>Space</kbd> <?php echo _('expand detail'); ?>
			</div>

		</div>
	</div>
</div>

<script type="text/javascript">
(function() {
	"use strict";

	var AJAX_BASE = "ajax.php?module=auditcompliance&command=";
	var PAGE_SIZE = 50;
	var currentOffset = 0;
	var currentTotal = 0;
	var sortField = "occurred_at_unix";
	var sortDir = "DESC";
	var selectedRowIdx = -1;
	var currentRows = [];
	var preset = <?php echo json_encode($preset); ?>;

	function esc(str) {
		var d = document.createElement("div");
		d.appendChild(document.createTextNode(String(str || "")));
		return d.innerHTML;
	}

	function initDateInput(el) {
		if (!el) return;
		el.addEventListener("input", function() {
			var v = this.value.replace(/[^0-9]/g, "");
			if (v.length > 8) v = v.substring(0, 8);
			var parts = [];
			if (v.length > 0) parts.push(v.substring(0, Math.min(2, v.length)));
			if (v.length > 2) parts.push(v.substring(2, Math.min(4, v.length)));
			if (v.length > 4) parts.push(v.substring(4, 8));
			this.value = parts.join("-");
		});
		el.addEventListener("blur", function() {
			var v = this.value.trim();
			if (v !== "" && !/^\d{2}-\d{2}-\d{4}$/.test(v)) {
				this.style.borderColor = "#c0392b";
				this.title = "Use DD-MM-YYYY format";
			} else {
				this.style.borderColor = "";
				this.title = "";
			}
		});
	}

	function relativeTime(unixTs) {
		var now = Math.floor(Date.now() / 1000);
		var diff = now - parseInt(unixTs, 10);
		if (isNaN(diff) || diff < 0) return "";
		if (diff < 60) return diff + "s ago";
		if (diff < 3600) return Math.floor(diff / 60) + "m ago";
		if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
		if (diff < 604800) return Math.floor(diff / 86400) + "d ago";
		return Math.floor(diff / 604800) + "w ago";
	}

	function channelBadge(ch) {
		var map = {gui:"audit-badge-gui",ajax:"audit-badge-ajax",rest:"audit-badge-rest",hook:"audit-badge-hook",auth:"audit-badge-auth"};
		var cls = map[ch] || "audit-badge-default";
		var icons = {gui:"fa-desktop",ajax:"fa-exchange",rest:"fa-plug",hook:"fa-link",auth:"fa-lock"};
		var icon = icons[ch] || "fa-circle-o";
		return '<span class="audit-badge-sm ' + cls + '"><i class="fa ' + icon + '" style="margin-right:3px;font-size:9px;"></i>' + esc(ch) + '</span>';
	}

	function outcomeBadge(o) {
		var cls = o === "success" ? "audit-badge-success" : "audit-badge-failure";
		var icon = o === "success" ? "fa-check" : "fa-times";
		return '<span class="audit-badge-sm ' + cls + '"><i class="fa ' + icon + '" style="margin-right:2px;font-size:9px;"></i>' + esc(o) + '</span>';
	}

	function phaseBadge(p) {
		var map = {login:"audit-badge-success",activity:"audit-badge-default",logout:"audit-badge-gui",timeout:"audit-badge-ajax",failure:"audit-badge-failure"};
		var cls = map[p] || "audit-badge-default";
		return '<span class="audit-badge-sm ' + cls + '">' + esc(p) + '</span>';
	}

	function collectFilters() {
		return {
			date_from: document.getElementById("audit-date-from").value,
			date_to: document.getElementById("audit-date-to").value,
			actor: document.getElementById("audit-actor").value.trim(),
			module_name: document.getElementById("audit-module").value,
			action_filter: document.getElementById("audit-action").value.trim(),
			channel: document.getElementById("audit-channel").value,
			outcome: document.getElementById("audit-outcome").value,
			session_phase: document.getElementById("audit-phase").value,
			source_ip: document.getElementById("audit-source-ip").value.trim(),
			search_text: document.getElementById("audit-search-text").value.trim()
		};
	}

	function buildQueryString(filters, extra) {
		var parts = [];
		for (var k in filters) {
			if (filters.hasOwnProperty(k) && filters[k] !== "") {
				parts.push(encodeURIComponent(k) + "=" + encodeURIComponent(filters[k]));
			}
		}
		if (extra) {
			for (var e in extra) {
				if (extra.hasOwnProperty(e)) {
					parts.push(encodeURIComponent(e) + "=" + encodeURIComponent(extra[e]));
				}
			}
		}
		return parts.join("&");
	}

	function ajaxGet(url, callback) {
		var xhr = new XMLHttpRequest();
		xhr.open("GET", url, true);
		xhr.setRequestHeader("Accept", "application/json");
		xhr.timeout = 30000;
		xhr.onload = function() {
			if (xhr.status >= 200 && xhr.status < 300) {
				try { callback(null, JSON.parse(xhr.responseText)); }
				catch (e) { callback("Invalid JSON response"); }
			} else {
				callback("HTTP " + xhr.status);
			}
		};
		xhr.onerror = function() { callback("Network error"); };
		xhr.ontimeout = function() { callback("Request timeout"); };
		xhr.send();
	}

	function loadFilterValues() {
		ajaxGet(AJAX_BASE + "getFilterValues&column=module_name", function(err, data) {
			if (err || !data || !data.values) return;
			if (data.error) {
				console.warn("Audit filter values warning: " + data.error);
			}
			var sel = document.getElementById("audit-module");
			var current = sel.value;
			while (sel.options.length > 1) sel.remove(1);
			for (var i = 0; i < data.values.length; i++) {
				var opt = document.createElement("option");
				opt.value = data.values[i];
				opt.textContent = data.values[i];
				sel.appendChild(opt);
			}
			if (current) sel.value = current;
		});
	}

	function doSearch() {
		var filters = collectFilters();
		var extra = {
			sort: sortField,
			sort_dir: sortDir,
			limit: PAGE_SIZE,
			offset: currentOffset
		};
		var url = AJAX_BASE + "searchEvents&" + buildQueryString(filters, extra);
		var tbody = document.getElementById("audit-result-body");
		tbody.innerHTML = '<tr><td colspan="9" class="audit-loading"><i class="fa fa-spinner fa-spin"></i> Loading&hellip;</td></tr>';
		selectedRowIdx = -1;

		ajaxGet(url, function(err, data) {
			if (err) {
				tbody.innerHTML = '<tr><td colspan="9" class="audit-no-results"><i class="fa fa-exclamation-circle" style="color:#c0392b;"></i> Error: ' + esc(err) + '</td></tr>';
				updatePagination(0, 0);
				return;
			}
			if (data && data.error) {
				tbody.innerHTML = '<tr><td colspan="9" class="audit-no-results"><i class="fa fa-exclamation-circle" style="color:#c0392b;"></i> Error: ' + esc(data.error) + '</td></tr>';
				updatePagination(0, 0);
				return;
			}
			currentTotal = data.total || 0;
			currentRows = data.rows || [];
			renderResults(currentRows);
			updatePagination(currentTotal, currentOffset);
			document.getElementById("audit-btn-export-csv").disabled = (currentTotal === 0);
			document.getElementById("audit-btn-export-json").disabled = (currentTotal === 0);
		});
	}

	function renderResults(rows) {
		var tbody = document.getElementById("audit-result-body");
		if (rows.length === 0) {
			tbody.innerHTML = '<tr><td colspan="9" class="audit-no-results"><i class="fa fa-inbox" style="font-size:20px;display:block;margin-bottom:6px;"></i>No events found matching your criteria.</td></tr>';
			return;
		}
		var html = "";
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			var objStr = r.object_type ? (r.object_type + (r.object_id ? (":" + r.object_id) : "")) : "";
			html += '<tr class="audit-event-row" data-idx="' + i + '" style="cursor:pointer;" tabindex="0">';
			html += '<td><div class="audit-time-cell"><span class="audit-time-abs">' + esc(r.occurred_at_local || r.occurred_at_utc) + '</span>';
			html += '<span class="audit-time-rel">' + relativeTime(r.occurred_at_unix) + '</span></div></td>';
			html += '<td><strong>' + esc(r.actor) + '</strong></td>';
			html += '<td>' + channelBadge(r.channel) + '</td>';
			html += '<td>' + esc(r.module_name) + '</td>';
			html += '<td>' + esc(r.action) + '</td>';
			html += '<td>' + outcomeBadge(r.outcome) + '</td>';
			html += '<td>' + phaseBadge(r.session_phase) + '</td>';
			html += '<td title="' + esc(objStr) + '">' + esc(objStr.length > 30 ? objStr.substring(0, 30) + "\u2026" : objStr) + '</td>';
			html += '<td><code style="font-size:11px;">' + esc(r.source_ip) + '</code></td>';
			html += '</tr>';
			html += buildDetailRow(r, i);
		}
		tbody.innerHTML = html;
	}

	function buildDetailRow(r, idx) {
		var html = '<tr class="audit-detail-row" data-detail="' + idx + '" style="display:none;">';
		html += '<td colspan="9"><div class="audit-detail-inner">';
		html += '<div class="audit-detail-grid">';
		html += detailField("Event ID", r.event_id);
		html += detailField("Session ID", r.session_id);
		html += detailField("Route", r.route);
		html += detailField("Method", r.request_method);
		html += detailField("Request URI", r.request_uri);
		html += detailField("UTC Time", r.occurred_at_utc);
		html += '</div>';

		var hasChanges = false;
		var changesHtml = '';
		if (r.change_changed && r.change_changed !== '{}' && r.change_changed !== '[]') {
			hasChanges = true;
			changesHtml += '<span class="change-label change-label-changed"><i class="fa fa-pencil" style="margin-right:4px;"></i>Changed:</span>';
			changesHtml += '<div class="audit-detail-json">' + formatJson(r.change_changed) + '</div>';
		}
		if (r.change_added && r.change_added !== '{}' && r.change_added !== '[]') {
			hasChanges = true;
			changesHtml += '<span class="change-label change-label-added"><i class="fa fa-plus" style="margin-right:4px;"></i>Added:</span>';
			changesHtml += '<div class="audit-detail-json">' + formatJson(r.change_added) + '</div>';
		}
		if (r.change_removed && r.change_removed !== '{}' && r.change_removed !== '[]') {
			hasChanges = true;
			changesHtml += '<span class="change-label change-label-removed"><i class="fa fa-minus" style="margin-right:4px;"></i>Removed:</span>';
			changesHtml += '<div class="audit-detail-json">' + formatJson(r.change_removed) + '</div>';
		}
		if (hasChanges) {
			html += '<div class="audit-detail-changes">';
			html += '<div class="audit-detail-changes-title"><i class="fa fa-code-fork" style="margin-right:4px;"></i>Change Detail</div>';
			html += changesHtml;
			html += '</div>';
		}

		html += '</div></td></tr>';
		return html;
	}

	function detailField(label, value) {
		return '<div class="audit-detail-field"><span class="audit-detail-label">' + esc(label) + '</span><span class="audit-detail-value">' + esc(value || "-") + '</span></div>';
	}

	function formatJson(str) {
		try {
			var obj = JSON.parse(str);
			return esc(JSON.stringify(obj, null, 2));
		} catch (e) {
			return esc(str);
		}
	}

	function updatePagination(total, offset) {
		var pag = document.getElementById("audit-pagination");
		var info = document.getElementById("audit-page-info");
		var countEl = document.getElementById("audit-result-count");
		var btnPrev = document.getElementById("audit-btn-prev");
		var btnNext = document.getElementById("audit-btn-next");

		if (total === 0) {
			pag.style.display = "none";
			countEl.textContent = "";
			return;
		}
		pag.style.display = "flex";
		var pageStart = offset + 1;
		var pageEnd = Math.min(offset + PAGE_SIZE, total);
		var currentPage = Math.floor(offset / PAGE_SIZE) + 1;
		var totalPages = Math.ceil(total / PAGE_SIZE);
		info.textContent = "Page " + currentPage + " of " + totalPages + " (" + pageStart + "\u2013" + pageEnd + " of " + total + ")";
		countEl.innerHTML = '<i class="fa fa-list" style="margin-right:4px;"></i>' + total + " event" + (total !== 1 ? "s" : "") + " found";

		btnPrev.disabled = (offset <= 0);
		btnNext.disabled = (offset + PAGE_SIZE >= total);
	}

	function doExport(format) {
		var filters = collectFilters();
		var url = AJAX_BASE + "exportEvents&" + buildQueryString(filters);
		var btn = (format === "csv") ? document.getElementById("audit-btn-export-csv") : document.getElementById("audit-btn-export-json");
		var origText = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Exporting\u2026';

		ajaxGet(url, function(err, data) {
			btn.disabled = false;
			btn.innerHTML = origText;
			if (err || !data || !data.export) {
				alert("Export failed: " + (err || "No data"));
				return;
			}
			var rows = data.export;
			if (rows.length === 0) {
				alert("No data to export.");
				return;
			}
			if (format === "csv") downloadCsv(rows);
			else downloadJson(rows);
		});
	}

	function downloadCsv(rows) {
		var cols = ["occurred_at_local","occurred_at_utc","actor","channel","module_name","action","outcome","session_phase","object_type","object_id","source_ip","session_id","event_id","route","request_method","request_uri"];
		var lines = [cols.join(",")];
		for (var i = 0; i < rows.length; i++) {
			var cells = [];
			for (var c = 0; c < cols.length; c++) {
				var val = String(rows[i][cols[c]] || "").replace(/"/g, '""');
				cells.push('"' + val + '"');
			}
			lines.push(cells.join(","));
		}
		var blob = new Blob([lines.join("\r\n")], {type: "text/csv;charset=utf-8;"});
		triggerDownload(blob, "audit_events_" + isoNow() + ".csv");
	}

	function downloadJson(rows) {
		var blob = new Blob([JSON.stringify(rows, null, 2)], {type: "application/json;charset=utf-8;"});
		triggerDownload(blob, "audit_events_" + isoNow() + ".json");
	}

	function triggerDownload(blob, filename) {
		var a = document.createElement("a");
		a.href = URL.createObjectURL(blob);
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		setTimeout(function() {
			document.body.removeChild(a);
			URL.revokeObjectURL(a.href);
		}, 100);
	}

	function isoNow() {
		var d = new Date();
		return d.getFullYear() + "-" +
			String(d.getMonth() + 1).padStart(2, "0") + "-" +
			String(d.getDate()).padStart(2, "0") + "_" +
			String(d.getHours()).padStart(2, "0") +
			String(d.getMinutes()).padStart(2, "0") +
			String(d.getSeconds()).padStart(2, "0");
	}

	function applyPreset() {
		if (preset === "failures") {
			document.getElementById("audit-phase").value = "failure";
			document.getElementById("audit-outcome").value = "failure";
			var adv = document.getElementById("audit-advanced-panel");
			var btn = document.getElementById("audit-toggle-advanced");
			adv.classList.add("visible");
			btn.classList.add("open");
		}
	}

	function initEvents() {
		document.getElementById("audit-btn-search").addEventListener("click", function() {
			currentOffset = 0;
			doSearch();
		});

		document.getElementById("audit-btn-reset").addEventListener("click", function() {
			document.getElementById("audit-date-from").value = "";
			document.getElementById("audit-date-to").value = "";
			document.getElementById("audit-actor").value = "";
			document.getElementById("audit-module").value = "";
			document.getElementById("audit-action").value = "";
			document.getElementById("audit-channel").value = "";
			document.getElementById("audit-outcome").value = "";
			document.getElementById("audit-phase").value = "";
			document.getElementById("audit-source-ip").value = "";
			document.getElementById("audit-search-text").value = "";
			sortField = "occurred_at_unix";
			sortDir = "DESC";
			currentOffset = 0;
			currentTotal = 0;
			selectedRowIdx = -1;
			updateSortHeaders();
			doSearch();
		});

		document.getElementById("audit-btn-prev").addEventListener("click", function() {
			if (currentOffset >= PAGE_SIZE) {
				currentOffset -= PAGE_SIZE;
				doSearch();
			}
		});

		document.getElementById("audit-btn-next").addEventListener("click", function() {
			if (currentOffset + PAGE_SIZE < currentTotal) {
				currentOffset += PAGE_SIZE;
				doSearch();
			}
		});

		document.getElementById("audit-btn-export-csv").addEventListener("click", function() { doExport("csv"); });
		document.getElementById("audit-btn-export-json").addEventListener("click", function() { doExport("json"); });

		document.getElementById("audit-search-form").addEventListener("keypress", function(e) {
			if (e.key === "Enter" || e.keyCode === 13) {
				e.preventDefault();
				currentOffset = 0;
				doSearch();
			}
		});

		document.getElementById("audit-toggle-advanced").addEventListener("click", function() {
			var panel = document.getElementById("audit-advanced-panel");
			panel.classList.toggle("visible");
			this.classList.toggle("open");
		});

		var ths = document.querySelectorAll("#audit-result-table thead th[data-sort]");
		for (var i = 0; i < ths.length; i++) {
			ths[i].addEventListener("click", function() {
				var col = this.getAttribute("data-sort");
				if (sortField === col) {
					sortDir = (sortDir === "DESC") ? "ASC" : "DESC";
				} else {
					sortField = col;
					sortDir = "DESC";
				}
				updateSortHeaders();
				currentOffset = 0;
				doSearch();
			});
		}

		document.getElementById("audit-result-body").addEventListener("click", function(e) {
			var row = e.target.closest("tr.audit-event-row");
			if (!row) return;
			toggleDetail(parseInt(row.getAttribute("data-idx"), 10));
		});

		document.addEventListener("keydown", function(e) {
			var tbody = document.getElementById("audit-result-body");
			var eventRows = tbody.querySelectorAll("tr.audit-event-row");
			if (eventRows.length === 0) return;
			if (e.target.tagName === "INPUT" || e.target.tagName === "SELECT" || e.target.tagName === "TEXTAREA") return;

			if (e.key === "ArrowDown") {
				e.preventDefault();
				if (selectedRowIdx < eventRows.length - 1) {
					selectedRowIdx++;
					highlightRow(eventRows, selectedRowIdx);
				}
			} else if (e.key === "ArrowUp") {
				e.preventDefault();
				if (selectedRowIdx > 0) {
					selectedRowIdx--;
					highlightRow(eventRows, selectedRowIdx);
				}
			} else if (e.key === " " && selectedRowIdx >= 0) {
				e.preventDefault();
				toggleDetail(selectedRowIdx);
			}
		});
	}

	function toggleDetail(idx) {
		var detailRow = document.querySelector('tr.audit-detail-row[data-detail="' + idx + '"]');
		if (detailRow) {
			detailRow.style.display = (detailRow.style.display === "none") ? "" : "none";
		}
	}

	function highlightRow(eventRows, idx) {
		for (var i = 0; i < eventRows.length; i++) {
			eventRows[i].style.outline = "none";
		}
		if (eventRows[idx]) {
			eventRows[idx].style.outline = "2px solid #3498db";
			eventRows[idx].scrollIntoView({block: "nearest", behavior: "smooth"});
		}
	}

	function updateSortHeaders() {
		var ths = document.querySelectorAll("#audit-result-table thead th[data-sort]");
		for (var i = 0; i < ths.length; i++) {
			var col = ths[i].getAttribute("data-sort");
			var arrow = ths[i].querySelector(".sort-arrow");
			if (col === sortField) {
				arrow.className = "sort-arrow active";
				arrow.innerHTML = (sortDir === "DESC") ? "&#9660;" : "&#9650;";
			} else {
				arrow.className = "sort-arrow";
				arrow.innerHTML = "&#9650;";
			}
		}
	}

	function initDateInputs() {
		initDateInput(document.getElementById("audit-date-from"));
		initDateInput(document.getElementById("audit-date-to"));
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function() {
			initDateInputs();
			loadFilterValues();
			initEvents();
			applyPreset();
			doSearch();
		});
	} else {
		initDateInputs();
		loadFilterValues();
		initEvents();
		applyPreset();
		doSearch();
	}
})();
</script>
