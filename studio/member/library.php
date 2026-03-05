<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
Auth::requireLogin('/studio/library.php');

$userId = Auth::userId();

// Only show items THEY have access to
$stmt = $pdo->prepare("
  SELECT p.id, p.slug, p.name, p.kind, e.source, e.expires_at
  FROM entitlements e
  JOIN products p ON p.id = e.product_id
  WHERE e.user_id = ?
    AND e.status = 'active'
    AND (e.expires_at IS NULL OR e.expires_at > NOW())
  ORDER BY e.created_at DESC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Library • Nick Sanzeri Studio</title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>
<main class="container" style="padding:3rem 0;">
  <div style="display:flex; align-items:baseline; justify-content:space-between; gap:1rem;">
    <h1 style="margin:0;">My Library</h1>
    <a class="text-link" href="/studio/logout.php">Log out</a>
  </div>

  <?php if (!$items): ?>
    <div class="card" style="margin-top:1.5rem; padding:1.25rem;">
      <p style="margin:0 0 0.75rem;">Your library is empty (for now).</p>
      <p class="muted" style="margin:0 0 1rem;">When you buy something, it shows up here automatically.</p>
      <a class="btn btn-primary" href="/shop/">Browse the Shop</a>
      <a class="btn btn-outline" href="/studio/pricing.php" style="margin-left:0.5rem;">See memberships</a>
    </div>
  <?php else: ?>
    <div style="margin-top:1.25rem; display:grid; gap:1rem;">
      <?php foreach ($items as $it): ?>
        <div class="card" style="padding:1.15rem; display:flex; align-items:center; justify-content:space-between; gap:1rem;">
          <div>
            <div style="font-weight:600;"><?php echo e($it['name']); ?></div>
            <div class="muted" style="font-size:0.92rem;">
              <?php
                if ($it['source'] === 'subscription') echo 'Included with membership';
                else echo 'Purchased';
              ?>
            </div>
          </div>

          <?php if ($it['kind'] === 'digital'): ?>
            <a class="btn btn-primary" href="/download.php?product_id=<?php echo (int)$it['id']; ?>">Download</a>
          <?php else: ?>
            <a class="btn btn-outline" href="/studio/order.php?product_id=<?php echo (int)$it['id']; ?>">View</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>