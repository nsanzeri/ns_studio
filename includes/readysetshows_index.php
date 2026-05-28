<?php
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
$assetBase = $siteBase . '/assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ready Set Shows | Tools for Working Musicians</title>
    <meta name="description" content="Ready Set Shows helps working musicians manage calendars, availability, setlists, requests, and gig workflows from one practical tool suite.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/style.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($assetBase . '/favicons/favicon-32.png', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars($assetBase . '/favicons/favicon-16.png', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($assetBase . '/favicons/favicon-180.png', ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root {
            --rss-bg: #090b14;
            --rss-panel: #111522;
            --rss-panel-soft: rgba(255,255,255,.055);
            --rss-border: rgba(255,255,255,.105);
            --rss-gold: #d8b34a;
            --rss-gold-bright: #f1d778;
            --rss-coral: #f06b4f;
            --rss-blue: #6aa8ff;
            --rss-text: #f7f4ec;
            --rss-muted: rgba(247,244,236,.74);
        }

        body {
            background:
                radial-gradient(circle at 12% 18%, rgba(240,107,79,.18), transparent 28rem),
                radial-gradient(circle at 88% 8%, rgba(106,168,255,.16), transparent 25rem),
                linear-gradient(180deg, #090b14 0%, #10131e 48%, #080910 100%);
            color: var(--rss-text);
        }

        .rss-header {
            position: sticky;
            top: 0;
            z-index: 20;
            border-bottom: 1px solid var(--rss-border);
            background: rgba(9,11,20,.86);
            backdrop-filter: blur(14px);
        }

        .rss-header-inner {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .rss-brand {
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            color: #fff;
            text-decoration: none;
        }

        .rss-brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #111;
            font-weight: 800;
            letter-spacing: .03em;
            background: linear-gradient(135deg, var(--rss-gold-bright), var(--rss-gold));
            box-shadow: 0 12px 32px rgba(216,179,74,.24);
        }

        .rss-brand-text {
            display: grid;
            line-height: 1.1;
        }

        .rss-brand-name {
            font-weight: 700;
            letter-spacing: .02em;
        }

        .rss-brand-tagline {
            color: var(--rss-muted);
            font-size: .78rem;
            margin-top: .2rem;
        }

        .rss-nav {
            display: flex;
            align-items: center;
            gap: .65rem;
        }

        .rss-nav a {
            color: rgba(255,255,255,.82);
            text-decoration: none;
            font-weight: 500;
            font-size: .94rem;
            padding: .7rem .85rem;
            border-radius: 999px;
        }

        .rss-nav a:hover {
            color: #111;
            background: var(--rss-gold-bright);
        }

        .rss-hero {
            min-height: calc(100vh - 78px);
            display: grid;
            align-items: center;
            padding: 4.5rem 0 3rem;
        }

        .rss-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.02fr) minmax(310px, .98fr);
            gap: 3rem;
            align-items: center;
        }

        .rss-kicker {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .38rem .72rem;
            border: 1px solid rgba(216,179,74,.34);
            border-radius: 999px;
            color: var(--rss-gold-bright);
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 1.15rem;
        }

        .rss-hero h1 {
            font-size: clamp(2.65rem, 7vw, 6.2rem);
            line-height: .92;
            max-width: 820px;
            margin: 0 0 1.1rem;
        }

        .rss-hero-copy {
            color: var(--rss-muted);
            font-size: clamp(1.03rem, 1.8vw, 1.18rem);
            line-height: 1.75;
            max-width: 710px;
            margin-bottom: 1.45rem;
        }

        .rss-actions {
            display: flex;
            gap: .8rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .rss-actions .btn {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .rss-visual {
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.035));
            box-shadow: 0 28px 80px rgba(0,0,0,.36);
        }

        .rss-visual-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .85rem 1rem;
            border-bottom: 1px solid var(--rss-border);
            color: rgba(255,255,255,.74);
            font-size: .9rem;
        }

        .rss-visual-dots {
            display: inline-flex;
            gap: .4rem;
        }

        .rss-visual-dots span {
            width: 10px;
            height: 10px;
            border-radius: 99px;
            background: var(--rss-coral);
        }

        .rss-visual-dots span:nth-child(2) { background: var(--rss-gold); }
        .rss-visual-dots span:nth-child(3) { background: var(--rss-blue); }

        .rss-visual img {
            display: block;
            width: 100%;
            background: #10131e;
        }

        .rss-feature-band {
            padding: 3.5rem 0 4.5rem;
            background: rgba(0,0,0,.18);
            border-top: 1px solid var(--rss-border);
        }

        .rss-section-head {
            max-width: 760px;
            margin-bottom: 1.65rem;
        }

        .rss-section-head h2 {
            margin-bottom: .55rem;
            font-size: clamp(2rem, 4vw, 3.25rem);
        }

        .rss-section-head p {
            color: var(--rss-muted);
            margin: 0;
        }

        .rss-feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .rss-feature-card {
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            background: rgba(255,255,255,.05);
            padding: 1.2rem;
            min-height: 230px;
            display: grid;
            align-content: start;
            gap: .75rem;
        }

        .rss-feature-icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #111;
            background: var(--rss-gold-bright);
        }

        .rss-feature-card h3 {
            margin: 0;
            color: #fff;
            font-size: 1.08rem;
        }

        .rss-feature-card p {
            margin: 0;
            color: var(--rss-muted);
            font-size: .95rem;
            line-height: 1.62;
        }

        .rss-final-cta {
            padding: 4rem 0;
        }

        .rss-final-box {
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(216,179,74,.13), rgba(106,168,255,.10));
            padding: clamp(1.4rem, 4vw, 2.4rem);
            display: flex;
            justify-content: space-between;
            gap: 1.25rem;
            align-items: center;
        }

        .rss-final-box h2 {
            margin: 0 0 .35rem;
        }

        .rss-final-box p {
            margin: 0;
            color: var(--rss-muted);
        }

        .rss-footer {
            padding: 1.35rem 0;
            border-top: 1px solid var(--rss-border);
            color: rgba(255,255,255,.58);
            font-size: .92rem;
        }

        @media (max-width: 980px) {
            .rss-header-inner {
                min-height: 72px;
            }

            .rss-nav a:not(.rss-nav-login):not(.rss-nav-primary) {
                display: none;
            }

            .rss-hero {
                min-height: auto;
                padding-top: 3rem;
            }

            .rss-hero-grid,
            .rss-feature-grid {
                grid-template-columns: 1fr;
            }

            .rss-feature-card {
                min-height: 0;
            }

            .rss-final-box {
                display: grid;
            }
        }

        @media (max-width: 620px) {
            .rss-brand-tagline,
            .rss-nav-login {
                display: none;
            }

            .rss-brand-mark {
                width: 40px;
                height: 40px;
            }

            .rss-actions .btn,
            .rss-final-box .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="rss-header">
        <div class="container rss-header-inner">
            <a class="rss-brand" href="<?= htmlspecialchars($siteBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
                <span class="rss-brand-mark">RS</span>
                <span class="rss-brand-text">
                    <span class="rss-brand-name">Ready Set Shows</span>
                    <span class="rss-brand-tagline">Tools for working musicians</span>
                </span>
            </a>
            <nav class="rss-nav" aria-label="Ready Set Shows navigation">
                <a href="<?= htmlspecialchars($studioBase . '/shop/index.php', ENT_QUOTES, 'UTF-8') ?>">Suite</a>
                <a href="<?= htmlspecialchars($studioBase . '/setmaxx/index.php', ENT_QUOTES, 'UTF-8') ?>">SetMaxx</a>
                <a class="rss-nav-login" href="<?= htmlspecialchars($studioBase . '/member/login.php', ENT_QUOTES, 'UTF-8') ?>">Log In</a>
                <a class="rss-nav-primary" href="<?= htmlspecialchars($studioBase . '/member/pricing.php', ENT_QUOTES, 'UTF-8') ?>">Start Trial</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="rss-hero">
            <div class="container rss-hero-grid">
                <div>
                    <div class="rss-kicker"><i class="fa-solid fa-bolt"></i> Built for gig life</div>
                    <h1>Run the show before you play the show.</h1>
                    <p class="rss-hero-copy">
                        Ready Set Shows gives working musicians a practical command center for availability,
                        calendars, setlists, public requests, and the repeatable details that keep gigs moving.
                    </p>
                    <div class="rss-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($studioBase . '/member/pricing.php', ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-play"></i> Start Free Trial
                        </a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($studioBase . '/tools/index.php', ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-regular fa-calendar-check"></i> Open Tools
                        </a>
                    </div>
                </div>

                <a class="rss-visual" href="<?= htmlspecialchars($studioBase . '/shop/index.php', ENT_QUOTES, 'UTF-8') ?>" aria-label="View Ready Set Shows suite">
                    <div class="rss-visual-bar">
                        <span class="rss-visual-dots"><span></span><span></span><span></span></span>
                        <span>readysetshows.com/studio</span>
                    </div>
                    <img src="<?= htmlspecialchars($assetBase . '/img/rss-tools.png', ENT_QUOTES, 'UTF-8') ?>" alt="Ready Set Shows tool suite preview">
                </a>
            </div>
        </section>

        <section class="rss-feature-band">
            <div class="container">
                <div class="rss-section-head">
                    <p class="eyebrow">One suite, real workflows</p>
                    <h2>Less admin between shows.</h2>
                    <p>Start with calendar and availability tools, then build into set planning, requests, and business workflows as the suite grows.</p>
                </div>

                <div class="rss-feature-grid">
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-regular fa-calendar-check"></i></span>
                        <h3>Availability</h3>
                        <p>Check open dates across calendars and share clean date lists without rebuilding them by hand.</p>
                    </article>

                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-file-export"></i></span>
                        <h3>Exports</h3>
                        <p>Format calendar data for printing, copying, and bulk show prep for places like Bandsintown.</p>
                    </article>

                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-list-check"></i></span>
                        <h3>SetMaxx</h3>
                        <p>Manage song catalogs, setlists, request sessions, and public QR-friendly request pages.</p>
                    </article>

                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-chart-line"></i></span>
                        <h3>Roadmap</h3>
                        <p>Publishing, promo, revenue, deposits, and booking tools are planned around real gigging needs.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rss-final-cta">
            <div class="container">
                <div class="rss-final-box">
                    <div>
                        <h2>Ready to make the admin quieter?</h2>
                        <p>Use the suite from this domain, with the same account and tools behind the scenes.</p>
                    </div>
                    <a class="btn btn-primary" href="<?= htmlspecialchars($studioBase . '/shop/index.php', ENT_QUOTES, 'UTF-8') ?>">View the Suite</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="rss-footer">
        <div class="container">
            &copy; <?= date('Y') ?> Ready Set Shows. All rights reserved.
        </div>
    </footer>
</body>
</html>
