<?php
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$studioBase = $siteBase . '/studio';
$assetBase = $siteBase . '/assets';
$setmaxxUrl = $studioBase . '/setmaxx/index.php';
$pricingUrl = $studioBase . '/member/pricing.php';
$loginUrl = $studioBase . '/member/login.php';
$shopUrl = $studioBase . '/shop/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Maxx by Ready Set Shows | Live Requests for Working Musicians</title>
    <meta name="description" content="Set Maxx turns your song list into a live request and tipping system for working musicians. Build request pages, collect paid requests, manage setlists, and run better shows.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background:
                radial-gradient(circle at 15% 12%, rgba(255,122,89,.2), transparent 22rem),
                radial-gradient(circle at 90% 6%, rgba(106,168,255,.22), transparent 24rem),
                linear-gradient(180deg, #080910 0%, #11131f 44%, #080910 100%);
            color: var(--rss-text);
        }

        .rss-header {
            position: sticky;
            top: 0;
            z-index: 20;
            border-bottom: 1px solid var(--rss-border);
            background: rgba(8,9,16,.88);
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
            border-radius: 12px;
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
            font-weight: 700;
        }

        .rss-brand-tagline {
            color: var(--rss-muted);
            font-size: .78rem;
            margin-top: .2rem;
        }

        .rss-nav {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .rss-nav a {
            color: rgba(255,255,255,.82);
            text-decoration: none;
            font-weight: 500;
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
            font-weight: 700 !important;
        }

        .rss-hero {
            position: relative;
            min-height: calc(100vh - 76px);
            display: grid;
            align-items: center;
            overflow: hidden;
            border-bottom: 1px solid var(--rss-border);
        }

        .rss-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(8,9,16,.97) 0%, rgba(8,9,16,.84) 45%, rgba(8,9,16,.42) 100%),
                radial-gradient(circle at 72% 22%, rgba(231,199,90,.18), transparent 24rem);
            z-index: 1;
        }

        .rss-hero-scene {
            position: absolute;
            inset: 0;
            z-index: 0;
        }

        .rss-product-frame {
            position: absolute;
            right: max(1.2rem, calc((100vw - 1120px) / 2));
            top: 50%;
            width: min(570px, 46vw);
            transform: translateY(-50%);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            background: rgba(14,15,25,.94);
            box-shadow: 0 34px 100px rgba(0,0,0,.48);
            overflow: hidden;
        }

        .rss-frame-top {
            min-height: 46px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.1);
            color: rgba(255,255,255,.68);
            font-size: .84rem;
        }

        .rss-dots {
            display: inline-flex;
            gap: .4rem;
        }

        .rss-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--rss-coral);
        }

        .rss-dots span:nth-child(2) { background: var(--rss-gold); }
        .rss-dots span:nth-child(3) { background: var(--rss-blue); }

        .rss-frame-body {
            padding: 1rem;
            display: grid;
            gap: .75rem;
        }

        .rss-request-demo {
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 8px;
            background: rgba(255,255,255,.045);
            padding: .9rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: .8rem;
            align-items: center;
        }

        .rss-demo-title {
            font-weight: 700;
            color: #fff;
        }

        .rss-demo-meta {
            color: rgba(255,255,255,.64);
            font-size: .88rem;
            margin-top: .15rem;
        }

        .rss-demo-pill {
            border-radius: 999px;
            padding: .42rem .65rem;
            color: #111;
            background: var(--rss-gold);
            font-weight: 800;
            font-size: .84rem;
            white-space: nowrap;
        }

        .rss-demo-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .55rem;
        }

        .rss-demo-actions span {
            min-height: 40px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,.78);
            background: rgba(255,255,255,.04);
            font-size: .86rem;
        }

        .rss-logo-plate {
            position: absolute;
            right: 5vw;
            bottom: 7vh;
            width: 190px;
            opacity: .3;
        }

        .rss-hero-content {
            position: relative;
            z-index: 2;
            padding: 5rem 0 4rem;
            max-width: 690px;
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
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 1.05rem;
            letter-spacing: 0;
        }

        .rss-hero h1 {
            font-size: clamp(3rem, 8vw, 7rem);
            line-height: .9;
            margin: 0 0 1rem;
        }

        .rss-hero-copy {
            color: var(--rss-muted);
            font-size: clamp(1.05rem, 1.8vw, 1.22rem);
            line-height: 1.7;
            max-width: 650px;
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

        .rss-section {
            padding: clamp(3.2rem, 6vw, 5.6rem) 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .rss-section-head {
            max-width: 790px;
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
        .rss-feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .rss-proof-item,
        .rss-feature-card,
        .rss-step,
        .rss-quote,
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
        .rss-feature-card p,
        .rss-step p,
        .rss-faq-item p {
            color: var(--rss-muted);
            margin: .3rem 0 0;
            line-height: 1.58;
        }

        .rss-feature-card {
            min-height: 235px;
            display: grid;
            align-content: start;
            gap: .7rem;
        }

        .rss-feature-icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #111;
            background: var(--rss-gold);
        }

        .rss-feature-card h3,
        .rss-step h3,
        .rss-faq-item h3 {
            margin: 0;
            color: #fff;
            font-size: 1.08rem;
        }

        .rss-workflow {
            display: grid;
            grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
            gap: 1.4rem;
            align-items: start;
        }

        .rss-steps {
            display: grid;
            gap: .85rem;
        }

        .rss-step {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: .9rem;
            align-items: start;
        }

        .rss-step-number {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(231,199,90,.16);
            border: 1px solid rgba(231,199,90,.34);
            color: var(--rss-gold);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .rss-phone {
            max-width: 360px;
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            background: #11101b;
            padding: .8rem;
            box-shadow: 0 26px 80px rgba(0,0,0,.42);
        }

        .rss-phone-screen {
            border-radius: 8px;
            background:
                linear-gradient(180deg, rgba(106,168,255,.15), transparent 38%),
                #241f38;
            padding: 1rem;
            min-height: 520px;
            display: grid;
            align-content: start;
            gap: .8rem;
        }

        .rss-phone-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: .3rem;
        }

        .rss-phone-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            border-radius: 8px;
            background: rgba(255,255,255,.08);
            padding: .25rem;
        }

        .rss-phone-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 8px;
            background: rgba(255,255,255,.06);
            padding: .82rem;
        }

        .rss-phone-card strong {
            display: block;
            margin-bottom: .18rem;
        }

        .rss-phone-card span {
            color: rgba(255,255,255,.66);
            font-size: .86rem;
        }

        .rss-phone-button {
            border-radius: 999px;
            background: var(--rss-gold);
            color: #111;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin-top: .7rem;
        }

        .rss-money-band {
            background:
                radial-gradient(circle at 20% 0%, rgba(101,213,138,.14), transparent 24rem),
                rgba(0,0,0,.22);
        }

        .rss-money-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
            gap: 1.5rem;
            align-items: center;
        }

        .rss-quote {
            background: linear-gradient(135deg, rgba(231,199,90,.13), rgba(106,168,255,.1));
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

            .rss-product-frame {
                position: relative;
                right: auto;
                top: auto;
                width: min(100%, 620px);
                transform: none;
                margin: 0 auto 2rem;
                opacity: .9;
            }

            .rss-hero-scene {
                position: relative;
                order: 2;
                padding: 0 1.25rem 2rem;
            }

            .rss-hero::before {
                background: linear-gradient(180deg, rgba(8,9,16,.96), rgba(8,9,16,.78));
            }

            .rss-logo-plate {
                display: none;
            }

            .rss-proof-grid,
            .rss-feature-grid,
            .rss-workflow,
            .rss-money-grid,
            .rss-faq-grid {
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

            .rss-hero-content {
                padding-top: 3.2rem;
            }

            .rss-actions .btn,
            .rss-final-box .btn {
                width: 100%;
                justify-content: center;
            }

            .rss-demo-actions {
                grid-template-columns: 1fr;
            }

            .rss-phone-screen {
                min-height: 460px;
            }
        }
    </style>
</head>
<body>
    <header class="rss-header">
        <div class="container rss-header-inner">
            <a class="rss-brand" href="<?= htmlspecialchars($siteBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
                <span class="rss-brand-mark">SM</span>
                <span class="rss-brand-text">
                    <span class="rss-brand-name">Set Maxx</span>
                    <span class="rss-brand-tagline">by Ready Set Shows</span>
                </span>
            </a>
            <nav class="rss-nav" aria-label="Ready Set Shows navigation">
                <a href="#features">Features</a>
                <a href="#workflow">How it works</a>
                <a href="<?= htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8') ?>">Suite</a>
                <a class="rss-nav-login" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Log In</a>
                <a class="rss-nav-primary" href="<?= htmlspecialchars($pricingUrl, ENT_QUOTES, 'UTF-8') ?>">Start Trial</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="rss-hero">
            <div class="rss-hero-scene" aria-hidden="true">
                <div class="rss-product-frame">
                    <div class="rss-frame-top">
                        <span class="rss-dots"><span></span><span></span><span></span></span>
                        <span>Live request dashboard</span>
                    </div>
                    <div class="rss-frame-body">
                        <div class="rss-request-demo">
                            <div>
                                <div class="rss-demo-title">24K Magic</div>
                                <div class="rss-demo-meta">Bruno Mars - from Sarah</div>
                            </div>
                            <span class="rss-demo-pill">$20</span>
                        </div>
                        <div class="rss-request-demo">
                            <div>
                                <div class="rss-demo-title">September</div>
                                <div class="rss-demo-meta">Earth, Wind &amp; Fire - from Mike</div>
                            </div>
                            <span class="rss-demo-pill">$10</span>
                        </div>
                        <div class="rss-request-demo">
                            <div>
                                <div class="rss-demo-title">Sweet Caroline</div>
                                <div class="rss-demo-meta">Neil Diamond - free request</div>
                            </div>
                            <span class="rss-demo-pill">$0</span>
                        </div>
                        <div class="rss-demo-actions">
                            <span>Queue</span>
                            <span>Played</span>
                            <span>Lyrics</span>
                        </div>
                    </div>
                </div>
                <img class="rss-logo-plate" src="<?= htmlspecialchars($assetBase . '/uploads/setmaxx/setmaxx-logo-1-253b39c20e.png', ENT_QUOTES, 'UTF-8') ?>" alt="">
            </div>

            <div class="container">
                <div class="rss-hero-content">
                    <div class="rss-kicker"><i class="fa-solid fa-bolt"></i> The request button your tip jar wished it had</div>
                    <h1>Turn your setlist into a paycheck.</h1>
                    <p class="rss-hero-copy">
                        Set Maxx helps working musicians take live song requests, collect tips, offer card and Venmo options, and give the crowd a clean way to participate without taking over the room.
                    </p>
                    <div class="rss-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($pricingUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-play"></i> Start Free Trial
                        </a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($setmaxxUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-list-check"></i> Open Set Maxx
                        </a>
                    </div>
                    <div class="rss-hero-note">
                        <span>Paid requests and tips</span>
                        <span>Card and Venmo options</span>
                        <span>Custom links for every session</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="rss-section">
            <div class="container">
                <div class="rss-proof-grid">
                    <div class="rss-proof-item">
                        <div class="rss-proof-value">$$$</div>
                        <p>You decide the lowest amount per request for each show, and even each song. Freebird? That will be $100, please.</p>
                    </div>
                    <div class="rss-proof-item">
                        <div class="rss-proof-value">QR</div>
                        <p>Your QR code links to the current active show. People scan, select, and ka-ching!</p>
                    </div>
                    <div class="rss-proof-item">
                        <div class="rss-proof-value">Live</div>
                        <p>Control the queue by marking incoming requests as played and seeing who paid what to make certain songs a priority.</p>
                    </div>
                    <div class="rss-proof-item">
                        <div class="rss-proof-value">Flow</div>
                        <p>Every session is saved, so the requests, tips, notes, and outcomes are still there after the lights come up.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rss-section" id="features">
            <div class="container">
                <div class="rss-section-head">
                    <span class="rss-eyebrow">Built for the gig, not the office</span>
                    <h2>Everything the room needs. Less of what slows you down.</h2>
                    <p>Set Maxx keeps the audience interaction simple on the public side and gives the performer enough control to protect the show.</p>
                </div>

                <div class="rss-feature-grid">
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-dollar-sign"></i></span>
                        <h3>Paid request nudges</h3>
                        <p>Start requests at a friendly paid amount while keeping a visible free option for the right rooms.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-qrcode"></i></span>
                        <h3>Public request pages</h3>
                        <p>Create a clean link or QR code for each live session, with song search, tips, suggestions, and your own show details built in.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-music"></i></span>
                        <h3>Song catalog control</h3>
                        <p>Manage the songs you want requested, then let the crowd choose from the menu instead of yelling the specials.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-microphone-lines"></i></span>
                        <h3>Performer dashboard</h3>
                        <p>See requester names, notes, amounts, payment method, status, session history, and lyrics links when you need a quick refresh.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-layer-group"></i></span>
                        <h3>Custom sessions</h3>
                        <p>Set up each gig with its own title, venue, Venmo setting, live status, and public link so tonight feels like tonight.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-lock"></i></span>
                        <h3>Website and review links</h3>
                        <p>Send happy guests back to your website, your review page, or wherever the next useful click should go.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-brands fa-stripe"></i></span>
                        <h3>Card, Venmo, and tips</h3>
                        <p>Use card checkout for paid requests and tips, then turn on Venmo for sessions where that is the room's native language.</p>
                    </article>
                    <article class="rss-feature-card">
                        <span class="rss-feature-icon"><i class="fa-solid fa-lightbulb"></i></span>
                        <h3>Future song ideas</h3>
                        <p>Let guests suggest songs you do not currently perform without turning tonight's request queue into a homework assignment.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rss-section" id="workflow">
            <div class="container rss-workflow">
                <div class="rss-phone" aria-label="Set Maxx public request page preview">
                    <div class="rss-phone-screen">
                        <div class="rss-phone-brand">
                            <img src="<?= htmlspecialchars($assetBase . '/uploads/setmaxx/setmaxx-logo-1-253b39c20e.png', ENT_QUOTES, 'UTF-8') ?>" alt="Set Maxx logo">
                            <div>
                                <strong>Live requests</strong>
                                <div class="rss-demo-meta">Tonight's show</div>
                            </div>
                        </div>
                        <div class="rss-phone-card">
                            <strong>Tip the performer</strong>
                            <span>Card checkout or Venmo, depending on the session.</span>
                        </div>
                        <div class="rss-phone-card">
                            <strong>Search songs or artists</strong>
                            <span>Filter the list, tap a song, then request it.</span>
                        </div>
                        <div class="rss-phone-card">
                            <strong>September</strong>
                            <span>Earth, Wind &amp; Fire</span>
                            <div class="rss-phone-button">Request with card</div>
                        </div>
                        <div class="rss-phone-card">
                            <strong>Suggest a song</strong>
                            <span>Capture future-show ideas without derailing tonight.</span>
                        </div>
                        <div class="rss-phone-card">
                            <strong>Website and reviews</strong>
                            <span>Send fans back to your site or favorite review page.</span>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="rss-section-head">
                        <span class="rss-eyebrow">How it works</span>
                        <h2>From song list to live request page in minutes.</h2>
                        <p>The audience sees a simple, compact experience. You get the control surface behind it.</p>
                    </div>
                    <div class="rss-steps">
                        <article class="rss-step">
                            <span class="rss-step-number">1</span>
                            <div>
                                <h3>Load your songs</h3>
                                <p>Import or manage the songs you actually want available for requests.</p>
                            </div>
                        </article>
                        <article class="rss-step">
                            <span class="rss-step-number">2</span>
                            <div>
                                <h3>Create a live session</h3>
                                <p>Customize the gig title, venue, public links, review link, and Venmo setting, then share the public page.</p>
                            </div>
                        </article>
                        <article class="rss-step">
                            <span class="rss-step-number">3</span>
                            <div>
                                <h3>Let the room participate</h3>
                                <p>Guests search, request, tip, suggest songs, visit your site, or leave a review without interrupting the stage.</p>
                            </div>
                        </article>
                        <article class="rss-step">
                            <span class="rss-step-number">4</span>
                            <div>
                                <h3>Run the queue</h3>
                                <p>Mark requests queued, played, or declined, keep a lyrics link close, and come back later to the saved session history.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="rss-section rss-money-band">
            <div class="container rss-money-grid">
                <div class="rss-section-head">
                    <span class="rss-eyebrow">Simple and profitable</span>
                    <h2>Make requests feel like part of the show, not a side hustle.</h2>
                    <p>
                        The goal is not to pressure the room. It is to make support obvious, keep free participation available, and give generous guests an easy card or Venmo path when they want a song to jump the line.
                    </p>
                    <div class="rss-actions" style="margin-top:1.2rem;">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($pricingUrl, ENT_QUOTES, 'UTF-8') ?>">Start Free Trial</a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8') ?>">See the full suite</a>
                    </div>
                </div>
                <aside class="rss-quote">
                    <strong>Play the requests that pay.</strong>
                    <p>Use the public page to collect paid requests, standalone tips, Venmo-recorded payments, and future song ideas while keeping your set under your control.</p>
                </aside>
            </div>
        </section>

        <section class="rss-section">
            <div class="container">
                <div class="rss-section-head">
                    <span class="rss-eyebrow">Ready Set Shows suite</span>
                    <h2>Built around the way working musicians actually gig.</h2>
                    <p>Ready Set Shows is growing around the same mission: practical tools for musicians who play real rooms and need less admin between gigs.</p>
                </div>
                <div class="rss-faq-grid">
                    <article class="rss-faq-item">
                        <h3>Calendar tools</h3>
                        <p>Check availability, format dates, and prep show exports when booking work needs to move fast.</p>
                    </article>
                    <article class="rss-faq-item">
                        <h3>Publishing tools <span>Soon</span></h3>
                        <p>Planned tools for turning show data into event copy, newsletters, and social posts.</p>
                    </article>
                    <article class="rss-faq-item">
                        <h3>Finance <span>Soon</span></h3>
                        <p>Coming-soon workflows for deposits, balances, revenue totals, and better gig-value visibility.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rss-final-cta">
            <div class="container">
                <div class="rss-final-box">
                    <div>
                        <h2>Ready to make requests work for you?</h2>
                        <p>Open Set Maxx, build your request page, and give the room a better way to ask.</p>
                    </div>
                    <div class="rss-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($pricingUrl, ENT_QUOTES, 'UTF-8') ?>">Start Free Trial</a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($setmaxxUrl, ENT_QUOTES, 'UTF-8') ?>">Open Set Maxx</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="rss-footer">
        <div class="container">
            &copy; <?= date('Y') ?> Ready Set Shows. Set Maxx is built for working musicians.
            <a href="<?= htmlspecialchars($siteBase . '/privacy.php', ENT_QUOTES, 'UTF-8') ?>">Privacy Policy</a>
        </div>
    </footer>
</body>
</html>
