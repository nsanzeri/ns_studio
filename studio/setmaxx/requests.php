<?php
require_once __DIR__ . '/_common.php';

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
            $activeLock = in_array($newStatus, ['declined', 'canceled'], true) ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id SET r.status = ?, r.active_lock = ? WHERE r.id = ? AND gs.user_id = ?");
            $stmt->execute([$newStatus, $activeLock, $requestId, $userId]);
            $messages[] = 'Request updated.';
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

$liveSession = null;
$requests = [];
$recentRequests = [];
if ($tablesReady) {
    $sessionsStmt = $pdo->prepare("SELECT id, title, venue_name, public_token, status FROM setmaxx_gig_sessions WHERE user_id = ? AND status = 'live' ORDER BY created_at DESC LIMIT 1");
    $sessionsStmt->execute([$userId]);
    $liveSession = $sessionsStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($liveSession) {
        $reqStmt = $pdo->prepare("SELECT r.id, r.requester_name, r.request_note, r.amount_cents, r.status, r.created_at, s.title, s.artist FROM setmaxx_requests r JOIN setmaxx_songs s ON s.id = r.song_id WHERE r.gig_session_id = ? ORDER BY FIELD(r.status, 'pending', 'queued', 'played', 'declined', 'canceled'), r.amount_cents DESC, r.created_at DESC");
        $reqStmt->execute([(int)$liveSession['id']]);
        $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $recentStmt = $pdo->prepare("SELECT r.id, r.requester_name, r.amount_cents, r.status, r.created_at, s.title, s.artist, gs.title AS session_title FROM setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id JOIN setmaxx_songs s ON s.id = r.song_id WHERE gs.user_id = ? ORDER BY r.created_at DESC LIMIT 12");
    $recentStmt->execute([$userId]);
    $recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
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
  </section>
  <?php endif; ?>
</main>
<style>
  .setmaxx-mini-link { color:#efe7ff; font-size:.84rem; text-decoration:none; }
  .setmaxx-mini-link:hover { text-decoration:underline; }
</style>
<?php setmaxx_page_foot(); ?>
