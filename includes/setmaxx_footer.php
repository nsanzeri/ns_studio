<?php
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase   = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
?>
<footer class="site-footer setmaxx-site-footer">
    <div class="container footer-inner setmaxx-footer-inner">
        <div class="footer-left">
            <ul class="setmaxx-footer-links">
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php') ?>">Dashboard</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/songs.php') ?>">Songs</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/sessions.php') ?>">Sessions</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/setmaxx/requests.php') ?>">Requests</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/member/library.php') ?>">My Products</a></li>
                <li><a href="<?= htmlspecialchars($studioBase . '/tools/index.php') ?>">Calendar Tools</a></li>
                <li><a href="<?= htmlspecialchars($siteBase . '/contact.php') ?>">Support</a></li>
            </ul>
            <p>© <span id="setmaxxYear"></span> Set Maxx</p>
            <p class="footer-location">Built for working performers</p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const yearEl = document.getElementById('setmaxxYear');
    if (yearEl) {
        yearEl.textContent = new Date().getFullYear();
    }

    const navToggle = document.getElementById('setmaxxNavToggle');
    const mobileNav = document.getElementById('setmaxxMobileNav');

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
});
</script>

<style>
  .setmaxx-site-footer {
    border-top: 1px solid rgba(255,255,255,.08);
    background: linear-gradient(180deg, rgba(10,10,22,.94), rgba(9,8,16,.98));
    margin-top: 3rem;
  }
  .setmaxx-footer-inner {
    padding: 2rem 0;
  }
  .setmaxx-footer-links {
    list-style:none;
    display:flex;
    flex-wrap:wrap;
    gap:1rem 1.25rem;
    padding:0;
    margin:0 0 1rem 0;
  }
  .setmaxx-footer-links a {
    text-decoration:none;
    color:rgba(255,255,255,.82);
  }
  .setmaxx-footer-links a:hover {
    color:#efe7ff;
  }
  @media (max-width: 980px) {
    .setmaxx-footer-links {
      flex-direction:column;
      gap:.7rem;
    }
  }
</style>
