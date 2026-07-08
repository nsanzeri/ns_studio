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

$artists = [];
$states = [];
$directoryReady = directory_public_profiles_ready($pdo);
$visibilityReady = $directoryReady && directory_profile_visibility_columns_ready($pdo);
$songsReady = rss_table_exists($pdo, 'setmaxx_songs');
$linksReady = rss_table_exists($pdo, 'setmaxx_public_links');

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
            pp.logo_path,
            " . ($visibilityReady ? "pp.directory_show_song_count" : "1") . " AS directory_show_song_count,
            " . ($visibilityReady ? "pp.directory_show_songlist" : "0") . " AS directory_show_songlist,
            u.display_name,
            " . ($songsReady ? "COUNT(s.id)" : "0") . " AS active_song_count,
            " . ($songsReady && $visibilityReady ? "GROUP_CONCAT(CASE WHEN pp.directory_show_songlist = 1 THEN CONCAT(REPLACE(s.title, '||', ' '), '~~', REPLACE(COALESCE(s.artist, ''), '||', ' ')) ELSE NULL END ORDER BY s.title ASC SEPARATOR '||')" : "NULL") . " AS active_songlist,
            " . ($linksReady ? "spl.public_token" : "NULL") . " AS public_token
        FROM setmaxx_public_profiles pp
        JOIN users u ON u.id = pp.user_id
        " . ($songsReady ? "LEFT JOIN setmaxx_songs s ON s.user_id = pp.user_id AND s.is_active = 1" : "") . "
        " . ($linksReady ? "LEFT JOIN setmaxx_public_links spl ON spl.user_id = pp.user_id" : "") . "
        {$where}
        GROUP BY pp.user_id, pp.directory_state, pp.artist_name, pp.website_url, pp.logo_path" . ($visibilityReady ? ", pp.directory_show_song_count, pp.directory_show_songlist" : "") . ", u.display_name" . ($linksReady ? ", spl.public_token" : "") . "
        HAVING COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, '')) IS NOT NULL
        ORDER BY
            CASE WHEN pp.directory_state IS NULL OR pp.directory_state = '' THEN 1 ELSE 0 END,
            pp.directory_state ASC,
            COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, '')) ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
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
    .directory-card { display:grid; grid-template-columns:76px 1fr; gap:1rem; align-items:start; padding:1rem; border-radius:8px; background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.08); }
    .directory-image { width:76px; height:76px; border-radius:8px; object-fit:cover; background:linear-gradient(135deg, rgba(212,175,55,.32), rgba(140,107,255,.24)); display:grid; place-items:center; color:#fff; font-weight:800; font-size:1.25rem; }
    .directory-card h2 { margin:0 0 .25rem; font-size:1.1rem; line-height:1.2; }
    .directory-meta { color:rgba(255,255,255,.7); font-size:.92rem; margin:.15rem 0 .7rem; }
    .directory-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
    .directory-actions a, .directory-actions button { display:inline-flex; align-items:center; min-height:34px; padding:.45rem .7rem; border-radius:8px; font-size:.9rem; text-decoration:none; border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.9); background:rgba(255,255,255,.03); font:inherit; cursor:pointer; }
    .directory-actions a:hover, .directory-actions button:hover { border-color:rgba(212,175,55,.36); color:#f4d57a; }
    .directory-songlist { margin:.75rem 0 0; padding:.75rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); background:rgba(0,0,0,.16); }
    .directory-songlist[hidden] { display:none; }
    .directory-songlist-title { font-weight:700; color:#fff; font-size:.9rem; }
    .directory-songlist-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:.5rem .75rem; margin-top:.6rem; }
    .directory-song { min-width:0; padding:.45rem .5rem; border-radius:7px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.06); }
    .directory-song-name { color:#fff; font-size:.88rem; font-weight:700; line-height:1.25; overflow-wrap:anywhere; }
    .directory-song-artist { margin-top:.12rem; color:rgba(255,255,255,.62); font-size:.78rem; line-height:1.25; overflow-wrap:anywhere; }
    .directory-empty { padding:1.25rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); color:rgba(255,255,255,.76); }
    @media (max-width: 980px) { .directory-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 980px) { .directory-songlist-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .directory-shell { padding-top:2rem; } .directory-grid { grid-template-columns:1fr; } .directory-card { grid-template-columns:64px 1fr; } .directory-image { width:64px; height:64px; } .directory-songlist-grid { grid-template-columns:1fr; } }
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
          $requestUrl = (!empty($artist['public_token']) && directory_user_has_request_page_access($pdo, (int)$artist['user_id'])) ? $siteBase . '/studio/request.php?link=' . rawurlencode((string)$artist['public_token']) : '';
          $songlist = array_values(array_filter(array_map(static function ($songLabel) {
              $parts = explode('~~', (string)$songLabel, 2);
              $title = trim((string)($parts[0] ?? ''));
              if ($title === '') return null;
              return [
                  'title' => $title,
                  'artist' => trim((string)($parts[1] ?? '')),
              ];
          }, explode('||', (string)($artist['active_songlist'] ?? '')))));
          $visibleSonglist = array_slice($songlist, 0, 40);
          $songlistId = 'songlist-' . (int)$artist['user_id'];
        ?>
        <article class="directory-card">
          <?php if ($logoPath !== ''): ?>
            <img class="directory-image" src="<?= e($siteBase . '/' . ltrim(preg_replace('#^\.\./#', '', $logoPath), '/')) ?>" alt="">
          <?php else: ?>
            <div class="directory-image" aria-hidden="true"><?= e($initial) ?></div>
          <?php endif; ?>
          <div>
            <h2><?= e($name) ?></h2>
            <div class="directory-meta">
              <?= e((string)($artist['directory_state'] ?: 'State not set')) ?>
              <?php if (!empty($artist['directory_show_song_count']) && (int)$artist['active_song_count'] > 0): ?>
                &middot; <?= (int)$artist['active_song_count'] ?> active song<?= (int)$artist['active_song_count'] === 1 ? '' : 's' ?>
              <?php endif; ?>
            </div>
            <div class="directory-actions">
              <?php if (!empty($artist['website_url'])): ?>
                <a href="<?= e((string)$artist['website_url']) ?>" target="_blank" rel="noopener">Website</a>
              <?php endif; ?>
              <?php if ($requestUrl !== ''): ?>
                <a href="<?= e($requestUrl) ?>">Request Page</a>
              <?php endif; ?>
              <?php if ($songlist): ?>
                <button type="button" class="directory-songlist-toggle" data-target="<?= e($songlistId) ?>" aria-expanded="false" aria-controls="<?= e($songlistId) ?>">Songlist</button>
              <?php endif; ?>
            </div>
            <?php if ($songlist): ?>
              <div class="directory-songlist" id="<?= e($songlistId) ?>" hidden>
                <div class="directory-songlist-title">Active songs</div>
                <div class="directory-songlist-grid">
                  <?php foreach ($visibleSonglist as $song): ?>
                    <div class="directory-song">
                      <div class="directory-song-name"><?= e($song['title']) ?></div>
                      <?php if ($song['artist'] !== ''): ?>
                        <div class="directory-song-artist"><?= e($song['artist']) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php if (count($songlist) > count($visibleSonglist)): ?>
                  <div class="directory-meta" style="margin-top:.55rem;">Showing <?= count($visibleSonglist) ?> of <?= count($songlist) ?> active songs.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.directory-songlist-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const target = document.getElementById(button.getAttribute('data-target') || '');
      if (!target) return;
      const willOpen = target.hidden;
      target.hidden = !willOpen;
      button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
  });
});
</script>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>
