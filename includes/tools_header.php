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
	['label' => 'Finance', 'href' => $studioBase . '/finance/index.php', 'active' => str_contains($currentPath, '/finance/'), 'soon' => false],
	['label' => 'Publishing', 'href' => $studioBase . '/publishing/index.php', 'active' => str_contains($currentPath, '/publishing/'), 'soon' => false],
];
$showCalendarSubnav = str_contains($currentPath, '/tools/');
?>

<header class="site-header tools-site-header">
    <div class="container header-inner tools-header-inner">
        <a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>" class="brand tools-brand">
            <span class="brand-mark">RS</span>
            <span class="brand-text">
                <span class="brand-name">Ready Set Shows</span>
                <span class="brand-tagline">Calendar &middot; SetMaxx &middot; Finance &middot; Publishing</span>
            </span>
        </a>

        <button
            class="nav-toggle tools-nav-toggle"
            id="toolsNavToggle"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="toolsMobileNav"
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
        <div class="tools-header-actions">
            <?php rss_render_account_menu('tools'); ?>
        </div>
    </div>

    <?php if ($showCalendarSubnav): ?>
    <div class="tools-subheader">
      <div class="container tools-subheader-inner">
        <span class="tools-subheader-label">Calendar</span>
        <nav class="tools-desktop-nav" aria-label="Calendar tools navigation">
            <ul>
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && str_contains($currentPath, '/tools/')) ?>">Availability</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/pretty-print.php') ?>" class="<?= nav_active($currentPage === 'pretty-print.php') ?>">Print</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/bandsintown.php') ?>" class="<?= nav_active($currentPage === 'bandsintown.php') ?>">Export</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/calendars.php') ?>" class="<?= nav_active($currentPage === 'calendars.php') ?>">Calendars</a></li>
            </ul>
        </nav>
      </div>
    </div>
    <?php endif; ?>

    <nav class="tools-mobile-nav" id="toolsMobileNav" aria-label="Mobile tools navigation" hidden>
        <div class="tools-mobile-nav-inner">
            <div class="tools-mobile-section">Ready Set Shows</div>
            <?php foreach ($suiteModules as $module): ?>
                <a href="<?= htmlspecialchars($module['href']) ?>" class="<?= nav_active($module['active']) ?>">
                    <?= htmlspecialchars($module['label']) ?><?= $module['soon'] ? ' (Soon)' : '' ?>
                </a>
            <?php endforeach; ?>
            <?php if ($showCalendarSubnav): ?>
                <div class="tools-mobile-section">Calendar</div>
                <a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && str_contains($currentPath, '/tools/')) ?>">Availability</a>
                <a href="<?= htmlspecialchars($studioBase . '/tools/pretty-print.php') ?>" class="<?= nav_active($currentPage === 'pretty-print.php') ?>">Print</a>
                <a href="<?= htmlspecialchars($studioBase . '/tools/bandsintown.php') ?>" class="<?= nav_active($currentPage === 'bandsintown.php') ?>">Export</a>
                <a href="<?= htmlspecialchars($studioBase . '/tools/calendars.php') ?>" class="<?= nav_active($currentPage === 'calendars.php') ?>">Calendars</a>
            <?php endif; ?>

            <div class="tools-mobile-extra">
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
<?php rss_render_header_widget_script('tools'); ?>

<style>
  .tools-site-header {
    border-bottom: 1px solid rgba(255,255,255,.08);
    background: linear-gradient(180deg, rgba(5,8,22,.98), rgba(8,10,28,.96));
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(10px);
  }

  .tools-header-inner {
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }

  .tools-brand .brand-mark {
    background: linear-gradient(135deg, #d4af37, #b68a2f);
    color: #111;
    box-shadow: 0 8px 24px rgba(212,175,55,.25);
  }

  .tools-brand .brand-name {
    letter-spacing: .04em;
  }

  .tools-brand .brand-tagline {
    opacity: .82;
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
    background:rgba(212,175,55,.16);
    color:#f4d57a;
  }

  .rss-suite-nav span {
    padding:.14rem .42rem;
    border-radius:999px;
    background:rgba(212,175,55,.16);
    color:#f4d57a;
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.04em;
  }

  .tools-subheader {
    border-top:1px solid rgba(255,255,255,.055);
    border-bottom:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.025);
  }

  .tools-subheader-inner {
    min-height:52px;
    display:flex;
    align-items:center;
    gap:1rem;
  }

  .tools-subheader-label {
    color:#f4d57a;
    font-weight:700;
    letter-spacing:.04em;
    text-transform:uppercase;
    font-size:.82rem;
  }

  .tools-desktop-nav ul {
    display: flex;
    align-items: center;
    gap: .5rem;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .tools-desktop-nav a {
    display: inline-block;
    border-radius: 999px;
    padding: .72rem 1rem;
    text-decoration: none;
    color: rgba(255,255,255,.86);
  }

  .tools-desktop-nav a.active,
  .tools-desktop-nav a:hover {
    background: rgba(212,175,55,.14);
    color: #f4d57a;
  }

  .tools-nav-toggle {
    display: none;
  }

  .tools-header-actions {
    display:flex;
    align-items:center;
    gap:.55rem;
  }

  .tools-mobile-nav {
    display: none;
  }

  .tools-mobile-extra {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255,255,255,.08);
  }

  .tools-mobile-extra a {
    display: block;
    padding: .9rem 1rem;
    border-radius: 12px;
    background: rgba(255,255,255,.05);
    text-decoration: none;
    color: rgba(255,255,255,.86);
  }

  @media (max-width: 980px) {
    .tools-header-inner {
      min-height: 76px;
    }

    .tools-desktop-nav,
    .rss-suite-nav,
    .tools-subheader {
      display: none !important;
    }

    .tools-nav-toggle {
      display: inline-flex !important;
      align-items: center;
      justify-content: center;
    }

    .tools-header-actions {
      margin-left:auto;
    }

    .tools-mobile-nav {
      position: absolute;
      top: 100%;
      left: 1rem;
      right: 1rem;
      z-index: 1001;
      display: block;
    }

    .tools-mobile-nav[hidden] {
      display: none !important;
    }

    .tools-mobile-nav:not([hidden]) {
      display: block !important;
    }

    .tools-mobile-nav-inner {
      margin-top: 10px;
      padding: 1rem;
      border-radius: 18px;
      background: rgba(10,12,28,.98);
      border: 1px solid rgba(255,255,255,.08);
      box-shadow: 0 20px 50px rgba(0,0,0,.35);
    }

    .tools-mobile-nav a {
      display: block;
      width: 100%;
      padding: .95rem 1rem;
      border-radius: 14px;
      text-decoration: none;
      color: rgba(255,255,255,.86);
    }

    .tools-mobile-nav a.active,
    .tools-mobile-nav a:hover {
      background: rgba(212,175,55,.14);
      color: #f4d57a;
    }

    .tools-mobile-section {
      margin:.35rem 0 .25rem;
      padding:.65rem 1rem .35rem;
      color:#f4d57a;
      font-size:.78rem;
      font-weight:700;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
  }
</style>
