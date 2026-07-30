<?php
require __DIR__ . '/studio/_private/_core/bootstrap.php';
require_once __DIR__ . '/studio/_private/_core/tool_access.php';

function artist_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function artist_slug(string $artistName, int $userId): string {
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $artistName), '-'));
    return ($base !== '' ? $base : 'artist') . '-' . $userId;
}

function artist_user_has_profile_page_access(PDO $pdo, int $userId): bool {
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

function artist_ics_unfold(string $raw): array {
    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $out = [];
    foreach ($lines as $line) {
        if (isset($out[count($out) - 1]) && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))) {
            $out[count($out) - 1] .= ltrim($line);
        } else {
            $out[] = $line;
        }
    }
    return $out;
}

function artist_calendar_datetime(string $raw, DateTimeZone $tz): DateTime {
    $date = new DateTime($raw, $tz);
    $date->setTimezone($tz);
    return $date;
}

function artist_ics_text(string $value): string {
    return str_replace(['\\,', '\\;', '\\n', '\\N', '\\\\'], [',', ';', "\n", "\n", '\\'], $value);
}

function artist_push_event(array &$events, array $event, DateTime $start, DateTime $end): void {
    $events[] = [
        'start' => $start->format(DateTime::ATOM),
        'end' => $end->format(DateTime::ATOM),
        'summary' => artist_ics_text((string)($event['SUMMARY'] ?? 'Show')),
        'location' => artist_ics_text((string)($event['LOCATION'] ?? '')),
    ];
}

function artist_fetch_calendar_events(array $calendar, string $startDate, string $endDate): array {
    $tzName = (string)($calendar['timezone'] ?: 'America/Chicago');
    try {
        $tz = new DateTimeZone($tzName);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('America/Chicago');
    }
    $rangeStart = new DateTime($startDate, $tz);
    $rangeEnd = (new DateTime($endDate, $tz))->setTime(23, 59, 59);
    $icalUrl = rss_normalize_ical_url((string)$calendar['ics_url']);
    if (!filter_var($icalUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($icalUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) return [];

    $ch = curl_init($icalUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return [];

    $events = [];
    $event = null;
    foreach (artist_ics_unfold((string)$raw) as $line) {
        $trim = trim($line);
        if ($trim === 'BEGIN:VEVENT') {
            $event = [];
            continue;
        }
        if ($trim === 'END:VEVENT') {
            if ($event && isset($event['DTSTART'])) {
                $dtStartRaw = preg_replace('/^.*:/', '', (string)$event['DTSTART']);
                $dtEndRaw = isset($event['DTEND']) ? preg_replace('/^.*:/', '', (string)$event['DTEND']) : $dtStartRaw;
                try {
                    $start = artist_calendar_datetime($dtStartRaw, $tz);
                    $end = artist_calendar_datetime($dtEndRaw, $tz);
                    if (preg_match('/^\d{8}$/', $dtStartRaw)) $end->setTime(23, 59, 59);
                    if ($end >= $rangeStart && $start <= $rangeEnd) artist_push_event($events, $event, $start, $end);
                } catch (Throwable $e) {
                }
            }
            $event = null;
            continue;
        }
        if ($event !== null && str_contains($trim, ':')) {
            [$key, $value] = explode(':', $trim, 2);
            $event[strtoupper(explode(';', $key)[0])] = $value;
        }
    }
    usort($events, fn($a, $b) => strcmp((string)$a['start'], (string)$b['start']));
    return array_slice($events, 0, 10);
}

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$requestedSlug = trim((string)($_GET['artist'] ?? ''));
$profile = null;
$events = [];

if (rss_table_exists($pdo, 'setmaxx_public_profiles')) {
    $contactReady = artist_column_exists($pdo, 'setmaxx_public_profiles', 'youtube_url')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'contact_email')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'contact_phone')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'review_url')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'booking_url')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'venmo_handle')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'minimum_tip_dollars')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'suggested_request_dollars')
        && artist_column_exists($pdo, 'setmaxx_public_profiles', 'price_step_dollars');
    $descriptionReady = artist_column_exists($pdo, 'setmaxx_public_profiles', 'directory_description');
    $genresReady = artist_column_exists($pdo, 'setmaxx_public_profiles', 'directory_genres');
    $visibilityReady = artist_column_exists($pdo, 'setmaxx_public_profiles', 'directory_show_song_count');
    $linksReady = rss_table_exists($pdo, 'setmaxx_public_links');
    $songsReady = rss_table_exists($pdo, 'setmaxx_songs');

    $stmt = $pdo->query("
        SELECT
          pp.user_id,
          pp.directory_state,
          pp.artist_name,
          pp.website_url,
          " . ($contactReady ? "pp.youtube_url, pp.contact_email, pp.contact_phone, pp.review_url, pp.booking_url, pp.venmo_handle, pp.minimum_tip_dollars, pp.suggested_request_dollars, pp.price_step_dollars" : "NULL AS youtube_url, NULL AS contact_email, NULL AS contact_phone, NULL AS review_url, NULL AS booking_url, NULL AS venmo_handle, NULL AS minimum_tip_dollars, NULL AS suggested_request_dollars, NULL AS price_step_dollars") . ",
          " . ($descriptionReady ? "pp.directory_description" : "NULL") . " AS directory_description,
          " . ($genresReady ? "pp.directory_genres" : "NULL") . " AS directory_genres,
          " . ($visibilityReady ? "pp.directory_show_song_count, pp.directory_show_songlist" : "1 AS directory_show_song_count, 0 AS directory_show_songlist") . ",
          pp.logo_path,
          u.display_name,
          " . ($linksReady ? "spl.public_token" : "NULL") . " AS public_token,
          " . ($songsReady ? "(SELECT COUNT(*) FROM setmaxx_songs s WHERE s.user_id = pp.user_id AND s.is_active = 1)" : "0") . " AS active_song_count
        FROM setmaxx_public_profiles pp
        JOIN users u ON u.id = pp.user_id
        " . ($linksReady ? "LEFT JOIN setmaxx_public_links spl ON spl.user_id = pp.user_id" : "") . "
        WHERE pp.directory_visible = 1
        ORDER BY pp.artist_name ASC, u.display_name ASC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string)($row['artist_name'] ?: $row['display_name']));
        if (artist_slug($name, (int)$row['user_id']) === $requestedSlug && artist_user_has_profile_page_access($pdo, (int)$row['user_id'])) {
            $profile = $row;
            $profile['display_artist_name'] = $name;
            break;
        }
    }
}

if ($profile && rss_table_exists($pdo, 'calendars') && artist_column_exists($pdo, 'calendars', 'is_main_gig')) {
    $calStmt = $pdo->prepare("SELECT id, name, ics_url, timezone FROM calendars WHERE user_id = ? AND is_active = 1 AND is_main_gig = 1 LIMIT 1");
    $calStmt->execute([(int)$profile['user_id']]);
    $calendar = $calStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($calendar) {
        $events = artist_fetch_calendar_events($calendar, date('Y-m-d'), (new DateTimeImmutable('+18 months'))->format('Y-m-d'));
    }
}

if (!$profile) {
    http_response_code(404);
}

$artistName = $profile ? (string)$profile['display_artist_name'] : 'Artist not found';
$logoPath = $profile ? trim((string)($profile['logo_path'] ?? '')) : '';
$logoUrl = $logoPath !== '' ? $siteBase . '/' . ltrim(preg_replace('#^\.\./#', '', $logoPath), '/') : '';
$requestUrl = $profile && !empty($profile['public_token']) ? $siteBase . '/studio/request.php?link=' . rawurlencode((string)$profile['public_token']) : '';
$songlistUrl = $profile && !empty($profile['directory_show_songlist']) && (int)$profile['active_song_count'] > 0 ? $siteBase . '/directory.php?songlist=' . (int)$profile['user_id'] : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($artistName) ?> | Ready Set Shows Directory</title>
  <meta name="description" content="<?= e($artistName) ?> public artist profile on Ready Set Shows.">
  <link rel="stylesheet" href="<?= e($siteBase . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .artist-shell { padding:3rem 0 4rem; }
    .artist-hero { display:grid; grid-template-columns:170px minmax(0,1fr); gap:1.4rem; align-items:center; margin-bottom:1rem; }
    .artist-photo { width:170px; height:170px; border-radius:8px; object-fit:cover; background:linear-gradient(135deg, rgba(212,175,55,.32), rgba(140,107,255,.24)); display:grid; place-items:center; font-size:3rem; font-weight:800; color:#fff; }
    .artist-kicker { color:#d4af37; font-size:.82rem; text-transform:uppercase; letter-spacing:.08em; font-weight:800; }
    .artist-hero h1 { margin:.25rem 0 .35rem; }
    .artist-meta { color:rgba(255,255,255,.72); }
    .artist-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1rem; }
    .artist-actions a { display:inline-flex; align-items:center; min-height:36px; padding:.48rem .8rem; border-radius:999px; border:1px solid rgba(255,255,255,.14); color:#fff; text-decoration:none; background:rgba(255,255,255,.04); }
    .artist-actions a:hover { color:#f4d57a; border-color:rgba(212,175,55,.36); }
    .artist-layout { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:1rem; align-items:start; }
    .artist-card { padding:1.2rem; border-radius:8px; background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.08); }
    .artist-card h2 { margin-top:0; }
    .artist-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; }
    .artist-detail { padding:.85rem; border-radius:8px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); }
    .artist-detail span { display:block; color:#d4af37; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .artist-detail strong, .artist-detail a { color:#fff; word-break:break-word; }
    .artist-events { display:grid; gap:.6rem; }
    .artist-event { padding:.85rem; border-radius:8px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); }
    .artist-event strong { display:block; }
    .artist-event span { color:rgba(255,255,255,.68); }
    @media (max-width: 860px) { .artist-hero, .artist-layout { grid-template-columns:1fr; } .artist-detail-grid { grid-template-columns:1fr; } .artist-photo { width:130px; height:130px; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/tools_header.php'; ?>
<main class="container artist-shell">
  <?php if (!$profile): ?>
    <section class="artist-card">
      <h1 style="margin-top:0;">Artist not found</h1>
      <p class="artist-meta">This profile is unavailable or no longer listed.</p>
      <a class="btn btn-primary" href="<?= e($siteBase . '/directory.php') ?>">Back to directory</a>
    </section>
  <?php else: ?>
    <section class="artist-hero">
      <?php if ($logoUrl !== ''): ?><img class="artist-photo" src="<?= e($logoUrl) ?>" alt=""><?php else: ?><div class="artist-photo" aria-hidden="true"><?= e(strtoupper(substr($artistName, 0, 1))) ?></div><?php endif; ?>
      <div>
        <div class="artist-kicker">Featured Artist</div>
        <h1><?= e($artistName) ?></h1>
        <div class="artist-meta"><?= e((string)($profile['directory_state'] ?: 'State not set')) ?><?php if (!empty($profile['directory_genres'])): ?> &middot; <?= e(str_replace(',', ', ', (string)$profile['directory_genres'])) ?><?php endif; ?></div>
        <?php if (!empty($profile['directory_description'])): ?><p><?= e((string)$profile['directory_description']) ?></p><?php endif; ?>
        <div class="artist-actions">
          <?php if (!empty($profile['website_url'])): ?><a href="<?= e((string)$profile['website_url']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
          <?php if (!empty($profile['youtube_url'])): ?><a href="<?= e((string)$profile['youtube_url']) ?>" target="_blank" rel="noopener">YouTube</a><?php endif; ?>
          <?php if (!empty($profile['booking_url'])): ?><a href="<?= e((string)$profile['booking_url']) ?>" target="_blank" rel="noopener">Book</a><?php endif; ?>
          <?php if ($requestUrl !== ''): ?><a href="<?= e($requestUrl) ?>">Request Page</a><?php endif; ?>
          <?php if ($songlistUrl !== ''): ?><a href="<?= e($songlistUrl) ?>">Songlist</a><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="artist-layout">
      <div class="artist-card">
        <h2>Public Details</h2>
        <div class="artist-detail-grid">
          <?php if (!empty($profile['contact_email'])): ?><div class="artist-detail"><span>Email</span><a href="mailto:<?= e((string)$profile['contact_email']) ?>"><?= e((string)$profile['contact_email']) ?></a></div><?php endif; ?>
          <?php if (!empty($profile['contact_phone'])): ?><div class="artist-detail"><span>Phone</span><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$profile['contact_phone']) ?? '') ?>"><?= e((string)$profile['contact_phone']) ?></a></div><?php endif; ?>
          <?php if (!empty($profile['review_url'])): ?><div class="artist-detail"><span>Reviews</span><a href="<?= e((string)$profile['review_url']) ?>" target="_blank" rel="noopener">Leave a review</a></div><?php endif; ?>
          <?php if (!empty($profile['venmo_handle'])): ?><div class="artist-detail"><span>Venmo</span><strong>@<?= e(ltrim((string)$profile['venmo_handle'], '@')) ?></strong></div><?php endif; ?>
          <div class="artist-detail"><span>Active songs</span><strong><?= !empty($profile['directory_show_song_count']) ? (int)$profile['active_song_count'] : 'Available by request' ?></strong></div>
          <div class="artist-detail"><span>Request pricing</span><strong>$<?= (int)($profile['suggested_request_dollars'] ?? 10) ?> suggested</strong></div>
          <div class="artist-detail"><span>Lowest paid amount</span><strong>$<?= (int)($profile['minimum_tip_dollars'] ?? 10) ?></strong></div>
          <div class="artist-detail"><span>Price increments</span><strong>$<?= (int)($profile['price_step_dollars'] ?? 1) ?></strong></div>
        </div>
      </div>
      <aside class="artist-card">
        <h2>Upcoming Dates</h2>
        <?php if (!$events): ?>
          <p class="artist-meta">No public dates are listed right now.</p>
        <?php else: ?>
          <div class="artist-events">
            <?php foreach ($events as $event): $start = new DateTime((string)$event['start']); ?>
              <div class="artist-event">
                <strong><?= e((string)$event['summary']) ?></strong>
                <span><?= e($start->format('M j, Y')) ?><?php if (!empty($event['location'])): ?> &middot; <?= e((string)$event['location']) ?><?php endif; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </aside>
    </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>
