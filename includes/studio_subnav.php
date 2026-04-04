<?php
if (!isset($currentPath)) {
	$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
}

$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
$isMember   = str_contains($currentPath, '/studio/member/');
$isShopArea = str_contains($currentPath, '/studio/shop/') || $isMember;

if (!$isShopArea) {
	return;
}

$esc = function (string $value): string {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};
?>
<nav class="studio-subnav" aria-label="Studio navigation">
  <div class="container" style="display:flex; gap:1rem; flex-wrap:wrap; padding:0.9rem 0 0;">
    <a class="text-link" href="<?= $esc($studioBase . '/shop/') ?>">Shop</a>
    <?php if (class_exists('Auth') && Auth::isLoggedIn()): ?>
      <a class="text-link" href="<?= $esc($studioBase . '/member/library.php') ?>">Library</a>
      <a class="text-link" href="<?= $esc($studioBase . '/member/settings.php') ?>">Settings</a>
      <a class="text-link" href="<?= $esc($studioBase . '/member/logout.php') ?>">Log out</a>
    <?php else: ?>
      <a class="text-link" href="<?= $esc($studioBase . '/member/login.php') ?>">Log in</a>
      <a class="text-link" href="<?= $esc($studioBase . '/member/register.php') ?>">Register</a>
    <?php endif; ?>
  </div>
</nav>