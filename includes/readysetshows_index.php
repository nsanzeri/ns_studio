<?php
require_once __DIR__ . '/../studio/_private/_core/bootstrap.php';

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
$assetBase = $siteBase . '/assets';
$readySetShowsUrl = $studioBase . '/member/library.php';
$pricingUrl = $studioBase . '/member/pricing.php';
$loginUrl = $studioBase . '/member/login.php?brand=rss';
$shopUrl = $studioBase . '/shop/index.php';
$bookBandUrl = $siteBase . '/booking-request.php';
$artistRegisterUrl = $studioBase . '/member/register.php?account_type=artist&brand=rss';
$screensBase = $assetBase . '/img/readysetshows';
$isLoggedIn = class_exists('Auth') && Auth::isLoggedIn();
$currentUser = ($isLoggedIn && isset($pdo) && $pdo instanceof PDO) ? Auth::currentUser($pdo) : null;
$accountType = $isLoggedIn && $currentUser ? Auth::normalizeAccountType((string)($currentUser['account_type'] ?? 'artist')) : '';
$primaryNavUrl = $isLoggedIn ? $readySetShowsUrl : $pricingUrl;
$primaryNavLabel = $isLoggedIn ? 'Open Ready Set Shows' : 'Pricing';

$screens = [
    'availability' => $screensBase . '/calendar-availability.png',
    'bandsintown' => $screensBase . '/bandsintown-export.png',
    'songs' => $screensBase . '/setmaxx-song-catalog.png',
    'setlists' => $screensBase . '/setmaxx-setlist-generator.png',
    'public' => $screensBase . '/setmaxx-public-requests.png',
    'finance' => $screensBase . '/finance-ledger.png',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ready Set Shows | Calendar, SetMaxx, Finance, and Publishing Tools for Working Musicians</title>
    <meta name="description" content="Ready Set Shows helps working musicians book faster, earn more per gig, track income, promote upcoming shows, and spend less time on admin.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/style.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($assetBase . '/favicons/rss-favicon-32.png', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars($assetBase . '/favicons/rss-favicon-16.png', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($assetBase . '/favicons/rss-favicon-180.png', ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root {
            --rss-bg: #080910;
            --rss-panel: #121521;
            --rss-panel-2: #181324;
            --rss-border: rgba(255,255,255,.12);
            --rss-gold: #e7c75a;
            --rss-coral: #ff7a59;
            --rss-blue: #6aa8ff;
            --rss-green: #65d58a;
            --rss-text: #f8f4eb;
            --rss-muted: rgba(248,244,235,.74);
        }

        html { scroll-behavior: smooth; }

        body {
            background: linear-gradient(180deg, #080910 0%, #10131d 46%, #080910 100%);
            color: var(--rss-text);
        }

        .rss-header {
            position: sticky;
            top: 0;
            z-index: 20;
            border-bottom: 1px solid var(--rss-border);
            background: rgba(8,9,16,.9);
            backdrop-filter: blur(14px);
        }

        .rss-header-inner {
            min-height: 76px;
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
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #111;
            font-weight: 800;
            background: linear-gradient(135deg, #ffe78c, var(--rss-gold));
            box-shadow: 0 12px 32px rgba(231,199,90,.24);
        }

        .rss-brand-text {
            display: grid;
            line-height: 1.1;
        }

        .rss-brand-name {
            font-family: "Playfair Display", serif;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .rss-brand-tagline {
            color: var(--rss-muted);
            font-size: .72rem;
            margin-top: .22rem;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .rss-nav {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .rss-nav a {
            color: rgba(255,255,255,.82);
            text-decoration: none;
            font-weight: 600;
            font-size: .94rem;
            padding: .65rem .8rem;
            border-radius: 999px;
        }

        .rss-nav a:hover {
            color: #111;
            background: var(--rss-gold);
        }

        .rss-nav-primary {
            color: #111 !important;
            background: var(--rss-gold);
            font-weight: 800 !important;
        }

        .rss-nav-login {
            border: 1px solid rgba(255,255,255,.2);
        }

        .rss-hero {
            position: relative;
            min-height: calc(100vh - 76px);
            overflow: hidden;
            border-bottom: 1px solid var(--rss-border);
            display: grid;
            align-items: end;
        }

        .rss-hero-media {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            grid-template-rows: repeat(8, 1fr);
            gap: 1rem;
            padding: max(1rem, 3vw);
            opacity: .68;
            transform: scale(1.03);
        }

        .rss-hero-shot {
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            background: #10121c;
            box-shadow: 0 28px 80px rgba(0,0,0,.5);
            overflow: hidden;
        }

        .rss-hero-shot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .rss-shot-a { grid-column: 6 / 13; grid-row: 1 / 5; }
        .rss-shot-b { grid-column: 2 / 7; grid-row: 4 / 9; }
        .rss-shot-c { grid-column: 9 / 13; grid-row: 5 / 9; }

        .rss-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(8,9,16,.98) 0%, rgba(8,9,16,.82) 48%, rgba(8,9,16,.5) 100%),
                linear-gradient(180deg, rgba(8,9,16,.18) 0%, rgba(8,9,16,.72) 76%, #080910 100%);
            z-index: 1;
        }

        .rss-hero-content {
            position: relative;
            z-index: 2;
            padding: 5rem 0 4.5rem;
            max-width: 780px;
        }

        .rss-kicker,
        .rss-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .38rem .72rem;
            border: 1px solid rgba(231,199,90,.34);
            border-radius: 999px;
            color: var(--rss-gold);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 1.05rem;
            letter-spacing: 0;
        }

        .rss-hero h1 {
            font-size: clamp(3rem, 7vw, 6.8rem);
            line-height: .9;
            margin: 0 0 1rem;
        }

        .rss-hero-copy {
            color: var(--rss-muted);
            font-size: clamp(1.05rem, 1.8vw, 1.22rem);
            line-height: 1.7;
            max-width: 670px;
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

        .rss-hero-note {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.2rem;
            color: rgba(255,255,255,.72);
            font-size: .94rem;
        }

        .rss-paths {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.6rem;
            max-width: 760px;
        }

        .rss-path-card {
            display: grid;
            gap: .7rem;
            align-content: start;
            min-height: 190px;
            padding: 1.1rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.055);
            text-decoration: none;
            color: var(--rss-text);
            box-shadow: 0 18px 48px rgba(0,0,0,.22);
        }

        .rss-path-card:hover {
            border-color: rgba(231,199,90,.46);
            background: rgba(231,199,90,.1);
            transform: translateY(-1px);
        }

        .rss-path-icon {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #111;
            background: var(--rss-gold);
        }

        .rss-path-card h2 {
            margin: .1rem 0 0;
            font-size: clamp(1.25rem, 2.2vw, 1.75rem);
        }

        .rss-path-card p {
            margin: 0;
            color: var(--rss-muted);
            line-height: 1.55;
        }

        .rss-path-cta {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-top: auto;
            color: var(--rss-gold);
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
            font-size: .82rem;
        }

        .rss-section {
            padding: clamp(3.2rem, 6vw, 5.6rem) 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .rss-section-head {
            max-width: 860px;
            margin-bottom: 1.8rem;
        }

        .rss-section-head h2 {
            margin: 0 0 .65rem;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1;
        }

        .rss-section-head p {
            color: var(--rss-muted);
            margin: 0;
            line-height: 1.7;
        }

        .rss-proof-grid,
        .rss-module-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .rss-proof-item,
        .rss-module-card,
        .rss-faq-item {
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            background: rgba(255,255,255,.052);
            padding: 1.15rem;
        }

        .rss-proof-value {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
        }

        .rss-proof-item p,
        .rss-module-card p,
        .rss-faq-item p {
            color: var(--rss-muted);
            margin: .3rem 0 0;
            line-height: 1.58;
        }

        .rss-module-card {
            display: grid;
            gap: .75rem;
        }

        .rss-module-card h3,
        .rss-faq-item h3 {
            margin: 0;
            color: #fff;
            font-size: 1.08rem;
        }

        .rss-module-icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #111;
            background: var(--rss-gold);
        }

        .rss-showcase {
            display: grid;
            gap: 1.2rem;
        }

        .rss-showcase-row {
            display: grid;
            grid-template-columns: minmax(260px, .8fr) minmax(0, 1.2fr);
            gap: 1.25rem;
            align-items: center;
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            background: rgba(255,255,255,.045);
            padding: clamp(1rem, 2.4vw, 1.5rem);
        }

        .rss-showcase-row:nth-child(even) {
            grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
        }

        .rss-showcase-row:nth-child(even) .rss-showcase-copy {
            order: 2;
        }

        .rss-showcase-copy h3 {
            margin: .3rem 0 .55rem;
            font-size: clamp(1.55rem, 2.8vw, 2.35rem);
            line-height: 1.05;
        }

        .rss-showcase-copy p {
            color: var(--rss-muted);
            line-height: 1.7;
            margin: 0 0 .8rem;
        }

        .rss-checks {
            display: grid;
            gap: .45rem;
            color: rgba(255,255,255,.82);
            margin-top: 1rem;
        }

        .rss-checks span {
            display: flex;
            gap: .55rem;
            align-items: flex-start;
        }

        .rss-checks i {
            color: var(--rss-green);
            margin-top: .18rem;
        }

        .rss-screen {
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 8px;
            background: #10121c;
            overflow: hidden;
            box-shadow: 0 26px 80px rgba(0,0,0,.42);
        }

        .rss-screen img {
            width: 100%;
            display: block;
        }

        .rss-money-band {
            background: rgba(0,0,0,.22);
        }

        .rss-money-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
            gap: 1.5rem;
            align-items: center;
        }

        .rss-quote {
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            padding: 1.3rem;
            background: linear-gradient(135deg, rgba(231,199,90,.13), rgba(101,213,138,.08));
        }

        .rss-quote strong {
            display: block;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            color: #fff;
            margin-bottom: .8rem;
        }

        .rss-quote p {
            color: var(--rss-muted);
            line-height: 1.65;
            margin: 0;
        }

        .rss-faq-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .rss-final-cta {
            padding: clamp(3.5rem, 7vw, 6rem) 0;
        }

        .rss-final-box {
            border: 1px solid var(--rss-border);
            border-radius: 8px;
            background:
                linear-gradient(135deg, rgba(231,199,90,.17), rgba(255,122,89,.09)),
                rgba(255,255,255,.045);
            padding: clamp(1.5rem, 4vw, 2.6rem);
            display: flex;
            justify-content: space-between;
            gap: 1.25rem;
            align-items: center;
        }

        .rss-final-box h2 {
            margin: 0 0 .35rem;
            font-size: clamp(2rem, 4vw, 3.2rem);
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
            .rss-nav a:not(.rss-nav-login):not(.rss-nav-primary) {
                display: none;
            }

            .rss-hero {
                min-height: auto;
            }

            .rss-hero-media {
                opacity: .36;
                grid-template-columns: 1fr;
                grid-template-rows: repeat(3, 1fr);
            }

            .rss-shot-a,
            .rss-shot-b,
            .rss-shot-c {
                grid-column: 1;
            }

            .rss-shot-a { grid-row: 1; }
            .rss-shot-b { grid-row: 2; }
            .rss-shot-c { grid-row: 3; }

            .rss-proof-grid,
            .rss-paths,
            .rss-module-grid,
            .rss-showcase-row,
            .rss-showcase-row:nth-child(even),
            .rss-money-grid,
            .rss-faq-grid {
                grid-template-columns: 1fr;
            }

            .rss-showcase-row:nth-child(even) .rss-showcase-copy {
                order: 0;
            }

            .rss-final-box {
                display: grid;
            }
        }

        @media (max-width: 620px) {
            .rss-brand-tagline {
                display: none;
            }

            .rss-brand-mark {
                width: 40px;
                height: 40px;
            }

            .rss-nav {
                gap: .35rem;
            }

            .rss-nav a {
                padding: .55rem .68rem;
                font-size: .86rem;
            }

            .rss-nav-guest .rss-nav-login {
                color: #111;
                background: var(--rss-gold);
                border-color: var(--rss-gold);
                font-weight: 800;
            }

            .rss-nav-guest .rss-nav-primary {
                display: none;
            }

            .rss-hero-content {
                padding-top: 3.2rem;
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
                    <span class="rss-brand-tagline">Live requests, setlists, and gig tools</span>
                </span>
            </a>
            <nav class="rss-nav <?= $isLoggedIn ? 'rss-nav-auth' : 'rss-nav-guest' ?>" aria-label="Ready Set Shows navigation">
                <a href="<?= htmlspecialchars($bookBandUrl, ENT_QUOTES, 'UTF-8') ?>">Hire an Artist</a>
                <?php if (!$isLoggedIn): ?>
                    <a href="<?= htmlspecialchars($artistRegisterUrl, ENT_QUOTES, 'UTF-8') ?>">Join as Artist</a>
                    <a class="rss-nav-login" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Log In</a>
                <?php endif; ?>
                <a class="rss-nav-primary" href="<?= htmlspecialchars($primaryNavUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($primaryNavLabel, ENT_QUOTES, 'UTF-8') ?></a>
            </nav>
        </div>
    </header>

    <main>
        <section class="rss-hero">
            <div class="rss-hero-media" aria-hidden="true">
                <div class="rss-hero-shot rss-shot-a"><img src="<?= htmlspecialchars($screens['public'], ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
                <div class="rss-hero-shot rss-shot-b"><img src="<?= htmlspecialchars($screens['availability'], ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
                <div class="rss-hero-shot rss-shot-c"><img src="<?= htmlspecialchars($screens['finance'], ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
            </div>

            <div class="container">
                <div class="rss-hero-content">
                    <div class="rss-kicker"><i class="fa-solid fa-bolt"></i> Built by working musicians</div>
                    <h1>Running a music career is hard enough.</h1>
                    <p class="rss-hero-copy">
                        Ready Set Shows handles the busy work so you can get back to making music. We built the musician&apos;s assistant we always wished we had.
                    </p>
                    <div class="rss-paths" aria-label="Choose how to use Ready Set Shows">
                        <a class="rss-path-card" href="<?= htmlspecialchars($bookBandUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="rss-path-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                            <h2>Hire an artist</h2>
                            <p>Create one event request and invite multiple artists to bid on the date.</p>
                            <span class="rss-path-cta">Start a booking <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <?php if (!$isLoggedIn): ?>
                        <a class="rss-path-card" href="<?= htmlspecialchars($artistRegisterUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="rss-path-icon"><i class="fa-solid fa-guitar"></i></span>
                            <h2>Join as artist</h2>
                            <p>List your artist profile, manage gig tools, respond faster, and keep the business side organized.</p>
                            <span class="rss-path-cta">Join as artist <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="rss-hero-note">
                        <span><i class="fa-solid fa-check"></i> We know how hard it is to be a working musician.</span>
                        <span><i class="fa-solid fa-check"></i> Calendar, SetMaxx, Finance, Publishing, and booking requests.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="rss-section" id="modules">
            <div class="container">
                <div class="rss-section-head">
                    <span class="rss-eyebrow">Built for working musicians</span>
                    <h2>Spend less time managing the career and more time making music.</h2>
                    <p>We know how hard it is to be a working musician. Ready Set Shows brings the busy work into one place: availability, setlists, booking requests, income tracking, paid audience requests, and promotion that actually gets done.</p>
                </div>

                <div class="rss-module-grid">
                    <article class="rss-module-card">
                        <span class="rss-module-icon"><i class="fa-regular fa-calendar-check"></i></span>
                        <h3>Calendar</h3>
                        <p>Respond quickly, cleanly, and accurately when buyers ask for dates. Faster answers help you look pro, win the gig, and make more money.</p>
                    </article>
                    <article class="rss-module-card">
                        <span class="rss-module-icon"><i class="fa-solid fa-music"></i></span>
                        <h3>SetMaxx</h3>
                        <p>Max out each room with paid requests, tips, card and Venmo options, email signups, reviews, and song suggestions for future shows.</p>
                    </article>
                    <article class="rss-module-card">
                        <span class="rss-module-icon"><i class="fa-solid fa-chart-line"></i></span>
                        <h3>Finance</h3>
                        <p>Track every gig, import past income, reconcile payouts, and finally see how your music business is actually doing.</p>
                    </article>
                    <article class="rss-module-card">
                        <span class="rss-module-icon"><i class="fa-solid fa-bullhorn"></i></span>
                        <h3>Publishing</h3>
                        <p>Never skimp on promotion. Generate posts, captions, newsletters, and date-list copy for your upcoming shows in a few clicks.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rss-section" id="screens">
            <div class="container">
                <div class="rss-section-head">
                    <span class="rss-eyebrow">The actual tools</span>
                    <h2>A working suite, not another blank dashboard.</h2>
                    <p>Every module is built around the things performers actually do before, during, and after the gig.</p>
                </div>

                <div class="rss-showcase">
                    <article class="rss-showcase-row">
                        <div class="rss-showcase-copy">
                            <span class="rss-eyebrow">Calendar</span>
                            <h3>Book faster while the buyer is still paying attention.</h3>
                            <p>Find open dates across calendars, print clean availability, and export show data when a platform needs it.</p>
                            <div class="rss-checks">
                                <span><i class="fa-solid fa-check"></i> Fast availability replies</span>
                                <span><i class="fa-solid fa-check"></i> Client-friendly print and export tools</span>
                                <span><i class="fa-solid fa-check"></i> Bandsintown-ready export when you need it</span>
                            </div>
                        </div>
                        <div class="rss-screen"><img src="<?= htmlspecialchars($screens['availability'], ENT_QUOTES, 'UTF-8') ?>" alt="Ready Set Shows calendar availability screen"></div>
                    </article>

                    <article class="rss-showcase-row">
                        <div class="rss-showcase-copy">
                            <span class="rss-eyebrow">SetMaxx</span>
                            <h3>Turn the audience into a revenue channel.</h3>
                            <p>Build your requestable song catalog, generate better sets, and open a public page that lets fans request, tip, join your list, leave reviews, and suggest songs.</p>
                            <div class="rss-checks">
                                <span><i class="fa-solid fa-check"></i> Song catalog and editable setlist generator</span>
                                <span><i class="fa-solid fa-check"></i> Saved favorite setlists with Pro</span>
                                <span><i class="fa-solid fa-check"></i> Public request page with card and Venmo options</span>
                                <span><i class="fa-solid fa-check"></i> Emails, reviews, and song ideas for future shows</span>
                            </div>
                        </div>
                        <div class="rss-screen"><img src="<?= htmlspecialchars($screens['public'], ENT_QUOTES, 'UTF-8') ?>" alt="SetMaxx public request page"></div>
                    </article>

                    <article class="rss-showcase-row">
                        <div class="rss-showcase-copy">
                            <span class="rss-eyebrow">Finance</span>
                            <h3>Know what you made, who got paid, and what is working.</h3>
                            <p>Bring in calendar gigs, enter guarantees and tips, track money out, and keep the ledger clean enough for real reconciliation.</p>
                            <div class="rss-checks">
                                <span><i class="fa-solid fa-check"></i> Gig ledger with guarantees, tips, payouts, mileage, and notes</span>
                                <span><i class="fa-solid fa-check"></i> Import past income from spreadsheets with Pro</span>
                                <span><i class="fa-solid fa-check"></i> See trends instead of guessing how you are doing</span>
                            </div>
                        </div>
                        <div class="rss-screen"><img src="<?= htmlspecialchars($screens['finance'], ENT_QUOTES, 'UTF-8') ?>" alt="Ready Set Shows finance ledger"></div>
                    </article>

                    <article class="rss-showcase-row">
                        <div class="rss-showcase-copy">
                            <span class="rss-eyebrow">Publishing</span>
                            <h3>Promote every show without starting from zero.</h3>
                            <p>Use your real gig data to generate social posts, blurbs, newsletters, and date-list copy so the shows you worked to book actually get promoted.</p>
                            <div class="rss-checks">
                                <span><i class="fa-solid fa-check"></i> Captions and event blurbs from selected gigs</span>
                                <span><i class="fa-solid fa-check"></i> Newsletter and date-list copy</span>
                                <span><i class="fa-solid fa-check"></i> Less admin, more consistency</span>
                            </div>
                        </div>
                        <div class="rss-screen"><img src="<?= htmlspecialchars($screens['setlists'], ENT_QUOTES, 'UTF-8') ?>" alt="Ready Set Shows setlist generator"></div>
                    </article>
                </div>
            </div>
        </section>

        <section class="rss-section rss-money-band">
            <div class="container rss-money-grid">
                <div class="rss-section-head" style="margin-bottom:0;">
                    <span class="rss-eyebrow">Free where it should be</span>
                    <h2>The musician&apos;s assistant we always wished we had.</h2>
                    <p>Start with the tools that keep you organized. Calendar availability, song catalogs, editable setlist generation, Finance tracking, and Publishing tools help before you ever charge the crowd. Pro unlocks saved setlists, live request pages, payments, Bandsintown export, and spreadsheet import workflows that directly support paid operations.</p>
                </div>
                <div class="rss-quote">
                    <strong>More signal. Less admin.</strong>
                    <p>Ready Set Shows is for musicians who want the business side handled cleanly so the creative side has room to breathe.</p>
                </div>
            </div>
        </section>

        <section class="rss-section">
            <div class="container">
                <div class="rss-section-head">
                    <span class="rss-eyebrow">Quick answers</span>
                    <h2>Built for the real rhythm of gig work.</h2>
                </div>
                <div class="rss-faq-grid">
                    <article class="rss-faq-item">
                        <h3>Is it only SetMaxx?</h3>
                        <p>No. SetMaxx is the live request engine, but Ready Set Shows also includes Calendar, Finance, and Publishing tools.</p>
                    </article>
                    <article class="rss-faq-item">
                        <h3>Can I start free?</h3>
                        <p>Yes. The planning tools are free so performers can get value before adding paid live request workflows.</p>
                    </article>
                    <article class="rss-faq-item">
                        <h3>Does it replace my current process?</h3>
                        <p>It can, but it does not have to. Start with the module that solves today&apos;s pain, then add the rest when you are ready.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rss-final-cta">
            <div class="container">
                <div class="rss-final-box">
                    <div>
                        <h2>Ready to make the gig work harder for you?</h2>
                        <p>Hire an artist for your event, or join as an artist and let Ready Set Shows handle more of the busy work.</p>
                    </div>
                    <div class="rss-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($bookBandUrl, ENT_QUOTES, 'UTF-8') ?>">Hire an Artist</a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($primaryNavUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($primaryNavLabel, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="rss-footer">
        <div class="container">
            Ready Set Shows &copy; <?= date('Y') ?>. Calendar, SetMaxx, Finance, and Publishing tools for working musicians.
        </div>
    </footer>
</body>
</html>
