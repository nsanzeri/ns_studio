<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
?>

<footer class="site-footer tools-site-footer">
    <div class="container footer-inner tools-footer-inner">
        <div class="footer-left">
            <ul class="tools-footer-links">
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>">Availability</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/member/library.php') ?>">Library</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/contact.php') ?>">Support</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/index.php') ?>">Main Site</a></li>
            </ul>
            <p>© <span id="year"></span> Ready Set Shows</p>
            <p class="footer-location">Built by Nick Sanzeri</p>
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

    const navToggle = document.getElementById('toolsNavToggle');
    const mobileNav = document.getElementById('toolsMobileNav');

    if (navToggle && mobileNav) {
        navToggle.addEventListener('click', function () {
            const willOpen = mobileNav.hasAttribute('hidden');
            if (willOpen) {
                mobileNav.removeAttribute('hidden');
            } else {
                mobileNav.setAttribute('hidden', '');
            }
            navToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            navToggle.classList.toggle('is-active', willOpen);
        });

        mobileNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mobileNav.setAttribute('hidden', '');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.classList.remove('is-active');
            });
        });

        document.addEventListener('click', function (event) {
            const clickedInsideNav = mobileNav.contains(event.target);
            const clickedToggle = navToggle.contains(event.target);

            if (!clickedInsideNav && !clickedToggle && !mobileNav.hasAttribute('hidden')) {
                mobileNav.setAttribute('hidden', '');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.classList.remove('is-active');
            }
        });
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