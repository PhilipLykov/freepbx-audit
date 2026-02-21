<?php
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<style>
.audit-dash-tabs { margin-bottom: 0; }
.audit-dash-hero {
	background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
	color: #fff;
	padding: 28px 24px 20px;
	border-radius: 6px 6px 0 0;
	margin-bottom: 0;
}
.audit-dash-hero h1 {
	margin: 0 0 4px;
	font-size: 22px;
	font-weight: 600;
	color: #fff;
}
.audit-dash-hero .subtitle {
	font-size: 13px;
	opacity: 0.85;
}
.audit-kpi-row {
	display: flex;
	gap: 16px;
	padding: 20px 24px;
	background: #f8f9fa;
	border-left: 1px solid #dee2e6;
	border-right: 1px solid #dee2e6;
	flex-wrap: wrap;
}
.audit-kpi-card {
	flex: 1;
	min-width: 140px;
	background: #fff;
	border-radius: 8px;
	padding: 16px 18px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.08);
	border: 1px solid #e9ecef;
	transition: box-shadow 0.15s, transform 0.15s;
	cursor: default;
}
.audit-kpi-card:hover {
	box-shadow: 0 4px 12px rgba(0,0,0,0.12);
	transform: translateY(-1px);
}
.audit-kpi-card .kpi-value {
	font-size: 28px;
	font-weight: 700;
	line-height: 1.1;
	margin-bottom: 4px;
}
.audit-kpi-card .kpi-label {
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #6c757d;
	font-weight: 600;
}
.audit-kpi-card .kpi-icon {
	float: right;
	font-size: 20px;
	opacity: 0.25;
	margin-top: -2px;
}
.kpi-blue .kpi-value { color: #2980b9; }
.kpi-green .kpi-value { color: #27ae60; }
.kpi-red .kpi-value { color: #c0392b; }
.kpi-orange .kpi-value { color: #e67e22; }
.kpi-purple .kpi-value { color: #8e44ad; }

.audit-dash-body {
	display: flex;
	gap: 20px;
	padding: 20px 24px 24px;
	background: #f8f9fa;
	border: 1px solid #dee2e6;
	border-top: none;
	border-radius: 0 0 6px 6px;
	flex-wrap: wrap;
}
.audit-dash-main {
	flex: 3;
	min-width: 350px;
}
.audit-dash-side {
	flex: 1;
	min-width: 220px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.audit-card {
	background: #fff;
	border: 1px solid #e9ecef;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.audit-card-header {
	padding: 12px 16px;
	border-bottom: 1px solid #f0f0f0;
	font-size: 13px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.4px;
	color: #495057;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.audit-card-header .badge-live {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 10px;
	font-weight: 700;
	background: #e8f5e9;
	color: #2e7d32;
	animation: audit-pulse 2s ease-in-out infinite;
}
@keyframes audit-pulse {
	0%, 100% { opacity: 1; }
	50% { opacity: 0.5; }
}
.audit-card-body { padding: 0; }

.audit-feed-list { list-style: none; margin: 0; padding: 0; }
.audit-feed-item {
	padding: 10px 16px;
	border-bottom: 1px solid #f5f5f5;
	font-size: 13px;
	display: flex;
	gap: 10px;
	align-items: flex-start;
	transition: background 0.1s;
}
.audit-feed-item:hover { background: #f8f9fa; }
.audit-feed-item:last-child { border-bottom: none; }
.audit-feed-icon {
	width: 30px;
	height: 30px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	flex-shrink: 0;
	margin-top: 2px;
}
.feed-icon-gui { background: #e3f2fd; color: #1565c0; }
.feed-icon-ajax { background: #fff8e1; color: #f57f17; }
.feed-icon-hook { background: #e8f5e9; color: #2e7d32; }
.feed-icon-auth { background: #fce4ec; color: #c62828; }
.feed-icon-rest { background: #f3e5f5; color: #6a1b9a; }
.feed-icon-default { background: #f5f5f5; color: #616161; }
.audit-feed-content { flex: 1; min-width: 0; }
.audit-feed-actor { font-weight: 600; color: #212529; }
.audit-feed-action { color: #495057; }
.audit-feed-module { color: #6c757d; font-size: 12px; }
.audit-feed-time {
	font-size: 11px;
	color: #adb5bd;
	white-space: nowrap;
	margin-top: 3px;
}
.audit-feed-outcome {
	display: inline-block;
	padding: 1px 5px;
	border-radius: 3px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
}
.outcome-success { background: #e8f5e9; color: #2e7d32; }
.outcome-failure { background: #ffebee; color: #c62828; }

.audit-side-card-body { padding: 12px 16px; }
.audit-actor-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 6px 0;
	font-size: 13px;
	border-bottom: 1px solid #f5f5f5;
}
.audit-actor-row:last-child { border-bottom: none; }
.audit-actor-name { font-weight: 600; color: #333; }
.audit-actor-count {
	background: #e3f2fd;
	color: #1565c0;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: 700;
}

.audit-channel-bar {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 5px 0;
	font-size: 12px;
}
.audit-channel-label { width: 40px; text-transform: uppercase; font-weight: 700; font-size: 10px; color: #6c757d; }
.audit-channel-track {
	flex: 1;
	height: 8px;
	background: #f0f0f0;
	border-radius: 4px;
	overflow: hidden;
}
.audit-channel-fill {
	height: 100%;
	border-radius: 4px;
	transition: width 0.5s ease;
}
.channel-fill-gui { background: #42a5f5; }
.channel-fill-ajax { background: #ffca28; }
.channel-fill-hook { background: #66bb6a; }
.channel-fill-auth { background: #ef5350; }
.channel-fill-rest { background: #ab47bc; }
.audit-channel-count { font-size: 11px; color: #868e96; width: 30px; text-align: right; }

.audit-quick-actions { padding: 12px 16px; display: flex; flex-direction: column; gap: 6px; }
.audit-quick-action {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 8px 12px;
	background: #f8f9fa;
	border: 1px solid #e9ecef;
	border-radius: 6px;
	color: #495057;
	text-decoration: none;
	font-size: 13px;
	font-weight: 500;
	transition: background 0.1s, border-color 0.1s;
}
.audit-quick-action:hover {
	background: #e9ecef;
	border-color: #ced4da;
	color: #212529;
	text-decoration: none;
}
.audit-quick-action i { font-size: 14px; width: 18px; text-align: center; color: #6c757d; }

.audit-dash-loading {
	text-align: center;
	padding: 60px 20px;
	color: #adb5bd;
}
.audit-dash-loading i { font-size: 24px; margin-bottom: 10px; display: block; }
.audit-dash-empty {
	text-align: center;
	padding: 30px 16px;
	color: #adb5bd;
	font-size: 13px;
}
</style>

<div class="container-fluid">
	<h1><?php echo _('Audit Compliance'); ?></h1>

	<ul class="nav nav-tabs audit-dash-tabs">
		<li class="active"><a href="?display=auditcompliance&view=dashboard"><i class="fa fa-tachometer"></i> <?php echo _('Dashboard'); ?></a></li>
		<li><a href="?display=auditcompliance&view=search"><i class="fa fa-search"></i> <?php echo _('Search'); ?></a></li>
		<li><a href="?display=auditcompliance&view=timeline"><i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?></a></li>
		<li><a href="?display=auditcompliance&view=discovery"><i class="fa fa-puzzle-piece"></i> <?php echo _('Module Discovery'); ?></a></li>
	</ul>

	<div class="audit-dash-hero">
		<h1><i class="fa fa-shield" style="margin-right: 8px;"></i><?php echo _('Audit Compliance'); ?></h1>
		<span class="subtitle"><?php echo _('Administrator activity monitoring and compliance log'); ?></span>
	</div>

	<div class="audit-kpi-row" id="audit-kpi-row">
		<div class="audit-kpi-card kpi-blue">
			<i class="fa fa-calendar kpi-icon"></i>
			<div class="kpi-value" id="kpi-events-today"><i class="fa fa-spinner fa-spin" style="font-size:18px;color:#ccc;"></i></div>
			<div class="kpi-label"><?php echo _('Events Today'); ?></div>
		</div>
		<div class="audit-kpi-card kpi-green">
			<i class="fa fa-users kpi-icon"></i>
			<div class="kpi-value" id="kpi-active-sessions"><i class="fa fa-spinner fa-spin" style="font-size:18px;color:#ccc;"></i></div>
			<div class="kpi-label"><?php echo _('Active Sessions'); ?></div>
		</div>
		<div class="audit-kpi-card kpi-red">
			<i class="fa fa-ban kpi-icon"></i>
			<div class="kpi-value" id="kpi-auth-failures"><i class="fa fa-spinner fa-spin" style="font-size:18px;color:#ccc;"></i></div>
			<div class="kpi-label"><?php echo _('Auth Failures (24h)'); ?></div>
		</div>
		<div class="audit-kpi-card kpi-orange">
			<i class="fa fa-eye kpi-icon"></i>
			<div class="kpi-value" id="kpi-sensitive-reads"><i class="fa fa-spinner fa-spin" style="font-size:18px;color:#ccc;"></i></div>
			<div class="kpi-label"><?php echo _('Sensitive Reads (24h)'); ?></div>
		</div>
		<div class="audit-kpi-card kpi-purple">
			<i class="fa fa-database kpi-icon"></i>
			<div class="kpi-value" id="kpi-events-total"><i class="fa fa-spinner fa-spin" style="font-size:18px;color:#ccc;"></i></div>
			<div class="kpi-label"><?php echo _('Total Audit Events'); ?></div>
		</div>
	</div>

	<div class="audit-dash-body">
		<div class="audit-dash-main">
			<div class="audit-card">
				<div class="audit-card-header">
					<span><i class="fa fa-bolt" style="margin-right: 6px;"></i><?php echo _('Recent Activity'); ?></span>
					<span class="badge-live"><?php echo _('LIVE'); ?></span>
				</div>
				<div class="audit-card-body" id="audit-feed-container">
					<div class="audit-dash-loading">
						<i class="fa fa-spinner fa-spin"></i>
						<?php echo _('Loading recent events...'); ?>
					</div>
				</div>
			</div>
		</div>

		<div class="audit-dash-side">
			<div class="audit-card">
				<div class="audit-card-header">
					<span><i class="fa fa-user" style="margin-right: 6px;"></i><?php echo _('Top Actors Today'); ?></span>
				</div>
				<div class="audit-side-card-body" id="audit-top-actors">
					<div class="audit-dash-empty"><?php echo _('Loading...'); ?></div>
				</div>
			</div>

			<div class="audit-card">
				<div class="audit-card-header">
					<span><i class="fa fa-pie-chart" style="margin-right: 6px;"></i><?php echo _('Channels Today'); ?></span>
				</div>
				<div class="audit-side-card-body" id="audit-channel-breakdown">
					<div class="audit-dash-empty"><?php echo _('Loading...'); ?></div>
				</div>
			</div>

			<div class="audit-card">
				<div class="audit-card-header">
					<span><i class="fa fa-arrow-right" style="margin-right: 6px;"></i><?php echo _('Quick Actions'); ?></span>
				</div>
				<div class="audit-quick-actions">
					<a href="?display=auditcompliance&view=search" class="audit-quick-action">
						<i class="fa fa-search"></i> <?php echo _('Search All Events'); ?>
					</a>
					<a href="?display=auditcompliance&view=timeline" class="audit-quick-action">
						<i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?>
					</a>
					<a href="?display=auditcompliance&view=search&preset=failures" class="audit-quick-action">
						<i class="fa fa-exclamation-triangle"></i> <?php echo _('Auth Failures'); ?>
					</a>
					<a href="?display=auditcompliance&view=discovery" class="audit-quick-action">
						<i class="fa fa-puzzle-piece"></i> <?php echo _('Module Coverage'); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
(function() {
	"use strict";

	var AJAX_BASE = "ajax.php?module=auditcompliance&command=";

	function esc(str) {
		var d = document.createElement("div");
		d.appendChild(document.createTextNode(String(str || "")));
		return d.innerHTML;
	}

	function relativeTime(unixTs) {
		var now = Math.floor(Date.now() / 1000);
		var diff = now - parseInt(unixTs, 10);
		if (isNaN(diff) || diff < 0) return "";
		if (diff < 60) return diff + "s ago";
		if (diff < 3600) return Math.floor(diff / 60) + "m ago";
		if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
		return Math.floor(diff / 86400) + "d ago";
	}

	function formatNumber(n) {
		n = parseInt(n, 10) || 0;
		if (n >= 1000000) return (n / 1000000).toFixed(1) + "M";
		if (n >= 1000) return (n / 1000).toFixed(1) + "K";
		return String(n);
	}

	function channelIcon(ch) {
		var map = {gui:"fa-desktop",ajax:"fa-exchange",hook:"fa-link",auth:"fa-lock",rest:"fa-plug"};
		return map[ch] || "fa-circle-o";
	}

	function channelIconClass(ch) {
		var map = {gui:"feed-icon-gui",ajax:"feed-icon-ajax",hook:"feed-icon-hook",auth:"feed-icon-auth",rest:"feed-icon-rest"};
		return map[ch] || "feed-icon-default";
	}

	function loadDashboard() {
		var url = AJAX_BASE + "getDashboardStats";
		var xhr = new XMLHttpRequest();
		xhr.open("GET", url, true);
		xhr.setRequestHeader("Accept", "application/json");
		xhr.timeout = 15000;
		xhr.onload = function() {
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var data = JSON.parse(xhr.responseText);
					renderDashboard(data);
				} catch (e) {
					showError("Failed to parse dashboard data");
				}
			} else {
				showError("HTTP " + xhr.status);
			}
		};
		xhr.onerror = function() { showError("Network error"); };
		xhr.ontimeout = function() { showError("Request timeout"); };
		xhr.send();
	}

	function showError(msg) {
		var el = document.getElementById("audit-feed-container");
		if (el) el.innerHTML = '<div class="audit-dash-empty" style="color:#c0392b;"><i class="fa fa-exclamation-circle"></i> ' + esc(msg) + '</div>';
	}

	function renderDashboard(data) {
		document.getElementById("kpi-events-today").textContent = formatNumber(data.events_today);
		document.getElementById("kpi-active-sessions").textContent = formatNumber(data.active_sessions);
		document.getElementById("kpi-auth-failures").textContent = formatNumber(data.auth_failures_24h);
		document.getElementById("kpi-sensitive-reads").textContent = formatNumber(data.sensitive_reads_24h);
		document.getElementById("kpi-events-total").textContent = formatNumber(data.events_total);

		if (data.auth_failures_24h > 0) {
			document.getElementById("kpi-auth-failures").parentElement.style.borderLeft = "3px solid #c0392b";
		}

		renderRecentFeed(data.recent_events || []);
		renderTopActors(data.top_actors || []);
		renderChannelBreakdown(data.channel_breakdown || [], data.events_today || 0);
	}

	function renderRecentFeed(events) {
		var container = document.getElementById("audit-feed-container");
		if (events.length === 0) {
			container.innerHTML = '<div class="audit-dash-empty"><i class="fa fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>' +
				'No events recorded yet. Activity will appear here as administrators use the system.</div>';
			return;
		}
		var html = '<ul class="audit-feed-list">';
		for (var i = 0; i < events.length; i++) {
			var ev = events[i];
			var iconCls = channelIconClass(ev.channel);
			var icon = channelIcon(ev.channel);
			var outCls = ev.outcome === "success" ? "outcome-success" : "outcome-failure";
			html += '<li class="audit-feed-item">';
			html += '<div class="audit-feed-icon ' + iconCls + '"><i class="fa ' + icon + '"></i></div>';
			html += '<div class="audit-feed-content">';
			html += '<span class="audit-feed-actor">' + esc(ev.actor) + '</span> ';
			html += '<span class="audit-feed-action">' + esc(ev.action) + '</span> ';
			html += '<span class="audit-feed-outcome ' + outCls + '">' + esc(ev.outcome) + '</span>';
			html += '<div class="audit-feed-module">' + esc(ev.module_name) + ' &middot; ' + esc(ev.channel) + '</div>';
			html += '</div>';
			html += '<div class="audit-feed-time" title="' + esc(ev.occurred_at_local) + '">' + relativeTime(ev.occurred_at_unix) + '</div>';
			html += '</li>';
		}
		html += '</ul>';
		container.innerHTML = html;
	}

	function renderTopActors(actors) {
		var container = document.getElementById("audit-top-actors");
		if (actors.length === 0) {
			container.innerHTML = '<div class="audit-dash-empty">No activity today</div>';
			return;
		}
		var html = '';
		for (var i = 0; i < actors.length; i++) {
			html += '<div class="audit-actor-row">';
			html += '<span class="audit-actor-name"><i class="fa fa-user-circle-o" style="margin-right:6px;color:#adb5bd;"></i>' + esc(actors[i].actor) + '</span>';
			html += '<span class="audit-actor-count">' + esc(actors[i].cnt) + '</span>';
			html += '</div>';
		}
		container.innerHTML = html;
	}

	function renderChannelBreakdown(channels, total) {
		var container = document.getElementById("audit-channel-breakdown");
		if (channels.length === 0 || total === 0) {
			container.innerHTML = '<div class="audit-dash-empty">No events today</div>';
			return;
		}
		var html = '';
		for (var i = 0; i < channels.length; i++) {
			var ch = channels[i];
			var pct = Math.max(1, Math.round((parseInt(ch.cnt, 10) / total) * 100));
			var fillClass = "channel-fill-" + (ch.channel || "gui");
			html += '<div class="audit-channel-bar">';
			html += '<span class="audit-channel-label">' + esc(ch.channel) + '</span>';
			html += '<div class="audit-channel-track"><div class="audit-channel-fill ' + fillClass + '" style="width:' + pct + '%;"></div></div>';
			html += '<span class="audit-channel-count">' + esc(ch.cnt) + '</span>';
			html += '</div>';
		}
		container.innerHTML = html;
	}

	function init() {
		loadDashboard();
		setInterval(loadDashboard, 30000);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
</script>
