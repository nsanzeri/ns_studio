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
    e.source,
    e.expires_at,
    e.created_at AS granted_at
FROM entitlements e
JOIN products p
    ON p.id = e.product_id
WHERE e.user_id = ?
  AND e.status = 'active'
  AND (e.expires_at IS NULL OR e.expires_at > NOW())
ORDER BY e.created_at DESC, p.name ASC;
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$toolsBadge = rss_tools_access_badge($pdo);
$toolsState = $toolsBadge['state'];
$toolsActions = [
    'launch_url'  => rss_tool_launch_url(),
    'pricing_url' => rss_tool_upgrade_url(),
    'trial_url'   => rss_tool_trial_url(),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Library • Nick Sanzeri Studio</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="container" style="padding:3rem 0;">
  <div style="display:flex; align-items:baseline; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <div>
      <h1 style="margin:0;">My Library</h1>
      <p class="muted" style="margin:.4rem 0 0;">Signed in as <?= e($user['email'] ?? '') ?></p>
    </div>
  </div>

  <div style="margin-top:1.25rem; display:grid; gap:1rem;">
    <div class="card" style="padding:1.15rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; border-color:rgba(212,175,55,.28);">
      <div>
        <div style="display:flex; align-items:center; gap:.55rem; flex-wrap:wrap;">
          <div style="font-weight:600;">Calendar Tools</div>
          <span style="display:inline-flex; align-items:center; padding:.22rem .65rem; border-radius:999px; font-size:.8rem; font-weight:600; background:rgba(212,175,55,.14); color:#f2d67c;">
            <?= e($toolsBadge['label']) ?>
          </span>
        </div>
        <div class="muted" style="font-size:0.92rem; margin-top:.2rem;">
          <?= e($toolsBadge['description']) ?>
        </div>
      </div>

      <div style="display:flex; gap:.65rem; flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= e($toolsActions['launch_url']) ?>">Open Tools</a>
        <?php if ($toolsState === 'free'): ?>
          <a class="btn btn-primary" href="<?= e($toolsActions['trial_url']) ?>">Start Free Trial</a>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e($toolsActions['pricing_url']) ?>">
            <?= $toolsState === 'trial' ? 'View Pro Plan' : 'Manage Access' ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$items): ?>
      <div class="card" style="padding:1.25rem;">
        <p style="margin:0 0 0.75rem;">No purchased products are in your library yet.</p>
        <p class="muted" style="margin:0 0 1rem;">Your free Calendar Tools access is already live above. When you buy something in the shop, it will appear here automatically.</p>
        <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/shop/') ?>">Browse the Shop</a>
      </div>
    <?php else: ?>
      <?php foreach ($items as $it): ?>
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

          <?php if (($it['kind'] ?? '') === 'digital'): ?>
            <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/member/download.php') ?>?product_id=<?= (int)$it['id'] ?>">Download</a>
          <?php else: ?>
            <a class="btn btn-outline" href="#">View</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
