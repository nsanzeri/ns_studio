<?php
if (!function_exists('rss_pwa_context')) {
    function rss_pwa_context(): array
    {
        $currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
        $siteBase = str_contains($currentPath, '/ns_studio/') ? '/ns_studio' : '';

        return [
            'site_base' => $siteBase,
            'manifest' => $siteBase . '/rss-manifest.php',
            'sw' => $siteBase . '/rss-sw.js',
            'icons' => $siteBase . '/assets/favicons',
        ];
    }
}

if (!function_exists('rss_render_pwa_install_button')) {
    function rss_render_pwa_install_button(string $className = ''): void
    {
        $class = trim('rss-install-button ' . $className);
        ?>
        <button class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" type="button" data-rss-install hidden>Install</button>
        <?php
    }
}

if (!function_exists('rss_render_pwa_script')) {
    function rss_render_pwa_script(): void
    {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;
        $ctx = rss_pwa_context();
        ?>
        <script>
        (function() {
          const appLinks = [
            { rel: 'icon', type: 'image/png', sizes: '32x32', href: '<?= htmlspecialchars($ctx['icons'] . '/rss-favicon-32.png?v=20260826', ENT_QUOTES, 'UTF-8') ?>' },
            { rel: 'icon', type: 'image/png', sizes: '16x16', href: '<?= htmlspecialchars($ctx['icons'] . '/rss-favicon-16.png?v=20260826', ENT_QUOTES, 'UTF-8') ?>' },
            { rel: 'apple-touch-icon', sizes: '180x180', href: '<?= htmlspecialchars($ctx['icons'] . '/rss-favicon-180.png?v=20260826', ENT_QUOTES, 'UTF-8') ?>' },
            { rel: 'manifest', href: '<?= htmlspecialchars($ctx['manifest'] . '?v=20260826', ENT_QUOTES, 'UTF-8') ?>' }
          ];

          appLinks.forEach(function(attrs) {
            if (attrs.rel === 'manifest' && document.querySelector('link[rel="manifest"]')) return;
            const link = document.createElement('link');
            Object.keys(attrs).forEach(function(key) { link.setAttribute(key, attrs[key]); });
            document.head.appendChild(link);
          });

          let deferredInstallPrompt = null;
          const installButtons = Array.from(document.querySelectorAll('[data-rss-install]'));

          function hideInstallButtons() {
            installButtons.forEach(function(button) { button.hidden = true; });
          }

          function showInstallButtons() {
            installButtons.forEach(function(button) { button.hidden = false; });
          }

          if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
              navigator.serviceWorker.register('<?= htmlspecialchars($ctx['sw'], ENT_QUOTES, 'UTF-8') ?>').catch(function() {});
            });
          }

          window.addEventListener('beforeinstallprompt', function(event) {
            event.preventDefault();
            deferredInstallPrompt = event;
            showInstallButtons();
          });

          installButtons.forEach(function(button) {
            button.addEventListener('click', async function() {
              if (!deferredInstallPrompt) return;
              button.disabled = true;
              deferredInstallPrompt.prompt();
              await deferredInstallPrompt.userChoice.catch(function() {});
              deferredInstallPrompt = null;
              button.disabled = false;
              hideInstallButtons();
            });
          });

          window.addEventListener('appinstalled', hideInstallButtons);
        })();
        </script>
        <style>
          .rss-install-button {
            min-height:32px;
            padding:.4rem .72rem;
            border-radius:999px;
            border:1px solid rgba(255,255,255,.12);
            background:rgba(255,255,255,.05);
            color:rgba(255,255,255,.86);
            font:inherit;
            font-size:.82rem;
            font-weight:700;
            cursor:pointer;
            white-space:nowrap;
          }
          .rss-install-button:hover {
            background:rgba(212,175,55,.14);
            color:#f4d57a;
          }
          .rss-install-button[hidden] {
            display:none !important;
          }
          @media (max-width: 640px) {
            .rss-install-button {
              padding:.36rem .58rem;
              font-size:.76rem;
            }
          }
        </style>
        <?php
    }
}
