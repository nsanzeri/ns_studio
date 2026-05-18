<?php
require_once __DIR__ . '/_common.php';

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create_session') {
                $title = trim((string)($_POST['session_title'] ?? ''));
                $venue = trim((string)($_POST['venue_name'] ?? ''));
                $goLive = isset($_POST['go_live']) ? 1 : 0;
                if ($title === '') throw new RuntimeException('Session title is required.');
                $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'gig';
                $sessionSlug = $baseSlug . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
                $publicToken = bin2hex(random_bytes(16));
                $status = $goLive ? 'live' : 'draft';
                $pdo->beginTransaction();
                if ($goLive) {
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live'")->execute([$userId]);
                }
                $stmt = $pdo->prepare("INSERT INTO setmaxx_gig_sessions (user_id, title, venue_name, session_slug, public_token, status, starts_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $title, $venue !== '' ? $venue : null, $sessionSlug, $publicToken, $status, $goLive ? date('Y-m-d H:i:s') : null]);
                $pdo->commit();
                $messages[] = $goLive ? 'New live session created.' : 'Session created in draft mode.';
            }
            if ($action === 'session_status') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $newStatus = (string)($_POST['new_status'] ?? '');
                if ($sessionId <= 0 || !in_array($newStatus, ['live', 'closed'], true)) throw new RuntimeException('Invalid session update.');
                $pdo->beginTransaction();
                if ($newStatus === 'live') {
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live' AND id <> ?")->execute([$userId, $sessionId]);
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'live', starts_at = COALESCE(starts_at, NOW()), ends_at = NULL WHERE id = ? AND user_id = ?")->execute([$sessionId, $userId]);
                    $messages[] = 'Session is now live.';
                } else {
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE id = ? AND user_id = ?")->execute([$sessionId, $userId]);
                    $messages[] = 'Session closed.';
                }
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$sessions = [];
if ($tablesReady) {
    $sessionsStmt = $pdo->prepare("SELECT id, title, venue_name, session_slug, public_token, status, starts_at, ends_at, created_at FROM setmaxx_gig_sessions WHERE user_id = ? ORDER BY FIELD(status, 'live', 'draft', 'closed'), created_at DESC LIMIT 20");
    $sessionsStmt->execute([$userId]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
}
setmaxx_page_head('Set Maxx | Gig Sessions');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Gig Sessions</div>
    <h1 style="margin:.8rem 0 .35rem;">Create request pages for each show</h1>
    <p class="setmaxx-help">Only one session can be live at a time. Going live automatically closes any other live session.</p>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">New session</h2>
      <form method="post" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_session">
        <div class="setmaxx-form-grid">
          <div class="setmaxx-field"><label for="session_title">Session title</label><input class="setmaxx-input" id="session_title" name="session_title" placeholder="Friday at Moretti's" required></div>
          <div class="setmaxx-field"><label for="venue_name">Venue</label><input class="setmaxx-input" id="venue_name" name="venue_name" placeholder="Moretti's Rosemont"></div>
        </div>
        <label style="display:flex; gap:.6rem; align-items:center;"><input type="checkbox" name="go_live" value="1" checked><span class="setmaxx-help">Make this the live request page now</span></label>
        <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Create session</button></div>
      </form>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">When live</h2>
      <p class="setmaxx-help">Open the public page, copy the link, or turn it into a QR code for the room. Requests then appear on the dashboard.</p>
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/requests.php')) ?>">Open Request Dashboard</a>
    </div>
  </section>
  <div class="setmaxx-card" style="margin-top:1rem;">
    <h2 style="margin-top:0;">Sessions</h2>
    <div class="setmaxx-list">
      <?php if (!$sessions): ?>
        <div class="setmaxx-row"><div class="setmaxx-meta">No gig sessions yet.</div></div>
      <?php else: foreach ($sessions as $session): ?>
        <?php $publicUrl = $sessionLinkBase . rawurlencode((string)$session['public_token']); ?>
        <div class="setmaxx-row">
          <div style="min-width:0; flex:1;">
            <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;"><div style="font-weight:600;"><?= e($session['title']) ?></div><?= setmaxx_status_pill((string)$session['status']) ?></div>
            <div class="setmaxx-meta"><?= e((string)($session['venue_name'] ?: 'Venue not set')) ?></div>
            <div class="setmaxx-link-box" style="margin-top:.7rem;"><strong>Public page</strong><code><?= e($publicUrl) ?></code><a class="btn btn-outline" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open</a></div>
          </div>
          <div class="setmaxx-actions">
            <?php if (($session['status'] ?? '') !== 'live'): ?>
              <form method="post" action=""><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="session_status"><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><input type="hidden" name="new_status" value="live"><button class="btn btn-outline" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Go live</button></form>
            <?php else: ?>
              <form method="post" action=""><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="session_status"><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><input type="hidden" name="new_status" value="closed"><button class="btn btn-outline" type="submit">Close</button></form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>
