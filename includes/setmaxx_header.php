<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentPage = basename($currentPath);

$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';

if (!function_exists('nav_active')) {
    function nav_active(bool $condition): string
    {
        return $condition ? 'active' : '';
    }
}
?>
<header class="site-header setmaxx-site-header">
    <div class="container setmaxx-header-inner">
        <a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php') ?>" class="brand setmaxx-brand">
            <span class="brand-mark">SM</span>
            <span class="brand-text">
                <span class="brand-name">Set Maxx</span>
                <span class="brand-tagline">Song lists · requests · live control</span>
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

        <nav class="setmaxx-desktop-nav" aria-label="Set Maxx navigation">
            <ul>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && str_contains($currentPath, '/setmaxx/')) ?>">Dashboard</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/member/library.php') ?>" class="<?= nav_active($currentPage === 'library.php') ?>">My Products</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>">Calendar Tools</a></li>
            </ul>
        </nav>
    </div>

    <nav class="setmaxx-mobile-nav" id="setmaxxMobileNav" aria-label="Mobile Set Maxx navigation" hidden>
        <div class="setmaxx-mobile-nav-inner">
            <a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && str_contains($currentPath, '/setmaxx/')) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($studioBase . '/member/library.php') ?>" class="<?= nav_active($currentPage === 'library.php') ?>">My Products</a>
            <a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>">Calendar Tools</a>
            <div class="setmaxx-mobile-extra">
                <a href="<?= htmlspecialchars($siteBase . '/index.php') ?>">← Back to Main Site</a>
            </div>
        </div>
    </nav>
</header>

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
    min-height: 84px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
  }
  .setmaxx-brand .brand-mark {
    background: linear-gradient(135deg, #8c6bff, #db4fff);
    color:#fff;
    box-shadow: 0 8px 24px rgba(140,107,255,.28);
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
    .setmaxx-desktop-nav { display:none !important; }
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
  }
</style>
