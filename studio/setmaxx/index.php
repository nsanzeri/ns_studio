<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = base_url('/setmaxx/index.php');
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$isProUser = rss_current_user_is_pro($pdo);
$upgradeUrl = rss_tool_upgrade_url();
$sessionLinkBase = base_url('/setmaxx/public.php?token=');
$messages = [];
$errors = [];

function setmaxx_table_exists(PDO $pdo, string $tableName): bool {
    static $cache = [];
    $key = strtolower(trim($tableName));
    if ($key === '') {
        return false;
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$key]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

$requiredTables = ['setmaxx_songs', 'setmaxx_gig_sessions', 'setmaxx_requests'];
$tablesReady = true;
foreach ($requiredTables as $tableName) {
    if (!setmaxx_table_exists($pdo, $tableName)) {
        $tablesReady = false;
        break;
    }
}

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        try {
            if ($action === 'add_song') {
                $title = trim((string)($_POST['title'] ?? ''));
                $artist = trim((string)($_POST['artist'] ?? ''));
                $tipDollars = (float)($_POST['tip_dollars'] ?? 10);
                $tipCents = max(0, (int)round($tipDollars * 100));

                if ($title === '') {
                    throw new RuntimeException('Song title is required.');
                }

                $stmt = $pdo->prepare("INSERT INTO setmaxx_songs (user_id, title, artist, tip_amount_cents) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $title, $artist !== '' ? $artist : null, $tipCents]);
                $messages[] = 'Song added to your Set Maxx catalog.';
            }

            if ($action === 'create_session') {
                $title = trim((string)($_POST['session_title'] ?? ''));
                $venue = trim((string)($_POST['venue_name'] ?? ''));
                $goLive = isset($_POST['go_live']) ? 1 : 0;

                if ($title === '') {
                    throw new RuntimeException('Session title is required.');
                }

                $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
                if ($baseSlug === '') {
                    $baseSlug = 'gig';
                }
                $sessionSlug = $baseSlug . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
                $publicToken = bin2hex(random_bytes(16));
                $status = $goLive ? 'live' : 'draft';

                $pdo->beginTransaction();
                if ($goLive) {
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live'")
                        ->execute([$userId]);
                }
                $stmt = $pdo->prepare("INSERT INTO setmaxx_gig_sessions (user_id, title, venue_name, session_slug, public_token, status, starts_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $title, $venue !== '' ? $venue : null, $sessionSlug, $publicToken, $status, $goLive ? date('Y-m-d H:i:s') : null]);
                $pdo->commit();
                $messages[] = $goLive ? 'New live session created.' : 'Session created in draft mode.';
            }

            if ($action === 'session_status') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $newStatus = (string)($_POST['new_status'] ?? '');
                if ($sessionId <= 0 || !in_array($newStatus, ['live', 'closed'], true)) {
                    throw new RuntimeException('Invalid session update.');
                }

                $pdo->beginTransaction();
                if ($newStatus === 'live') {
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live' AND id <> ?")
                        ->execute([$userId, $sessionId]);
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'live', starts_at = COALESCE(starts_at, NOW()), ends_at = NULL WHERE id = ? AND user_id = ?")
                        ->execute([$sessionId, $userId]);
                    $messages[] = 'Session is now live.';
                } else {
                    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE id = ? AND user_id = ?")
                        ->execute([$sessionId, $userId]);
                    $messages[] = 'Session closed.';
                }
                $pdo->commit();
            }

            if ($action === 'request_status') {
                $requestId = (int)($_POST['request_id'] ?? 0);
                $newStatus = (string)($_POST['new_status'] ?? '');
                $allowedStatuses = ['queued', 'played', 'declined', 'canceled'];
                if ($requestId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
                    throw new RuntimeException('Invalid request update.');
                }

                $activeLock = in_array($newStatus, ['declined', 'canceled'], true) ? 0 : 1;
                $stmt = $pdo->prepare(
                    "UPDATE setmaxx_requests r
                     JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
                     SET r.status = ?, r.active_lock = ?
                     WHERE r.id = ? AND gs.user_id = ?"
                );
                $stmt->execute([$newStatus, $activeLock, $requestId, $userId]);
                $messages[] = 'Request updated.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$songs = [];
$sessions = [];
$liveSession = null;
$requests = [];
$recentRequests = [];

if ($tablesReady) {
    $songsStmt = $pdo->prepare("SELECT id, title, artist, tip_amount_cents, is_active, created_at FROM setmaxx_songs WHERE user_id = ? ORDER BY is_active DESC, title ASC, artist ASC");
    $songsStmt->execute([$userId]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);

    $sessionsStmt = $pdo->prepare("SELECT id, title, venue_name, session_slug, public_token, status, starts_at, ends_at, created_at FROM setmaxx_gig_sessions WHERE user_id = ? ORDER BY FIELD(status, 'live', 'draft', 'closed'), created_at DESC LIMIT 12");
    $sessionsStmt->execute([$userId]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sessions as $session) {
        if (($session['status'] ?? '') === 'live') {
            $liveSession = $session;
            break;
        }
    }

    if ($liveSession) {
        $reqStmt = $pdo->prepare(
            "SELECT r.id, r.requester_name, r.request_note, r.amount_cents, r.status, r.created_at,
                    s.title, s.artist
             FROM setmaxx_requests r
             JOIN setmaxx_songs s ON s.id = r.song_id
             WHERE r.gig_session_id = ?
             ORDER BY FIELD(r.status, 'pending', 'queued', 'played', 'declined', 'canceled'), r.amount_cents DESC, r.created_at DESC"
        );
        $reqStmt->execute([(int)$liveSession['id']]);
        $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $recentStmt = $pdo->prepare(
        "SELECT r.id, r.requester_name, r.amount_cents, r.status, r.created_at, s.title, s.artist, gs.title AS session_title
         FROM setmaxx_requests r
         JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
         JOIN setmaxx_songs s ON s.id = r.song_id
         WHERE gs.user_id = ?
         ORDER BY r.created_at DESC
         LIMIT 8"
    );
    $recentStmt->execute([$userId]);
    $recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set Maxx | Song requests for working performers</title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .setmaxx-shell { padding: 2rem 0 4rem; }
    .setmaxx-hero { display:grid; grid-template-columns:1.25fr .75fr; gap:1.25rem; margin-bottom:1.5rem; }
    .setmaxx-card {
      background: rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:24px;
      padding:1.35rem;
      box-shadow:0 16px 34px rgba(0,0,0,.18);
    }
    .setmaxx-grid { display:grid; grid-template-columns: 1.05fr .95fr; gap:1.25rem; }
    .setmaxx-stack { display:grid; gap:1rem; }
    .setmaxx-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.9rem; }
    .setmaxx-field { display:grid; gap:.45rem; }
    .setmaxx-input, .setmaxx-select, .setmaxx-textarea {
      width:100%; padding:.85rem .95rem; border-radius:14px; border:1px solid rgba(255,255,255,.1);
      background:rgba(255,255,255,.05); color:#fff; font:inherit;
    }
    .setmaxx-textarea { min-height:100px; resize:vertical; }
    .setmaxx-actions { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }
    .setmaxx-pill {
      display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .75rem; border-radius:999px; font-size:.84rem; font-weight:600;
      background: rgba(140,107,255,.16); color:#efe7ff;
    }
    .setmaxx-list { display:grid; gap:.8rem; }
    .setmaxx-row {
      display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;
      padding:.95rem 1rem; border-radius:16px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.06);
    }
    .setmaxx-meta { color:rgba(255,255,255,.72); font-size:.92rem; }
    .setmaxx-request-amount { font-weight:700; color:#efe7ff; }
    .setmaxx-status { text-transform:capitalize; }
    .setmaxx-status.pending { color:#ffd86b; }
    .setmaxx-status.queued { color:#7fd0ff; }
    .setmaxx-status.played { color:#7dffbf; }
    .setmaxx-status.declined, .setmaxx-status.canceled { color:#ff9999; }
    .setmaxx-help { color: rgba(255,255,255,.72); font-size:.93rem; }
    .setmaxx-note { border-left:3px solid rgba(140,107,255,.5); padding-left:.9rem; }
    .setmaxx-link-box { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; padding:.9rem 1rem; border-radius:16px; background:rgba(140,107,255,.08); border:1px solid rgba(140,107,255,.16); }
    .setmaxx-link-box code { word-break:break-all; }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 980px) {
      .setmaxx-hero, .setmaxx-grid, .setmaxx-form-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/setmaxx_header.php'; ?>
<main class="container setmaxx-shell">
  <?php foreach ($messages as $message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <section class="setmaxx-hero">
    <div class="setmaxx-card">
      <div class="setmaxx-pill">First pass</div>
      <h1 style="margin:.8rem 0 .45rem;">Set Maxx</h1>
      <p class="setmaxx-help" style="font-size:1rem; margin:0 0 1rem;">Build a requestable song list, spin up a live gig page, and keep the performer in control. This first pass enforces one active request per song per gig session so the same tune cannot be requested twice in one show.</p>
      <div class="setmaxx-actions">
        <span class="setmaxx-pill"><?= $isProUser ? 'Included in your tools plan' : 'Paid tools plan required' ?></span>
        <?php if (!$isProUser): ?>
          <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade to unlock Set Maxx</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">What this version does</h2>
      <ul class="setmaxx-help" style="margin:0; padding-left:1.2rem;">
        <li>Creates song records with suggested tip amounts.</li>
        <li>Creates live or draft gig sessions with unique public links.</li>
        <li>Prevents duplicate requests for the same song in the same session.</li>
        <li>Lets you queue, play, decline, or cancel requests from the dashboard.</li>
      </ul>
      <p class="setmaxx-help setmaxx-note" style="margin-top:1rem;">Payment processing and direct Stripe Connect payouts are not wired in yet in this first pass. Requests capture the amount and workflow so the product structure is in place.</p>
    </div>
  </section>

  <?php if (!$tablesReady): ?>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Database setup required</h2>
      <p class="setmaxx-help">Run the SQL in <code>studio/setmaxx/install.sql</code> first. After that, refresh this page and the module will come alive.</p>
    </div>
  <?php else: ?>
    <section class="setmaxx-grid">
      <div class="setmaxx-stack">
        <div class="setmaxx-card">
          <h2 style="margin-top:0;">Song catalog</h2>
          <form method="post" class="setmaxx-stack" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_song">
            <div class="setmaxx-form-grid">
              <div class="setmaxx-field">
                <label for="title">Song title</label>
                <input class="setmaxx-input" id="title" name="title" required>
              </div>
              <div class="setmaxx-field">
                <label for="artist">Artist</label>
                <input class="setmaxx-input" id="artist" name="artist">
              </div>
              <div class="setmaxx-field">
                <label for="tip_dollars">Suggested tip</label>
                <input class="setmaxx-input" id="tip_dollars" name="tip_dollars" type="number" min="1" step="1" value="10">
              </div>
            </div>
            <div class="setmaxx-actions">
              <button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Add song</button>
              <span class="setmaxx-help">Keep the catalog tight. This will become the public request list.</span>
            </div>
          </form>

          <div class="setmaxx-list" style="margin-top:1rem;">
            <?php if (!$songs): ?>
              <div class="setmaxx-row"><div class="setmaxx-meta">No songs yet. Add a few staples first so you can test the full request flow.</div></div>
            <?php else: ?>
              <?php foreach ($songs as $song): ?>
                <div class="setmaxx-row">
                  <div>
                    <div style="font-weight:600;"><?= e($song['title']) ?></div>
                    <div class="setmaxx-meta"><?= e((string)($song['artist'] ?: 'Artist not set')) ?></div>
                  </div>
                  <div class="setmaxx-request-amount">$<?= number_format(((int)$song['tip_amount_cents']) / 100, 0) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="setmaxx-card">
          <h2 style="margin-top:0;">Recent requests</h2>
          <div class="setmaxx-list">
            <?php if (!$recentRequests): ?>
              <div class="setmaxx-row"><div class="setmaxx-meta">No requests yet.</div></div>
            <?php else: ?>
              <?php foreach ($recentRequests as $request): ?>
                <div class="setmaxx-row">
                  <div>
                    <div style="font-weight:600;"><?= e($request['title']) ?></div>
                    <div class="setmaxx-meta"><?= e($request['session_title']) ?> · <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
                  </div>
                  <div>
                    <div class="setmaxx-request-amount">$<?= number_format(((int)$request['amount_cents']) / 100, 0) ?></div>
                    <div class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="setmaxx-stack">
        <div class="setmaxx-card">
          <h2 style="margin-top:0;">Gig sessions</h2>
          <form method="post" class="setmaxx-stack" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_session">
            <div class="setmaxx-form-grid">
              <div class="setmaxx-field">
                <label for="session_title">Session title</label>
                <input class="setmaxx-input" id="session_title" name="session_title" placeholder="Friday at Moretti's" required>
              </div>
              <div class="setmaxx-field">
                <label for="venue_name">Venue</label>
                <input class="setmaxx-input" id="venue_name" name="venue_name" placeholder="Moretti's Rosemont">
              </div>
            </div>
            <label style="display:flex; gap:.6rem; align-items:center;">
              <input type="checkbox" name="go_live" value="1" checked>
              <span class="setmaxx-help">Make this the live request page now</span>
            </label>
            <div class="setmaxx-actions">
              <button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Create session</button>
            </div>
          </form>

          <div class="setmaxx-list" style="margin-top:1rem;">
            <?php if (!$sessions): ?>
              <div class="setmaxx-row"><div class="setmaxx-meta">No gig sessions yet.</div></div>
            <?php else: ?>
              <?php foreach ($sessions as $session): ?>
                <?php $publicUrl = $sessionLinkBase . rawurlencode((string)$session['public_token']); ?>
                <div class="setmaxx-row">
                  <div style="min-width:0; flex:1;">
                    <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;">
                      <div style="font-weight:600;"><?= e($session['title']) ?></div>
                      <span class="setmaxx-pill"><?= e(ucfirst((string)$session['status'])) ?></span>
                    </div>
                    <div class="setmaxx-meta"><?= e((string)($session['venue_name'] ?: 'Venue not set')) ?></div>
                    <div class="setmaxx-link-box" style="margin-top:.7rem;">
                      <strong>Public page</strong>
                      <code><?= e($publicUrl) ?></code>
                      <a class="btn btn-outline" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open</a>
                    </div>
                  </div>
                  <div class="setmaxx-actions">
                    <?php if (($session['status'] ?? '') !== 'live'): ?>
                      <form method="post" action="">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="session_status">
                        <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                        <input type="hidden" name="new_status" value="live">
                        <button class="btn btn-outline" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Go live</button>
                      </form>
                    <?php endif; ?>
                    <?php if (($session['status'] ?? '') === 'live'): ?>
                      <form method="post" action="">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="session_status">
                        <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                        <input type="hidden" name="new_status" value="closed">
                        <button class="btn btn-outline" type="submit">Close</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="setmaxx-card">
          <h2 style="margin-top:0;">Live request dashboard</h2>
          <?php if (!$liveSession): ?>
            <p class="setmaxx-help">No live session right now. Create one above and make it live to start collecting requests.</p>
          <?php else: ?>
            <div class="setmaxx-meta" style="margin-bottom:1rem;">Live now: <strong><?= e($liveSession['title']) ?></strong></div>
            <div class="setmaxx-list">
              <?php if (!$requests): ?>
                <div class="setmaxx-row"><div class="setmaxx-meta">No requests yet for this session.</div></div>
              <?php else: ?>
                <?php foreach ($requests as $request): ?>
                  <div class="setmaxx-row">
                    <div style="min-width:0; flex:1;">
                      <div style="display:flex; gap:.55rem; align-items:center; flex-wrap:wrap;">
                        <div style="font-weight:600;"><?= e($request['title']) ?></div>
                        <span class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></span>
                      </div>
                      <div class="setmaxx-meta"><?= e((string)($request['artist'] ?: 'Artist not set')) ?> · from <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
                      <?php if (!empty($request['request_note'])): ?>
                        <div class="setmaxx-help" style="margin-top:.35rem;">“<?= e((string)$request['request_note']) ?>”</div>
                      <?php endif; ?>
                    </div>
                    <div>
                      <div class="setmaxx-request-amount">$<?= number_format(((int)$request['amount_cents']) / 100, 0) ?></div>
                      <div class="setmaxx-actions" style="justify-content:flex-end; margin-top:.45rem;">
                        <?php foreach (['queued' => 'Queue', 'played' => 'Played', 'declined' => 'Decline'] as $statusValue => $label): ?>
                          <?php if ($request['status'] !== $statusValue): ?>
                            <form method="post" action="">
                              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="request_status">
                              <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                              <input type="hidden" name="new_status" value="<?= e($statusValue) ?>">
                              <button class="btn btn-outline" type="submit"><?= e($label) ?></button>
                            </form>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../../includes/setmaxx_footer.php'; ?>
</body>
</html>
