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

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $newStatus = (string)($_POST['new_status'] ?? '');
            $allowedStatuses = ['queued', 'played', 'declined', 'canceled'];
            if ($requestId <= 0 || !in_array($newStatus, $allowedStatuses, true)) throw new RuntimeException('Invalid request update.');
            $activeLock = in_array($newStatus, ['declined', 'canceled'], true) ? null : 1;
            $stmt = $pdo->prepare("UPDATE setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id SET r.status = ?, r.active_lock = ? WHERE r.id = ? AND gs.user_id = ?");
            $stmt->execute([$newStatus, $activeLock, $requestId, $userId]);
            $messages[] = 'Request updated.';
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

$liveSession = null;
$requests = [];
$recentRequests = [];
$suggestions = [];
$generalTips = [];
$suggestionsReady = false;
$generalTipsReady = false;
if ($tablesReady) {
    try {
        setmaxx_requests_ensure_suggestions_table($pdo);
        $suggestionsReady = true;
    } catch (Throwable $e) {
        $errors[] = 'Song suggestions could not be loaded right now.';
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
        $reqStmt = $pdo->prepare("SELECT r.id, r.requester_name, r.request_note, r.amount_cents, r.status, r.created_at, s.title, s.artist FROM setmaxx_requests r JOIN setmaxx_songs s ON s.id = r.song_id WHERE r.gig_session_id = ? ORDER BY FIELD(r.status, 'pending', 'queued', 'played', 'declined', 'canceled'), r.amount_cents DESC, r.created_at DESC");
        $reqStmt->execute([(int)$liveSession['id']]);
        $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($generalTipsReady) {
            $tipsStmt = $pdo->prepare("SELECT tipper_name, tip_note, amount_cents, created_at FROM setmaxx_general_tips WHERE gig_session_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 10");
            $tipsStmt->execute([(int)$liveSession['id']]);
            $generalTips = $tipsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif ($generalTipsReady) {
        $tipsStmt = $pdo->prepare("SELECT tipper_name, tip_note, amount_cents, created_at FROM setmaxx_general_tips WHERE user_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 10");
        $tipsStmt->execute([$userId]);
        $generalTips = $tipsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $recentStmt = $pdo->prepare("SELECT r.id, r.requester_name, r.amount_cents, r.status, r.created_at, s.title, s.artist, gs.title AS session_title FROM setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id JOIN setmaxx_songs s ON s.id = r.song_id WHERE gs.user_id = ? ORDER BY r.created_at DESC LIMIT 12");
    $recentStmt->execute([$userId]);
    $recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($suggestionsReady) {
        $suggestStmt = $pdo->prepare(
            "SELECT ss.suggested_title, ss.suggested_artist, ss.requester_name, ss.suggestion_note, ss.created_at, gs.title AS session_title
             FROM setmaxx_song_suggestions ss
             LEFT JOIN setmaxx_gig_sessions gs ON gs.id = ss.gig_session_id
             WHERE ss.user_id = ?
             ORDER BY ss.created_at DESC
             LIMIT 8"
        );
        $suggestStmt->execute([$userId]);
        $suggestions = $suggestStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
setmaxx_page_head('Set Maxx | Request Dashboard');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Request Dashboard</div>
    <h1 style="margin:.8rem 0 .35rem;">Control the room without losing the room</h1>
    <p class="setmaxx-help">Queue, mark played, or decline requests from the current live session.</p>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Live requests</h2>
      <?php if (!$liveSession): ?>
        <p class="setmaxx-help">No live session right now. Create one first.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Create Session</a>
      <?php else: ?>
        <?php $publicUrl = $sessionLinkBase . rawurlencode((string)$liveSession['public_token']); ?>
        <div class="setmaxx-meta" style="margin-bottom:1rem;">Live now: <strong><?= e($liveSession['title']) ?></strong></div>
        <div class="setmaxx-link-box" style="margin-bottom:1rem;"><strong>Public page</strong><code><?= e($publicUrl) ?></code><a class="btn btn-outline" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open</a></div>
        <div class="setmaxx-list">
          <?php if (!$requests): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No requests yet for this session.</div></div>
          <?php else: foreach ($requests as $request): ?>
            <div class="setmaxx-row">
              <div style="min-width:0; flex:1;">
                <div style="display:flex; gap:.55rem; align-items:center; flex-wrap:wrap;"><div style="font-weight:600;"><?= e($request['title']) ?></div><span class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></span></div>
                <div class="setmaxx-meta"><?= e((string)($request['artist'] ?: 'Artist not set')) ?> &middot; from <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
                <?php if (!empty($request['request_note'])): ?><div class="setmaxx-help" style="margin-top:.35rem;">"<?= e((string)$request['request_note']) ?>"</div><?php endif; ?>
              </div>
              <div>
                <div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$request['amount_cents'])) ?></div>
                <div class="setmaxx-actions" style="justify-content:flex-end; margin-top:.45rem;">
                  <?php foreach (['queued' => 'Queue', 'played' => 'Played', 'declined' => 'Decline'] as $statusValue => $label): ?>
                    <?php if ($request['status'] !== $statusValue): ?>
                      <form method="post" action=""><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="new_status" value="<?= e($statusValue) ?>"><button class="btn btn-outline" type="submit"><?= e($label) ?></button></form>
                    <?php endif; ?>
                  <?php endforeach; ?>
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
            <div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$tip['amount_cents'])) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Recent requests</h2>
      <div class="setmaxx-list">
        <?php if (!$recentRequests): ?>
          <div class="setmaxx-row"><div class="setmaxx-meta">No requests yet.</div></div>
        <?php else: foreach ($recentRequests as $request): ?>
          <div class="setmaxx-row">
            <div>
              <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                <div style="font-weight:600;"><?= e($request['title']) ?></div>
                <a class="setmaxx-mini-link" href="<?= e(setmaxx_lyrics_url((string)$request['title'], (string)$request['artist'])) ?>" target="_blank" rel="noopener">Lyrics</a>
              </div>
              <div class="setmaxx-meta"><?= e($request['session_title']) ?> &middot; <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
            </div>
            <div><div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$request['amount_cents'])) ?></div><div class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></div></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Song suggestions</h2>
      <div class="setmaxx-list">
        <?php if (!$suggestions): ?>
          <div class="setmaxx-row"><div class="setmaxx-meta">No song suggestions yet.</div></div>
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
</style>
<?php setmaxx_page_foot(); ?>
