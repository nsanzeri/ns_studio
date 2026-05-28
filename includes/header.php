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

$isLoggedIn = class_exists('Auth') && Auth::isLoggedIn();
$currentUser = ($isLoggedIn && isset($pdo)) ? Auth::currentUser($pdo) : null;
$accountLabel = $isLoggedIn ? trim((string)($currentUser['display_name'] ?? $currentUser['email'] ?? 'Account')) : 'Account';
$accountInitial = strtoupper(substr($accountLabel !== '' ? $accountLabel : 'A', 0, 1));

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
                <li><a href="<?= htmlspecialchars($siteBase . '/requests.php') ?>" class="<?= nav_active($currentPage === 'requests.php') ?>">Requests</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/media.php') ?>" class="<?= nav_active($currentPage === 'media.php') ?>">Video</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/testimonials.php') ?>" class="<?= nav_active($currentPage === 'testimonials.php') ?>">Reviews</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/booking.php') ?>" class="<?= nav_active($currentPage === 'booking.php') ?>">Book</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/shop/') ?>" class="<?= nav_active($isShop) ?>">Shop</a></li>
            </ul>
        </nav>

        <div class="account-menu">
            <button class="account-menu-toggle" id="accountMenuToggle" type="button" aria-label="<?= htmlspecialchars($isLoggedIn ? 'Open account menu' : 'Open login menu') ?>" aria-expanded="false" aria-controls="accountMenuPanel">
                <span class="account-menu-icon"><?= htmlspecialchars($isLoggedIn ? $accountInitial : '') ?></span>
            </button>
            <div class="account-menu-panel" id="accountMenuPanel" hidden>
                <?php if ($isLoggedIn): ?>
                    <div class="account-menu-name"><?= htmlspecialchars($accountLabel) ?></div>
                    <a href="<?= htmlspecialchars($studioBase . '/member/library.php') ?>">My Products</a>
                    <a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>">Tools</a>
                    <a href="<?= htmlspecialchars($studioBase . '/member/settings.php') ?>">Settings</a>
                    <a href="<?= htmlspecialchars($studioBase . '/member/logout.php') ?>">Log out</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($studioBase . '/member/login.php') ?>">Log in</a>
                    <a href="<?= htmlspecialchars($studioBase . '/member/register.php') ?>">Create account</a>
                <?php endif; ?>
            </div>
        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const accountToggle = document.getElementById('accountMenuToggle');
    const accountPanel = document.getElementById('accountMenuPanel');
    if (!accountToggle || !accountPanel) return;

    function closeAccountMenu() {
        accountPanel.hidden = true;
        accountToggle.setAttribute('aria-expanded', 'false');
    }

    accountToggle.addEventListener('click', function () {
        const willOpen = accountPanel.hidden;
        accountPanel.hidden = !willOpen;
        accountToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
        if (!accountPanel.hidden && !accountPanel.contains(event.target) && !accountToggle.contains(event.target)) {
            closeAccountMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAccountMenu();
    });
});
</script>
