<?php
require_once __DIR__ . '/_common.php';

$summaryStats = [
    'total_requests' => 0,
    'unique_songs' => 0,
    'tracked_cents' => 0,
];
$topWindows = [
    'this_week' => ['label' => 'This week', 'help' => 'Monday through Sunday', 'rows' => []],
    'last_30' => ['label' => 'Last 30 days', 'help' => 'Recent audience momentum', 'rows' => []],
    'all_time' => ['label' => 'All time', 'help' => 'Your full request history', 'rows' => []],
];
$rankedSongs = [];

function setmaxx_most_requested_paid_condition(bool $hasPaymentMethod, bool $hasPaymentIntent): string {
    if ($hasPaymentMethod && $hasPaymentIntent) {
        return "r.amount_cents > 0 AND (
                    (r.payment_method = 'stripe' AND r.stripe_payment_intent_id IS NOT NULL AND r.stripe_payment_intent_id <> '')
                    OR r.payment_method = 'venmo'
                )";
    }

    return "r.amount_cents > 0";
}

function setmaxx_most_requested_rows(PDO $pdo, int $userId, string $dateCondition, string $paidCondition, int $limit): array {
    $limit = max(1, min(100, $limit));
    $sql = "
        SELECT
            s.id,
            s.title,
            s.artist,
            COUNT(*) AS request_count,
            SUM(CASE WHEN {$paidCondition} THEN 1 ELSE 0 END) AS paid_request_count,
            COALESCE(SUM(CASE WHEN {$paidCondition} THEN r.amount_cents ELSE 0 END), 0) AS tracked_cents,
            MAX(r.created_at) AS last_requested_at
        FROM setmaxx_requests r
        JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
        JOIN setmaxx_songs s ON s.id = r.song_id
        WHERE gs.user_id = ?
          AND r.status <> 'canceled'
          {$dateCondition}
        GROUP BY s.id, s.title, s.artist
        ORDER BY request_count DESC, tracked_cents DESC, last_requested_at DESC
        LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tablesReady && $isProUser) {
    try {
        $hasPaymentMethod = setmaxx_column_exists($pdo, 'setmaxx_requests', 'payment_method');
        $hasPaymentIntent = setmaxx_column_exists($pdo, 'setmaxx_requests', 'stripe_payment_intent_id');
        $paidCondition = setmaxx_most_requested_paid_condition($hasPaymentMethod, $hasPaymentIntent);

        $summaryStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_requests,
                COUNT(DISTINCT r.song_id) AS unique_songs,
                COALESCE(SUM(CASE WHEN {$paidCondition} THEN r.amount_cents ELSE 0 END), 0) AS tracked_cents
             FROM setmaxx_requests r
             JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
             WHERE gs.user_id = ?
               AND r.status <> 'canceled'"
        );
        $summaryStmt->execute([$userId]);
        $summaryStats = array_merge($summaryStats, $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: []);

        $weekCondition = "AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
          AND r.created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)";
        $last30Condition = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        $topWindows['this_week']['rows'] = setmaxx_most_requested_rows($pdo, $userId, $weekCondition, $paidCondition, 5);
        $topWindows['last_30']['rows'] = setmaxx_most_requested_rows($pdo, $userId, $last30Condition, $paidCondition, 5);
        $topWindows['all_time']['rows'] = setmaxx_most_requested_rows($pdo, $userId, '', $paidCondition, 5);
        $rankedSongs = setmaxx_most_requested_rows($pdo, $userId, '', $paidCondition, 50);
    } catch (Throwable $e) {
        $errors[] = 'Most requested songs could not be loaded right now.';
    }
}

setmaxx_page_head('Set Maxx | Most Requested');
?>
<style>
  .setmaxx-analytics-stats { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:1rem; margin-bottom:1.25rem; }
  .setmaxx-stat-row { display:grid; gap:.25rem; }
  .setmaxx-stat-value { color:#fff; font-size:1.7rem; font-weight:700; line-height:1.1; }
  .setmaxx-chart-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:1rem; margin-bottom:1.25rem; }
  .setmaxx-chart-card h2 { margin:.1rem 0 .25rem; font-size:1.15rem; }
  .setmaxx-mini-rank-list { display:grid; gap:.55rem; margin-top:1rem; }
  .setmaxx-mini-rank-row { display:grid; grid-template-columns:2rem minmax(0, 1fr) auto; gap:.6rem; align-items:center; padding:.65rem .75rem; border-radius:14px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.06); }
  .setmaxx-rank-number { color:rgba(255,255,255,.6); font-weight:700; text-align:center; }
  .setmaxx-song-title { font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .setmaxx-song-artist { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .setmaxx-rank-money { color:#efe7ff; font-weight:700; white-space:nowrap; }
  .setmaxx-rank-table-wrap { overflow:auto; border:1px solid rgba(255,255,255,.08); border-radius:16px; }
  .setmaxx-rank-table { width:100%; min-width:760px; border-collapse:collapse; }
  .setmaxx-rank-table th,
  .setmaxx-rank-table td { padding:.75rem .8rem; border-bottom:1px solid rgba(255,255,255,.07); text-align:left; vertical-align:middle; }
  .setmaxx-rank-table th { color:rgba(255,255,255,.74); font-size:.8rem; font-weight:600; background:rgba(255,255,255,.045); }
  .setmaxx-rank-table tr:last-child td { border-bottom:0; }
  .setmaxx-number-cell { text-align:right; white-space:nowrap; }
  @media (max-width: 980px) {
    .setmaxx-analytics-stats,
    .setmaxx-chart-grid { grid-template-columns:1fr; }
  }
</style>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>

  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Pro analytics</div>
    <h1 style="margin:.8rem 0 .35rem;">Most requested songs</h1>
    <p class="setmaxx-help" style="margin:0;">See what your audiences keep asking for, which songs drive paid requests, and what belongs near the front of your next setlist.</p>
    <div class="setmaxx-actions" style="margin-top:1rem;">
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/requests.php')) ?>">Request dashboard</a>
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/history.php')) ?>">History</a>
    </div>
  </div>

  <?php if (!$tablesReady): ?>
    <?php setmaxx_install_notice(); ?>
  <?php elseif (!$isProUser): ?>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Upgrade for request analytics</h2>
      <p class="setmaxx-help">Most requested song charts are a Pro feature because they use live session request history, paid request tracking, and audience behavior over time.</p>
      <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade to Pro</a>
    </div>
  <?php else: ?>
    <section class="setmaxx-analytics-stats">
      <div class="setmaxx-card setmaxx-stat-row">
        <span class="setmaxx-meta">Total requests</span>
        <strong class="setmaxx-stat-value"><?= (int)($summaryStats['total_requests'] ?? 0) ?></strong>
      </div>
      <div class="setmaxx-card setmaxx-stat-row">
        <span class="setmaxx-meta">Requested songs</span>
        <strong class="setmaxx-stat-value"><?= (int)($summaryStats['unique_songs'] ?? 0) ?></strong>
      </div>
      <div class="setmaxx-card setmaxx-stat-row">
        <span class="setmaxx-meta">Tracked request dollars</span>
        <strong class="setmaxx-stat-value"><?= e(setmaxx_money((int)($summaryStats['tracked_cents'] ?? 0))) ?></strong>
      </div>
    </section>

    <section class="setmaxx-chart-grid" aria-label="Most requested song snapshots">
      <?php foreach ($topWindows as $window): ?>
        <div class="setmaxx-card setmaxx-chart-card">
          <h2><?= e($window['label']) ?></h2>
          <div class="setmaxx-meta"><?= e($window['help']) ?></div>
          <div class="setmaxx-mini-rank-list">
            <?php if (empty($window['rows'])): ?>
              <div class="setmaxx-row"><div class="setmaxx-meta">No requests in this window yet.</div></div>
            <?php else: foreach ($window['rows'] as $index => $song): ?>
              <div class="setmaxx-mini-rank-row">
                <div class="setmaxx-rank-number"><?= $index + 1 ?></div>
                <div style="min-width:0;">
                  <div class="setmaxx-song-title"><?= e((string)$song['title']) ?></div>
                  <div class="setmaxx-meta setmaxx-song-artist"><?= e((string)($song['artist'] ?: 'Artist not set')) ?> &middot; <?= (int)$song['request_count'] ?> request<?= (int)$song['request_count'] === 1 ? '' : 's' ?></div>
                </div>
                <div class="setmaxx-rank-money"><?= e(setmaxx_money((int)$song['tracked_cents'])) ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="setmaxx-card">
      <h2 style="margin-top:0;">All-time ranking</h2>
      <div class="setmaxx-rank-table-wrap">
        <table class="setmaxx-rank-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Song</th>
              <th>Artist</th>
              <th class="setmaxx-number-cell">Requests</th>
              <th class="setmaxx-number-cell">Paid</th>
              <th class="setmaxx-number-cell">Tracked</th>
              <th>Last requested</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rankedSongs): ?>
              <tr><td colspan="7" class="setmaxx-meta">No requests yet. Once fans start using your public request pages, your top songs will show here.</td></tr>
            <?php else: foreach ($rankedSongs as $index => $song): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><strong><?= e((string)$song['title']) ?></strong></td>
                <td><?= e((string)($song['artist'] ?: 'Artist not set')) ?></td>
                <td class="setmaxx-number-cell"><?= (int)$song['request_count'] ?></td>
                <td class="setmaxx-number-cell"><?= (int)$song['paid_request_count'] ?></td>
                <td class="setmaxx-number-cell"><?= e(setmaxx_money((int)$song['tracked_cents'])) ?></td>
                <td><?= !empty($song['last_requested_at']) ? e(date('M j, Y', strtotime((string)$song['last_requested_at']))) : 'Not set' ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>
