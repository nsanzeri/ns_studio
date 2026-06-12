<?php
require_once __DIR__ . '/_common.php';

function setmaxx_session_view_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_session_view_ensure_payment_method_columns(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_requests') && !setmaxx_session_view_column_exists($pdo, 'setmaxx_requests', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_requests ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
    if (setmaxx_table_exists($pdo, 'setmaxx_general_tips') && !setmaxx_session_view_column_exists($pdo, 'setmaxx_general_tips', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_general_tips ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
}

$sessionId = (int)($_GET['id'] ?? 0);
$session = null;
$requests = [];
$generalTips = [];

if ($tablesReady && $sessionId > 0) {
    try {
        setmaxx_session_view_ensure_payment_method_columns($pdo);
        $stmt = $pdo->prepare("SELECT id, title, venue_name, status, starts_at, ends_at, created_at FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($session) {
            $reqStmt = $pdo->prepare(
                "SELECT r.requester_name, r.request_note, r.amount_cents, r.status, r.payment_method, r.created_at, s.title, s.artist
                 FROM setmaxx_requests r
                 JOIN setmaxx_songs s ON s.id = r.song_id
                 WHERE r.gig_session_id = ?
                 ORDER BY r.created_at DESC"
            );
            $reqStmt->execute([$sessionId]);
            $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

            if (setmaxx_table_exists($pdo, 'setmaxx_general_tips')) {
                $tipsStmt = $pdo->prepare("SELECT tipper_name, tip_note, amount_cents, payment_method, created_at FROM setmaxx_general_tips WHERE gig_session_id = ? AND status = 'paid' ORDER BY created_at DESC");
                $tipsStmt->execute([$sessionId]);
                $generalTips = $tipsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Session history could not be loaded right now.';
    }
}

setmaxx_page_head('Set Maxx | Session History');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <?php if (!$tablesReady): ?>
    <?php setmaxx_install_notice(); ?>
  <?php elseif (!$session): ?>
    <div class="setmaxx-card">
      <h1 style="margin-top:0;">Session not found</h1>
      <p class="setmaxx-help">That session could not be opened.</p>
      <a class="btn btn-primary" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Back to Sessions</a>
    </div>
  <?php else: ?>
    <div class="setmaxx-card" style="margin-bottom:1rem;">
      <div class="setmaxx-pill">Session history</div>
      <h1 style="margin:.8rem 0 .35rem;"><?= e((string)$session['title']) ?></h1>
      <p class="setmaxx-help" style="margin:0;"><?= e((string)($session['venue_name'] ?: 'Venue not set')) ?> &middot; <?= e((string)$session['status']) ?></p>
      <div class="setmaxx-actions" style="margin-top:1rem;"><a class="btn btn-outline" href="<?= e(base_url('/setmaxx/history.php')) ?>">Back to History</a></div>
    </div>

    <section class="setmaxx-grid">
      <div class="setmaxx-card">
        <h2 style="margin-top:0;">Requests</h2>
        <div class="setmaxx-list">
          <?php if (!$requests): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No requests for this session.</div></div>
          <?php else: foreach ($requests as $request): ?>
            <div class="setmaxx-row">
              <div>
                <div style="font-weight:600;"><?= e((string)$request['title']) ?></div>
                <div class="setmaxx-meta"><?= e((string)($request['artist'] ?: 'Artist not set')) ?> &middot; <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
                <?php if (!empty($request['request_note'])): ?><div class="setmaxx-help" style="margin-top:.35rem;">"<?= e((string)$request['request_note']) ?>"</div><?php endif; ?>
              </div>
              <div>
                <div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$request['amount_cents'])) ?></div>
                <div class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></div>
                <div class="setmaxx-meta"><?= (($request['payment_method'] ?? '') === 'venmo') ? 'Venmo recorded' : (((int)($request['amount_cents'] ?? 0) > 0) ? 'Stripe' : 'Free') ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="setmaxx-card">
        <h2 style="margin-top:0;">General tips</h2>
        <div class="setmaxx-list">
          <?php if (!$generalTips): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta">No standalone tips for this session.</div></div>
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
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>
