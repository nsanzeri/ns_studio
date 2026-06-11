<?php
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isLocal = str_contains($currentPath, '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$siteName = $isReadySetShowsHost ? 'Ready Set Shows' : 'Nick Sanzeri Music';
$contactEmail = 'booking@nicksanzeri.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Privacy Policy | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Privacy policy for <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>.">
  <link rel="stylesheet" href="<?= htmlspecialchars($siteBase . '/assets/css/style.css', ENT_QUOTES, 'UTF-8') ?>">
  <style>
    body {
      background: #f7f4ec;
      color: #191716;
    }
    .privacy-page {
      padding: 4rem 1rem;
    }
    .privacy-card {
      max-width: 860px;
      margin: 0 auto;
      background: #fff;
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 8px;
      padding: clamp(1.5rem, 4vw, 3rem);
      box-shadow: 0 18px 50px rgba(0,0,0,.08);
    }
    .privacy-card h1,
    .privacy-card h2 {
      color: #191716;
    }
    .privacy-card h1 {
      margin-top: 0;
    }
    .privacy-card h2 {
      margin-top: 2rem;
    }
    .privacy-card p,
    .privacy-card li {
      line-height: 1.7;
    }
    .privacy-home {
      display: inline-block;
      margin-top: 2rem;
      font-weight: 700;
    }
  </style>
</head>
<body>
  <main class="privacy-page">
    <article class="privacy-card">
      <h1>Privacy Policy</h1>
      <p>
        Your privacy matters. This page explains how <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>
        collects and uses information submitted through this website and related musician tools.
      </p>

      <h2>Information We Collect</h2>
      <p>
        When you contact us, book a show, create an account, purchase a product, or use a tool on this site,
        we may collect information such as your name, email address, phone number, event details, account details,
        song/request details, payment-related records, and other information you choose to provide.
      </p>

      <h2>Google Sign-In</h2>
      <p>
        If you choose to sign in with Google, we use Google OAuth to receive basic profile information such as
        your name, email address, and Google account identifier. We use that information only to create or access
        your account, keep you signed in, and provide the products or tools connected to your account.
      </p>

      <h2>How Information Is Used</h2>
      <p>Information submitted through this website is used to:</p>
      <ul>
        <li>Respond to booking, contact, support, and availability inquiries</li>
        <li>Provide account access, purchased products, and musician tools</li>
        <li>Operate live request, tipping, setlist, and calendar-related features</li>
        <li>Process purchases, subscriptions, or tips through payment providers</li>
        <li>Improve communication, services, and site reliability</li>
      </ul>
      <p>We do not sell or rent your personal information.</p>

      <h2>Third-Party Services</h2>
      <p>
        This website may use third-party services such as Google for sign-in, Stripe for payments, email providers
        for communication, analytics or advertising platforms, and hosting or security providers. These services may
        process information according to their own privacy policies.
      </p>

      <h2>Data Security</h2>
      <p>
        Reasonable measures are taken to protect submitted information. However, no method of transmission over
        the internet or electronic storage is completely secure.
      </p>

      <h2>Your Choices</h2>
      <p>
        You may contact us to ask questions about your information or request help with account information
        associated with this site.
      </p>

      <h2>Contact</h2>
      <p>
        <strong>Nick Sanzeri Music</strong><br>
        <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></a>
      </p>

      <p class="muted small">Last updated: June 2026</p>
      <a class="privacy-home" href="<?= htmlspecialchars($siteBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">Return to home</a>
    </article>
  </main>
</body>
</html>
