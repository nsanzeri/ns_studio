<?php
require_once __DIR__ . '/_common.php';

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > ((int)date('Y') + 2)) $year = (int)date('Y');
$range = (string)($_GET['range'] ?? 'year');
if ($range !== 'all') $range = 'year';
$previousYear = $year - 1;

$summary = [
    'week' => ['gigs' => 0, 'gross_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0],
    'month' => ['gigs' => 0, 'gross_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0],
    'year' => ['gigs' => 0, 'gross_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0],
];
$monthlyRows = [];
$previousMonthlyRows = [];
$memberRows = [];
$availableYears = [];
$overview = [
    'gigs' => 0,
    'guarantee_cents' => 0,
    'tips_cents' => 0,
    'gross_cents' => 0,
    'active_months' => 0,
];
$highestNetGig = null;
$lowestNetGig = null;
$biggestMonth = null;
$chartRows = [];
$chartLabels = [];
$chartValues = [];
$chartPoints = '';
$chartAreaPoints = '';
$chartMax = 0;
$chartWidth = 900;
$chartHeight = 220;
$chartPadX = 34;
$chartPadY = 22;
$chartPlotWidth = $chartWidth - ($chartPadX * 2);
$chartPlotHeight = $chartHeight - ($chartPadY * 2);

if ($financeReady) {
    $yearStmt = $pdo->prepare("
        SELECT DISTINCT YEAR(starts_at) AS gig_year
        FROM finance_gigs
        WHERE user_id = ?
        ORDER BY gig_year DESC
    ");
    $yearStmt->execute([$userId]);
    $availableYears = array_values(array_filter(array_map('intval', $yearStmt->fetchAll(PDO::FETCH_COLUMN)), fn($value) => $value > 0));
    if ($availableYears && !in_array($year, $availableYears, true)) {
        $year = $availableYears[0];
        $previousYear = $year - 1;
    }

    $summarySql = "
        SELECT
          COUNT(*) AS gigs,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents), 0) AS gross_cents,
          COALESCE(SUM(g.tips_cents), 0) AS tips_cents,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents - COALESCE(p.payout_cents, 0)), 0) AS net_cents
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE g.user_id = ?
    ";
    $ranges = [
        'week' => ["g.starts_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND g.starts_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)"],
        'month' => ["YEAR(g.starts_at) = YEAR(CURDATE()) AND MONTH(g.starts_at) = MONTH(CURDATE())"],
        'year' => ["YEAR(g.starts_at) = ?"],
    ];
    foreach ($ranges as $key => $parts) {
        $stmt = $pdo->prepare($summarySql . ' AND ' . $parts[0]);
        $params = $key === 'year' ? [$userId, $year] : [$userId];
        $stmt->execute($params);
        $summary[$key] = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC) ?: $summary[$key]);
    }

    $monthSql = "
        SELECT MONTH(g.starts_at) AS month_num,
               COUNT(*) AS gigs,
               COALESCE(SUM(g.guarantee_cents), 0) AS guarantee_cents,
               COALESCE(SUM(g.guarantee_cents + g.tips_cents), 0) AS gross_cents,
               COALESCE(SUM(g.tips_cents), 0) AS tips_cents,
               COALESCE(SUM(g.guarantee_cents + g.tips_cents - COALESCE(p.payout_cents, 0)), 0) AS net_cents
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE g.user_id = ? AND YEAR(g.starts_at) = ?
        GROUP BY MONTH(g.starts_at)
        ORDER BY MONTH(g.starts_at)
    ";
    $stmt = $pdo->prepare($monthSql);
    $stmt->execute([$userId, $year]);
    $monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->execute([$userId, $previousYear]);
    $previousMonthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $memberStmt = $pdo->prepare("
        SELECT m.name,
               COUNT(DISTINCT p.gig_id) AS gigs,
               COALESCE(SUM(p.amount_cents), 0) AS payout_cents
        FROM finance_gig_payouts p
        JOIN finance_members m ON m.id = p.member_id
        JOIN finance_gigs g ON g.id = p.gig_id
        WHERE g.user_id = ?
          AND YEAR(g.starts_at) = ?
          AND p.payout_type = 'band_member'
        GROUP BY m.id, m.name
        ORDER BY payout_cents DESC, m.name ASC
    ");
    $memberStmt->execute([$userId, $year]);
    $memberRows = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

    $overviewWhere = "g.user_id = ?";
    $overviewParams = [$userId];
    if ($range === 'year') {
        $overviewWhere .= " AND YEAR(g.starts_at) = ?";
        $overviewParams[] = $year;
    }

    $overviewStmt = $pdo->prepare("
        SELECT
          COUNT(*) AS gigs,
          COALESCE(SUM(g.guarantee_cents), 0) AS guarantee_cents,
          COALESCE(SUM(g.tips_cents), 0) AS tips_cents,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents), 0) AS gross_cents,
          COUNT(DISTINCT DATE_FORMAT(g.starts_at, '%Y-%m')) AS active_months
        FROM finance_gigs g
        WHERE {$overviewWhere}
    ");
    $overviewStmt->execute($overviewParams);
    $overview = array_map('intval', $overviewStmt->fetch(PDO::FETCH_ASSOC) ?: $overview);

    $gigHighlightStmt = $pdo->prepare("
        SELECT
          g.title,
          g.starts_at,
          COALESCE(g.guarantee_cents + g.tips_cents - COALESCE(p.payout_cents, 0), 0) AS net_cents
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE {$overviewWhere}
        ORDER BY net_cents DESC, g.starts_at ASC, g.title ASC
        LIMIT 1
    ");
    $gigHighlightStmt->execute($overviewParams);
    $highestNetGig = $gigHighlightStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $gigHighlightStmt = $pdo->prepare("
        SELECT
          g.title,
          g.starts_at,
          COALESCE(g.guarantee_cents + g.tips_cents - COALESCE(p.payout_cents, 0), 0) AS net_cents
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE {$overviewWhere}
        ORDER BY net_cents ASC, g.starts_at ASC, g.title ASC
        LIMIT 1
    ");
    $gigHighlightStmt->execute($overviewParams);
    $lowestNetGig = $gigHighlightStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $chartStmt = $pdo->prepare("
        SELECT
          YEAR(g.starts_at) AS year_num,
          MONTH(g.starts_at) AS month_num,
          COALESCE(SUM(g.guarantee_cents), 0) AS guarantee_cents,
          COALESCE(SUM(g.tips_cents), 0) AS tips_cents,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents), 0) AS gross_cents
        FROM finance_gigs g
        WHERE {$overviewWhere}
        GROUP BY YEAR(g.starts_at), MONTH(g.starts_at)
        ORDER BY YEAR(g.starts_at), MONTH(g.starts_at)
    ");
    $chartStmt->execute($overviewParams);
    $chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

    $biggestMonthStmt = $pdo->prepare("
        SELECT
          YEAR(g.starts_at) AS year_num,
          MONTH(g.starts_at) AS month_num,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents - COALESCE(p.payout_cents, 0)), 0) AS net_cents
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE {$overviewWhere}
        GROUP BY YEAR(g.starts_at), MONTH(g.starts_at)
        ORDER BY net_cents DESC, YEAR(g.starts_at) ASC, MONTH(g.starts_at) ASC
        LIMIT 1
    ");
    $biggestMonthStmt->execute($overviewParams);
    $biggestMonth = $biggestMonthStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$previousByMonth = [];
foreach ($previousMonthlyRows as $row) {
    $previousByMonth[(int)$row['month_num']] = $row;
}

if ($range === 'year') {
    $chartByMonth = [];
    foreach ($chartRows as $row) $chartByMonth[(int)$row['month_num']] = $row;
    $filledChartRows = [];
    for ($month = 1; $month <= 12; $month++) {
        $filledChartRows[] = $chartByMonth[$month] ?? [
            'year_num' => $year,
            'month_num' => $month,
            'guarantee_cents' => 0,
            'tips_cents' => 0,
            'gross_cents' => 0,
        ];
    }
    $chartRows = $filledChartRows;
}

foreach ($chartRows as $row) {
    $month = (int)$row['month_num'];
    $rowYear = (int)$row['year_num'];
    $chartLabels[] = $range === 'all'
        ? date('M Y', mktime(0, 0, 0, $month, 1, $rowYear))
        : date('M', mktime(0, 0, 0, $month, 1));
    $chartValues[] = (int)$row['gross_cents'];
}

$chartMax = max($chartValues ?: [0]);
$pointCount = count($chartValues);
if ($pointCount > 0) {
    $points = [];
    foreach ($chartValues as $index => $value) {
        $x = $pointCount === 1 ? $chartPadX + ($chartPlotWidth / 2) : $chartPadX + (($chartPlotWidth / max(1, $pointCount - 1)) * $index);
        $y = $chartPadY + ($chartPlotHeight - (($chartMax > 0 ? $value / $chartMax : 0) * $chartPlotHeight));
        $points[] = round($x, 2) . ',' . round($y, 2);
    }
    $chartPoints = implode(' ', $points);
    $chartAreaPoints = $chartPadX . ',' . ($chartHeight - $chartPadY) . ' ' . $chartPoints . ' ' . ($chartWidth - $chartPadX) . ',' . ($chartHeight - $chartPadY);
}

$activeMonths = max(1, (int)$overview['active_months']);
$monthlyAverageGuarantee = (int)round((int)$overview['guarantee_cents'] / $activeMonths);
$monthlyAverageTips = (int)round((int)$overview['tips_cents'] / $activeMonths);
$monthlyAverageCombined = (int)round((int)$overview['gross_cents'] / $activeMonths);
$gigCount = max(1, (int)$overview['gigs']);
$gigAverageGuarantee = (int)round((int)$overview['guarantee_cents'] / $gigCount);
$gigAverageTips = (int)round((int)$overview['tips_cents'] / $gigCount);
$gigAverageCombined = (int)round((int)$overview['gross_cents'] / $gigCount);
$overviewLabel = $range === 'all' ? 'All years' : (string)$year;
$monthlyTotals = ['gigs' => 0, 'guarantee_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0];
foreach ($monthlyRows as $row) {
    $monthlyTotals['gigs'] += (int)$row['gigs'];
    $monthlyTotals['guarantee_cents'] += (int)$row['guarantee_cents'];
    $monthlyTotals['tips_cents'] += (int)$row['tips_cents'];
    $monthlyTotals['net_cents'] += (int)$row['net_cents'];
}
$comparisonTotals = ['current_net_cents' => 0, 'previous_net_cents' => 0, 'change_cents' => 0];
foreach ($monthlyRows as $row) {
    $prev = $previousByMonth[(int)$row['month_num']] ?? null;
    $comparisonTotals['current_net_cents'] += (int)$row['net_cents'];
    $comparisonTotals['previous_net_cents'] += (int)($prev['net_cents'] ?? 0);
}
$comparisonTotals['change_cents'] = $comparisonTotals['current_net_cents'] - $comparisonTotals['previous_net_cents'];

finance_page_head('Finance | Ready Set Shows');
?>
<main class="container finance-shell">
  <?php finance_flash($messages, $errors); ?>
  <section class="finance-card" style="margin-bottom:1rem;">
    <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
      <div>
        <h1 style="margin:0 0 .35rem;">Finance</h1>
        <p class="finance-muted" style="margin:0;">Import gigs from your calendars, enrich the rows with pay details, and watch totals by week, month, and year.</p>
      </div>
      <a class="btn btn-primary" href="<?= e(base_url('/finance/gigs.php')) ?>">Open gig ledger</a>
    </div>
  </section>

  <?php if (!$financeReady): ?>
    <?php finance_install_notice(); ?>
  <?php else: ?>
    <section class="finance-card finance-overview-card" style="margin-bottom:1rem;">
      <div class="finance-overview-head">
        <div>
          <h2 style="margin:0;">Income overview</h2>
          <p class="finance-muted" style="margin:.35rem 0 0;"><?= e($overviewLabel) ?> guarantee plus tips, grouped by month.</p>
        </div>
        <div class="finance-range-actions" aria-label="Income range">
          <a class="finance-year-link <?= $range === 'all' ? 'active' : '' ?>" href="<?= e(base_url('/finance/index.php?range=all')) ?>">All</a>
          <?php foreach ($availableYears as $availableYear): ?>
            <a class="finance-year-link <?= $range === 'year' && (int)$availableYear === (int)$year ? 'active' : '' ?>" href="<?= e(base_url('/finance/index.php?year=' . (int)$availableYear)) ?>"><?= (int)$availableYear ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="finance-overview-total">
        <span>Total income</span>
        <strong><?= finance_money((int)$overview['gross_cents']) ?></strong>
      </div>

      <div class="finance-chart-wrap">
        <?php if (!$chartValues || $chartMax <= 0): ?>
          <div class="finance-chart-empty">No income rows for this range yet.</div>
        <?php else: ?>
          <svg class="finance-income-chart" viewBox="0 0 <?= (int)$chartWidth ?> <?= (int)$chartHeight ?>" role="img" aria-label="Total income chart">
            <defs>
              <linearGradient id="financeIncomeFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#55c8ff" stop-opacity=".34"></stop>
                <stop offset="100%" stop-color="#55c8ff" stop-opacity=".04"></stop>
              </linearGradient>
            </defs>
            <line x1="<?= (int)$chartPadX ?>" y1="<?= (int)($chartHeight - $chartPadY) ?>" x2="<?= (int)($chartWidth - $chartPadX) ?>" y2="<?= (int)($chartHeight - $chartPadY) ?>" class="finance-chart-axis"></line>
            <polygon points="<?= e($chartAreaPoints) ?>" class="finance-chart-area"></polygon>
            <polyline points="<?= e($chartPoints) ?>" class="finance-chart-line"></polyline>
          </svg>
          <div class="finance-chart-labels">
            <?php
              $labelIndexes = $chartLabels ? array_values(array_unique([0, (int)floor((count($chartLabels) - 1) / 2), count($chartLabels) - 1])) : [];
              foreach ($labelIndexes as $labelIndex):
            ?>
              <span><?= e((string)($chartLabels[$labelIndex] ?? '')) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="finance-overview-stats">
        <div class="finance-stat-mini"><span>Gig total</span><strong><?= (int)$overview['gigs'] ?></strong></div>
        <div class="finance-stat-mini"><span>Tips total</span><strong><?= finance_money((int)$overview['tips_cents']) ?></strong></div>
        <div class="finance-stat-mini"><span>Monthly avg guarantee</span><strong><?= finance_money($monthlyAverageGuarantee) ?></strong></div>
        <div class="finance-stat-mini"><span>Monthly avg tips</span><strong><?= finance_money($monthlyAverageTips) ?></strong></div>
        <div class="finance-stat-mini"><span>Monthly avg combined</span><strong><?= finance_money($monthlyAverageCombined) ?></strong></div>
        <div class="finance-stat-mini"><span>Avg guarantee per gig</span><strong><?= finance_money($gigAverageGuarantee) ?></strong></div>
        <div class="finance-stat-mini"><span>Avg tips per gig</span><strong><?= finance_money($gigAverageTips) ?></strong></div>
        <div class="finance-stat-mini"><span>Avg combined per gig</span><strong><?= finance_money($gigAverageCombined) ?></strong></div>
        <div class="finance-stat-mini"><span><?= $range === 'all' ? 'All-time total' : 'Yearly total' ?></span><strong><?= finance_money((int)$overview['gross_cents']) ?></strong></div>
        <div class="finance-stat-mini">
          <span>Highest net gig</span>
          <strong><?= $highestNetGig ? finance_money((int)$highestNetGig['net_cents']) : '$0.00' ?></strong>
          <?php if ($highestNetGig): ?><em><?= e((string)$highestNetGig['title']) ?> &middot; <?= e((new DateTime((string)$highestNetGig['starts_at']))->format('M j')) ?></em><?php endif; ?>
        </div>
        <div class="finance-stat-mini">
          <span>Lowest net gig</span>
          <strong><?= $lowestNetGig ? finance_money((int)$lowestNetGig['net_cents']) : '$0.00' ?></strong>
          <?php if ($lowestNetGig): ?><em><?= e((string)$lowestNetGig['title']) ?> &middot; <?= e((new DateTime((string)$lowestNetGig['starts_at']))->format('M j')) ?></em><?php endif; ?>
        </div>
        <div class="finance-stat-mini">
          <span>Biggest month</span>
          <strong><?= $biggestMonth ? finance_money((int)$biggestMonth['net_cents']) : '$0.00' ?></strong>
          <?php if ($biggestMonth): ?><em><?= e(date($range === 'all' ? 'M Y' : 'F', mktime(0, 0, 0, (int)$biggestMonth['month_num'], 1, (int)$biggestMonth['year_num']))) ?></em><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="finance-grid" style="margin-bottom:1rem;">
      <?php foreach (['week' => 'This week', 'month' => 'This month', 'year' => (string)$year] as $key => $label): ?>
        <div class="finance-card finance-stat">
          <span class="finance-pill"><?= e($label) ?></span>
          <strong><?= finance_money((int)$summary[$key]['net_cents']) ?></strong>
          <div class="finance-muted"><?= (int)$summary[$key]['gigs'] ?> gigs &middot; <?= finance_money((int)$summary[$key]['gross_cents'] - (int)$summary[$key]['tips_cents']) ?> guarantee &middot; <?= finance_money((int)$summary[$key]['tips_cents']) ?> tips</div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="finance-two">
      <div class="finance-card">
        <h2 style="margin-top:0;">Monthly totals</h2>
        <?php if (!$monthlyRows): ?>
          <p class="finance-muted">No finance rows for <?= (int)$year ?> yet.</p>
        <?php else: ?>
          <div class="finance-table-wrap">
            <table class="finance-table" style="min-width:620px;">
              <thead><tr><th>Month</th><th>Gigs</th><th>Guarantee</th><th>Tips</th><th>Net</th></tr></thead>
              <tbody>
                <?php foreach ($monthlyRows as $row): ?>
                  <tr>
                    <td><?= e(date('F', mktime(0, 0, 0, (int)$row['month_num'], 1))) ?></td>
                    <td><?= (int)$row['gigs'] ?></td>
                    <td><?= finance_money((int)$row['guarantee_cents']) ?></td>
                    <td><?= finance_money((int)$row['tips_cents']) ?></td>
                    <td><?= finance_money((int)$row['net_cents']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th>Total</th>
                  <th><?= (int)$monthlyTotals['gigs'] ?></th>
                  <th><?= finance_money((int)$monthlyTotals['guarantee_cents']) ?></th>
                  <th><?= finance_money((int)$monthlyTotals['tips_cents']) ?></th>
                  <th><?= finance_money((int)$monthlyTotals['net_cents']) ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="finance-card">
        <h2 style="margin-top:0;">Previous year comparison</h2>
        <?php if (!$monthlyRows || !$previousMonthlyRows): ?>
          <p class="finance-muted">Comparison appears once there is data for both <?= (int)$year ?> and <?= (int)$previousYear ?>.</p>
        <?php else: ?>
          <div class="finance-table-wrap">
            <table class="finance-table" style="min-width:620px;">
              <thead><tr><th>Month</th><th><?= (int)$year ?> Net</th><th><?= (int)$previousYear ?> Net</th><th>Change</th></tr></thead>
              <tbody>
                <?php foreach ($monthlyRows as $row): $prev = $previousByMonth[(int)$row['month_num']] ?? null; $changeCents = (int)$row['net_cents'] - (int)($prev['net_cents'] ?? 0); ?>
                  <tr>
                    <td><?= e(date('F', mktime(0, 0, 0, (int)$row['month_num'], 1))) ?></td>
                    <td><?= finance_money((int)$row['net_cents']) ?></td>
                    <td><?= $prev ? finance_money((int)$prev['net_cents']) : '$0.00' ?></td>
                    <td class="<?= $changeCents >= 0 ? 'finance-positive' : 'finance-negative' ?>"><?= finance_money($changeCents) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th>Total</th>
                  <th><?= finance_money((int)$comparisonTotals['current_net_cents']) ?></th>
                  <th><?= finance_money((int)$comparisonTotals['previous_net_cents']) ?></th>
                  <th class="<?= (int)$comparisonTotals['change_cents'] >= 0 ? 'finance-positive' : 'finance-negative' ?>"><?= finance_money((int)$comparisonTotals['change_cents']) ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="finance-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Band member tax rollup</h2>
      <?php if (!$memberRows): ?>
        <p class="finance-muted">No band-member payouts for <?= (int)$year ?> yet.</p>
      <?php else: ?>
        <div class="finance-table-wrap">
          <table class="finance-table" style="min-width:560px;">
            <thead><tr><th>Member</th><th>Gigs</th><th>Total paid</th></tr></thead>
            <tbody>
              <?php foreach ($memberRows as $row): ?>
                <tr>
                  <td><?= e((string)$row['name']) ?></td>
                  <td><?= (int)$row['gigs'] ?></td>
                  <td><?= finance_money((int)$row['payout_cents']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  <?php endif; ?>
</main>
<?php finance_page_foot(); ?>
