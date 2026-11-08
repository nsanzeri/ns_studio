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

      <h2>Google Calendar Access</h2>
      <p>
        Ready Set Shows offers an optional Google Calendar connection, separate from basic Google sign-in.
        When you choose Connect Google Calendar, we request read-only access to your calendar list and events.
        We use the calendar list to let you select calendars. For selected calendars and date ranges, we read
        event identifiers, titles, start and end times, locations, and descriptions to provide schedules,
        availability checks, printing, exports, and calendar-related Finance features.
        We do not create, edit, or delete events or calendars in your Google account.
      </p>

      <h2>Retention and Deletion of Google User Data</h2>
      <ul>
        <li>
          <strong>Account information:</strong> Google sign-in identifiers, name, and email used for your
          site account are retained while that account exists. Disconnecting Google Calendar does not delete
          your site account or its sign-in information.
        </li>
        <li>
          <strong>Connection credentials:</strong> We store your connected Google account identifier, email,
          and encrypted access and refresh tokens while the calendar connection is active. Clicking Disconnect
          on the Calendars page deletes that connection record and its tokens from the app's active database.
        </li>
        <li>
          <strong>Saved calendar selections:</strong> Calendar identifiers, names, colors, timezones, and
          settings remain saved until you delete the calendar entry or your site account. Disconnecting keeps
          these selections for reconnection, but stops the app from retrieving their Google events.
        </li>
        <li>
          <strong>Event data:</strong> The Google Calendar integration retrieves events on demand for the
          requested date range and processes them to provide the requested feature. It does not maintain a
          permanent server-side archive of the retrieved Google events. Information you separately save in
          Finance records remains with those records until deleted; downloaded or printed exports remain
          under your control and are not removed by disconnecting.
        </li>
      </ul>
      <p>
        To remove saved calendar selections, use Delete beside the calendar on the Calendars page.
        To delete your site account, use the account deletion option in Settings; an active subscription
        must first be canceled. Account deletion removes the associated Google connection and calendar
        selections from the active database. You may also request deletion of Google-derived information
        by emailing <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></a>.
        You can revoke Google permissions in
        <a href="https://myaccount.google.com/connections">your Google account's third-party connections settings</a>.
        Revoking permissions stops authorized access but does not itself delete saved information in this app;
        use the deletion options above. Deleting information here does not delete your original Google events.
      </p>

      <h2>Use, Sharing, and Protection of Google Data</h2>
      <p>
        Google user data is used to provide the account and calendar features you choose. It is processed
        by our hosting infrastructure to operate those features and may be included in exports or documents
        you choose to generate or share. We do not sell Google user data, use it for advertising, or use it
        to train generalized AI or machine-learning models. Calendar connection tokens are encrypted at rest,
        and access to saved calendars is restricted to the owning site account.
      </p>
      <p>
        Ready Set Shows' use and transfer of information received from Google APIs adheres to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>,
        including the Limited Use requirements.
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

      <p class="muted small">Last updated: November 2, 2026</p>
      <a class="privacy-home" href="<?= htmlspecialchars($siteBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">Return to home</a>
    </article>
  </main>
</body>
</html>
