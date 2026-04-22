<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$errors = [];
$messages = [];
$session = null;
$songs = [];
$lockedSongIds = [];

function setmaxx_public_tables_ready(PDO $pdo): bool {
    foreach (['setmaxx_songs', 'setmaxx_gig_sessions', 'setmaxx_requests'] as $tableName) {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        $stmt->execute([$tableName]);
        if (!(bool)$stmt->fetchColumn()) {
            return false;
        }
    }
    return true;
}

$tablesReady = setmaxx_public_tables_ready($pdo);

if ($tablesReady && $token !== '') {
    $stmt = $pdo->prepare(
        "SELECT gs.id, gs.title, gs.venue_name, gs.status, gs.starts_at, u.display_name
         FROM setmaxx_gig_sessions gs
         JOIN users u ON u.id = gs.user_id
         WHERE gs.public_token = ?
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($session && $tablesReady) {
    $songsStmt = $pdo->prepare(
        "SELECT id, title, artist, tip_amount_cents
         FROM setmaxx_songs
         WHERE user_id = (
            SELECT user_id FROM setmaxx_gig_sessions WHERE id = ?
         )
           AND is_active = 1
         ORDER BY title ASC, artist ASC"
    );
    $songsStmt->execute([(int)$session['id']]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);

    $lockStmt = $pdo->prepare("SELECT song_id FROM setmaxx_requests WHERE gig_session_id = ? AND active_lock = 1");
    $lockStmt->execute([(int)$session['id']]);
    $lockedSongIds = array_map('intval', array_column($lockStmt->fetchAll(PDO::FETCH_ASSOC), 'song_id'));
}

if ($session && $tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Please refresh the page and try again.';
    } elseif (($session['status'] ?? '') !== 'live') {
        $errors[] = 'This request page is not accepting live requests right now.';
    } else {
        $songId = (int)($_POST['song_id'] ?? 0);
        $requesterName = trim((string)($_POST['requester_name'] ?? ''));
        $requestNote = trim((string)($_POST['request_note'] ?? ''));

        $songStmt = $pdo->prepare(
            "SELECT id, tip_amount_cents
             FROM setmaxx_songs
             WHERE id = ?
               AND user_id = (
                  SELECT user_id FROM setmaxx_gig_sessions WHERE id = ?
               )
               AND is_active = 1
             LIMIT 1"
        );
        $songStmt->execute([$songId, (int)$session['id']]);
        $song = $songStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$song) {
            $errors[] = 'That song is not available for this request page.';
        } elseif (in_array($songId, $lockedSongIds, true)) {
            $errors[] = 'That song has already been requested for this gig.';
        } else {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO setmaxx_requests (gig_session_id, song_id, requester_name, request_note, amount_cents, status, active_lock)
                     VALUES (?, ?, ?, ?, ?, 'pending', 1)"
                );
                $insert->execute([
                    (int)$session['id'],
                    $songId,
                    $requesterName !== '' ? $requesterName : null,
                    $requestNote !== '' ? $requestNote : null,
                    (int)$song['tip_amount_cents'],
                ]);
                $messages[] = 'Request sent to the performer.';
                $lockedSongIds[] = $songId;
            } catch (Throwable $e) {
                $errors[] = 'That song has already been requested for this gig.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($session['title'] ?? 'Set Maxx') ?> | Set Maxx</title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { background: radial-gradient(circle at top, rgba(140,107,255,.22), transparent 35%), #090814; }
    .public-shell { padding: 2rem 0 4rem; }
    .public-card {
      max-width: 980px; margin: 0 auto; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
      border-radius: 26px; padding: 1.4rem; box-shadow: 0 20px 50px rgba(0,0,0,.28);
    }
    .public-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top:1rem; }
    .song-card {
      padding: 1rem; border-radius: 18px; background: rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07);
      display:grid; gap:.75rem;
    }
    .song-card.locked { opacity:.6; }
    .song-meta { color: rgba(255,255,255,.72); font-size:.92rem; }
    .song-top { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; }
    .song-tip { font-weight:700; color:#efe7ff; }
    .request-form { display:grid; gap:.7rem; }
    .request-input, .request-textarea { width:100%; padding:.78rem .9rem; border-radius:14px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .request-textarea { min-height:88px; resize:vertical; }
    .request-note { margin-top:1rem; padding:1rem; border-radius:16px; background:rgba(140,107,255,.1); border:1px solid rgba(140,107,255,.16); }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 860px) { .public-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<main class="container public-shell">
  <div class="public-card">
    <?php foreach ($messages as $message): ?>
      <div class="alert alert-success"><?= e($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if (!$tablesReady): ?>
      <h1 style="margin-top:0;">Set Maxx is not installed yet.</h1>
    <?php elseif (!$session): ?>
      <h1 style="margin-top:0;">Request page not found.</h1>
    <?php else: ?>
      <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
        <div>
          <div style="display:inline-flex; padding:.3rem .7rem; border-radius:999px; background:rgba(140,107,255,.16); color:#efe7ff; font-weight:600;">Live song requests</div>
          <h1 style="margin:.8rem 0 .35rem;"><?= e($session['title']) ?></h1>
          <div class="song-meta"><?= e((string)($session['venue_name'] ?: 'Tonight\'s show')) ?> · hosted by <?= e((string)($session['display_name'] ?: 'the performer')) ?></div>
        </div>
        <div class="song-meta" style="max-width:320px;">Choose from the approved list below. One active request per song is allowed tonight, so anything already requested is locked.</div>
      </div>

      <div class="request-note song-meta">
        Tip amounts shown here are the suggested request amounts for this first pass. Payment and automatic payouts are the next phase. Requests are still subject to performer discretion.
      </div>

      <div class="public-grid">
        <?php foreach ($songs as $song): ?>
          <?php $locked = in_array((int)$song['id'], $lockedSongIds, true); ?>
          <div class="song-card <?= $locked ? 'locked' : '' ?>">
            <div class="song-top">
              <div>
                <div style="font-weight:600;"><?= e($song['title']) ?></div>
                <div class="song-meta"><?= e((string)($song['artist'] ?: 'Artist not listed')) ?></div>
              </div>
              <div class="song-tip">$<?= number_format(((int)$song['tip_amount_cents']) / 100, 0) ?></div>
            </div>

            <?php if ($locked): ?>
              <div class="song-meta"><strong>Already requested tonight.</strong></div>
            <?php else: ?>
              <form method="post" class="request-form" action="">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="song_id" value="<?= (int)$song['id'] ?>">
                <input class="request-input" name="requester_name" placeholder="Your name (optional)">
                <textarea class="request-textarea" name="request_note" placeholder="Optional note for the performer"></textarea>
                <button class="btn btn-primary" type="submit">Send request</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
