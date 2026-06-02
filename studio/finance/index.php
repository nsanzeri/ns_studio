<?php
require_once __DIR__ . '/_common.php';

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > ((int)date('Y') + 2)) $year = (int)date('Y');
$previousYear = $year - 1;

$summary = [
    'week' => ['gigs' => 0, 'gross_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0],
    'month' => ['gigs' => 0, 'gross_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0],
    'year' => ['gigs' => 0, 'gross_cents' => 0, 'tips_cents' => 0, 'net_cents' => 0],
];
$monthlyRows = [];
$previousMonthlyRows = [];

if ($financeReady) {
    $summarySql = "
        SELECT
          COUNT(*) AS gigs,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents), 0) AS gross_cents,
          COALESCE(SUM(g.tips_cents), 0) AS tips_cents,
          COALESCE(SUM(g.guarantee_cents + g.tips_cents - g.advertising_cents - COALESCE(p.payout_cents, 0)), 0) AS net_cents
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE g.user_id = ?
    ";
    $ranges = [
        'week' => ["starts_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND starts_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)"],
        'month' => ["YEAR(starts_at) = YEAR(CURDATE()) AND MONTH(starts_at) = MONTH(CURDATE())"],
        'year' => ["YEAR(starts_at) = ?"],
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
               COALESCE(SUM(g.guarantee_cents + g.tips_cents), 0) AS gross_cents,
               COALESCE(SUM(g.tips_cents), 0) AS tips_cents,
               COALESCE(SUM(g.guarantee_cents + g.tips_cents - g.advertising_cents - COALESCE(p.payout_cents, 0)), 0) AS net_cents
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
}

$previousByMonth = [];
foreach ($previousMonthlyRows as $row) {
    $previousByMonth[(int)$row['month_num']] = $row;
}

finance_page_head('Finance | Ready Set Shows');
?>
<main class="container finance-shell">
  <?php finance_flash($messages, $errors); ?>
  <section class="finance-card" style="margin-bottom:1rem;">
    <div class="finance-pill">Ready Set Shows module</div>
    <h1 style="margin:.8rem 0 .35rem;">Finance</h1>
    <p class="finance-muted" style="margin:0;">Import gigs from your calendars, enrich the rows with pay details, and watch totals by week, month, and year.</p>
  </section>

  <?php if (!$financeReady): ?>
    <?php finance_install_notice(); ?>
  <?php else: ?>
    <section class="finance-grid" style="margin-bottom:1rem;">
      <?php foreach (['week' => 'This week', 'month' => 'This month', 'year' => (string)$year] as $key => $label): ?>
        <div class="finance-card finance-stat">
          <span class="finance-pill"><?= e($label) ?></span>
          <strong><?= finance_money((int)$summary[$key]['gross_cents']) ?></strong>
          <div class="finance-muted"><?= (int)$summary[$key]['gigs'] ?> gigs &middot; <?= finance_money((int)$summary[$key]['tips_cents']) ?> tips &middot; <?= finance_money((int)$summary[$key]['net_cents']) ?> net</div>
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
              <thead><tr><th>Month</th><th>Gigs</th><th>Gross</th><th>Tips</th><th>Net</th></tr></thead>
              <tbody>
                <?php foreach ($monthlyRows as $row): ?>
                  <tr>
                    <td><?= e(date('F', mktime(0, 0, 0, (int)$row['month_num'], 1))) ?></td>
                    <td><?= (int)$row['gigs'] ?></td>
                    <td><?= finance_money((int)$row['gross_cents']) ?></td>
                    <td><?= finance_money((int)$row['tips_cents']) ?></td>
                    <td><?= finance_money((int)$row['net_cents']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
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
                <?php foreach ($monthlyRows as $row): $prev = $previousByMonth[(int)$row['month_num']] ?? null; ?>
                  <tr>
                    <td><?= e(date('F', mktime(0, 0, 0, (int)$row['month_num'], 1))) ?></td>
                    <td><?= finance_money((int)$row['net_cents']) ?></td>
                    <td><?= $prev ? finance_money((int)$prev['net_cents']) : '$0.00' ?></td>
                    <td><?= finance_money((int)$row['net_cents'] - (int)($prev['net_cents'] ?? 0)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="finance-card" style="margin-top:1rem;">
      <div class="finance-stack">
        <h2 style="margin:0;">Gig ledger</h2>
        <p class="finance-muted" style="margin:0;">Import upcoming or past calendar events, then fill in pay, tips, taxable status, mileage, ad costs, and member payouts.</p>
        <div><a class="btn btn-primary" href="<?= e(base_url('/finance/gigs.php')) ?>">Open gig ledger</a></div>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php finance_page_foot(); ?>
