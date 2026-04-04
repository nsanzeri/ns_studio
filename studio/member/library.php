<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
Auth::requireLogin(base_url('studio/member/library.php'));

$userId = Auth::userId();
Auth::syncEntitlementsByEmail($pdo, $userId);
$user = Auth::currentUser($pdo);

$stmt = $pdo->prepare("
  SELECT p.id, p.slug, p.name, p.kind, e.source, e.expires_at
  FROM entitlements e
  JOIN products p ON p.id = e.product_id
  WHERE e.user_id = ?
    AND e.status = 'active'
    AND (e.expires_at IS NULL OR e.expires_at > NOW())
  ORDER BY e.created_at DESC, p.name ASC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Library • Nick Sanzeri Studio</title>
  <link rel="stylesheet" href="<?= e(base_url('../../assets/css/style.css')) ?>">
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

  <?php if (!$items): ?>
    <div class="card" style="margin-top:1.5rem; padding:1.25rem;">
      <p style="margin:0 0 0.75rem;">No products are in your library yet.</p>
      <p class="muted" style="margin:0 0 1rem;">Use the same email you used at checkout. If you already did, buy something in the shop and it will appear here automatically.</p>
      <a class="btn btn-primary" href="<?= e(base_url('studio/shop/')) ?>">Browse the Shop</a>
    </div>
  <?php else: ?>
    <div style="margin-top:1.25rem; display:grid; gap:1rem;">
      <?php foreach ($items as $it): ?>
        <div class="card" style="padding:1.15rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
          <div>
            <div style="font-weight:600;"><?= e($it['name']) ?></div>
            <div class="muted" style="font-size:0.92rem;">
              <?= $it['source'] === 'subscription' ? 'Included with membership' : 'Purchased' ?>
            </div>
          </div>

          <?php if ($it['kind'] === 'digital'): ?>
            <a class="btn btn-primary" href="<?= e(base_url('studio/member/download.php')) ?>?product_id=<?= (int)$it['id'] ?>">Download</a>
          <?php else: ?>
            <a class="btn btn-outline" href="#">View</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
