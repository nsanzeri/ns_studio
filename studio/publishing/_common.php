<?php
declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = base_url('/publishing/index.php');
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$user = Auth::currentUser($pdo);
if (!$user) {
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$userId = (int)($user['id'] ?? 0);
$isProUser = rss_current_user_is_pro($pdo);
$messages = [];
$errors = [];

function publishing_table_exists(PDO $pdo, string $tableName): bool {
    static $cache = [];
    $key = strtolower(trim($tableName));
    if ($key === '') return false;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$key]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function publishing_clean_show_title(string $title): string {
    $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
    return $title !== '' ? $title : 'Live music';
}

function publishing_show_place(array $show): string {
    foreach (['venue_name', 'location'] as $key) {
        $value = trim((string)($show[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return publishing_clean_show_title((string)($show['title'] ?? ''));
}

function publishing_show_date(array $show, string $format = 'D, M j'): string {
    return (new DateTime((string)$show['starts_at']))->format($format);
}

function publishing_show_line(array $show): string {
    return publishing_show_date($show, 'D, M j') . ' - ' . publishing_show_place($show);
}

function publishing_page_head(string $title): void { ?>
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
    .publishing-shell { padding:2rem 0 4rem; }
    .publishing-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:24px; padding:1.35rem; box-shadow:0 16px 34px rgba(0,0,0,.18); }
    .publishing-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .publishing-stack { display:grid; gap:1rem; }
    .publishing-field { display:grid; gap:.45rem; }
    .publishing-input, .publishing-select, .publishing-textarea { width:100%; padding:.72rem .8rem; border-radius:12px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .publishing-select option { background:#151323; color:#fff; }
    .publishing-textarea { min-height:92px; resize:vertical; }
    .publishing-muted { color:rgba(255,255,255,.72); }
    .publishing-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .75rem; border-radius:999px; font-size:.84rem; font-weight:600; background:rgba(212,175,55,.16); color:#ffe28a; }
    .publishing-show-list { display:grid; gap:.5rem; max-height:430px; overflow:auto; padding-right:.25rem; }
    .publishing-show-row { display:grid; grid-template-columns:auto minmax(0, 1fr); gap:.65rem; align-items:start; padding:.65rem .75rem; border-radius:14px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.06); }
    .publishing-output-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; margin-top:1rem; }
    .publishing-output { display:grid; gap:.7rem; }
    .publishing-output textarea { min-height:180px; }
    .publishing-actions { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 980px) { .publishing-grid, .publishing-output-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header.php'; ?>
<?php }

function publishing_page_foot(): void { ?>
<?php include __DIR__ . '/../../includes/tools_footer.php'; ?>
</body>
</html>
<?php }

function publishing_flash(array $messages, array $errors): void {
    foreach ($messages as $message) echo '<div class="alert alert-success">' . e($message) . '</div>';
    foreach ($errors as $error) echo '<div class="alert alert-error">' . e($error) . '</div>';
}
