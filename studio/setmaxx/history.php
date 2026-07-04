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

function setmaxx_history_ensure_mailing_list_table(PDO $pdo): void {
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

$closedSessions = [];
$closedSuggestions = [];
$mailingSignups = [];

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Live session history controls are included with Pro.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'delete_closed_session') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($sessionId <= 0) throw new RuntimeException('Invalid session delete request.');

                $pdo->beginTransaction();

                $ownStmt = $pdo->prepare("SELECT id, title FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ? AND status = 'closed' LIMIT 1");
                $ownStmt->execute([$sessionId, $userId]);
                $sessionToDelete = $ownStmt->fetch(PDO::FETCH_ASSOC);
                if (!$sessionToDelete) {
                    throw new RuntimeException('Closed session not found.');
                }

                $pdo->prepare("DELETE r FROM setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id WHERE r.gig_session_id = ? AND gs.user_id = ?")->execute([$sessionId, $userId]);

                if (setmaxx_table_exists($pdo, 'setmaxx_song_suggestions')) {
                    $pdo->prepare("DELETE ss FROM setmaxx_song_suggestions ss JOIN setmaxx_gig_sessions gs ON gs.id = ss.gig_session_id WHERE ss.gig_session_id = ? AND gs.user_id = ?")->execute([$sessionId, $userId]);
                }

                if (setmaxx_table_exists($pdo, 'setmaxx_general_tips')) {
                    $pdo->prepare("DELETE gt FROM setmaxx_general_tips gt JOIN setmaxx_gig_sessions gs ON gs.id = gt.gig_session_id WHERE gt.gig_session_id = ? AND gs.user_id = ?")->execute([$sessionId, $userId]);
                }

                if (setmaxx_table_exists($pdo, 'setmaxx_mailing_list_signups')) {
                    $pdo->prepare("UPDATE setmaxx_mailing_list_signups SET gig_session_id = NULL WHERE gig_session_id = ? AND user_id = ?")->execute([$sessionId, $userId]);
                }

                $pdo->prepare("DELETE FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ? AND status = 'closed'")->execute([$sessionId, $userId]);
                $pdo->commit();
                $messages[] = 'Closed session deleted.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

if ($tablesReady) {
    try {
        setmaxx_history_ensure_suggestions_table($pdo);
        setmaxx_history_ensure_mailing_list_table($pdo);

        if (isset($_GET['export_mailing'])) {
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

        $hasRequestPaymentMethod = setmaxx_column_exists($pdo, 'setmaxx_requests', 'payment_method');
        $hasRequestPaymentIntent = setmaxx_column_exists($pdo, 'setmaxx_requests', 'stripe_payment_intent_id');
        $requestMoneySql = ($hasRequestPaymentMethod && $hasRequestPaymentIntent)
            ? "SELECT COALESCE(SUM(r.amount_cents), 0)
               FROM setmaxx_requests r
               WHERE r.gig_session_id = gs.id
                 AND r.amount_cents > 0
                 AND r.status <> 'canceled'
                 AND (
                      (r.payment_method = 'stripe' AND r.stripe_payment_intent_id IS NOT NULL AND r.stripe_payment_intent_id <> '')
                      OR r.payment_method = 'venmo'
                 )"
            : "SELECT COALESCE(SUM(r.amount_cents), 0)
               FROM setmaxx_requests r
               WHERE r.gig_session_id = gs.id
                 AND r.amount_cents > 0
                 AND r.status <> 'canceled'";
        $generalTipMoneySql = setmaxx_table_exists($pdo, 'setmaxx_general_tips')
            ? "SELECT COALESCE(SUM(gt.amount_cents), 0)
               FROM setmaxx_general_tips gt
               WHERE gt.gig_session_id = gs.id
                 AND gt.user_id = gs.user_id
                 AND gt.status = 'paid'"
            : "SELECT 0";

        $sessionsStmt = $pdo->prepare(
            "SELECT
                gs.id,
                gs.title,
                gs.venue_name,
                gs.starts_at,
                gs.ends_at,
                gs.created_at,
                (SELECT COUNT(*) FROM setmaxx_requests r WHERE r.gig_session_id = gs.id) AS request_count,
                (SELECT COUNT(*) FROM setmaxx_song_suggestions ss WHERE ss.gig_session_id = gs.id) AS suggestion_count,
                ({$requestMoneySql}) AS request_money_cents,
                ({$generalTipMoneySql}) AS general_tip_cents
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

        $mailingStmt = $pdo->prepare(
            "SELECT m.email, m.first_name, m.created_at, gs.title AS session_title
             FROM setmaxx_mailing_list_signups m
             LEFT JOIN setmaxx_gig_sessions gs ON gs.id = m.gig_session_id
             WHERE m.user_id = ?
             ORDER BY m.created_at DESC
             LIMIT 25"
        );
        $mailingStmt->execute([$userId]);
        $mailingSignups = $mailingStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = 'History could not be loaded right now.';
    }
}

setmaxx_page_head('Set Maxx | History');
?>
<style>
  .setmaxx-history-card { padding:1rem; }
  .setmaxx-history-card h2 { margin:.1rem 0 .75rem; }
  .setmaxx-history-session-list { gap:.5rem; }
  .setmaxx-history-session-row {
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto auto;
    align-items:center;
    gap:.75rem;
    padding:.62rem .72rem;
  }
  .setmaxx-history-title {
    font-weight:600;
    line-height:1.25;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .setmaxx-history-meta {
    display:flex;
    gap:.45rem;
    align-items:center;
    flex-wrap:wrap;
    margin-top:.18rem;
  }
  .setmaxx-history-stats {
    display:flex;
    gap:.8rem;
    align-items:center;
    justify-content:flex-end;
    white-space:nowrap;
  }
  .setmaxx-history-money {
    color:#efe7ff;
    font-size:1rem;
    font-weight:700;
  }
  .setmaxx-icon-btn {
    width:2.35rem;
    height:2.35rem;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0;
    border-radius:12px;
  }
  .setmaxx-icon-btn-danger {
    border-color:rgba(255,120,120,.45);
    color:#ffb3b3;
  }
  .setmaxx-history-actions {
    gap:.4rem;
    flex-wrap:nowrap;
  }
  @media (max-width: 640px) {
    .setmaxx-history-session-row {
      grid-template-columns:minmax(0, 1fr) auto;
    }
    .setmaxx-history-stats {
      grid-column:1 / -1;
      justify-content:flex-start;
      order:3;
    }
  }
</style>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">History</div>
    <h1 style="margin:.8rem 0 .35rem;">Past sessions and suggestions</h1>
    <p class="setmaxx-help">Closed sessions and their song suggestions live here so your live-show pages stay focused.</p>
    <div class="setmaxx-actions" style="margin-top:1rem;">
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Session setup</a>
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/requests.php')) ?>">Request dashboard</a>
      <a class="btn btn-outline" href="?export_mailing=1">Export mailing CSV</a>
    </div>
  </div>

  <?php if (!$tablesReady): ?>
    <?php setmaxx_install_notice(); ?>
  <?php else: ?>
    <section class="setmaxx-grid">
      <div class="setmaxx-card setmaxx-history-card" id="sessions">
        <h2>Closed sessions</h2>
        <div class="setmaxx-list setmaxx-history-session-list">
          <?php if (!$closedSessions): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No closed sessions yet.</div></div>
          <?php else: foreach ($closedSessions as $session): ?>
            <?php $sessionMoneyCents = (int)($session['request_money_cents'] ?? 0) + (int)($session['general_tip_cents'] ?? 0); ?>
            <div class="setmaxx-row setmaxx-history-session-row">
              <div style="min-width:0; flex:1;">
                <div class="setmaxx-history-title"><?= e((string)$session['title']) ?></div>
                <div class="setmaxx-meta setmaxx-history-meta">
                  <span><?= e((string)($session['venue_name'] ?: 'Venue not set')) ?></span>
                  <span>&middot;</span>
                  <span><?= !empty($session['ends_at']) ? e(date('M j, Y', strtotime((string)$session['ends_at']))) : 'date not set' ?></span>
                </div>
              </div>
              <div class="setmaxx-history-stats">
                <span class="setmaxx-history-money"><?= e(setmaxx_money($sessionMoneyCents)) ?></span>
                <span class="setmaxx-meta"><?= (int)$session['request_count'] ?> req</span>
                <span class="setmaxx-meta"><?= (int)$session['suggestion_count'] ?> sug</span>
              </div>
              <div class="setmaxx-actions setmaxx-history-actions">
                <a class="btn btn-outline setmaxx-icon-btn" href="<?= e(base_url('/setmaxx/session.php?id=' . (int)$session['id'])) ?>" aria-label="Open <?= e((string)$session['title']) ?> history" title="Open history"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                <form method="post" action="" onsubmit="return confirm('Delete this closed session and its requests, tips, and suggestions? This cannot be undone.');">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_closed_session">
                  <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                  <button class="btn btn-outline setmaxx-icon-btn setmaxx-icon-btn-danger" type="submit" <?= $isProUser ? '' : 'disabled' ?> aria-label="Delete <?= e((string)$session['title']) ?>" title="Delete"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                </form>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="setmaxx-card" id="mailing-list">
        <h2 style="margin-top:0;">Recent mailing list joins</h2>
        <p class="setmaxx-help" style="margin-top:-.35rem;">Fans who joined from your SetMaxx public page.</p>
        <div class="setmaxx-list">
          <?php if (!$mailingSignups): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No mailing list signups yet.</div></div>
          <?php else: foreach ($mailingSignups as $signup): ?>
            <div class="setmaxx-row">
              <div style="min-width:0; flex:1;">
                <div style="font-weight:600;"><?= e((string)($signup['first_name'] ?: 'New subscriber')) ?></div>
                <div class="setmaxx-meta">
                  <?= e((string)$signup['email']) ?>
                  &middot; <?= e((string)($signup['session_title'] ?: 'Off-session link')) ?>
                </div>
              </div>
              <div class="setmaxx-meta"><?= e(date('M j, Y', strtotime((string)$signup['created_at']))) ?></div>
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
