<?php
declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/_core/tool_usage.php';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = base_url('/encore/index.php');
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

function encore_page_head(string $title): void { ?>
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
    .encore-shell { padding:2rem 0 4rem; }
    .encore-card { min-width:0; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:24px; padding:1.35rem; box-shadow:0 16px 34px rgba(0,0,0,.18); }
    .encore-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
    .encore-hero-copy { max-width:760px; }
    .encore-hero-copy h1 { margin:.75rem 0 .35rem; }
    .encore-actions { display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; }
    .encore-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .75rem; border-radius:999px; font-size:.84rem; font-weight:700; background:rgba(212,175,55,.16); color:#ffe28a; }
    .encore-muted { color:rgba(255,255,255,.72); }
    .encore-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:1rem; margin-bottom:1rem; }
    .encore-stat { display:grid; gap:.3rem; }
    .encore-stat span { color:rgba(255,255,255,.64); font-size:.82rem; }
    .encore-stat strong { color:#fff; font-size:1.55rem; line-height:1.1; }
    .encore-stat em { color:rgba(255,255,255,.58); font-size:.78rem; font-style:normal; }
    .encore-layout { display:grid; grid-template-columns:minmax(0, 1.35fr) minmax(300px, .65fr); gap:1rem; align-items:start; }
    .encore-section-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
    .encore-section-head h2 { margin:0 0 .25rem; }
    .encore-tabs { display:inline-flex; gap:.35rem; border:1px solid rgba(255,255,255,.1); border-radius:999px; padding:.25rem; background:rgba(255,255,255,.035); }
    .encore-tab { border:0; border-radius:999px; padding:.45rem .75rem; background:transparent; color:rgba(255,255,255,.76); font:inherit; font-size:.86rem; font-weight:700; cursor:pointer; }
    .encore-tab.is-active { background:rgba(212,175,55,.18); color:#ffe28a; }
    .encore-pipeline { display:grid; grid-template-columns:repeat(4, minmax(190px, 1fr)); gap:.75rem; overflow-x:auto; padding-bottom:.25rem; }
    .encore-stage { min-width:190px; border:1px solid rgba(255,255,255,.08); border-radius:18px; background:rgba(255,255,255,.025); padding:.75rem; }
    .encore-stage h3 { margin:0; font-size:.9rem; color:#f4d35e; text-transform:uppercase; letter-spacing:.06em; }
    .encore-stage-count { color:rgba(255,255,255,.62); font-size:.78rem; margin-top:.15rem; }
    .encore-opportunity { display:grid; gap:.55rem; margin-top:.75rem; padding:.8rem; border-radius:14px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.045); }
    .encore-opportunity strong { color:#fff; }
    .encore-opportunity-meta { display:flex; gap:.4rem; flex-wrap:wrap; }
    .encore-tag { display:inline-flex; align-items:center; border-radius:999px; padding:.18rem .48rem; background:rgba(255,255,255,.07); color:rgba(255,255,255,.74); font-size:.72rem; font-weight:700; }
    .encore-tag.hot { background:rgba(255,122,89,.16); color:#ffb39f; }
    .encore-tag.money { background:rgba(101,213,138,.14); color:#a4f0bd; }
    .encore-followups { display:grid; gap:.7rem; }
    .encore-followup { display:grid; grid-template-columns:auto minmax(0, 1fr); gap:.75rem; align-items:start; padding:.8rem; border-radius:16px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.035); }
    .encore-date { min-width:54px; text-align:center; border-radius:14px; overflow:hidden; border:1px solid rgba(212,175,55,.22); background:rgba(212,175,55,.11); }
    .encore-date span { display:block; padding:.2rem .35rem; color:#ffe28a; font-size:.7rem; font-weight:800; text-transform:uppercase; }
    .encore-date strong { display:block; padding:.1rem .35rem .35rem; color:#fff; font-size:1.15rem; }
    .encore-followup h3 { margin:.05rem 0 .2rem; font-size:1rem; }
    .encore-history-row { display:grid; grid-template-columns:92px 62px minmax(0,1fr) auto; gap:.7rem; align-items:center; padding:.65rem .75rem; border-bottom:1px solid rgba(255,255,255,.07); }
    .encore-history-row:last-child { border-bottom:0; }
    .encore-history-row time { color:#ffe28a; font-size:.78rem; white-space:nowrap; }
    .encore-history-channel { color:rgba(255,255,255,.62); font-size:.76rem; text-transform:capitalize; }
    .encore-history-main { min-width:0; display:grid; gap:.15rem; }
    .encore-history-main strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .encore-history-view { padding:.28rem .55rem; font-size:.7rem; }
    .encore-history-detail { margin-top:.35rem; line-height:1.45; }
    #followups { width:auto; }
    .encore-compact h2 { margin:0 0 .5rem; }
    .encore-empty { margin:.35rem 0; font-size:.85rem; color:rgba(255,255,255,.6); }
    .encore-activity, .encore-task-row { display:grid; grid-template-columns:100px 95px minmax(0,1fr) auto; align-items:center; gap:.7rem; padding:.55rem 0; border-bottom:1px solid rgba(255,255,255,.08); font-size:.85rem; }
    .encore-task-row { grid-template-columns:100px minmax(0,1fr) minmax(0,1fr) auto; }
    .encore-activity:last-child, .encore-task-row:last-child { border-bottom:0; }
    .encore-activity time, .encore-task-row time { font-size:.75rem; color:#ffe28a; white-space:nowrap; }
    .encore-channel { font-size:.75rem; color:rgba(255,255,255,.65); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .encore-ellipsis { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .encore-activity-detail { grid-column:1/-1; padding:.5rem 0; overflow-wrap:anywhere; white-space:pre-wrap; }
    .encore-activity-detail p { margin:.4rem 0; }
    .encore-small { padding:.25rem .55rem; font-size:.7rem; }
    .encore-contact-fields { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .encore-field-wide { grid-column:1/-1; }
    .encore-dialog { max-height:calc(100dvh - 2rem); overflow:auto; box-sizing:border-box; }
    .encore-dialog h2 { padding-right:2.5rem; font-size:1.35rem; overflow-wrap:anywhere; }
    .encore-table th button { border:0; padding:0; background:none; font:inherit; color:inherit; text-transform:uppercase; cursor:pointer; }
    .encore-table th[aria-sort="ascending"] button::after { content:' ↑'; }
    .encore-table th[aria-sort="descending"] button::after { content:' ↓'; }
    [hidden] { display:none !important; }
    @media(max-width:600px) {
      .encore-contact-fields { grid-template-columns:1fr; }
      .encore-activity { grid-template-columns:78px 50px minmax(0,1fr) auto; gap:.35rem; }
      .encore-task-row { grid-template-columns:78px minmax(0,1fr) auto; gap:.35rem; }
      .encore-task-row > .encore-muted { display:none; }
      .encore-activity time, .encore-task-row time { font-size:.65rem; }
      .encore-dialog-inner { padding:.8rem; }
    }
    .encore-followup-group { margin:.8rem 0 .35rem; color:#f4d35e; font-size:.75rem; letter-spacing:.08em; text-transform:uppercase; }
    .encore-status-badge { display:inline-flex; border-radius:999px; padding:.25rem .55rem; font-size:.72rem; font-weight:700; }
    .encore-status-active { background:rgba(101,213,138,.2); color:#a4f0bd; }
    .encore-status-inactive { background:rgba(244,211,94,.18); color:#ffe28a; }
    .encore-status-no_response { background:rgba(255,100,100,.18); color:#ffb0b0; }
    .encore-edit-button { font-size:.72rem; padding:.32rem .55rem; }
    .encore-directory { margin-top:1rem; }
    .encore-table-wrap { max-width:100%; overflow:auto; border:1px solid rgba(255,255,255,.08); border-radius:16px; }
    .encore-table { width:100%; min-width:820px; border-collapse:collapse; }
    .encore-table th, .encore-table td { padding:.72rem .8rem; border-bottom:1px solid rgba(255,255,255,.07); text-align:left; vertical-align:top; }
    .encore-table th { color:#f4d35e; font-size:.76rem; letter-spacing:.08em; text-transform:uppercase; }
    .encore-table tr:last-child td { border-bottom:0; }
    .encore-form-grid { display:grid; gap:.75rem; }
    .encore-form-grid label { display:grid; gap:.35rem; color:rgba(255,255,255,.78); font-size:.84rem; font-weight:600; }
    .encore-form-grid input, .encore-form-grid select, .encore-form-grid textarea { width:100%; box-sizing:border-box; padding:.68rem .75rem; border:1px solid rgba(255,255,255,.12); border-radius:12px; background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .encore-form-grid select option { background:#151323; color:#fff; }
    .encore-row-actions { display:flex; align-items:center; gap:.4rem; }
    .encore-row-actions .btn { padding:.35rem .55rem; font-size:.75rem; }
    .encore-row-actions form { margin:0; }
    .btn-danger { border:1px solid rgba(255,110,110,.35); background:rgba(255,90,90,.12); color:#ffb0b0; }
    .encore-dialog { width:min(620px,calc(100vw - 2rem)); border:1px solid rgba(255,255,255,.14); border-radius:20px; padding:0; background:#151323; color:#fff; box-shadow:0 24px 70px rgba(0,0,0,.55); }
    .encore-dialog::backdrop { background:rgba(0,0,0,.65); backdrop-filter:blur(4px); }
    .encore-dialog-inner { padding:1.25rem; }
    .encore-dialog-close { float:right; border:1px solid rgba(255,255,255,.14); border-radius:999px; width:34px; height:34px; background:rgba(255,255,255,.05); color:#fff; cursor:pointer; }
    .encore-stage-form { margin-top:.55rem; }
    .encore-stage-form select { width:100%; padding:.38rem .45rem; border-radius:9px; border:1px solid rgba(255,255,255,.12); background:#24232c; color:#fff; font:inherit; font-size:.75rem; }
    .encore-directory-search { width:100%; box-sizing:border-box; margin:0 0 .75rem; padding:.72rem .8rem; border:1px solid rgba(255,255,255,.12); border-radius:12px; background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .encore-sortable { cursor:pointer; user-select:none; }
    .encore-sortable:hover { color:#fff; background:rgba(212,175,55,.08); }
    .encore-contact { display:grid; gap:.12rem; }
    .encore-contact strong { color:#fff; }
    .encore-contact span { color:rgba(255,255,255,.62); font-size:.82rem; }
    .encore-alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .encore-alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .encore-alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 1100px) { .encore-summary { grid-template-columns:repeat(2, minmax(0, 1fr)); } .encore-layout { grid-template-columns:1fr; } }
    @media (max-width: 680px) { .encore-hero, .encore-section-head { display:grid; } .encore-summary { grid-template-columns:1fr; } .encore-actions .btn { width:100%; justify-content:center; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header.php'; ?>
<?php }

function encore_page_foot(): void { ?>
<?php include __DIR__ . '/../../includes/tools_footer.php'; ?>
</body>
</html>
<?php }

function encore_flash(array $messages, array $errors): void {
    foreach ($messages as $message) echo '<div class="encore-alert encore-alert-success">' . e($message) . '</div>';
    foreach ($errors as $error) echo '<div class="encore-alert encore-alert-error">' . e($error) . '</div>';
}
