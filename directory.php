<?php
require __DIR__ . '/studio/_private/_core/bootstrap.php';
require_once __DIR__ . '/studio/_private/_core/tool_access.php';

$selectedState = strtoupper(trim((string)($_GET['state'] ?? '')));
if (!preg_match('/^[A-Z]{2}$/', $selectedState)) {
    $selectedState = '';
}

function directory_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function directory_public_profiles_ready(PDO $pdo): bool {
    return rss_table_exists($pdo, 'setmaxx_public_profiles')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'directory_visible')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'directory_state');
}

function directory_profile_visibility_columns_ready(PDO $pdo): bool {
    return directory_column_exists($pdo, 'setmaxx_public_profiles', 'directory_show_song_count')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'directory_show_songlist');
}

function directory_profile_description_ready(PDO $pdo): bool {
    return directory_column_exists($pdo, 'setmaxx_public_profiles', 'directory_description');
}

function directory_profile_contact_columns_ready(PDO $pdo): bool {
    return directory_column_exists($pdo, 'setmaxx_public_profiles', 'youtube_url')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'contact_email')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'contact_phone')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'review_url')
        && directory_column_exists($pdo, 'setmaxx_public_profiles', 'booking_url');
}

function directory_artist_slug(string $artistName, int $userId): string {
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $artistName), '-'));
    return ($base !== '' ? $base : 'artist') . '-' . $userId;
}

function directory_user_has_request_page_access(PDO $pdo, int $userId): bool {
    if ($userId <= 0) return false;
    $slugs = rss_tools_product_slugs();
    if (!$slugs) return false;
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));

    if (rss_table_exists($pdo, 'user_subscriptions') && rss_table_exists($pdo, 'subscription_plans')) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM user_subscriptions us
            JOIN subscription_plans sp ON sp.id = us.subscription_plan_id
            WHERE us.user_id = ?
              AND us.status IN ('trialing', 'active')
              AND (us.current_period_end IS NULL OR us.current_period_end > NOW())
              AND sp.slug IN ($placeholders)
              AND sp.is_active = 1
            LIMIT 1
        ");
        $stmt->execute(array_merge([$userId], $slugs));
        if ($stmt->fetchColumn()) return true;
    }

    if (rss_table_exists($pdo, 'entitlements') && rss_table_exists($pdo, 'products')) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM entitlements e
            JOIN products p ON p.id = e.product_id
            WHERE e.user_id = ?
              AND e.status = 'active'
              AND (e.expires_at IS NULL OR e.expires_at > NOW())
              AND p.slug IN ($placeholders)
            LIMIT 1
        ");
        $stmt->execute(array_merge([$userId], $slugs));
        if ($stmt->fetchColumn()) return true;
    }

    return false;
}

function directory_download_filename(string $artistName): string {
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $artistName), '-'));
    return ($base !== '' ? $base : 'artist') . '-songlist.txt';
}

$artists = [];
$states = [];
$directoryReady = directory_public_profiles_ready($pdo);
$visibilityReady = $directoryReady && directory_profile_visibility_columns_ready($pdo);
$descriptionReady = $directoryReady && directory_profile_description_ready($pdo);
$contactReady = $directoryReady && directory_profile_contact_columns_ready($pdo);
$songsReady = rss_table_exists($pdo, 'setmaxx_songs');
$linksReady = rss_table_exists($pdo, 'setmaxx_public_links');

$downloadSonglistUserId = (int)($_GET['songlist'] ?? 0);
if ($downloadSonglistUserId > 0) {
    if (!$directoryReady || !$visibilityReady || !$songsReady) {
        http_response_code(404);
        exit('Songlist not found.');
    }

    $profileStmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, ''), 'Artist') AS artist_name
        FROM setmaxx_public_profiles pp
        JOIN users u ON u.id = pp.user_id
        WHERE pp.user_id = ?
          AND pp.directory_visible = 1
          AND pp.directory_show_songlist = 1
        LIMIT 1
    ");
    $profileStmt->execute([$downloadSonglistUserId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        http_response_code(404);
        exit('Songlist not found.');
    }

    $songsStmt = $pdo->prepare("
        SELECT title, COALESCE(artist, '') AS artist
        FROM setmaxx_songs
        WHERE user_id = ?
          AND is_active = 1
        ORDER BY title ASC, artist ASC
    ");
    $songsStmt->execute([$downloadSonglistUserId]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$songs) {
        http_response_code(404);
        exit('Songlist not found.');
    }

    $artistName = (string)$profile['artist_name'];
    $lines = [$artistName . ' - Active Songlist', 'Generated by Ready Set Shows', ''];
    foreach ($songs as $song) {
        $title = trim((string)$song['title']);
        $artist = trim((string)$song['artist']);
        if ($title === '') continue;
        $lines[] = $artist !== '' ? $title . ' - ' . $artist : $title;
    }
    $body = implode("\r\n", $lines) . "\r\n";

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . directory_download_filename($artistName) . '"');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

if ($directoryReady) {
    $stateStmt = $pdo->query("
        SELECT DISTINCT directory_state
        FROM setmaxx_public_profiles
        WHERE directory_visible = 1
          AND directory_state IS NOT NULL
          AND directory_state <> ''
        ORDER BY directory_state
    ");
    $states = array_values(array_filter(array_map('strval', $stateStmt->fetchAll(PDO::FETCH_COLUMN))));

    $where = "WHERE pp.directory_visible = 1";
    $params = [];
    if ($selectedState !== '') {
        $where .= " AND pp.directory_state = ?";
        $params[] = $selectedState;
    }

    $sql = "
        SELECT
            pp.user_id,
            pp.directory_state,
            pp.artist_name,
            pp.website_url,
            " . ($contactReady ? "pp.youtube_url" : "NULL") . " AS youtube_url,
            " . ($contactReady ? "pp.contact_email" : "NULL") . " AS contact_email,
            " . ($contactReady ? "pp.contact_phone" : "NULL") . " AS contact_phone,
            " . ($contactReady ? "pp.review_url" : "NULL") . " AS review_url,
            " . ($contactReady ? "pp.booking_url" : "NULL") . " AS booking_url,
            pp.logo_path,
            " . ($descriptionReady ? "pp.directory_description" : "NULL") . " AS directory_description,
            " . ($visibilityReady ? "pp.directory_show_song_count" : "1") . " AS directory_show_song_count,
            " . ($visibilityReady ? "pp.directory_show_songlist" : "0") . " AS directory_show_songlist,
            u.display_name,
            " . ($songsReady ? "COUNT(s.id)" : "0") . " AS active_song_count,
            " . ($linksReady ? "spl.public_token" : "NULL") . " AS public_token
        FROM setmaxx_public_profiles pp
        JOIN users u ON u.id = pp.user_id
        " . ($songsReady ? "LEFT JOIN setmaxx_songs s ON s.user_id = pp.user_id AND s.is_active = 1" : "") . "
        " . ($linksReady ? "LEFT JOIN setmaxx_public_links spl ON spl.user_id = pp.user_id" : "") . "
        {$where}
        GROUP BY pp.user_id, pp.directory_state, pp.artist_name, pp.website_url" . ($contactReady ? ", pp.youtube_url, pp.contact_email, pp.contact_phone, pp.review_url, pp.booking_url" : "") . ", pp.logo_path" . ($descriptionReady ? ", pp.directory_description" : "") . ($visibilityReady ? ", pp.directory_show_song_count, pp.directory_show_songlist" : "") . ", u.display_name" . ($linksReady ? ", spl.public_token" : "") . "
        HAVING COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, '')) IS NOT NULL
        ORDER BY
            CASE WHEN pp.directory_state IS NULL OR pp.directory_state = '' THEN 1 ELSE 0 END,
            pp.directory_state ASC,
            COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, '')) ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($artists as &$artist) {
        $name = trim((string)($artist['artist_name'] ?: $artist['display_name']));
        $artist['is_subscriber'] = directory_user_has_request_page_access($pdo, (int)$artist['user_id']) ? 1 : 0;
        $artist['profile_slug'] = directory_artist_slug($name, (int)$artist['user_id']);
    }
    unset($artist);
    usort($artists, static function (array $a, array $b): int {
        $subscriberCompare = (int)($b['is_subscriber'] ?? 0) <=> (int)($a['is_subscriber'] ?? 0);
        if ($subscriberCompare !== 0) return $subscriberCompare;
        $stateCompare = strcmp((string)($a['directory_state'] ?? ''), (string)($b['directory_state'] ?? ''));
        if ($stateCompare !== 0) return $stateCompare;
        return strcasecmp((string)($a['artist_name'] ?: $a['display_name']), (string)($b['artist_name'] ?: $b['display_name']));
    });
}

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$directoryViewerAccountType = '';
if (class_exists('Auth') && Auth::isLoggedIn()) {
    $directoryViewerId = Auth::userId();
    $directoryViewerAccountType = $directoryViewerId ? Auth::accountTypeForUser($pdo, (int)$directoryViewerId) : '';
}
$showQuoteAction = $directoryViewerAccountType !== 'artist';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Artist Directory | Ready Set Shows</title>
  <meta name="description" content="Discover prepared, working artists and bands using Ready Set Shows to manage songs, requests, and show details. Browse public artist profiles by state.">
  <link rel="stylesheet" href="<?= e($siteBase . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .directory-shell { padding: 3rem 0 4rem; }
    .directory-hero { display:grid; gap:1rem; margin-bottom:1.4rem; }
    .directory-kicker { color:#d4af37; font-weight:700; letter-spacing:.08em; text-transform:uppercase; font-size:.82rem; }
    .directory-hero h1 { margin:.15rem 0 0; max-width:820px; }
    .directory-hero p { max-width:760px; color:rgba(255,255,255,.76); }
    .directory-filter { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin:1.5rem 0; }
    .directory-filter a { display:inline-flex; align-items:center; min-height:38px; padding:.5rem .85rem; border-radius:999px; text-decoration:none; border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.86); }
    .directory-filter a.active, .directory-filter a:hover { background:rgba(212,175,55,.16); color:#f4d57a; border-color:rgba(212,175,55,.32); }
    .directory-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:1rem; }
    .directory-card { display:grid; grid-template-columns:76px 1fr; gap:1rem; align-items:center; width:100%; min-height:108px; padding:1rem; border-radius:8px; background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.08); color:#fff; text-align:left; text-decoration:none; font:inherit; cursor:pointer; }
    .directory-card:hover, .directory-card:focus-visible { border-color:rgba(212,175,55,.36); background:rgba(255,255,255,.065); }
    .directory-image { width:76px; height:76px; border-radius:8px; object-fit:cover; background:linear-gradient(135deg, rgba(212,175,55,.32), rgba(140,107,255,.24)); display:grid; place-items:center; color:#fff; font-weight:800; font-size:1.25rem; }
    .directory-card h2 { margin:0 0 .25rem; font-size:1.1rem; line-height:1.2; }
    .directory-meta { color:rgba(255,255,255,.7); font-size:.92rem; margin:.15rem 0 .7rem; }
    .directory-pro-pill { display:inline-flex; width:max-content; max-width:100%; margin-top:.35rem; min-height:24px; padding:.2rem .55rem; border-radius:999px; background:rgba(212,175,55,.16); color:#ffe28a; font-size:.75rem; font-weight:700; }
    .directory-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.85rem; }
    .directory-actions a, .directory-actions button { display:inline-flex; align-items:center; min-height:34px; padding:.45rem .7rem; border-radius:8px; font-size:.9rem; text-decoration:none; border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.9); background:rgba(255,255,255,.03); font:inherit; cursor:pointer; }
    .directory-actions a:hover, .directory-actions button:hover { border-color:rgba(212,175,55,.36); color:#f4d57a; }
    .directory-actions .directory-bid-action { background:linear-gradient(135deg, #f4d57a, #d4af37); color:#111; border-color:rgba(212,175,55,.45); }
    .directory-actions .directory-bid-action:hover { color:#111; border-color:rgba(255,228,138,.72); }
    .directory-empty { padding:1.25rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); color:rgba(255,255,255,.76); }
    .directory-photo-modal { position:fixed; inset:0; z-index:1000; display:grid; place-items:center; padding:1.25rem; background:rgba(2,4,14,.82); backdrop-filter:blur(8px); }
    .directory-photo-modal[hidden] { display:none; }
    .directory-photo-dialog { position:relative; width:min(92vw, 760px); }
    .directory-photo-dialog img { width:100%; max-height:82vh; object-fit:contain; border-radius:8px; background:#050713; box-shadow:0 24px 80px rgba(0,0,0,.45); }
    .directory-photo-info { margin-top:.85rem; padding:1rem; border-radius:8px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); }
    .directory-photo-info h3 { margin:0 0 .25rem; }
    .directory-photo-info p { margin:.25rem 0 0; color:rgba(255,255,255,.76); }
    .directory-photo-links { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.9rem; }
    .directory-photo-links a { display:inline-flex; align-items:center; min-height:34px; padding:.42rem .7rem; border-radius:999px; border:1px solid rgba(255,255,255,.14); color:#fff; text-decoration:none; background:rgba(255,255,255,.04); font-size:.9rem; }
    .directory-photo-links a:hover { color:#f4d57a; border-color:rgba(212,175,55,.36); }
    .directory-photo-close { position:absolute; top:-14px; right:-14px; width:38px; height:38px; border-radius:999px; border:1px solid rgba(255,255,255,.2); background:#10131f; color:#fff; font-size:1.3rem; line-height:1; cursor:pointer; }
    .directory-photo-close:hover, .directory-photo-close:focus-visible { color:#f4d57a; border-color:rgba(212,175,55,.5); }
    @media (max-width: 980px) { .directory-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .directory-shell { padding-top:2rem; } .directory-grid { grid-template-columns:1fr; } .directory-card { grid-template-columns:64px 1fr; } .directory-image { width:64px; height:64px; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/tools_header.php'; ?>
<main class="container directory-shell">
  <section class="directory-hero">
    <div class="directory-kicker">Artist Directory</div>
    <h1>Find artists who take the show seriously.</h1>
    <p>Ready Set Shows artists are already doing the extra work: organizing songs, managing requests, and making the night easier for hosts and audiences. Browse public profiles by state and connect with acts who show up prepared.</p>
  </section>

  <?php if ($directoryReady && $states): ?>
    <nav class="directory-filter" aria-label="Filter artists by state">
      <a href="<?= e($siteBase . '/directory.php') ?>" class="<?= $selectedState === '' ? 'active' : '' ?>">All</a>
      <?php foreach ($states as $state): ?>
        <a href="<?= e($siteBase . '/directory.php?state=' . rawurlencode($state)) ?>" class="<?= $selectedState === $state ? 'active' : '' ?>"><?= e($state) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if (!$directoryReady): ?>
    <div class="directory-empty">The artist directory needs the latest database update before listings can appear.</div>
  <?php elseif (!$artists): ?>
    <div class="directory-empty">No artists are listed<?= $selectedState !== '' ? ' in ' . e($selectedState) : '' ?> yet.</div>
  <?php else: ?>
    <section class="directory-grid" aria-label="Artists">
      <?php foreach ($artists as $artist): ?>
        <?php
          $name = trim((string)($artist['artist_name'] ?: $artist['display_name']));
          $initial = strtoupper(substr($name, 0, 1));
          $logoPath = trim((string)($artist['logo_path'] ?? ''));
          $logoUrl = $logoPath !== '' ? $siteBase . '/' . ltrim(preg_replace('#^\.\./#', '', $logoPath), '/') : '';
          $isSubscriber = !empty($artist['is_subscriber']);
          $requestUrl = (!empty($artist['public_token']) && $isSubscriber) ? $siteBase . '/studio/request.php?link=' . rawurlencode((string)$artist['public_token']) : '';
          $songlistUrl = (!empty($artist['directory_show_songlist']) && (int)$artist['active_song_count'] > 0) ? $siteBase . '/directory.php?songlist=' . (int)$artist['user_id'] : '';
          $profileUrl = $siteBase . '/artist.php?artist=' . rawurlencode((string)$artist['profile_slug']);
          $stateText = (string)($artist['directory_state'] ?: 'State not set');
          $cardAttrs = 'data-name="' . e($name) . '" data-state="' . e($stateText) . '" data-photo="' . e($logoUrl) . '" data-description="' . e(trim((string)($artist['directory_description'] ?? ''))) . '" data-website="' . e((string)($artist['website_url'] ?? '')) . '" data-youtube="' . e((string)($artist['youtube_url'] ?? '')) . '" data-email="' . e((string)($artist['contact_email'] ?? '')) . '" data-phone="' . e((string)($artist['contact_phone'] ?? '')) . '" data-review="' . e((string)($artist['review_url'] ?? '')) . '" data-booking="' . e((string)($artist['booking_url'] ?? '')) . '" data-request="' . e($requestUrl) . '" data-songlist="' . e($songlistUrl) . '"';
        ?>
        <<?= $isSubscriber ? 'a' : 'button' ?> class="directory-card" <?= $isSubscriber ? 'href="' . e($profileUrl) . '"' : 'type="button" ' . $cardAttrs . ' onclick="return window.openDirectoryPhoto ? window.openDirectoryPhoto(this) : false;"' ?>>
          <?php if ($logoUrl !== ''): ?>
            <img class="directory-image" src="<?= e($logoUrl) ?>" alt="">
          <?php else: ?>
            <div class="directory-image" aria-hidden="true"><?= e($initial) ?></div>
          <?php endif; ?>
          <div>
            <h2><?= e($name) ?></h2>
            <div class="directory-meta"><?= e($stateText) ?></div>
            <?php if ($isSubscriber): ?><span class="directory-pro-pill">Featured profile</span><?php endif; ?>
          </div>
        </<?= $isSubscriber ? 'a' : 'button' ?>>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
<div class="directory-photo-modal" id="directoryPhotoModal" hidden>
  <div class="directory-photo-dialog" role="dialog" aria-modal="true" aria-label="Artist image preview">
    <button type="button" class="directory-photo-close" aria-label="Close image preview">&times;</button>
    <img src="" alt="">
    <div class="directory-photo-info">
      <h3></h3>
      <p data-directory-photo-state></p>
      <p data-directory-photo-description></p>
      <div class="directory-photo-links" data-directory-photo-links></div>
    </div>
  </div>
</div>
<script>
window.openDirectoryPhoto = function (trigger) {
  const modal = document.getElementById('directoryPhotoModal');
  if (!modal || !trigger) return false;
  const image = modal.querySelector('img');
  const title = modal.querySelector('h3');
  const state = modal.querySelector('[data-directory-photo-state]');
  const description = modal.querySelector('[data-directory-photo-description]');
  const links = modal.querySelector('[data-directory-photo-links]');
  const closeButton = modal.querySelector('.directory-photo-close');
  if (!image || !closeButton) return false;
  window.directoryPhotoLastTrigger = trigger;
  image.src = trigger.getAttribute('data-photo') || '';
  image.alt = trigger.getAttribute('data-name') || 'Artist image';
  image.style.display = image.src ? '' : 'none';
  if (title) title.textContent = trigger.getAttribute('data-name') || '';
  if (state) state.textContent = trigger.getAttribute('data-state') || '';
  if (description) description.textContent = trigger.getAttribute('data-description') || 'No description yet.';
  if (links) {
    links.innerHTML = '';
    [
      ['Website', trigger.getAttribute('data-website')],
      ['YouTube', trigger.getAttribute('data-youtube')],
      ['Email', trigger.getAttribute('data-email') ? 'mailto:' + trigger.getAttribute('data-email') : ''],
      ['Phone', trigger.getAttribute('data-phone') ? 'tel:' + trigger.getAttribute('data-phone').replace(/[^0-9+]/g, '') : ''],
      ['Book', trigger.getAttribute('data-booking')],
      ['Review', trigger.getAttribute('data-review')],
      ['Request Page', trigger.getAttribute('data-request')],
      ['Songlist', trigger.getAttribute('data-songlist')]
    ].forEach(function (item) {
      if (!item[1]) return;
      var link = document.createElement('a');
      link.href = item[1];
      link.textContent = item[0];
      link.className = item[0] === 'Email' || item[0] === 'Phone' ? '' : 'directory-external-link';
      if (!/^mailto:|^tel:/i.test(item[1])) {
        link.target = '_blank';
        link.rel = 'noopener';
      }
      links.appendChild(link);
    });
  }
  modal.hidden = false;
  closeButton.focus();
  return false;
};

window.closeDirectoryPhoto = function () {
  const modal = document.getElementById('directoryPhotoModal');
  if (!modal) return;
  const image = modal.querySelector('img');
  modal.hidden = true;
  if (image) {
    image.src = '';
    image.alt = '';
    image.style.display = '';
  }
  if (window.directoryPhotoLastTrigger) window.directoryPhotoLastTrigger.focus();
};

document.addEventListener('click', function (event) {
  const directoryPopupCard = event.target.closest ? event.target.closest('button.directory-card') : null;
  if (directoryPopupCard) {
    event.preventDefault();
    window.openDirectoryPhoto(directoryPopupCard);
    return;
  }
  if (event.target.closest && event.target.closest('.directory-photo-close')) {
    window.closeDirectoryPhoto();
    return;
  }
  const modal = document.getElementById('directoryPhotoModal');
  if (modal && event.target === modal) window.closeDirectoryPhoto();
});

document.addEventListener('keydown', function (event) {
  const modal = document.getElementById('directoryPhotoModal');
  if (modal && !modal.hidden && event.key === 'Escape') window.closeDirectoryPhoto();
});
</script>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>
