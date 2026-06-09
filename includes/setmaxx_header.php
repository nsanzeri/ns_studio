<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentPage = basename($currentPath);

$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';

require_once __DIR__ . '/rss_header_widgets.php';

$trialStatus = null;
$headerPdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : ($GLOBALS['pdo'] ?? null);
if ($headerPdo instanceof PDO && function_exists('rss_get_current_user_trial_status')) {
    $trialStatus = rss_get_current_user_trial_status($headerPdo);
}

if (!function_exists('nav_active')) {
    function nav_active(bool $condition): string
    {
        return $condition ? 'active' : '';
    }
}

$suiteModules = [
    ['label' => 'Calendar', 'href' => $studioBase . '/tools/index.php', 'active' => str_contains($currentPath, '/tools/'), 'soon' => false],
    ['label' => 'SetMaxx', 'href' => $studioBase . '/setmaxx/index.php', 'active' => str_contains($currentPath, '/setmaxx/'), 'soon' => false],
    ['label' => 'Finance', 'href' => $studioBase . '/shop/#business-tracking', 'active' => false, 'soon' => true],
    ['label' => 'Publishing', 'href' => $studioBase . '/shop/#publishing-tools', 'active' => false, 'soon' => true],
];
?>
<header class="site-header setmaxx-site-header">
    <div class="container setmaxx-header-inner">
        <a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>" class="brand setmaxx-brand">
            <span class="brand-mark">RS</span>
            <span class="brand-text">
                <span class="brand-name">Ready Set Shows</span>
                <span class="brand-tagline">Calendar &middot; SetMaxx &middot; Finance Soon &middot; Publishing Soon</span>
            </span>
        </a>

        <button
            class="nav-toggle setmaxx-nav-toggle"
            id="setmaxxNavToggle"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="setmaxxMobileNav"
        >
            <span></span><span></span><span></span>
        </button>

        <nav class="rss-suite-nav" aria-label="Ready Set Shows modules">
            <?php foreach ($suiteModules as $module): ?>
                <a href="<?= htmlspecialchars($module['href']) ?>" class="<?= nav_active($module['active']) ?>">
                    <?= htmlspecialchars($module['label']) ?>
                    <?php if ($module['soon']): ?><span>Soon</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="setmaxx-header-actions">
            <?php rss_render_account_menu('setmaxx'); ?>
        </div>
    </div>

    <div class="setmaxx-subheader">
      <div class="container setmaxx-subheader-inner">
        <span class="setmaxx-subheader-label">SetMaxx</span>
        <nav class="setmaxx-desktop-nav" aria-label="SetMaxx navigation">
            <ul>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && str_contains($currentPath, '/setmaxx/')) ?>">Dashboard</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/songs.php') ?>" class="<?= nav_active($currentPage === 'songs.php') ?>">Songs</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/setlists.php') ?>" class="<?= nav_active($currentPage === 'setlists.php') ?>">Setlists</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/sessions.php') ?>" class="<?= nav_active($currentPage === 'sessions.php') ?>">Sessions</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/requests.php') ?>" class="<?= nav_active($currentPage === 'requests.php') ?>">Requests</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/payments.php') ?>" class="<?= nav_active($currentPage === 'payments.php') ?>">Payments</a></li>
            </ul>
        </nav>
      </div>
    </div>

    <nav class="setmaxx-mobile-nav" id="setmaxxMobileNav" aria-label="Mobile Set Maxx navigation" hidden>
        <div class="setmaxx-mobile-nav-inner">
            <div class="setmaxx-mobile-section">Ready Set Shows</div>
            <?php foreach ($suiteModules as $module): ?>
                <a href="<?= htmlspecialchars($module['href']) ?>" class="<?= nav_active($module['active']) ?>">
                    <?= htmlspecialchars($module['label']) ?><?= $module['soon'] ? ' (Soon)' : '' ?>
                </a>
            <?php endforeach; ?>
            <div class="setmaxx-mobile-section">SetMaxx</div>
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && str_contains($currentPath, '/setmaxx/')) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/songs.php') ?>" class="<?= nav_active($currentPage === 'songs.php') ?>">Songs</a>
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/setlists.php') ?>" class="<?= nav_active($currentPage === 'setlists.php') ?>">Setlists</a>
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/sessions.php') ?>" class="<?= nav_active($currentPage === 'sessions.php') ?>">Sessions</a>
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/requests.php') ?>" class="<?= nav_active($currentPage === 'requests.php') ?>">Requests</a>
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/payments.php') ?>" class="<?= nav_active($currentPage === 'payments.php') ?>">Payments</a>
            <div class="setmaxx-mobile-extra">
                <a href="<?= htmlspecialchars($siteBase . '/index.php') ?>">Back to Main Site</a>
            </div>
        </div>
    </nav>
</header>

<?php if ($trialStatus): ?>
<div class="trial-countdown-banner">
    <div class="container trial-countdown-banner__inner">
        <span><strong>Free trial:</strong> your access ends in <?= (int)$trialStatus['days_remaining'] ?> day<?= ((int)$trialStatus['days_remaining'] === 1 ? '' : 's') ?>.</span>
        <span class="trial-countdown-banner__meta">Ends <?= htmlspecialchars($trialStatus['expires_on'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</div>
<?php endif; ?>

<?php rss_render_header_widget_script('setmaxx'); ?>

<style>
  .setmaxx-site-header {
    border-bottom: 1px solid rgba(255,255,255,.08);
    background: linear-gradient(180deg, rgba(16,10,28,.98), rgba(10,10,22,.96));
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(10px);
  }
  .setmaxx-header-inner {
    min-height: 76px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
  }
  .setmaxx-brand .brand-mark {
    background: linear-gradient(135deg, #d4af37, #b68a2f);
    color:#111;
    box-shadow: 0 8px 24px rgba(212,175,55,.25);
  }
  .rss-suite-nav {
    display:flex;
    align-items:center;
    gap:.45rem;
    margin-left:auto;
  }
  .rss-suite-nav a {
    display:inline-flex;
    align-items:center;
    gap:.45rem;
    border-radius:999px;
    padding:.72rem 1rem;
    text-decoration:none;
    color:rgba(255,255,255,.86);
    font-weight:600;
  }
  .rss-suite-nav a.active,
  .rss-suite-nav a:hover {
    background:rgba(140,107,255,.16);
    color:#efe7ff;
  }
  .rss-suite-nav span {
    padding:.14rem .42rem;
    border-radius:999px;
    background:rgba(140,107,255,.18);
    color:#efe7ff;
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .setmaxx-subheader {
    border-top:1px solid rgba(255,255,255,.055);
    border-bottom:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.025);
  }
  .setmaxx-subheader-inner {
    min-height:52px;
    display:flex;
    align-items:center;
    gap:1rem;
  }
  .setmaxx-subheader-label {
    color:#efe7ff;
    font-weight:700;
    letter-spacing:.04em;
    text-transform:uppercase;
    font-size:.82rem;
  }
  .setmaxx-desktop-nav ul {
    display:flex;
    align-items:center;
    gap:.5rem;
    list-style:none;
    margin:0;
    padding:0;
  }
  .setmaxx-desktop-nav a {
    display:inline-block;
    border-radius:999px;
    padding:.72rem 1rem;
    text-decoration:none;
    color:rgba(255,255,255,.86);
  }
  .setmaxx-desktop-nav a.active,
  .setmaxx-desktop-nav a:hover {
    background: rgba(140,107,255,.16);
    color:#efe7ff;
  }
  .setmaxx-header-actions {
    display:flex;
    align-items:center;
    gap:.55rem;
  }
  .setmaxx-nav-toggle,
  .setmaxx-mobile-nav {
    display:none;
  }
  .setmaxx-mobile-extra {
    margin-top:1rem;
    padding-top:1rem;
    border-top:1px solid rgba(255,255,255,.08);
  }
  .setmaxx-mobile-extra a {
    display:block;
    padding:.9rem 1rem;
    border-radius:12px;
    background:rgba(255,255,255,.05);
    text-decoration:none;
    color:rgba(255,255,255,.86);
  }
  @media (max-width: 980px) {
    .setmaxx-desktop-nav,
    .rss-suite-nav,
    .setmaxx-subheader { display:none !important; }
    .setmaxx-header-actions { margin-left:auto; }
    .setmaxx-nav-toggle { display:inline-flex !important; align-items:center; justify-content:center; }
    .setmaxx-mobile-nav { position:absolute; top:100%; left:1rem; right:1rem; z-index:1001; display:block; }
    .setmaxx-mobile-nav[hidden] { display:none !important; }
    .setmaxx-mobile-nav:not([hidden]) { display:block !important; }
    .setmaxx-mobile-nav-inner {
      margin-top:10px;
      padding:1rem;
      border-radius:18px;
      background: rgba(14,11,30,.98);
      border:1px solid rgba(255,255,255,.08);
      box-shadow: 0 20px 50px rgba(0,0,0,.35);
    }
    .setmaxx-mobile-nav a {
      display:block;
      width:100%;
      padding:.95rem 1rem;
      border-radius:14px;
      text-decoration:none;
      color:rgba(255,255,255,.86);
    }
    .setmaxx-mobile-nav a.active,
    .setmaxx-mobile-nav a:hover {
      background: rgba(140,107,255,.16);
      color:#efe7ff;
    }
    .setmaxx-mobile-section {
      margin:.35rem 0 .25rem;
      padding:.65rem 1rem .35rem;
      color:#efe7ff;
      font-size:.78rem;
      font-weight:700;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
  }
</style>
