<?php
require_once __DIR__ . '/_common.php';

function setmaxx_requests_ensure_suggestions_table(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_song_suggestions')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_song_suggestions` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `gig_session_id` bigint(20) unsigned DEFAULT NULL,
          `user_id` int(10) unsigned NOT NULL,
          `suggested_title` varchar(190) NOT NULL,
          `suggested_artist` varchar(190) DEFAULT NULL,
          `requester_name` varchar(190) DEFAULT NULL,
          `suggestion_note` varchar(255) DEFAULT NULL,
          `status` enum('new','reviewed','added','dismissed') NOT NULL DEFAULT 'new',
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_setmaxx_suggestions_user` (`user_id`,`status`,`created_at`),
          KEY `idx_setmaxx_suggestions_session` (`gig_session_id`,`created_at`),
          CONSTRAINT `fk_setmaxx_suggestions_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_setmaxx_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_requests_ensure_mailing_list_table(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_mailing_list_signups')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_mailing_list_signups` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `gig_session_id` bigint(20) unsigned DEFAULT NULL,
          `user_id` int(10) unsigned NOT NULL,
          `email` varchar(190) NOT NULL,
          `first_name` varchar(100) DEFAULT NULL,
          `source` varchar(80) NOT NULL DEFAULT 'setmaxx_public_page',
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_mailing_user_email` (`user_id`,`email`),
          KEY `idx_setmaxx_mailing_user_created` (`user_id`,`created_at`),
          KEY `idx_setmaxx_mailing_session` (`gig_session_id`,`created_at`),
          CONSTRAINT `fk_setmaxx_mailing_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_setmaxx_mailing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_requests_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_requests_ensure_payment_method_columns(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_requests') && !setmaxx_requests_column_exists($pdo, 'setmaxx_requests', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_requests ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
    if (setmaxx_table_exists($pdo, 'setmaxx_general_tips') && !setmaxx_requests_column_exists($pdo, 'setmaxx_general_tips', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_general_tips ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
}

if (!empty($_SESSION['setmaxx_requests_messages']) && is_array($_SESSION['setmaxx_requests_messages'])) {
    $messages = array_merge($messages, $_SESSION['setmaxx_requests_messages']);
    unset($_SESSION['setmaxx_requests_messages']);
}

if (!empty($_SESSION['setmaxx_requests_errors']) && is_array($_SESSION['setmaxx_requests_errors'])) {
    $errors = array_merge($errors, $_SESSION['setmaxx_requests_errors']);
    unset($_SESSION['setmaxx_requests_errors']);
}

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $newStatus = (string)($_POST['new_status'] ?? '');
            $allowedTransitions = [
                'pending' => ['played', 'declined'],
                'queued' => ['played', 'declined'],
                'played' => [],
                'declined' => [],
                'canceled' => [],
            ];
            if ($requestId <= 0) throw new RuntimeException('Invalid request update.');
            $currentStmt = $pdo->prepare("SELECT r.status FROM setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id WHERE r.id = ? AND gs.user_id = ? LIMIT 1");
            $currentStmt->execute([$requestId, $userId]);
            $currentStatus = (string)($currentStmt->fetchColumn() ?: '');
            if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
                throw new RuntimeException('That request status cannot be changed that way.');
            }
            $activeLock = in_array($newStatus, ['declined', 'canceled'], true) ? null : 1;
            $stmt = $pdo->prepare("UPDATE setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id SET r.status = ?, r.active_lock = ? WHERE r.id = ? AND gs.user_id = ?");
            $stmt->execute([$newStatus, $activeLock, $requestId, $userId]);
            $messages[] = 'Request updated.';
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

if (is_post()) {
    $_SESSION['setmaxx_requests_messages'] = $messages;
    $_SESSION['setmaxx_requests_errors'] = $errors;
    header('Location: ' . base_url('/setmaxx/requests.php'));
    exit;
}

$liveSession = null;
$requests = [];
$suggestions = [];
$generalTips = [];
$mailingSignups = [];
$suggestionsReady = false;
$generalTipsReady = false;
$mailingReady = false;
if ($tablesReady) {
    try {
        setmaxx_requests_ensure_suggestions_table($pdo);
        setmaxx_requests_ensure_payment_method_columns($pdo);
        setmaxx_requests_ensure_mailing_list_table($pdo);
        $suggestionsReady = true;
        $mailingReady = true;
    } catch (Throwable $e) {
        $errors[] = 'Song suggestions could not be loaded right now.';
    }

    if ($mailingReady && isset($_GET['export_mailing'])) {
        $exportStmt = $pdo->prepare(
            "SELECT m.email, m.first_name, m.source, m.created_at, m.updated_at, gs.title AS session_title, gs.venue_name
             FROM setmaxx_mailing_list_signups m
             LEFT JOIN setmaxx_gig_sessions gs ON gs.id = m.gig_session_id
             WHERE m.user_id = ?
             ORDER BY m.created_at DESC"
        );
        $exportStmt->execute([$userId]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="setmaxx-mailing-list.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['email', 'first_name', 'source', 'session_title', 'venue_name', 'created_at', 'updated_at']);
        foreach ($exportStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            fputcsv($out, [
                (string)$row['email'],
                (string)($row['first_name'] ?? ''),
                (string)($row['source'] ?? ''),
                (string)($row['session_title'] ?? ''),
                (string)($row['venue_name'] ?? ''),
                (string)$row['created_at'],
                (string)$row['updated_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    if (!setmaxx_table_exists($pdo, 'setmaxx_general_tips')) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `setmaxx_general_tips` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `gig_session_id` bigint(20) unsigned DEFAULT NULL,
                  `user_id` int(10) unsigned NOT NULL,
                  `tipper_name` varchar(190) DEFAULT NULL,
                  `tip_note` varchar(255) DEFAULT NULL,
                  `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
                  `status` enum('paid','refunded') NOT NULL DEFAULT 'paid',
                  `payment_method` varchar(24) NOT NULL DEFAULT 'stripe',
                  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
                  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_setmaxx_general_tips_pi` (`stripe_payment_intent_id`),
                  KEY `idx_setmaxx_general_tips_user` (`user_id`,`created_at`),
                  KEY `idx_setmaxx_general_tips_session` (`gig_session_id`,`created_at`),
                  CONSTRAINT `fk_setmaxx_general_tips_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_setmaxx_general_tips_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            $generalTipsReady = true;
        } catch (Throwable $e) {
            $errors[] = 'General tips could not be loaded right now.';
        }
    } else {
        $generalTipsReady = true;
    }

    $sessionsStmt = $pdo->prepare("SELECT id, title, venue_name, public_token, status FROM setmaxx_gig_sessions WHERE user_id = ? AND status = 'live' ORDER BY created_at DESC LIMIT 1");
    $sessionsStmt->execute([$userId]);
    $liveSession = $sessionsStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($liveSession) {
        $reqStmt = $pdo->prepare("SELECT r.id, r.requester_name, r.request_note, r.amount_cents, r.status, r.payment_method, r.created_at, s.title, s.artist FROM setmaxx_requests r JOIN setmaxx_songs s ON s.id = r.song_id WHERE r.gig_session_id = ? ORDER BY FIELD(r.status, 'pending', 'queued', 'played', 'declined', 'canceled'), r.amount_cents DESC, r.created_at DESC");
        $reqStmt->execute([(int)$liveSession['id']]);
        $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($generalTipsReady) {
            $tipsStmt = $pdo->prepare("SELECT tipper_name, tip_note, amount_cents, payment_method, created_at FROM setmaxx_general_tips WHERE gig_session_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 10");
            $tipsStmt->execute([(int)$liveSession['id']]);
            $generalTips = $tipsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif ($generalTipsReady) {
        $tipsStmt = $pdo->prepare("SELECT tipper_name, tip_note, amount_cents, payment_method, created_at FROM setmaxx_general_tips WHERE user_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 10");
        $tipsStmt->execute([$userId]);
        $generalTips = $tipsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($suggestionsReady) {
        if ($liveSession) {
            $suggestStmt = $pdo->prepare(
                "SELECT ss.suggested_title, ss.suggested_artist, ss.requester_name, ss.suggestion_note, ss.created_at, gs.title AS session_title
                 FROM setmaxx_song_suggestions ss
                 LEFT JOIN setmaxx_gig_sessions gs ON gs.id = ss.gig_session_id
                 WHERE ss.user_id = ?
                   AND ss.gig_session_id = ?
                 ORDER BY ss.created_at DESC
                 LIMIT 8"
            );
            $suggestStmt->execute([$userId, (int)$liveSession['id']]);
        } else {
            $suggestStmt = $pdo->prepare(
                "SELECT ss.suggested_title, ss.suggested_artist, ss.requester_name, ss.suggestion_note, ss.created_at, gs.title AS session_title
                 FROM setmaxx_song_suggestions ss
                 LEFT JOIN setmaxx_gig_sessions gs ON gs.id = ss.gig_session_id
                 WHERE ss.user_id = ?
                   AND (ss.gig_session_id IS NULL OR gs.status <> 'closed')
                 ORDER BY ss.created_at DESC
                 LIMIT 8"
            );
            $suggestStmt->execute([$userId]);
        }
        $suggestions = $suggestStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($mailingReady) {
        $mailingStmt = $pdo->prepare(
            "SELECT m.email, m.first_name, m.created_at, gs.title AS session_title
             FROM setmaxx_mailing_list_signups m
             LEFT JOIN setmaxx_gig_sessions gs ON gs.id = m.gig_session_id
             WHERE m.user_id = ?
             ORDER BY m.created_at DESC
             LIMIT 10"
        );
        $mailingStmt->execute([$userId]);
        $mailingSignups = $mailingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($tablesReady && isset($_GET['poll']) && $_GET['poll'] === 'live') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'live_session_id' => $liveSession ? (int)$liveSession['id'] : null,
        'request_ids' => array_map('intval', array_column($requests, 'id')),
        'request_count' => count($requests),
        'latest_request_id' => $requests ? max(array_map('intval', array_column($requests, 'id'))) : null,
        'server_time' => date('c'),
    ]);
    exit;
}

setmaxx_page_head('Set Maxx | Request Dashboard');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Request Dashboard</div>
    <?php if ($liveSession): ?>
      <?php $publicUrl = $sessionLinkBase . rawurlencode((string)$liveSession['public_token']); ?>
      <div class="setmaxx-dashboard-hero">
        <div>
          <h1 style="margin:.8rem 0 .35rem;">Tonight's requests, right here</h1>
          <p class="setmaxx-help">Live now: <strong><?= e($liveSession['title']) ?></strong>. New requests appear quietly and get a little glow.</p>
          <div class="setmaxx-public-link-inline"><span>Public page</span><code><?= e($publicUrl) ?></code></div>
        </div>
        <a class="btn btn-outline" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open public page</a>
      </div>
    <?php else: ?>
      <h1 style="margin:.8rem 0 .35rem;">Tonight's requests, right here</h1>
      <p class="setmaxx-help">Start a live session and this page will watch for requests quietly.</p>
    <?php endif; ?>
  </div>
  <div class="setmaxx-card setmaxx-notification-card" style="margin-bottom:1rem;">
    <div>
      <div class="setmaxx-pill">Optional</div>
      <h2 style="margin:.5rem 0 .35rem;">Request notifications</h2>
      <p class="setmaxx-help" id="setmaxxPushHelp" style="margin:0;">Enable device notifications so new requests can pop up even when this page is not front and center.</p>
    </div>
    <div class="setmaxx-actions">
      <button class="btn btn-outline" type="button" id="setmaxxPushDisableBtn" hidden>Disable on this device</button>
      <button class="btn btn-primary" type="button" id="setmaxxPushEnableBtn">Enable notifications</button>
    </div>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Live requests</h2>
      <?php if (!$liveSession): ?>
        <p class="setmaxx-help">No live session right now. Create one first.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Create Session</a>
      <?php else: ?>
        <div class="setmaxx-list">
          <?php if (!$requests): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No requests yet for this session.</div></div>
          <?php else: foreach ($requests as $request): ?>
            <div class="setmaxx-row setmaxx-request-row" data-request-id="<?= (int)$request['id'] ?>">
              <div style="min-width:0; flex:1;">
                <div style="display:flex; gap:.55rem; align-items:center; flex-wrap:wrap;"><div style="font-weight:600;"><?= e($request['title']) ?></div><a class="setmaxx-mini-link" href="<?= e(setmaxx_lyrics_url((string)$request['title'], (string)$request['artist'])) ?>" target="_blank" rel="noopener">Lyrics</a><span class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></span><?php if (($request['payment_method'] ?? '') === 'venmo'): ?><span class="setmaxx-pill">Venmo recorded</span><?php endif; ?></div>
                <div class="setmaxx-meta"><?= e((string)($request['artist'] ?: 'Artist not set')) ?> &middot; from <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
                <?php if (!empty($request['request_note'])): ?><div class="setmaxx-help" style="margin-top:.35rem;">"<?= e((string)$request['request_note']) ?>"</div><?php endif; ?>
              </div>
              <div>
                <div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$request['amount_cents'])) ?></div>
                <?php if (($request['payment_method'] ?? '') === 'stripe'): ?><div class="setmaxx-meta">Stripe</div><?php endif; ?>
                <div class="setmaxx-actions" style="justify-content:flex-end; margin-top:.45rem;">
                  <?php
                    $requestStatus = (string)$request['status'];
                    $requestActions = $requestStatus === 'pending'
                        ? ['played' => 'Played', 'declined' => 'Decline']
                        : ($requestStatus === 'queued' ? ['played' => 'Played', 'declined' => 'Decline'] : []);
                  ?>
                  <?php if (!$requestActions): ?>
                    <span class="setmaxx-meta">No further action</span>
                  <?php else: foreach ($requestActions as $statusValue => $label): ?>
                      <form method="post" action=""><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="new_status" value="<?= e($statusValue) ?>"><button class="btn btn-outline" type="submit"><?= e($label) ?></button></form>
                  <?php endforeach; endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">General tips</h2>
      <div class="setmaxx-list">
        <?php if (!$generalTips): ?>
          <div class="setmaxx-row"><div class="setmaxx-meta">No standalone tips yet for the live session.</div></div>
        <?php else: foreach ($generalTips as $tip): ?>
          <div class="setmaxx-row">
            <div>
              <div style="font-weight:600;"><?= e((string)($tip['tipper_name'] ?: 'Anonymous')) ?></div>
              <?php if (!empty($tip['tip_note'])): ?><div class="setmaxx-help" style="margin-top:.35rem;">"<?= e((string)$tip['tip_note']) ?>"</div><?php endif; ?>
            </div>
            <div><div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$tip['amount_cents'])) ?></div><div class="setmaxx-meta"><?= (($tip['payment_method'] ?? '') === 'venmo') ? 'Venmo recorded' : 'Stripe' ?></div></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="setmaxx-card">
      <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
        <div>
          <h2 style="margin:0;">Mailing list</h2>
          <p class="setmaxx-help" style="margin:.35rem 0 0;">Recent fans who joined from your Set Maxx public page.</p>
        </div>
        <a class="btn btn-outline" href="?export_mailing=1">Export CSV</a>
      </div>
      <div class="setmaxx-list" style="margin-top:1rem;">
        <?php if (!$mailingSignups): ?>
          <div class="setmaxx-row"><div class="setmaxx-meta">No mailing list signups yet.</div></div>
        <?php else: foreach ($mailingSignups as $signup): ?>
          <div class="setmaxx-row">
            <div>
              <div style="font-weight:600;"><?= e((string)($signup['first_name'] ?: 'New subscriber')) ?></div>
              <div class="setmaxx-meta">
                <?= e((string)$signup['email']) ?>
                &middot; <?= e((string)($signup['session_title'] ?: 'Off-session link')) ?>
              </div>
            </div>
            <div class="setmaxx-meta"><?= e(date('M j', strtotime((string)$signup['created_at']))) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="setmaxx-card">
      <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
        <div>
          <h2 style="margin:0;">Song suggestions</h2>
          <p class="setmaxx-help" style="margin:.35rem 0 0;"><?= $liveSession ? 'Suggestions for the current live session.' : 'Recent suggestions that are not tied to closed sessions.' ?></p>
        </div>
        <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/history.php#suggestions')) ?>">View history</a>
      </div>
      <div class="setmaxx-list">
        <?php if (!$suggestions): ?>
          <div class="setmaxx-row"><div class="setmaxx-meta">No current song suggestions yet.</div></div>
        <?php else: foreach ($suggestions as $suggestion): ?>
          <div class="setmaxx-row">
            <div>
              <div style="font-weight:600;"><?= e($suggestion['suggested_title']) ?></div>
              <div class="setmaxx-meta">
                <?= e((string)($suggestion['suggested_artist'] ?: 'Artist not listed')) ?>
                &middot; <?= e((string)($suggestion['requester_name'] ?: 'Anonymous')) ?>
                &middot; <?= e((string)($suggestion['session_title'] ?: 'Off-session link')) ?>
              </div>
              <?php if (!empty($suggestion['suggestion_note'])): ?>
                <div class="setmaxx-help" style="margin-top:.35rem;">"<?= e((string)$suggestion['suggestion_note']) ?>"</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>
<style>
  .setmaxx-mini-link { color:#efe7ff; font-size:.84rem; text-decoration:none; }
  .setmaxx-mini-link:hover { text-decoration:underline; }
  .setmaxx-dashboard-hero { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; flex-wrap:wrap; }
  .setmaxx-notification-card { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
  .setmaxx-public-link-inline { display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; margin-top:.8rem; color:rgba(255,255,255,.72); font-size:.88rem; }
  .setmaxx-public-link-inline span { font-weight:700; color:#efe7ff; }
  .setmaxx-public-link-inline code { word-break:break-all; color:rgba(255,255,255,.86); }
  .setmaxx-live-alert { position:sticky; top: calc(76px + 2rem); z-index:20; display:none; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; padding:.9rem 1rem; border-radius:16px; border:1px solid rgba(126,255,191,.32); background:rgba(28,95,68,.28); color:#eafff4; box-shadow:0 12px 30px rgba(0,0,0,.22); }
  .setmaxx-live-alert.visible { display:flex; }
  .setmaxx-live-alert strong { color:#fff; }
  .setmaxx-live-alert button { border:1px solid rgba(255,255,255,.18); border-radius:999px; background:rgba(255,255,255,.08); color:#fff; padding:.45rem .75rem; cursor:pointer; font:inherit; font-size:.86rem; }
  .setmaxx-request-row.is-new-request { animation:setmaxxNewRequestPulse 3.6s ease-out 1; border-color:rgba(126,255,191,.55); background:rgba(126,255,191,.09); }
  @keyframes setmaxxNewRequestPulse {
    0% { box-shadow:0 0 0 0 rgba(126,255,191,.45); background:rgba(126,255,191,.18); }
    70% { box-shadow:0 0 0 12px rgba(126,255,191,0); }
    100% { box-shadow:none; background:rgba(126,255,191,.09); }
  }
</style>
<script>
(function() {
  const enableBtn = document.getElementById('setmaxxPushEnableBtn');
  const disableBtn = document.getElementById('setmaxxPushDisableBtn');
  const help = document.getElementById('setmaxxPushHelp');
  const endpoint = <?= json_encode(base_url('/setmaxx/push.php')) ?>;
  const swUrl = <?= json_encode(base_url('/sw.js')) ?>;
  const csrf = <?= json_encode(csrf_token()) ?>;

  if (!enableBtn || !help) return;

  function setHelp(text) {
    help.textContent = text;
  }

  function setEnabledUi(enabled) {
    enableBtn.hidden = enabled;
    if (disableBtn) disableBtn.hidden = !enabled;
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  }

  async function registrationAndSubscription() {
    const registration = await navigator.serviceWorker.register(swUrl);
    const subscription = await registration.pushManager.getSubscription();
    return { registration, subscription };
  }

  async function getPublicKey() {
    const response = await fetch(endpoint + '?action=key', { credentials: 'same-origin', cache: 'no-store' });
    const data = await response.json();
    if (!data || !data.success || !data.publicKey) throw new Error('Missing push key.');
    return data.publicKey;
  }

  async function saveSubscription(subscription) {
    const response = await fetch(endpoint + '?action=subscribe', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        _csrf: csrf,
        subscription: JSON.stringify(subscription)
      })
    });
    const data = await response.json();
    if (!data || !data.success) throw new Error((data && data.error) || 'Could not save notification setup.');
    return data;
  }

  async function disableSubscription(subscription) {
    if (!subscription) return;
    await fetch(endpoint + '?action=unsubscribe', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _csrf: csrf, endpoint: subscription.endpoint || '' })
    });
    await subscription.unsubscribe();
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    enableBtn.disabled = true;
    setHelp('This browser does not support web push notifications. On iPhone, add Ready Set Shows to the Home Screen and use iOS 16.4 or newer.');
    return;
  }

  registrationAndSubscription().then(function(result) {
    if (result.subscription) {
      setEnabledUi(true);
      setHelp('Notifications are enabled on this device.');
    } else if (Notification.permission === 'denied') {
      enableBtn.disabled = true;
      setHelp('Notifications are blocked for this site. Re-enable them in your browser or device settings.');
    }
  }).catch(function() {});

  enableBtn.addEventListener('click', async function() {
    enableBtn.disabled = true;
    setHelp('Setting up notifications...');
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        setHelp('Notifications were not enabled. You can try again anytime.');
        return;
      }
      const publicKey = await getPublicKey();
      const result = await registrationAndSubscription();
      const subscription = result.subscription || await result.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey)
      });
      await saveSubscription(subscription);
      setEnabledUi(true);
      setHelp('Notifications are enabled on this device.');
    } catch (error) {
      setHelp('Notification setup is not available right now.');
    } finally {
      enableBtn.disabled = false;
    }
  });

  if (disableBtn) {
    disableBtn.addEventListener('click', async function() {
      disableBtn.disabled = true;
      try {
        const result = await registrationAndSubscription();
        await disableSubscription(result.subscription);
        setEnabledUi(false);
        setHelp('Notifications are disabled on this device.');
      } catch (error) {
        setHelp('Could not disable notifications right now.');
      } finally {
        disableBtn.disabled = false;
      }
    });
  }
})();
</script>
<?php if ($liveSession): ?>
<script>
(function() {
  const liveSessionId = <?= json_encode((int)$liveSession['id']) ?>;
  const currentIds = <?= json_encode(array_map('intval', array_column($requests, 'id'))) ?>;
  const pollUrl = <?= json_encode(base_url('/setmaxx/requests.php?poll=live')) ?>;
  const knownKey = 'setmaxxKnownRequestIds:' + liveSessionId;
  const newKey = 'setmaxxNewRequestIds:' + liveSessionId;
  const pollMs = 5000;
  let knownIds = readIds(knownKey);
  let polling = false;

  function readIds(key) {
    try {
      const value = window.sessionStorage.getItem(key);
      return value ? JSON.parse(value).map(Number).filter(Boolean) : [];
    } catch (error) {
      return [];
    }
  }

  function writeIds(key, ids) {
    try {
      window.sessionStorage.setItem(key, JSON.stringify(Array.from(new Set(ids.map(Number).filter(Boolean)))));
    } catch (error) {}
  }

  function showAlert(count) {
    let alert = document.getElementById('setmaxxLiveAlert');
    if (!alert) {
      alert = document.createElement('div');
      alert.className = 'setmaxx-live-alert';
      alert.id = 'setmaxxLiveAlert';
      alert.setAttribute('role', 'status');
      alert.setAttribute('aria-live', 'polite');
      alert.innerHTML = '<div><strong>New request in.</strong> <span></span></div><button type="button">Dismiss</button>';
      const shell = document.querySelector('.setmaxx-shell');
      if (shell) shell.insertBefore(alert, shell.firstElementChild ? shell.firstElementChild.nextSibling : null);
      const close = alert.querySelector('button');
      if (close) close.addEventListener('click', function() { alert.classList.remove('visible'); });
    }
    const detail = alert.querySelector('span');
    if (detail) detail.textContent = count > 1 ? count + ' fresh picks just landed.' : 'Fresh pick just landed.';
    alert.classList.add('visible');
  }

  function highlightStoredNewRequests() {
    const newIds = readIds(newKey);
    if (!newIds.length) return;
    let highlighted = 0;
    newIds.forEach(function(id) {
      const row = document.querySelector('[data-request-id="' + id + '"]');
      if (row) {
        row.classList.add('is-new-request');
        highlighted++;
      }
    });
    if (highlighted > 0) showAlert(highlighted);
    try { window.sessionStorage.removeItem(newKey); } catch (error) {}
  }

  function dashboardIsBusy() {
    return document.querySelector('form.is-submitting, input:focus, textarea:focus, select:focus') !== null;
  }

  document.addEventListener('submit', function(event) {
    if (event.target && event.target.tagName === 'FORM') {
      event.target.classList.add('is-submitting');
    }
  }, true);

  if (!knownIds.length) {
    knownIds = currentIds;
    writeIds(knownKey, knownIds);
  } else {
    const merged = knownIds.concat(currentIds);
    knownIds = Array.from(new Set(merged));
    writeIds(knownKey, knownIds);
  }

  highlightStoredNewRequests();

  async function pollForRequests() {
    if (polling) return;
    polling = true;
    try {
      const response = await fetch(pollUrl + '&t=' + Date.now(), {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) return;
      const data = await response.json();
      if (!data || !data.ok) return;
      if (Number(data.live_session_id || 0) !== liveSessionId) {
        if (!dashboardIsBusy()) window.location.reload();
        return;
      }
      const incomingIds = Array.isArray(data.request_ids) ? data.request_ids.map(Number).filter(Boolean) : [];
      const known = new Set(readIds(knownKey));
      const newIds = incomingIds.filter(function(id) { return !known.has(id); });
      if (newIds.length) {
        writeIds(newKey, newIds);
        if (!dashboardIsBusy()) {
          writeIds(knownKey, Array.from(new Set(Array.from(known).concat(incomingIds))));
          window.location.reload();
        } else {
          showAlert(newIds.length);
        }
      }
    } catch (error) {
    } finally {
      polling = false;
    }
  }

  window.setInterval(pollForRequests, pollMs);
})();
</script>
<?php endif; ?>
<?php setmaxx_page_foot(); ?>
