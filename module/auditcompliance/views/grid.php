<?php
$timeline = $timeline ?? array();
$authFailures = $authFailures ?? array();
$actorFilter = $actorFilter ?? '';
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<style>
.audit-search-tabs { margin-bottom: 20px; }

.audit-timeline-filter {
	display: flex;
	gap: 10px;
	align-items: flex-end;
	margin-bottom: 20px;
	padding: 14px 18px;
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.audit-timeline-filter label {
	font-weight: 600;
	font-size: 11px;
	text-transform: uppercase;
	color: #6c757d;
	letter-spacing: 0.3px;
	margin-bottom: 3px;
	display: block;
}
.audit-timeline-filter .form-group { margin-bottom: 0; }

.audit-failures-banner {
	background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
	border: 1px solid #ef9a9a;
	border-radius: 8px;
	padding: 16px 20px;
	margin-bottom: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.audit-failures-title {
	font-size: 14px;
	font-weight: 700;
	color: #c62828;
	margin-bottom: 10px;
}
.audit-failures-title i { margin-right: 6px; }
.audit-failure-item {
	display: flex;
	gap: 16px;
	padding: 6px 0;
	font-size: 13px;
	border-bottom: 1px solid rgba(239,154,154,0.3);
	align-items: center;
}
.audit-failure-item:last-child { border-bottom: none; }
.audit-failure-time { color: #c62828; font-weight: 600; white-space: nowrap; min-width: 160px; }
.audit-failure-user { color: #d32f2f; }
.audit-failure-ip { color: #e57373; font-family: monospace; font-size: 12px; }

.audit-tl-container { position: relative; padding-left: 28px; }
.audit-tl-line {
	position: absolute;
	left: 13px;
	top: 0;
	bottom: 0;
	width: 2px;
	background: #dee2e6;
}

.audit-session-card {
	position: relative;
	margin-bottom: 24px;
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	box-shadow: 0 1px 4px rgba(0,0,0,0.06);
	overflow: hidden;
	transition: box-shadow 0.2s;
}
.audit-session-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
.audit-session-card::before {
	content: '';
	position: absolute;
	left: -22px;
	top: 18px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	border: 2px solid #fff;
	box-shadow: 0 0 0 2px #dee2e6;
	z-index: 1;
}
.audit-session-card.session-active::before { background: #27ae60; box-shadow: 0 0 0 2px #27ae60; }
.audit-session-card.session-closed::before { background: #95a5a6; box-shadow: 0 0 0 2px #95a5a6; }
.audit-session-card.session-timeout::before { background: #e67e22; box-shadow: 0 0 0 2px #e67e22; }

.audit-session-head {
	padding: 14px 18px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	cursor: pointer;
	user-select: none;
	transition: background 0.1s;
	flex-wrap: wrap;
	gap: 8px;
}
.audit-session-head:hover { background: #f8f9fa; }
.audit-session-head-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.audit-session-head-right { display: flex; align-items: center; gap: 10px; }

.audit-session-actor {
	font-size: 15px;
	font-weight: 700;
	color: #212529;
}
.audit-session-meta {
	font-size: 12px;
	color: #6c757d;
}
.audit-session-meta i { margin-right: 3px; }
.audit-session-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.badge-active { background: #e8f5e9; color: #2e7d32; }
.badge-logout { background: #e3f2fd; color: #1565c0; }
.badge-timeout { background: #fff3e0; color: #e65100; }
.audit-session-count {
	background: #f0f0f0;
	color: #495057;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
}
.audit-session-duration {
	font-size: 11px;
	color: #adb5bd;
}
.audit-session-chevron {
	transition: transform 0.2s;
	color: #adb5bd;
	font-size: 12px;
}
.audit-session-card.open .audit-session-chevron { transform: rotate(180deg); }

.audit-session-events {
	display: none;
	border-top: 1px solid #f0f0f0;
}
.audit-session-card.open .audit-session-events { display: block; }

.audit-evt-list { list-style: none; margin: 0; padding: 0; }
.audit-evt-item {
	padding: 8px 18px;
	border-bottom: 1px solid #f8f8f8;
	font-size: 13px;
	display: flex;
	align-items: center;
	gap: 10px;
	transition: background 0.1s;
}
.audit-evt-item:hover { background: #f8f9fa; }
.audit-evt-item:last-child { border-bottom: none; }

.audit-evt-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;
}
.dot-login { background: #27ae60; }
.dot-logout { background: #2980b9; }
.dot-timeout { background: #e67e22; }
.dot-failure { background: #c0392b; }
.dot-activity { background: #bdc3c7; }

.audit-evt-time {
	font-size: 12px;
	color: #6c757d;
	white-space: nowrap;
	min-width: 140px;
}
.audit-evt-detail {
	flex: 1;
	min-width: 0;
	color: #495057;
}
.audit-evt-module { font-weight: 600; color: #212529; }
.audit-evt-action { color: #495057; }
.audit-evt-channel-badge {
	display: inline-block;
	padding: 1px 6px;
	border-radius: 3px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
}
.ch-gui { background: #e3f2fd; color: #1565c0; }
.ch-ajax { background: #fff8e1; color: #f57f17; }
.ch-hook { background: #e8f5e9; color: #2e7d32; }
.ch-auth { background: #fce4ec; color: #c62828; }
.ch-rest { background: #f3e5f5; color: #6a1b9a; }
.audit-evt-outcome {
	display: inline-block;
	padding: 1px 5px;
	border-radius: 3px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
}
.outcome-ok { background: #e8f5e9; color: #2e7d32; }
.outcome-fail { background: #ffebee; color: #c62828; }

.audit-evt-boundary {
	background: #fafbfc;
	font-weight: 600;
}
.audit-evt-boundary.boundary-login { border-left: 3px solid #27ae60; }
.audit-evt-boundary.boundary-logout { border-left: 3px solid #2980b9; }
.audit-evt-boundary.boundary-timeout { border-left: 3px solid #e67e22; }
.audit-evt-boundary.boundary-failure { border-left: 3px solid #c0392b; }

.audit-session-footer {
	padding: 10px 18px;
	border-top: 1px solid #f0f0f0;
	background: #fafbfc;
	font-size: 12px;
	color: #6c757d;
	display: flex;
	align-items: center;
	gap: 8px;
}
.audit-session-footer i { margin-right: 4px; }

.audit-tl-empty {
	text-align: center;
	padding: 40px 20px;
	color: #adb5bd;
	font-size: 14px;
}
.audit-tl-empty i { display: block; font-size: 32px; margin-bottom: 10px; }
</style>

<div class="container-fluid">
	<h1><?php echo _('Audit Compliance'); ?></h1>

	<ul class="nav nav-tabs audit-search-tabs" style="margin-bottom: 20px;">
		<li><a href="?display=auditcompliance&view=dashboard"><i class="fa fa-tachometer"></i> <?php echo _('Dashboard'); ?></a></li>
		<li><a href="?display=auditcompliance&view=search"><i class="fa fa-search"></i> <?php echo _('Search'); ?></a></li>
		<li class="active"><a href="?display=auditcompliance&view=timeline"><i class="fa fa-clock-o"></i> <?php echo _('Session Timeline'); ?></a></li>
		<li><a href="?display=auditcompliance&view=discovery"><i class="fa fa-puzzle-piece"></i> <?php echo _('Module Discovery'); ?></a></li>
	</ul>

	<div class="display full-border">
		<div class="fpbx-container">

			<div class="audit-timeline-filter">
				<form method="get" class="form-inline" style="display:flex;gap:10px;align-items:flex-end;width:100%;">
					<input type="hidden" name="display" value="auditcompliance"/>
					<input type="hidden" name="view" value="timeline"/>
					<div class="form-group">
						<label for="actor"><i class="fa fa-user" style="margin-right:3px;"></i> <?php echo _('Actor'); ?></label>
						<input type="text" id="actor" name="actor"
							value="<?php echo $esc($actorFilter); ?>"
							class="form-control input-sm"
							placeholder="<?php echo _('Filter by user...'); ?>"/>
					</div>
					<button type="submit" class="btn btn-primary btn-sm">
						<i class="fa fa-filter"></i> <?php echo _('Filter'); ?>
					</button>
					<?php if ($actorFilter): ?>
					<a href="?display=auditcompliance&view=timeline" class="btn btn-default btn-sm">
						<i class="fa fa-times"></i> <?php echo _('Clear'); ?>
					</a>
					<?php endif; ?>
				</form>
			</div>

			<?php if (!empty($authFailures)): ?>
			<div class="audit-failures-banner">
				<div class="audit-failures-title">
					<i class="fa fa-exclamation-triangle"></i>
					<?php echo _('Authentication Failures'); ?>
					<span style="font-weight:400;font-size:12px;margin-left:4px;">(<?php echo count($authFailures); ?>)</span>
				</div>
				<?php foreach ($authFailures as $fail): ?>
				<div class="audit-failure-item">
					<span class="audit-failure-time"><i class="fa fa-clock-o"></i> <?php echo $esc($fail['occurred_at_local']); ?></span>
					<span class="audit-failure-user"><i class="fa fa-user-times"></i> <?php echo $esc($fail['actor']); ?></span>
					<span class="audit-failure-ip"><i class="fa fa-globe"></i> <?php echo $esc($fail['source_ip']); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if (empty($timeline)): ?>
				<div class="audit-tl-empty">
					<i class="fa fa-clock-o"></i>
					<?php echo _('No session audit records found.'); ?>
					<?php if ($actorFilter): ?>
						<br><small><?php echo _('Try clearing the actor filter.'); ?></small>
					<?php endif; ?>
				</div>
			<?php else: ?>

			<div class="audit-tl-container">
				<div class="audit-tl-line"></div>

				<?php foreach ($timeline as $idx => $item): ?>
				<?php
					$session = $item['session'];
					$events = $item['events'];
					$isActive = (empty($session['end_reason']) || $session['end_reason'] === 'active');
					$endReason = $isActive ? 'active' : (string) $session['end_reason'];
					$cardClass = $isActive ? 'session-active' : ($endReason === 'timeout' ? 'session-timeout' : 'session-closed');
					$badgeClass = $isActive ? 'badge-active' : ($endReason === 'timeout' ? 'badge-timeout' : 'badge-logout');
					$badgeLabel = $isActive ? _('Active') : strtoupper($endReason);

					$duration = '';
					if (!empty($session['login_at_unix']) && !$isActive) {
						$endUnix = $session['end_at_unix'] ?? time();
						$durSec = max(0, (int)$endUnix - (int)$session['login_at_unix']);
						if ($durSec < 60) { $duration = $durSec . 's'; }
						elseif ($durSec < 3600) { $duration = floor($durSec / 60) . 'm ' . ($durSec % 60) . 's'; }
						else { $hours = floor($durSec / 3600); $mins = floor(($durSec % 3600) / 60); $duration = $hours . 'h ' . $mins . 'm'; }
					}
				?>
				<div class="audit-session-card <?php echo $cardClass; ?>" data-session="<?php echo $idx; ?>">
					<div class="audit-session-head" onclick="this.parentElement.classList.toggle('open');">
						<div class="audit-session-head-left">
							<span class="audit-session-actor">
								<i class="fa fa-user-circle-o" style="margin-right:4px;color:#adb5bd;"></i>
								<?php echo $esc($session['actor']); ?>
							</span>
							<span class="audit-session-meta">
								<i class="fa fa-clock-o"></i> <?php echo $esc($session['login_at_local']); ?>
							</span>
							<span class="audit-session-meta">
								<i class="fa fa-globe"></i> <?php echo $esc($session['source_ip']); ?>
							</span>
						</div>
						<div class="audit-session-head-right">
							<?php if ($duration): ?>
							<span class="audit-session-duration">
								<i class="fa fa-hourglass-half"></i> <?php echo $esc($duration); ?>
							</span>
							<?php endif; ?>
							<span class="audit-session-count">
								<i class="fa fa-list-ul" style="margin-right:3px;"></i><?php echo (int) $session['event_count']; ?>
							</span>
							<span class="audit-session-badge <?php echo $badgeClass; ?>"><?php echo $esc($badgeLabel); ?></span>
							<i class="fa fa-chevron-down audit-session-chevron"></i>
						</div>
					</div>

					<?php if (!empty($events)): ?>
					<div class="audit-session-events">
						<ul class="audit-evt-list">
							<?php foreach ($events as $event): ?>
							<?php
								$phase = (string) ($event['session_phase'] ?? 'activity');
								$isBoundary = in_array($phase, array('login', 'logout', 'timeout', 'failure'), true);
								$dotClass = 'dot-' . $phase;
								$liClass = $isBoundary ? ('audit-evt-boundary boundary-' . $phase) : '';
								$chClass = 'ch-' . ($event['channel'] ?? 'gui');
								$outClass = ($event['outcome'] ?? 'success') === 'success' ? 'outcome-ok' : 'outcome-fail';
							?>
							<li class="audit-evt-item <?php echo $liClass; ?>">
								<span class="audit-evt-dot <?php echo $dotClass; ?>"></span>
								<span class="audit-evt-time"><?php echo $esc($event['occurred_at_local']); ?></span>
								<?php if ($isBoundary): ?>
									<span class="audit-evt-detail">
										<strong><?php echo $esc(strtoupper($phase)); ?></strong>
									</span>
								<?php else: ?>
									<span class="audit-evt-detail">
										<span class="audit-evt-module"><?php echo $esc($event['module_name']); ?></span>
										<span class="audit-evt-action"> / <?php echo $esc($event['action']); ?></span>
									</span>
									<span class="audit-evt-channel-badge <?php echo $chClass; ?>"><?php echo $esc($event['channel'] ?? ''); ?></span>
									<span class="audit-evt-outcome <?php echo $outClass; ?>"><?php echo $esc($event['outcome'] ?? ''); ?></span>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>

					<?php if (!$isActive && !empty($session['end_at_local'])): ?>
					<div class="audit-session-footer">
						<i class="fa fa-<?php echo $endReason === 'timeout' ? 'clock-o' : 'sign-out'; ?>"></i>
						<?php echo _('Ended:'); ?>
						<strong><?php echo $esc($session['end_at_local']); ?></strong>
						<?php if ($duration): ?>
						&middot; <?php echo _('Duration:'); ?> <?php echo $esc($duration); ?>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>

			<?php endif; ?>

		</div>
	</div>
</div>

<script type="text/javascript">
(function() {
	"use strict";
	var cards = document.querySelectorAll(".audit-session-card");
	if (cards.length > 0 && cards.length <= 3) {
		for (var i = 0; i < cards.length; i++) {
			cards[i].classList.add("open");
		}
	} else if (cards.length > 0) {
		cards[0].classList.add("open");
	}
})();
</script>
