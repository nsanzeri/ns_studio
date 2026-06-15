<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = base_url('/setmaxx/index.php');
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$isProUser = rss_current_user_is_pro($pdo);
$upgradeUrl = rss_tool_upgrade_url();
$sessionLinkBase = base_url('/request.php?token=');
$stableSessionLinkBase = base_url('/request.php?link=');
$messages = [];
$errors = [];

function setmaxx_table_exists(PDO $pdo, string $tableName): bool {
    static $cache = [];
    $key = strtolower(trim($tableName));
    if ($key === '') return false;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$key]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function setmaxx_tables_ready(PDO $pdo): bool {
    foreach (['setmaxx_songs', 'setmaxx_gig_sessions', 'setmaxx_requests'] as $tableName) {
        if (!setmaxx_table_exists($pdo, $tableName)) return false;
    }
    return true;
}

function setmaxx_ensure_public_links_table(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_public_links')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_public_links` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `public_token` char(32) NOT NULL,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_public_links_user` (`user_id`),
          UNIQUE KEY `uq_setmaxx_public_links_token` (`public_token`),
          CONSTRAINT `fk_setmaxx_public_links_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_public_link_token(PDO $pdo, int $userId): string {
    setmaxx_ensure_public_links_table($pdo);

    $stmt = $pdo->prepare("SELECT public_token FROM setmaxx_public_links WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $token = (string)($stmt->fetchColumn() ?: '');
    if ($token !== '') return $token;

    $insert = $pdo->prepare("INSERT INTO setmaxx_public_links (user_id, public_token) VALUES (?, ?)");
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(16));
        try {
            $insert->execute([$userId, $token]);
            return $token;
        } catch (Throwable $e) {
            if ($attempt === 4) throw $e;
        }
    }

    throw new RuntimeException('Could not create a stable public link.');
}

function setmaxx_absolute_url(string $path): string {
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function setmaxx_enforce_single_live_session(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("SELECT id FROM setmaxx_gig_sessions WHERE user_id = ? AND status = 'live' ORDER BY COALESCE(starts_at, created_at) DESC, id DESC");
    $stmt->execute([$userId]);
    $liveIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    if (count($liveIds) <= 1) return;

    $keepId = array_shift($liveIds);
    $placeholders = implode(',', array_fill(0, count($liveIds), '?'));
    $params = array_merge([$userId, $keepId], $liveIds);
    $pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live' AND id <> ? AND id IN ({$placeholders})")->execute($params);
}

function setmaxx_money(int $cents): string {
    return '$' . number_format($cents / 100, 0);
}

function setmaxx_status_pill(string $status): string {
    return '<span class="setmaxx-pill">' . e(ucfirst($status)) . '</span>';
}

function setmaxx_lyrics_url(string $title, ?string $artist = null): string {
    $query = trim($title . ' ' . (string)$artist . ' lyrics');
    return 'https://www.google.com/search?q=' . rawurlencode($query);
}

function setmaxx_tip_platform_fee_percent(): int {
    return 0;
}

function setmaxx_direct_platform_tip_user_ids(): array {
    $raw = (string)env('SETMAXX_DIRECT_PLATFORM_TIP_USER_IDS', '');
    if (trim($raw) === '') return [];
    return array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw) ?: []), fn($id) => $id > 0)));
}

function setmaxx_user_uses_direct_platform_tips(int $userId): bool {
    return in_array($userId, setmaxx_direct_platform_tip_user_ids(), true);
}

function setmaxx_tip_application_fee_cents(int $amountCents, int $performerUserId): int {
    if ($amountCents <= 0 || setmaxx_user_uses_direct_platform_tips($performerUserId)) {
        return 0;
    }
    return (int)floor($amountCents * (setmaxx_tip_platform_fee_percent() / 100));
}

function setmaxx_ensure_connect_accounts_table(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_connect_accounts')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_connect_accounts` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `stripe_account_id` varchar(255) NOT NULL,
          `charges_enabled` tinyint(1) NOT NULL DEFAULT 0,
          `payouts_enabled` tinyint(1) NOT NULL DEFAULT 0,
          `details_submitted` tinyint(1) NOT NULL DEFAULT 0,
          `onboarding_completed` tinyint(1) NOT NULL DEFAULT 0,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_connect_user` (`user_id`),
          UNIQUE KEY `uq_setmaxx_connect_account` (`stripe_account_id`),
          CONSTRAINT `fk_setmaxx_connect_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_connect_account_row(PDO $pdo, int $userId): ?array {
    setmaxx_ensure_connect_accounts_table($pdo);
    $stmt = $pdo->prepare("SELECT * FROM setmaxx_connect_accounts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row ?: null;
}

function setmaxx_upsert_connect_account(PDO $pdo, int $userId, string $accountId, $stripeAccount): void {
    setmaxx_ensure_connect_accounts_table($pdo);
    $chargesEnabled = !empty($stripeAccount->charges_enabled) ? 1 : 0;
    $payoutsEnabled = !empty($stripeAccount->payouts_enabled) ? 1 : 0;
    $detailsSubmitted = !empty($stripeAccount->details_submitted) ? 1 : 0;
    $onboardingCompleted = ($chargesEnabled && $payoutsEnabled && $detailsSubmitted) ? 1 : 0;

    $pdo->prepare(
        "INSERT INTO setmaxx_connect_accounts
            (user_id, stripe_account_id, charges_enabled, payouts_enabled, details_submitted, onboarding_completed)
         VALUES
            (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            stripe_account_id = VALUES(stripe_account_id),
            charges_enabled = VALUES(charges_enabled),
            payouts_enabled = VALUES(payouts_enabled),
            details_submitted = VALUES(details_submitted),
            onboarding_completed = VALUES(onboarding_completed)"
    )->execute([$userId, $accountId, $chargesEnabled, $payoutsEnabled, $detailsSubmitted, $onboardingCompleted]);
}

function setmaxx_connect_ready(?array $row): bool {
    if (!$row) return false;
    return !empty($row['charges_enabled']) && !empty($row['payouts_enabled']) && !empty($row['details_submitted']);
}

$tablesReady = setmaxx_tables_ready($pdo);

function setmaxx_page_head(string $title): void { ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .setmaxx-shell { padding: 2rem 0 4rem; }
    .setmaxx-hero { display:grid; grid-template-columns:1.25fr .75fr; gap:1.25rem; margin-bottom:1.5rem; }
    .setmaxx-card { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:24px; padding:1.35rem; box-shadow:0 16px 34px rgba(0,0,0,.18); }
    .setmaxx-grid { display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; }
    .setmaxx-stack { display:grid; gap:1rem; }
    .setmaxx-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.9rem; }
    .setmaxx-field { display:grid; gap:.45rem; }
    .setmaxx-input, .setmaxx-select, .setmaxx-textarea { width:100%; padding:.85rem .95rem; border-radius:14px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .setmaxx-select option, .setmaxx-input option { background:#151323; color:#fff; }
    .setmaxx-textarea { min-height:100px; resize:vertical; }
    .setmaxx-actions { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }
    .setmaxx-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .75rem; border-radius:999px; font-size:.84rem; font-weight:600; background: rgba(140,107,255,.16); color:#efe7ff; }
    .setmaxx-list { display:grid; gap:.8rem; }
    .setmaxx-row { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap; padding:.95rem 1rem; border-radius:16px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.06); }
    .setmaxx-meta { color:rgba(255,255,255,.72); font-size:.92rem; }
    .setmaxx-request-amount { font-weight:700; color:#efe7ff; }
    .setmaxx-status { text-transform:capitalize; }
    .setmaxx-status.pending { color:#ffd86b; }
    .setmaxx-status.queued { color:#7fd0ff; }
    .setmaxx-status.played { color:#7dffbf; }
    .setmaxx-status.declined, .setmaxx-status.canceled { color:#ff9999; }
    .setmaxx-help { color: rgba(255,255,255,.72); font-size:.93rem; }
    .setmaxx-note { border-left:3px solid rgba(140,107,255,.5); padding-left:.9rem; }
    .setmaxx-link-box { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; padding:.9rem 1rem; border-radius:16px; background:rgba(140,107,255,.08); border:1px solid rgba(140,107,255,.16); }
    .setmaxx-link-box code { word-break:break-all; }
    .setmaxx-module-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:1rem; }
    .setmaxx-module-card { text-decoration:none; color:inherit; transition:transform .16s ease, border-color .16s ease; }
    .setmaxx-module-card:hover { transform:translateY(-2px); border-color:rgba(140,107,255,.35); }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 980px) { .setmaxx-hero, .setmaxx-grid, .setmaxx-form-grid, .setmaxx-module-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/setmaxx_header.php'; ?>
<?php }

function setmaxx_page_foot(): void { ?>
<?php include __DIR__ . '/../../includes/setmaxx_footer.php'; ?>
</body>
</html>
<?php }

function setmaxx_flash(array $messages, array $errors): void {
    foreach ($messages as $message) echo '<div class="alert alert-success">' . e($message) . '</div>';
    foreach ($errors as $error) echo '<div class="alert alert-error">' . e($error) . '</div>';
}

function setmaxx_install_notice(): void { ?>
  <div class="setmaxx-card">
    <h2 style="margin-top:0;">Database setup required</h2>
    <p class="setmaxx-help">Run the SQL in <code>studio/setmaxx/install.sql</code> first. After that, refresh this page and the module will come alive.</p>
  </div>
<?php }
