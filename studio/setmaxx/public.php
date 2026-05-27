<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$linkToken = trim((string)($_GET['link'] ?? ''));
$errors = [];
$messages = [];
$session = null;
$stableLinkFound = false;
$songs = [];
$lockedSongIds = [];
$availableLetters = [];
$songCount = 0;

function setmaxx_public_absolute_url(string $path): string {
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

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

function setmaxx_public_table_exists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_public_ensure_suggestions_table(PDO $pdo): void {
    if (setmaxx_public_table_exists($pdo, 'setmaxx_song_suggestions')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_song_suggestions` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `gig_session_id` bigint(20) unsigned NOT NULL,
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

function setmaxx_public_tip_fee_percent(): int {
    return max(0, min(100, (int)env('SETMAXX_TIP_PLATFORM_FEE_PERCENT', 10)));
}

function setmaxx_public_direct_platform_tip_user_ids(): array {
    $raw = (string)env('SETMAXX_DIRECT_PLATFORM_TIP_USER_IDS', '');
    if (trim($raw) === '') return [];
    return array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw) ?: []), fn($id) => $id > 0)));
}

function setmaxx_public_user_uses_direct_platform_tips(int $userId): bool {
    return in_array($userId, setmaxx_public_direct_platform_tip_user_ids(), true);
}

$tablesReady = setmaxx_public_tables_ready($pdo);

if ($tablesReady && $token !== '') {
    $stmt = $pdo->prepare(
        "SELECT gs.id, gs.user_id, gs.title, gs.venue_name, gs.status, gs.starts_at, u.display_name
         FROM setmaxx_gig_sessions gs
         JOIN users u ON u.id = gs.user_id
         WHERE gs.public_token = ?
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($tablesReady && $linkToken !== '' && setmaxx_public_table_exists($pdo, 'setmaxx_public_links')) {
    $linkStmt = $pdo->prepare(
        "SELECT gs.id, gs.user_id, gs.title, gs.venue_name, gs.status, gs.starts_at, u.display_name
         FROM setmaxx_public_links spl
         JOIN users u ON u.id = spl.user_id
         LEFT JOIN setmaxx_gig_sessions gs
           ON gs.user_id = spl.user_id
          AND gs.status = 'live'
         WHERE spl.public_token = ?
         ORDER BY COALESCE(gs.starts_at, gs.created_at) DESC, gs.id DESC
         LIMIT 1"
    );
    $linkStmt->execute([$linkToken]);
    $linkRow = $linkStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($linkRow) {
        $stableLinkFound = true;
        if (!empty($linkRow['id'])) {
            $session = $linkRow;
        }
    }
}

if ($session && $tablesReady) {
    if (isset($_GET['paid'])) {
        $messages[] = 'Payment received. Your request is being sent to the performer.';
    } elseif (isset($_GET['canceled'])) {
        $errors[] = 'Payment was canceled, so the paid request was not sent.';
    }

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
    $songCount = count($songs);

    foreach ($songs as $song) {
        $first = strtoupper(substr(trim((string)$song['title']), 0, 1));
        $letter = preg_match('/[A-Z]/', $first) ? $first : '#';
        $availableLetters[$letter] = true;
    }
    ksort($availableLetters);

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
        $action = (string)($_POST['action'] ?? 'request_song');
        if ($action === 'suggest_song') {
            $suggestedTitle = trim((string)($_POST['suggested_title'] ?? ''));
            $suggestedArtist = trim((string)($_POST['suggested_artist'] ?? ''));
            $suggestionName = trim((string)($_POST['suggestion_name'] ?? ''));
            $suggestionNote = trim((string)($_POST['suggestion_note'] ?? ''));

            if ($suggestedTitle === '') {
                $errors[] = 'Add a song title for the suggestion.';
            } else {
                try {
                    setmaxx_public_ensure_suggestions_table($pdo);
                    $suggestStmt = $pdo->prepare(
                        "INSERT INTO setmaxx_song_suggestions
                            (gig_session_id, user_id, suggested_title, suggested_artist, requester_name, suggestion_note)
                         VALUES
                            (?, ?, ?, ?, ?, ?)"
                    );
                    $suggestStmt->execute([
                        (int)$session['id'],
                        (int)$session['user_id'],
                        mb_substr($suggestedTitle, 0, 190),
                        $suggestedArtist !== '' ? mb_substr($suggestedArtist, 0, 190) : null,
                        $suggestionName !== '' ? mb_substr($suggestionName, 0, 190) : null,
                        $suggestionNote !== '' ? mb_substr($suggestionNote, 0, 255) : null,
                    ]);
                    $messages[] = 'Suggestion sent to the performer.';
                } catch (Throwable $e) {
                    $errors[] = 'The suggestion could not be sent right now.';
                }
            }
        } else {
        $songId = (int)($_POST['song_id'] ?? 0);
        $requesterName = trim((string)($_POST['requester_name'] ?? ''));
        $requestNote = trim((string)($_POST['request_note'] ?? ''));
        $requestAmountDollars = (int)($_POST['request_amount_dollars'] ?? 0);

        $songStmt = $pdo->prepare(
            "SELECT id, title, artist, tip_amount_cents
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
        $minimumDollars = $song ? (int)ceil(((int)$song['tip_amount_cents']) / 100) : 0;
        $minimumDollars = max(0, min(100, $minimumDollars));

        if (!($requestAmountDollars === 0 || ($requestAmountDollars >= 10 && $requestAmountDollars <= 100))) {
            $errors[] = 'Choose $0 for a free request, or a paid amount from $10 to $100.';
        } elseif ($minimumDollars > 0 && $requestAmountDollars < $minimumDollars) {
            $errors[] = 'This song starts at $' . $minimumDollars . '.';
        } elseif (!$song) {
            $errors[] = 'That song is not available for this request page.';
        } elseif ($requestAmountDollars > 0 && in_array($songId, $lockedSongIds, true)) {
            $errors[] = 'That song has already been requested for this gig.';
        } elseif ($requestAmountDollars > 0) {
            try {
                require_once __DIR__ . '/../_private/config/stripe.php';

                $performerUserId = (int)($session['user_id'] ?? 0);
                $amountCents = $requestAmountDollars * 100;
                $checkoutPayload = [
                    'mode' => 'payment',
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Song request: ' . (string)$song['title'],
                                'description' => trim((string)($song['artist'] ?? '')) !== '' ? (string)$song['artist'] : 'Set Maxx request',
                            ],
                            'unit_amount' => $amountCents,
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => setmaxx_public_absolute_url(base_url('/setmaxx/public.php?' . ($linkToken !== '' ? 'link=' . rawurlencode($linkToken) : 'token=' . rawurlencode($token)) . '&paid=1')),
                    'cancel_url' => setmaxx_public_absolute_url(base_url('/setmaxx/public.php?' . ($linkToken !== '' ? 'link=' . rawurlencode($linkToken) : 'token=' . rawurlencode($token)) . '&canceled=1')),
                    'metadata' => [
                        'kind' => 'setmaxx_tip',
                        'gig_session_id' => (string)(int)$session['id'],
                        'song_id' => (string)$songId,
                        'performer_user_id' => (string)$performerUserId,
                        'requester_name' => mb_substr($requesterName, 0, 190),
                        'request_note' => mb_substr($requestNote, 0, 255),
                    ],
                ];

                if (!setmaxx_public_user_uses_direct_platform_tips($performerUserId)) {
                    if (!setmaxx_public_table_exists($pdo, 'setmaxx_connect_accounts')) {
                        $errors[] = 'Paid requests are not ready for this performer yet.';
                    } else {
                        $connectStmt = $pdo->prepare("SELECT stripe_account_id, charges_enabled, payouts_enabled, details_submitted FROM setmaxx_connect_accounts WHERE user_id = ? LIMIT 1");
                        $connectStmt->execute([$performerUserId]);
                        $connectAccount = $connectStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                        if (!$connectAccount || empty($connectAccount['charges_enabled']) || empty($connectAccount['payouts_enabled']) || empty($connectAccount['details_submitted'])) {
                            $errors[] = 'Paid requests are not ready for this performer yet.';
                        } else {
                            $checkoutPayload['payment_intent_data'] = [
                                'application_fee_amount' => (int)floor($amountCents * (setmaxx_public_tip_fee_percent() / 100)),
                                'transfer_data' => [
                                    'destination' => (string)$connectAccount['stripe_account_id'],
                                ],
                            ];
                        }
                    }
                }

                if (!$errors) {
                    $checkoutSession = \Stripe\Checkout\Session::create($checkoutPayload);
                    header('Location: ' . (string)$checkoutSession->url);
                    exit;
                }
            } catch (Throwable $e) {
                error_log('SetMaxx paid request checkout failed: ' . $e->getMessage());
                $errors[] = 'Paid requests are not available right now. Please try a free request or check back shortly.';
            }
        } else {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO setmaxx_requests (gig_session_id, song_id, requester_name, request_note, amount_cents, status, active_lock)
                     VALUES (?, ?, ?, ?, ?, 'pending', NULL)"
                );
                $insert->execute([
                    (int)$session['id'],
                    $songId,
                    $requesterName !== '' ? $requesterName : null,
                    $requestNote !== '' ? $requestNote : null,
                    $requestAmountDollars * 100,
                ]);
                $messages[] = 'Request sent to the performer.';
            } catch (Throwable $e) {
                $errors[] = 'That song has already been requested for this gig.';
            }
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
      max-width: 1120px; margin: 0 auto; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
      border-radius: 26px; padding: 1.4rem; box-shadow: 0 20px 50px rgba(0,0,0,.28);
    }
    .alpha-menu { position:sticky; top:.5rem; z-index:3; display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin-top:1rem; padding:.65rem; border-radius:16px; background:rgba(9,8,20,.92); border:1px solid rgba(255,255,255,.08); }
    .alpha-label { color:rgba(255,255,255,.68); font-size:.82rem; font-weight:600; padding:0 .25rem; }
    .alpha-spacer { flex:1 1 1rem; }
    .alpha-button, .sort-button { min-width:34px; height:34px; border-radius:10px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.82rem; cursor:pointer; }
    .sort-button { padding:0 .75rem; }
    .alpha-button.active, .sort-button.active, .alpha-button:hover, .sort-button:hover { background:rgba(140,107,255,.24); border-color:rgba(140,107,255,.45); }
    .alpha-button:disabled { opacity:.35; cursor:not-allowed; }
    .public-grid { display:grid; gap:.45rem; margin-top:.85rem; }
    .song-card { padding:.6rem .7rem; border-radius:14px; background: rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); }
    .song-card.locked { opacity:.6; }
    .song-meta { color: rgba(255,255,255,.72); font-size:.92rem; }
    .song-title { font-weight:600; line-height:1.2; }
    .song-row { display:grid; grid-template-columns:minmax(220px, 1.1fr) minmax(430px, 1.7fr); gap:.75rem; align-items:center; }
    .request-form { display:grid; grid-template-columns:105px minmax(120px, 1fr) minmax(150px, 1.2fr) auto; gap:.5rem; align-items:center; }
    .request-input, .request-select { width:100%; padding:.52rem .62rem; border-radius:10px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.9rem; }
    .request-select option { background:#151323; color:#fff; }
    .request-input::placeholder { color:rgba(255,255,255,.52); }
    .request-submit { padding:.54rem .85rem; white-space:nowrap; }
    .request-note { margin-top:1rem; padding:1rem; border-radius:16px; background:rgba(140,107,255,.1); border:1px solid rgba(140,107,255,.16); }
    .suggestion-card { margin-top:1rem; padding:1rem; border-radius:18px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); }
    .suggestion-form { display:grid; grid-template-columns:minmax(160px, 1fr) minmax(140px, .9fr) minmax(120px, .8fr) minmax(180px, 1.2fr) auto; gap:.55rem; align-items:center; }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 900px) {
      .song-row, .request-form, .suggestion-form { grid-template-columns:1fr; }
      .request-submit { width:100%; }
    }
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
      <?php if ($stableLinkFound): ?>
        <p class="song-meta">There is no live Set Maxx session right now. Check back when the performer opens requests.</p>
      <?php endif; ?>
    <?php else: ?>
      <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
        <div>
          <div style="display:inline-flex; padding:.3rem .7rem; border-radius:999px; background:rgba(140,107,255,.16); color:#efe7ff; font-weight:600;">Live song requests</div>
          <h1 style="margin:.8rem 0 .35rem;"><?= e($session['title']) ?></h1>
          <div class="song-meta"><?= e((string)($session['venue_name'] ?: 'Tonight\'s show')) ?> &middot; hosted by <?= e((string)($session['display_name'] ?: 'the performer')) ?></div>
        </div>
        <div class="song-meta" style="max-width:320px;"><?= (int)$songCount ?> active <?= $songCount === 1 ? 'song' : 'songs' ?> available. One active request per song is allowed tonight, so anything already requested is locked.</div>
      </div>

      <div class="request-note song-meta">
        Choose $0 for a free request, or choose a paid request from $10 to $100. Requests are still subject to performer discretion.
      </div>

      <div class="suggestion-card">
        <div style="font-weight:600; margin-bottom:.55rem;">Suggest a song for the future</div>
        <form method="post" class="suggestion-form" action="">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="suggest_song">
          <input class="request-input" name="suggested_title" placeholder="Song title" required>
          <input class="request-input" name="suggested_artist" placeholder="Artist">
          <input class="request-input" name="suggestion_name" placeholder="Your name">
          <input class="request-input" name="suggestion_note" placeholder="Optional note">
          <button class="btn btn-outline request-submit" type="submit">Suggest</button>
        </form>
      </div>

      <?php if ($songs): ?>
        <div class="alpha-menu" aria-label="Song alphabet filter">
          <span class="alpha-label">Filter</span>
          <button class="alpha-button active" type="button" data-letter="all">All</button>
          <?php foreach (array_merge(['#'], range('A', 'Z')) as $letter): ?>
            <button class="alpha-button" type="button" data-letter="<?= e($letter) ?>" <?= isset($availableLetters[$letter]) ? '' : 'disabled' ?>><?= e($letter) ?></button>
          <?php endforeach; ?>
          <span class="alpha-spacer"></span>
          <span class="alpha-label">Sort</span>
          <button class="sort-button active" type="button" data-sort="title">Title</button>
          <button class="sort-button" type="button" data-sort="artist">Artist</button>
        </div>
      <?php endif; ?>

      <div class="public-grid">
        <?php if (!$songs): ?>
          <div class="song-card"><div class="song-meta">No active songs are available for this request page right now.</div></div>
        <?php else: foreach ($songs as $song): ?>
          <?php
            $locked = in_array((int)$song['id'], $lockedSongIds, true);
            $first = strtoupper(substr(trim((string)$song['title']), 0, 1));
            $letter = preg_match('/[A-Z]/', $first) ? $first : '#';
            $artistSort = (string)($song['artist'] ?: $song['title']);
            $artistFirst = strtoupper(substr(trim($artistSort), 0, 1));
            $artistLetter = preg_match('/[A-Z]/', $artistFirst) ? $artistFirst : '#';
            $minimumDollars = max(0, min(100, (int)ceil(((int)$song['tip_amount_cents']) / 100)));
          ?>
          <div class="song-card <?= $locked ? 'locked' : '' ?>" data-letter="<?= e($letter) ?>" data-title-letter="<?= e($letter) ?>" data-artist-letter="<?= e($artistLetter) ?>" data-title="<?= e(strtolower((string)$song['title'])) ?>" data-artist="<?= e(strtolower($artistSort)) ?>">
            <div class="song-row">
              <div>
                <div class="song-title"><?= e($song['title']) ?></div>
                <div class="song-meta"><?= e((string)($song['artist'] ?: 'Artist not listed')) ?></div>
              </div>

              <?php if ($locked): ?>
                <div class="song-meta"><strong>Already requested tonight.</strong></div>
              <?php else: ?>
                <form method="post" class="request-form" action="">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="request_song">
                  <input type="hidden" name="song_id" value="<?= (int)$song['id'] ?>">
                  <select class="request-select" name="request_amount_dollars" aria-label="Request amount">
                    <?php if ($minimumDollars <= 0): ?>
                      <option value="0">$0</option>
                    <?php endif; ?>
                    <?php for ($amount = max(10, $minimumDollars); $amount <= 100; $amount++): ?>
                      <option value="<?= $amount ?>">$<?= $amount ?></option>
                    <?php endfor; ?>
                  </select>
                  <input class="request-input" name="requester_name" placeholder="Your name">
                  <input class="request-input" name="request_note" placeholder="Optional note">
                  <button class="btn btn-primary request-submit" type="submit">Request</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<script>
(function() {
  const buttons = Array.from(document.querySelectorAll('.alpha-button'));
  const sortButtons = Array.from(document.querySelectorAll('.sort-button'));
  const cards = Array.from(document.querySelectorAll('.song-card[data-letter]'));
  const grid = document.querySelector('.public-grid');
  let currentLetter = 'all';
  let currentSort = 'title';
  if (!buttons.length || !cards.length) return;

  function cardLetter(card) {
    return card.getAttribute('data-' + currentSort + '-letter') || '#';
  }

  function updateAlphabetAvailability() {
    const letters = new Set(cards.map(cardLetter));
    buttons.forEach(function(button) {
      const letter = button.getAttribute('data-letter');
      if (letter === 'all') {
        button.disabled = false;
      } else {
        button.disabled = !letters.has(letter);
      }
      if (button.disabled && button.classList.contains('active')) {
        currentLetter = 'all';
      }
    });
  }

  function sortCards() {
    cards.sort(function(a, b) {
      const aValue = a.getAttribute('data-' + currentSort) || '';
      const bValue = b.getAttribute('data-' + currentSort) || '';
      return aValue.localeCompare(bValue);
    });
    cards.forEach(function(card) { grid.appendChild(card); });
  }

  function applyCatalogView(shouldScroll) {
    updateAlphabetAvailability();
    sortCards();
    buttons.forEach(function(item) {
      item.classList.toggle('active', item.getAttribute('data-letter') === currentLetter);
    });
    cards.forEach(function(card) {
      card.hidden = currentLetter !== 'all' && cardLetter(card) !== currentLetter;
    });
    if (shouldScroll) {
      const firstVisible = cards.find(function(card) { return !card.hidden; });
      if (firstVisible) firstVisible.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
  }

  buttons.forEach(function(button) {
    button.addEventListener('click', function() {
      if (button.disabled) return;
      currentLetter = button.getAttribute('data-letter') || 'all';
      applyCatalogView(true);
    });
  });

  sortButtons.forEach(function(button) {
    button.addEventListener('click', function() {
      currentSort = button.getAttribute('data-sort') || 'title';
      sortButtons.forEach(function(item) { item.classList.toggle('active', item === button); });
      currentLetter = 'all';
      applyCatalogView(false);
    });
  });

  applyCatalogView(false);
})();
</script>
</body>
</html>
