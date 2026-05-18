<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentPage = basename($currentPath);

$isShop = str_contains($currentPath, '/studio/shop/') || str_contains($currentPath, '/studio/member/');

if ($isShop) {
	require_once __DIR__ . '/../studio/_private/_core/bootstrap.php';
}

$trialStatus = null;
if ($isShop && isset($pdo) && function_exists('rss_get_current_user_trial_status')) {
	$trialStatus = rss_get_current_user_trial_status($pdo);
}

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

<header class="site-header">
    <div class="container header-inner">
        <a href="<?= htmlspecialchars($siteBase . '/index.php') ?>" class="brand">
            <span class="brand-mark">NS</span>
            <span class="brand-text">
                <span class="brand-name">Nick Sanzeri</span>
                <span class="brand-tagline">One Man · Full-Band Experience</span>
            </span>
        </a>

        <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="mainNav">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <ul>
                <li><a href="<?= htmlspecialchars($siteBase . '/shows.php') ?>" class="<?= nav_active($currentPage === 'shows.php') ?>">Dates</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/media.php') ?>" class="<?= nav_active($currentPage === 'media.php') ?>">Video</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/testimonials.php') ?>" class="<?= nav_active($currentPage === 'testimonials.php') ?>">Reviews</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/booking.php') ?>" class="<?= nav_active($currentPage === 'booking.php') ?>">Book</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/shop/') ?>" class="<?= nav_active($isShop) ?>">Shop</a></li>
            </ul>
        </nav>
    </div>
</header>


<?php if ($trialStatus): ?>
<div class="trial-countdown-banner">
    <div class="container trial-countdown-banner__inner">
        <span><strong>Free trial:</strong> your access ends in <?= (int)$trialStatus['days_remaining'] ?> day<?= ((int)$trialStatus['days_remaining'] === 1 ? '' : 's') ?>.</span>
        <span class="trial-countdown-banner__meta">Ends <?= htmlspecialchars($trialStatus['expires_on'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/studio_subnav.php'; ?>


<script>
(function () {
  function setupMainNavigation() {
    var navToggle = document.getElementById('navToggle');
    var mainNav = document.getElementById('mainNav');

    if (!navToggle || !mainNav || navToggle.dataset.bound === 'true') {
      return;
    }

    navToggle.dataset.bound = 'true';

    navToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('open');
      navToggle.classList.toggle('open', isOpen);
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupMainNavigation);
  } else {
    setupMainNavigation();
  }
})();
</script>
