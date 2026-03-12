<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentPage = basename($currentPath);
$isShop = str_contains($currentPath, '/studio/shop/');

/*
 |--------------------------------------------------------------------------
 | URL bases
 |--------------------------------------------------------------------------
 |
 | Local:
 |   /ns_studio/index.php
 |   /ns_studio/studio/shop/
 |
 | Production:
 |   /index.php
 |   /studio/shop/
 |
 */
$isLocal = str_contains($currentPath, '/ns_studio/');

$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';

function nav_active(bool $condition): string
{
	return $condition ? 'active' : '';
}
?>

<header class="site-header">
    <div class="container header-inner">
        <a href="<?= htmlspecialchars($siteBase . '/index.php') ?>" class="brand">
            <span class="brand-mark">NS</span>
            <span class="brand-text">
                <span class="brand-name">Nick Sanzeri</span>
                <span class="brand-tagline">One Man · Full-Band Experience</span>
            </span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <ul>
                <li><a href="<?= htmlspecialchars($siteBase . '/index.php') ?>" class="<?= nav_active($currentPage === 'index.php' && !$isShop) ?>">Home</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/shows.php') ?>" class="<?= nav_active($currentPage === 'shows.php') ?>">Shows</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/media.php') ?>" class="<?= nav_active($currentPage === 'media.php') ?>">Media</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/about.php') ?>" class="<?= nav_active($currentPage === 'about.php') ?>">About</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/testimonials.php') ?>" class="<?= nav_active($currentPage === 'testimonials.php') ?>">Testimonials</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/booking.php') ?>" class="<?= nav_active($currentPage === 'booking.php') ?>">Booking</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/shop/') ?>" class="<?= nav_active($isShop) ?>">Shop</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/contact.php') ?>" class="<?= nav_active($currentPage === 'contact.php') ?>">Contact</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/payments.php') ?>" class="<?= nav_active($currentPage === 'payments.php') ?>">Payments</a></li>
            </ul>
        </nav>
    </div>
</header>