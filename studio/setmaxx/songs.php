<?php
require_once __DIR__ . '/_common.php';

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        try {
            $title = trim((string)($_POST['title'] ?? ''));
            $artist = trim((string)($_POST['artist'] ?? ''));
            $tipDollars = (float)($_POST['tip_dollars'] ?? 10);
            $tipCents = max(0, (int)round($tipDollars * 100));
            if ($title === '') throw new RuntimeException('Song title is required.');
            $stmt = $pdo->prepare("INSERT INTO setmaxx_songs (user_id, title, artist, tip_amount_cents) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $artist !== '' ? $artist : null, $tipCents]);
            $messages[] = 'Song added to your Set Maxx catalog.';
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

$songs = [];
if ($tablesReady) {
    $songsStmt = $pdo->prepare("SELECT id, title, artist, tip_amount_cents, is_active, created_at FROM setmaxx_songs WHERE user_id = ? ORDER BY is_active DESC, title ASC, artist ASC");
    $songsStmt->execute([$userId]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
}
setmaxx_page_head('Set Maxx | Song Catalog');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Song Catalog</div>
    <h1 style="margin:.8rem 0 .35rem;">Songs fans can request</h1>
    <p class="setmaxx-help">Keep this list focused. These are the tunes you are actually willing to play when the request page is live.</p>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Add song</h2>
      <form method="post" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="setmaxx-form-grid">
          <div class="setmaxx-field"><label for="title">Song title</label><input class="setmaxx-input" id="title" name="title" required></div>
          <div class="setmaxx-field"><label for="artist">Artist</label><input class="setmaxx-input" id="artist" name="artist"></div>
          <div class="setmaxx-field"><label for="tip_dollars">Suggested tip</label><input class="setmaxx-input" id="tip_dollars" name="tip_dollars" type="number" min="1" step="1" value="10"></div>
        </div>
        <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Add song</button></div>
      </form>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Next step</h2>
      <p class="setmaxx-help">Once your core songs are loaded, create a gig session and share the request page QR/link.</p>
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Go to Gig Sessions</a>
    </div>
  </section>
  <div class="setmaxx-card" style="margin-top:1rem;">
    <h2 style="margin-top:0;">Your catalog</h2>
    <div class="setmaxx-list">
      <?php if (!$songs): ?>
        <div class="setmaxx-row"><div class="setmaxx-meta">No songs yet. Add a few staples first.</div></div>
      <?php else: foreach ($songs as $song): ?>
        <div class="setmaxx-row">
          <div><div style="font-weight:600;"><?= e($song['title']) ?></div><div class="setmaxx-meta"><?= e((string)($song['artist'] ?: 'Artist not set')) ?></div></div>
          <div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$song['tip_amount_cents'])) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>
