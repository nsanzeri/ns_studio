<?php
if (!function_exists('rss_header_widget_context')) {
    function rss_header_widget_context(): array
    {
        $currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
        $isLocal = str_contains($currentPath, '/ns_studio/');
        $siteBase = $isLocal ? '/ns_studio' : '';
        return [
            'current_path' => $currentPath,
            'site_base' => $siteBase,
            'studio_base' => $siteBase . '/studio',
        ];
    }
}

if (!function_exists('rss_header_is_active_path')) {
    function rss_header_is_active_path(string $currentPath, string $needle): bool
    {
        return str_contains($currentPath, $needle);
    }
}

if (!function_exists('rss_render_suite_menu')) {
    function rss_render_suite_menu(string $idPrefix = 'rss'): void
    {
        $ctx = rss_header_widget_context();
        $currentPath = $ctx['current_path'];
        $studioBase = $ctx['studio_base'];
        $modules = [
            ['label' => 'Calendar', 'meta' => 'Availability tools', 'href' => $studioBase . '/tools/index.php', 'active' => rss_header_is_active_path($currentPath, '/tools/'), 'soon' => false],
            ['label' => 'SetMaxx', 'meta' => 'Songs and requests', 'href' => $studioBase . '/setmaxx/index.php', 'active' => rss_header_is_active_path($currentPath, '/setmaxx/'), 'soon' => false],
            ['label' => 'Finance', 'meta' => 'Gig income tracking', 'href' => $studioBase . '/finance/index.php', 'active' => rss_header_is_active_path($currentPath, '/finance/'), 'soon' => false],
            ['label' => 'Publish', 'meta' => 'Promo copy writer', 'href' => $studioBase . '/publishing/index.php', 'active' => rss_header_is_active_path($currentPath, '/publishing/'), 'soon' => false],
            ['label' => 'Directory', 'meta' => 'Public artist discovery', 'href' => $ctx['site_base'] . '/directory.php', 'active' => basename($currentPath) === 'directory.php', 'soon' => false],
        ];
        $buttonId = $idPrefix . 'SuiteMenuToggle';
        $panelId = $idPrefix . 'SuiteMenuPanel';
        ?>
        <div class="rss-suite-menu">
            <button class="rss-suite-toggle" id="<?= htmlspecialchars($buttonId) ?>" type="button" aria-label="Open Ready Set Shows modules" aria-expanded="false" aria-controls="<?= htmlspecialchars($panelId) ?>">
                <span>Ready Set Shows</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="rss-suite-panel" id="<?= htmlspecialchars($panelId) ?>" hidden>
                <?php foreach ($modules as $module): ?>
                    <a href="<?= htmlspecialchars($module['href']) ?>" class="<?= $module['active'] ? 'active' : '' ?>">
                        <span>
                            <strong><?= htmlspecialchars($module['label']) ?></strong>
                            <small><?= htmlspecialchars($module['meta']) ?></small>
                        </span>
                        <?php if ($module['soon']): ?><em>Soon</em><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('rss_render_account_menu')) {
    function rss_render_account_menu(string $idPrefix = 'rss'): void
    {
        global $pdo;
        $ctx = rss_header_widget_context();
        $currentPath = $ctx['current_path'];
        $studioBase = $ctx['studio_base'];
        $isLoggedIn = class_exists('Auth') && Auth::isLoggedIn();
        $isDirectoryGuest = !$isLoggedIn && basename($currentPath) === 'directory.php';
        $currentUser = ($isLoggedIn && isset($pdo)) ? Auth::currentUser($pdo) : null;
        $accountLabel = $isLoggedIn ? trim((string)($currentUser['display_name'] ?? $currentUser['email'] ?? 'Account')) : 'Account';
        $accountInitial = strtoupper(substr($accountLabel !== '' ? $accountLabel : 'A', 0, 1));
        $modules = [
            ['label' => 'Calendar', 'href' => $studioBase . '/tools/index.php', 'active' => rss_header_is_active_path($currentPath, '/tools/'), 'soon' => false],
            ['label' => 'SetMaxx', 'href' => $studioBase . '/setmaxx/index.php', 'active' => rss_header_is_active_path($currentPath, '/setmaxx/'), 'soon' => false],
            ['label' => 'Finance', 'href' => $studioBase . '/finance/index.php', 'active' => rss_header_is_active_path($currentPath, '/finance/'), 'soon' => false],
            ['label' => 'Publish', 'href' => $studioBase . '/publishing/index.php', 'active' => rss_header_is_active_path($currentPath, '/publishing/'), 'soon' => false],
            ['label' => 'Directory', 'href' => $ctx['site_base'] . '/directory.php', 'active' => basename($currentPath) === 'directory.php', 'soon' => false],
        ];
        $buttonId = $idPrefix . 'AccountMenuToggle';
        $panelId = $idPrefix . 'AccountMenuPanel';
        ?>
        <div class="account-menu">
            <button class="account-menu-toggle" id="<?= htmlspecialchars($buttonId) ?>" type="button" aria-label="<?= htmlspecialchars($isLoggedIn ? 'Open account menu' : 'Open login menu') ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($panelId) ?>">
                <span class="account-menu-icon"><?= htmlspecialchars($isLoggedIn ? $accountInitial : '') ?></span>
            </button>
            <div class="account-menu-panel" id="<?= htmlspecialchars($panelId) ?>" hidden>
                <?php if ($isLoggedIn): ?>
                    <div class="account-menu-name"><?= htmlspecialchars($accountLabel) ?></div>
                    <a href="<?= htmlspecialchars($studioBase . '/member/library.php') ?>">My Products</a>
                    <div class="account-suite-group">
                        <div class="account-suite-title">Ready Set Shows</div>
                        <?php foreach ($modules as $module): ?>
                            <a href="<?= htmlspecialchars($module['href']) ?>" class="account-suite-link <?= $module['active'] ? 'active' : '' ?>">
                                <span><?= htmlspecialchars($module['label']) ?></span>
                                <?php if ($module['soon']): ?><em>Soon</em><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?= htmlspecialchars($studioBase . '/member/settings.php') ?>">Settings</a>
                    <a href="<?= htmlspecialchars($studioBase . '/member/logout.php') ?>">Log out</a>
                <?php else: ?>
                    <?php if ($isDirectoryGuest): ?>
                        <div class="account-suite-group">
                            <div class="account-suite-title">Artist tools</div>
                            <a href="<?= htmlspecialchars($studioBase . '/member/pricing.php') ?>" class="account-suite-link">
                                <span>Are you an artist? Start free</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="account-suite-group">
                            <div class="account-suite-title">Ready Set Shows</div>
                            <?php foreach ($modules as $module): ?>
                                <a href="<?= htmlspecialchars($module['href']) ?>" class="account-suite-link <?= $module['active'] ? 'active' : '' ?>">
                                    <span><?= htmlspecialchars($module['label']) ?></span>
                                    <?php if ($module['soon']): ?><em>Soon</em><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($studioBase . '/member/login.php') ?>">Log in</a>
                    <a href="<?= htmlspecialchars($studioBase . '/member/register.php') ?>">Create account</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('rss_render_header_widget_script')) {
    function rss_render_header_widget_script(string $idPrefix = 'rss'): void
    {
        $suiteButton = $idPrefix . 'SuiteMenuToggle';
        $suitePanel = $idPrefix . 'SuiteMenuPanel';
        $accountButton = $idPrefix . 'AccountMenuToggle';
        $accountPanel = $idPrefix . 'AccountMenuPanel';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            function wireMenu(buttonId, panelId) {
                const button = document.getElementById(buttonId);
                const panel = document.getElementById(panelId);
                if (!button || !panel) return;
                function close() {
                    panel.hidden = true;
                    button.setAttribute('aria-expanded', 'false');
                }
                button.addEventListener('click', function () {
                    const willOpen = panel.hidden;
                    panel.hidden = !willOpen;
                    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                });
                document.addEventListener('click', function (event) {
                    if (!panel.hidden && !panel.contains(event.target) && !button.contains(event.target)) close();
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') close();
                });
            }
            wireMenu(<?= json_encode($suiteButton) ?>, <?= json_encode($suitePanel) ?>);
            wireMenu(<?= json_encode($accountButton) ?>, <?= json_encode($accountPanel) ?>);
        });
        </script>
        <?php
    }
}
