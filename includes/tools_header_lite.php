<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');

$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
$rssHomeUrl = $siteBase . '/index.php' . ($isLocal ? '?rss_preview=1' : '');

$trialStatus = null;
if (isset($pdo) && function_exists('rss_get_current_user_trial_status')) {
	$trialStatus = rss_get_current_user_trial_status($pdo);
}
?>

<header class="site-header tools-site-header-lite">
    <div class="container tools-header-inner-lite">

        <!-- Brand -->
        <a href="<?= htmlspecialchars($rssHomeUrl, ENT_QUOTES, 'UTF-8') ?>" class="brand tools-brand-lite">
            <span class="brand-mark">RS</span>
            <span class="brand-text">
                <span class="brand-name">Ready Set Shows</span>
                <span class="brand-tagline">Live requests, setlists, and gig tools</span>
            </span>
        </a>

        <!-- CTA -->
        <div class="tools-header-cta">
            <?php if (!isset($user) || !$user): ?>
                <a class="btn btn-outline" href="<?= e($loginUrl ?? '#') ?>">Log In</a>
                <a class="btn btn-primary" href="<?= e($trialUrl ?? '#') ?>">Start Free Trial</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e($trialUrl ?? '#') ?>">Start Free Trial</a>
            <?php endif; ?>
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

<style>
.tools-site-header-lite {
    border-bottom: 1px solid rgba(255,255,255,.08);
    background: linear-gradient(180deg, rgba(5,8,22,.98), rgba(8,10,28,.96));
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.tools-header-inner-lite {
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.tools-brand-lite .brand-mark {
    background: linear-gradient(135deg, #d4af37, #b68a2f);
    color: #111;
    box-shadow: 0 8px 24px rgba(212,175,55,.25);
}

.tools-brand-lite .brand-name {
    letter-spacing: .04em;
    font-weight: 600;
}

.tools-brand-lite .brand-tagline {
    font-size: .78rem;
    opacity: .75;
}

.tools-header-cta {
    display: flex;
    align-items: center;
    gap: .5rem;
}

.tools-header-cta .btn {
    padding: .55rem .9rem;
    font-size: .85rem;
}

/* Mobile tightening */
@media (max-width: 640px) {
    .tools-brand-lite .brand-tagline {
        display: none;
    }

    .tools-header-cta .btn {
        padding: .5rem .7rem;
        font-size: .8rem;
    }
}
</style>
