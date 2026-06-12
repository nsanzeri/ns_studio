<?php
require_once __DIR__ . '/_common.php';

function setmaxx_history_ensure_suggestions_table(PDO $pdo): void {
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

$closedSessions = [];
$closedSuggestions = [];

if ($tablesReady) {
    try {
        setmaxx_history_ensure_suggestions_table($pdo);

        $sessionsStmt = $pdo->prepare(
            "SELECT
                gs.id,
                gs.title,
                gs.venue_name,
                gs.starts_at,
                gs.ends_at,
                gs.created_at,
                (SELECT COUNT(*) FROM setmaxx_requests r WHERE r.gig_session_id = gs.id) AS request_count,
                (SELECT COUNT(*) FROM setmaxx_song_suggestions ss WHERE ss.gig_session_id = gs.id) AS suggestion_count
             FROM setmaxx_gig_sessions gs
             WHERE gs.user_id = ?
               AND gs.status = 'closed'
             ORDER BY COALESCE(gs.ends_at, gs.starts_at, gs.created_at) DESC
             LIMIT 100"
        );
        $sessionsStmt->execute([$userId]);
        $closedSessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

        $suggestionsStmt = $pdo->prepare(
            "SELECT
                ss.suggested_title,
                ss.suggested_artist,
                ss.requester_name,
                ss.suggestion_note,
                ss.status,
                ss.created_at,
                gs.id AS session_id,
                gs.title AS session_title,
                gs.venue_name,
                gs.ends_at
             FROM setmaxx_song_suggestions ss
             JOIN setmaxx_gig_sessions gs ON gs.id = ss.gig_session_id
             WHERE ss.user_id = ?
               AND gs.status = 'closed'
             ORDER BY ss.created_at DESC
             LIMIT 100"
        );
        $suggestionsStmt->execute([$userId]);
        $closedSuggestions = $suggestionsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = 'History could not be loaded right now.';
    }
}

setmaxx_page_head('Set Maxx | History');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">History</div>
    <h1 style="margin:.8rem 0 .35rem;">Past sessions and suggestions</h1>
    <p class="setmaxx-help">Closed sessions and their song suggestions live here so your live-show pages stay focused.</p>
    <div class="setmaxx-actions" style="margin-top:1rem;">
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Session setup</a>
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/requests.php')) ?>">Request dashboard</a>
    </div>
  </div>

  <?php if (!$tablesReady): ?>
    <?php setmaxx_install_notice(); ?>
  <?php else: ?>
    <section class="setmaxx-grid">
      <div class="setmaxx-card" id="sessions">
        <h2 style="margin-top:0;">Closed sessions</h2>
        <div class="setmaxx-list">
          <?php if (!$closedSessions): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No closed sessions yet.</div></div>
          <?php else: foreach ($closedSessions as $session): ?>
            <div class="setmaxx-row">
              <div style="min-width:0; flex:1;">
                <div style="font-weight:600;"><?= e((string)$session['title']) ?></div>
                <div class="setmaxx-meta">
                  <?= e((string)($session['venue_name'] ?: 'Venue not set')) ?>
                  &middot; Closed <?= !empty($session['ends_at']) ? e(date('M j, Y', strtotime((string)$session['ends_at']))) : 'date not set' ?>
                </div>
                <div class="setmaxx-meta">
                  <?= (int)$session['request_count'] ?> request<?= (int)$session['request_count'] === 1 ? '' : 's' ?>
                  &middot; <?= (int)$session['suggestion_count'] ?> suggestion<?= (int)$session['suggestion_count'] === 1 ? '' : 's' ?>
                </div>
              </div>
              <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/session.php?id=' . (int)$session['id'])) ?>">Open history</a>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="setmaxx-card" id="suggestions">
        <h2 style="margin-top:0;">Historical song suggestions</h2>
        <div class="setmaxx-list">
          <?php if (!$closedSuggestions): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No suggestions from closed sessions yet.</div></div>
          <?php else: foreach ($closedSuggestions as $suggestion): ?>
            <div class="setmaxx-row">
              <div style="min-width:0; flex:1;">
                <div style="display:flex; gap:.55rem; align-items:center; flex-wrap:wrap;">
                  <div style="font-weight:600;"><?= e((string)$suggestion['suggested_title']) ?></div>
                  <span class="setmaxx-pill"><?= e((string)$suggestion['status']) ?></span>
                </div>
                <div class="setmaxx-meta">
                  <?= e((string)($suggestion['suggested_artist'] ?: 'Artist not listed')) ?>
                  &middot; <?= e((string)($suggestion['requester_name'] ?: 'Anonymous')) ?>
                </div>
                <div class="setmaxx-meta">
                  <?= e((string)$suggestion['session_title']) ?>
                  &middot; <?= e(date('M j, Y', strtotime((string)$suggestion['created_at']))) ?>
                </div>
                <?php if (!empty($suggestion['suggestion_note'])): ?>
                  <div class="setmaxx-help" style="margin-top:.35rem;">"<?= e((string)$suggestion['suggestion_note']) ?>"</div>
                <?php endif; ?>
              </div>
              <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/session.php?id=' . (int)$suggestion['session_id'])) ?>">Session</a>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>
