<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

Auth::requireLogin(rss_tool_library_url());

$userId = Auth::userId();
Auth::syncEntitlementsByEmail($pdo, $userId);
$user = Auth::currentUser($pdo);

$stmt = $pdo->prepare("
SELECT
    p.id,
    p.slug,
    p.name,
    p.kind,
    p.file_path,
    e_best.source,
    e_best.expires_at,
    e_best.created_at AS granted_at
FROM products p
JOIN (
    SELECT
        e1.user_id,
        e1.product_id,
        e1.source,
        e1.expires_at,
        e1.created_at,
        e1.id
    FROM entitlements e1
    JOIN (
        SELECT
            user_id,
            product_id,
            MAX(created_at) AS max_created_at,
            MAX(id) AS max_id
        FROM entitlements
        WHERE user_id = ?
          AND status = 'active'
          AND (expires_at IS NULL OR expires_at > NOW())
        GROUP BY user_id, product_id
    ) latest
      ON latest.user_id = e1.user_id
     AND latest.product_id = e1.product_id
     AND latest.max_created_at = e1.created_at
     AND latest.max_id = e1.id
) e_best
    ON p.id = e_best.product_id
ORDER BY e_best.created_at DESC, p.name ASC;
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hide tool-membership products from the library list because the dedicated
// Calendar Tools card above already represents that access.
$toolSynonymSlugs = [
		'ready-set-shows-pro',
		'calendar-tools-pro',
		'rss-pro',
		'calendar-tools',
		'ready-set-shows',
];

$items = array_values(array_filter($items, static function (array $it) use ($toolSynonymSlugs): bool {
	$slug = strtolower(trim((string)($it['slug'] ?? '')));
	return !in_array($slug, $toolSynonymSlugs, true);
}));
	
	$toolsBadge = rss_tools_access_badge($pdo);
	$toolsState = $toolsBadge['state'];
	$toolsActions = [
			'launch_url'  => rss_tool_launch_url(),
			'pricing_url' => rss_tool_upgrade_url(),
			'trial_url'   => rss_tool_trial_url(),
			'settings_url'=> base_url('member/settings.php'),
	];
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
	?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= $isReadySetShowsHost ? 'My Tools | Ready Set Shows' : 'My Products • Nick Sanzeri Studio' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">

  <style>
    @media (max-width: 820px) {
      main details { grid-column: 1 / -1; }
      main .card > div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
    }
  </style>
</head>
<body>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_header.php';
} else {
  include __DIR__ . '/../../includes/header.php';
}
?>
<main class="container" style="padding:3rem 0;">
  <div style="display:flex; align-items:baseline; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <div>
      <h1 style="margin:0;"><?= $isReadySetShowsHost ? 'My Tools' : 'My Products' ?></h1>
      <p class="muted" style="margin:.4rem 0 0;">Signed in as <?= e($user['email'] ?? '') ?></p>
    </div>
  </div>

  <div style="margin-top:1.25rem; display:grid; gap:1rem;">
    <div class="card" style="padding:1.25rem; border-color:rgba(212,175,55,.28);">
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
        <div style="max-width:720px;">
          <div style="display:flex; align-items:center; gap:.55rem; flex-wrap:wrap;">
            <div style="font-weight:700; font-size:1.2rem;">Ready Set Shows Pro</div>
            <span style="display:inline-flex; align-items:center; padding:.22rem .65rem; border-radius:999px; font-size:.8rem; font-weight:600; background:rgba(212,175,55,.14); color:#f2d67c;">
              <?= e($toolsBadge['label']) ?>
            </span>
          </div>
          <div class="muted" style="font-size:0.94rem; margin-top:.25rem;">
            <?= e($toolsBadge['description']) ?>
          </div>
          <p class="muted" style="margin:.75rem 0 0; max-width:680px;">
            One membership for the working-musician toolkit: calendar availability, Bandsintown prep, setlist/request tools, and future publishing/business features.
          </p>
        </div>

        <div style="display:flex; gap:.65rem; flex-wrap:wrap;">
          <a class="btn btn-outline" href="<?= e($toolsActions['launch_url']) ?>">Open Calendar Tools</a>
          <a class="btn btn-outline" href="<?= e(base_url('setmaxx/index.php')) ?>">Open Set Maxx</a>
          <?php if ($toolsState === 'free'): ?>
            <a class="btn btn-primary" href="<?= e($toolsActions['trial_url']) ?>">Start Free Trial</a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e($toolsState === 'paid' ? $toolsActions['settings_url'] : $toolsActions['pricing_url']) ?>">
              <?= $toolsState === 'trial' ? 'View Pro Plan' : 'Manage Subscription' ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.85rem; margin-top:1.15rem;">
        <details open style="border:1px solid rgba(255,255,255,.08); border-radius:18px; padding:1rem; background:rgba(255,255,255,.03);">
          <summary style="cursor:pointer; font-weight:700;">Availability & Calendar Tools</summary>
          <p class="muted" style="margin:.65rem 0 .75rem;">Find open dates across calendars, format availability for clients, and prepare gig data for real-world musician workflows.</p>
          <ul class="muted" style="margin:0; padding-left:1.2rem; font-size:.92rem;">
            <li>Multi-calendar availability checking</li>
            <li>Bandsintown bulk-upload prep</li>
            <li>Multiple date-output formats</li>
            <li>Printable/client-friendly calendar views</li>
          </ul>
        </details>

        <details style="border:1px solid rgba(255,255,255,.08); border-radius:18px; padding:1rem; background:rgba(255,255,255,.03);">
          <summary style="cursor:pointer; font-weight:700;">Set Maxx</summary>
          <p class="muted" style="margin:.65rem 0 .75rem;">A live-performance module for song catalogs, gig sessions, and controlled audience requests.</p>
          <ul class="muted" style="margin:0 0 .85rem; padding-left:1.2rem; font-size:.92rem;">
            <li>Requestable song catalog</li>
            <li>Public gig request pages</li>
            <li>One active request per song per show</li>
            <li>Queue, played, and decline workflow</li>
          </ul>
          <a class="btn btn-outline" href="<?= e(base_url('setmaxx/index.php')) ?>">Launch Set Maxx</a>
        </details>

        <details style="border:1px solid rgba(255,255,255,.08); border-radius:18px; padding:1rem; background:rgba(255,255,255,.03);">
          <summary style="cursor:pointer; font-weight:700;">Publishing Tools <span class="muted" style="font-weight:400;">soon</span></summary>
          <p class="muted" style="margin:.65rem 0 0;">Newsletter copy, Facebook-event prep, promo blurbs, and other gig-marketing helpers built from the calendar workflow.</p>
        </details>

        <details style="border:1px solid rgba(255,255,255,.08); border-radius:18px; padding:1rem; background:rgba(255,255,255,.03);">
          <summary style="cursor:pointer; font-weight:700;">Finance <span class="muted" style="font-weight:400;">soon</span></summary>
          <p class="muted" style="margin:.65rem 0 0;">Gig fee, deposit, balance due, payment status, average gig value, and yearly totals once the module is ready.</p>
        </details>
      </div>
    </div>

    <?php if (!$items): ?>
      <div class="card" style="padding:1.25rem;">
        <p style="margin:0 0 0.75rem;">No purchased products are in My Products yet.</p>
        <p class="muted" style="margin:0 0 1rem;">Your free Calendar Tools access is already live above. When you buy something in the shop, it will appear here automatically.</p>
        <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/shop/') ?>">Browse the Shop</a>
      </div>
    <?php else: ?>
      <?php foreach ($items as $it): ?>
        <?php
          $actionHtml = '';
          if (($it['kind'] ?? '') === 'digital') {
              $actionHtml = '<a class="btn btn-primary" href="' . e(rss_studio_root_url() . '/member/download.php') . '?product_id=' . (int)$it['id'] . '">Download</a>';
          }
        ?>
        <div class="card" style="padding:1.15rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
          <div>
            <div style="font-weight:600;"><?= e($it['name']) ?></div>
            <div class="muted" style="font-size:0.92rem;">
              <?php
                $source = (string)($it['source'] ?? '');
                $expiresAt = !empty($it['expires_at']) ? strtotime((string)$it['expires_at']) : false;
                if ($source === 'manual_grant' && $expiresAt) {
                    $daysRemaining = max(1, (int) ceil(($expiresAt - time()) / 86400));
                    echo 'Trial access · ends in ' . $daysRemaining . ' day' . ($daysRemaining === 1 ? '' : 's');
                } elseif ($source === 'subscription') {
                    echo 'Included with membership';
                } else {
                    echo 'Purchased';
                }
              ?>
            </div>
          </div>

          <?php if ($actionHtml !== ''): ?>
            <?= $actionHtml ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_footer_lite.php';
} else {
  include __DIR__ . '/../../includes/footer.php';
}
?>
</body>
</html>
