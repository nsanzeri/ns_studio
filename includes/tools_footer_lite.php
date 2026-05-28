<?php
$user = isset($pdo) && class_exists('Auth') ? Auth::currentUser($pdo) : null;

$libraryUrl = $user
? base_url('member/library.php')
: base_url('member/login.php');

$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
$homeUrl = function_exists('rss_public_root_url')
    ? rtrim(rss_public_root_url(), '/') . '/index.php'
    : preg_replace('#/studio$#', '', base_url('')) . '/index.php';
?>

<footer class="site-footer tools-site-footer">
    <div class="container footer-inner tools-footer-inner">
        <div class="footer-left">
            <ul class="tools-footer-links">
                <li>
                    <a href="<?= htmlspecialchars($libraryUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <?= $user ? 'My Products' : 'Log In' ?>
                    </a>
                </li>
                <li><a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Main Site</a></li>
            </ul>
            <p>© <span id="year"></span> Ready Set Shows</p>
            <?php if (!$isReadySetShowsHost): ?>
                <p class="footer-location">Built by Nick Sanzeri</p>
            <?php endif; ?>
        </div>
    </div>
</footer>

<a href="#top" class="back-to-top" id="backToTop" aria-label="Back to top">
    <i class="fas fa-arrow-up"></i>
</a>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const yearEl = document.getElementById('year');
    if (yearEl) {
        yearEl.textContent = new Date().getFullYear();
    }

    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
    }
});
</script>

<style>
  .tools-site-footer {
    border-top: 1px solid rgba(255,255,255,.08);
    background: linear-gradient(180deg, rgba(7,10,24,.98), rgba(5,7,18,1));
  }

  .tools-footer-inner {
    display: block;
  }

  .tools-footer-links {
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.25rem;
    padding: 0;
    margin: 0 0 1rem 0;
  }

  .tools-footer-links li {
    margin: 0;
  }

  .tools-footer-links a {
    text-decoration: none;
    color: rgba(255,255,255,.82);
  }

  .tools-footer-links a:hover {
    color: #f4d57a;
  }

  .tools-site-footer p {
    margin: .2rem 0;
  }

  @media (max-width: 980px) {
    .tools-footer-links {
      flex-direction: column;
      gap: .7rem;
    }
  }
</style>
