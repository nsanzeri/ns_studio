<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

Auth::requireLogin(base_url('member/settings.php'));

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$accountType = Auth::normalizeAccountType((string)($user['account_type'] ?? 'artist'));
$err = null;
$ok = null;
$artistGenreOptions = ['tribute', 'variety', 'pop', 'rock', 'originals', 'acoustic', 'alternative', 'reggae', 'country', 'blues', 'funk', 'jazz', 'dj', 'polka', 'mariachi', 'R&B', 'bluegrass'];

function ns_public_profile_table_exists(PDO $pdo): bool
{
    return rss_table_exists($pdo, 'setmaxx_public_profiles');
}

function ns_public_profile_column_exists(PDO $pdo, string $columnName): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = ? LIMIT 1");
    $stmt->execute([$columnName]);
    return (bool)$stmt->fetchColumn();
}

function ns_ensure_public_artist_profile_table(PDO $pdo): void
{
    if (!ns_public_profile_table_exists($pdo)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `setmaxx_public_profiles` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int(10) unsigned NOT NULL,
              `directory_visible` tinyint(1) NOT NULL DEFAULT 1,
              `directory_state` char(2) DEFAULT NULL,
              `directory_show_song_count` tinyint(1) NOT NULL DEFAULT 1,
              `directory_show_songlist` tinyint(1) NOT NULL DEFAULT 0,
              `artist_name` varchar(190) DEFAULT NULL,
              `website_url` varchar(255) DEFAULT NULL,
              `review_url` varchar(255) DEFAULT NULL,
              `booking_url` varchar(255) DEFAULT NULL,
              `logo_path` varchar(255) DEFAULT NULL,
              `venmo_handle` varchar(80) DEFAULT NULL,
              `minimum_tip_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
              `suggested_request_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
              `price_step_dollars` tinyint(3) unsigned NOT NULL DEFAULT 1,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_setmaxx_public_profiles_user` (`user_id`),
              KEY `idx_setmaxx_directory` (`directory_visible`,`directory_state`,`artist_name`),
              CONSTRAINT `fk_setmaxx_public_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    if (!ns_public_profile_column_exists($pdo, 'directory_visible')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_visible tinyint(1) NOT NULL DEFAULT 1 AFTER user_id");
    }
    if (!ns_public_profile_column_exists($pdo, 'directory_state')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_state char(2) DEFAULT NULL AFTER directory_visible");
    }
    if (!ns_public_profile_column_exists($pdo, 'directory_show_song_count')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_show_song_count tinyint(1) NOT NULL DEFAULT 1 AFTER directory_state");
    }
    if (!ns_public_profile_column_exists($pdo, 'directory_show_songlist')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_show_songlist tinyint(1) NOT NULL DEFAULT 0 AFTER directory_show_song_count");
    }
    if (!ns_public_profile_column_exists($pdo, 'directory_genres')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_genres varchar(500) DEFAULT NULL AFTER directory_show_songlist");
    }
    if (!ns_public_profile_column_exists($pdo, 'directory_description')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_description text DEFAULT NULL AFTER directory_genres");
    }
    if (!ns_public_profile_column_exists($pdo, 'artist_name')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN artist_name varchar(190) DEFAULT NULL AFTER user_id");
    }
    if (!ns_public_profile_column_exists($pdo, 'website_url')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN website_url varchar(255) DEFAULT NULL AFTER artist_name");
    }
    if (!ns_public_profile_column_exists($pdo, 'youtube_url')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN youtube_url varchar(255) DEFAULT NULL AFTER website_url");
    }
    if (!ns_public_profile_column_exists($pdo, 'contact_email')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN contact_email varchar(190) DEFAULT NULL AFTER youtube_url");
    }
    if (!ns_public_profile_column_exists($pdo, 'contact_phone')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN contact_phone varchar(64) DEFAULT NULL AFTER contact_email");
    }
    if (!ns_public_profile_column_exists($pdo, 'logo_path')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN logo_path varchar(255) DEFAULT NULL AFTER website_url");
    }
    if (!ns_public_profile_column_exists($pdo, 'review_url')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN review_url varchar(255) DEFAULT NULL AFTER website_url");
    }
    if (!ns_public_profile_column_exists($pdo, 'booking_url')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN booking_url varchar(255) DEFAULT NULL AFTER review_url");
    }
    if (!ns_public_profile_column_exists($pdo, 'venmo_handle')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN venmo_handle varchar(80) DEFAULT NULL AFTER logo_path");
    }
    if (!ns_public_profile_column_exists($pdo, 'minimum_tip_dollars')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN minimum_tip_dollars tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER logo_path");
    }
    if (!ns_public_profile_column_exists($pdo, 'suggested_request_dollars')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN suggested_request_dollars tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER minimum_tip_dollars");
    }
    if (!ns_public_profile_column_exists($pdo, 'price_step_dollars')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN price_step_dollars tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER suggested_request_dollars");
    }
}

function ns_clean_profile_text($value, int $maxLength = 190): ?string
{
    $text = trim(preg_replace('/\s+/', ' ', (string)$value) ?? '');
    if ($text === '') return null;
    return mb_substr($text, 0, $maxLength);
}

function ns_clean_profile_url($value): ?string
{
    $url = trim((string)$value);
    if ($url === '') return null;
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 255) : null;
}

function ns_clean_profile_email($value): ?string
{
    $email = strtolower(trim((string)$value));
    if ($email === '') return null;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 190) : null;
}

function ns_clean_profile_phone($value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        return '+1 (' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-' . substr($digits, 7);
    }
    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    }
    $phone = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
    return mb_substr($phone, 0, 64);
}

function ns_clean_profile_state($value): ?string
{
    $state = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
}

function ns_public_artist_profile(PDO $pdo, int $userId): array
{
    ns_ensure_public_artist_profile_table($pdo);
    $stmt = $pdo->prepare("SELECT directory_visible, directory_state, directory_show_song_count, directory_show_songlist, directory_genres, directory_description, artist_name, website_url, youtube_url, contact_email, contact_phone, logo_path FROM setmaxx_public_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'directory_visible' => 1,
        'directory_state' => '',
        'directory_show_song_count' => 1,
        'directory_show_songlist' => 0,
        'directory_genres' => '',
        'directory_description' => '',
        'artist_name' => '',
        'website_url' => '',
        'youtube_url' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'logo_path' => '',
    ];
}

function ns_active_stripe_subscription_for_user(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !rss_table_exists($pdo, 'user_subscriptions') || !rss_table_exists($pdo, 'subscription_plans')) {
        return null;
    }

    $toolSlugs = rss_tools_product_slugs();
    $placeholders = implode(',', array_fill(0, count($toolSlugs), '?'));

    $sql = "
        SELECT
            us.id,
            us.status,
            us.stripe_subscription_id,
            us.stripe_customer_id,
            us.current_period_end,
            us.canceled_at,
            sp.name AS plan_name,
            sp.slug AS plan_slug
        FROM user_subscriptions us
        JOIN subscription_plans sp ON sp.id = us.subscription_plan_id
        WHERE us.user_id = ?
          AND us.stripe_customer_id IS NOT NULL
          AND us.stripe_customer_id <> ''
          AND us.status IN ('trialing', 'active', 'past_due', 'unpaid')
          AND (us.current_period_end IS NULL OR us.current_period_end > NOW())
          AND sp.slug IN ($placeholders)
        ORDER BY
          CASE us.status
            WHEN 'active' THEN 1
            WHEN 'trialing' THEN 2
            WHEN 'past_due' THEN 3
            WHEN 'unpaid' THEN 4
            ELSE 5
          END,
          us.id DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $toolSlugs));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

$subscription = ns_active_stripe_subscription_for_user($pdo, $userId);
$hasActiveStripeSubscription = (bool)$subscription;
$manageSubscriptionUrl = base_url('api/create_customer_portal_session.php');
$publicArtistProfile = ns_public_artist_profile($pdo, $userId);

if (is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $err = 'Security check failed. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'account_type') {
            $newAccountType = Auth::normalizeAccountType((string)($_POST['account_type'] ?? 'customer'));
            if ($newAccountType === 'admin') $newAccountType = 'customer';
            Auth::updateAccountType($pdo, $userId, $newAccountType);
            $user = Auth::currentUser($pdo);
            $accountType = Auth::normalizeAccountType((string)($user['account_type'] ?? 'artist'));
            $ok = 'Account type saved.';
        }

        if ($action === 'public_artist_profile') {
            try {
                ns_ensure_public_artist_profile_table($pdo);
                $artistName = ns_clean_profile_text($_POST['artist_name'] ?? '', 190);
                $websiteUrl = ns_clean_profile_url($_POST['website_url'] ?? '');
                $youtubeUrl = ns_clean_profile_url($_POST['youtube_url'] ?? '');
                $contactEmail = ns_clean_profile_email($_POST['contact_email'] ?? '');
                $contactPhone = ns_clean_profile_phone($_POST['contact_phone'] ?? '');
                $directoryState = ns_clean_profile_state($_POST['directory_state'] ?? '');
                $directoryVisible = !empty($_POST['directory_visible']) ? 1 : 0;
                $directoryShowSongCount = !empty($_POST['directory_show_song_count']) ? 1 : 0;
                $directoryShowSonglist = !empty($_POST['directory_show_songlist']) ? 1 : 0;
                $postedGenres = isset($_POST['directory_genres']) && is_array($_POST['directory_genres']) ? $_POST['directory_genres'] : [];
                $directoryGenres = array_values(array_intersect($artistGenreOptions, array_map('strval', $postedGenres)));
                $directoryGenresText = implode(',', $directoryGenres);
                $directoryDescription = ns_clean_profile_text($_POST['directory_description'] ?? '', 700);
                $logoPath = trim((string)($publicArtistProfile['logo_path'] ?? ''));
                $imageErr = null;

                if (trim((string)($_POST['website_url'] ?? '')) !== '' && $websiteUrl === null) {
                    throw new RuntimeException('Website link is not valid.');
                }
                if (trim((string)($_POST['youtube_url'] ?? '')) !== '' && $youtubeUrl === null) {
                    throw new RuntimeException('YouTube link is not valid.');
                }
                if (trim((string)($_POST['contact_email'] ?? '')) !== '' && $contactEmail === null) {
                    throw new RuntimeException('Contact email is not valid.');
                }

                if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
                    $tmpPath = (string)$_FILES['logo_file']['tmp_name'];
                    $size = (int)($_FILES['logo_file']['size'] ?? 0);
                    if ($size <= 0 || $size > 2 * 1024 * 1024) {
                        $imageErr = 'Image must be under 2 MB.';
                    } else {
                        $info = @getimagesize($tmpPath);
                        $mime = $info['mime'] ?? '';
                        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                        if (!isset($extensions[$mime])) {
                            $imageErr = 'Image must be a JPG, PNG, WEBP, or GIF.';
                        } else {
                            $uploadDir = dirname(__DIR__, 2) . '/assets/uploads/setmaxx';
                            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                                $imageErr = 'Image upload folder could not be created.';
                            } else {
                                $fileName = 'setmaxx-logo-' . $userId . '-' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
                                $targetPath = $uploadDir . '/' . $fileName;
                                if (!move_uploaded_file($tmpPath, $targetPath)) {
                                    $imageErr = 'Image could not be saved.';
                                } else {
                                    $logoPath = '../assets/uploads/setmaxx/' . $fileName;
                                }
                            }
                        }
                    }
                }

                if (!empty($_POST['remove_logo'])) {
                    $logoPath = '';
                }

                $pdo->prepare(
                    "INSERT INTO setmaxx_public_profiles (user_id, directory_visible, directory_state, directory_show_song_count, directory_show_songlist, directory_genres, directory_description, artist_name, website_url, youtube_url, contact_email, contact_phone, logo_path)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE directory_visible = VALUES(directory_visible), directory_state = VALUES(directory_state), directory_show_song_count = VALUES(directory_show_song_count), directory_show_songlist = VALUES(directory_show_songlist), directory_genres = VALUES(directory_genres), directory_description = VALUES(directory_description), artist_name = VALUES(artist_name), website_url = VALUES(website_url), youtube_url = VALUES(youtube_url), contact_email = VALUES(contact_email), contact_phone = VALUES(contact_phone), logo_path = VALUES(logo_path)"
                )->execute([$userId, $directoryVisible, $directoryState, $directoryShowSongCount, $directoryShowSonglist, $directoryGenresText !== '' ? $directoryGenresText : null, $directoryDescription, $artistName, $websiteUrl, $youtubeUrl, $contactEmail, $contactPhone, $logoPath !== '' ? $logoPath : null]);

                $publicArtistProfile = ns_public_artist_profile($pdo, $userId);
                if ($imageErr) {
                    $err = 'Public artist profile saved, but the image was not updated. ' . $imageErr;
                } else {
                    $ok = 'Public artist profile saved.';
                }
            } catch (Throwable $e) {
                $err = $e->getMessage();
                $publicArtistProfile = array_merge($publicArtistProfile, [
                    'directory_visible' => !empty($_POST['directory_visible']) ? 1 : 0,
                    'directory_state' => strtoupper(trim((string)($_POST['directory_state'] ?? ''))),
                    'directory_show_song_count' => !empty($_POST['directory_show_song_count']) ? 1 : 0,
                    'directory_show_songlist' => !empty($_POST['directory_show_songlist']) ? 1 : 0,
                    'directory_genres' => implode(',', array_values(array_intersect($artistGenreOptions, array_map('strval', isset($_POST['directory_genres']) && is_array($_POST['directory_genres']) ? $_POST['directory_genres'] : [])))),
                    'directory_description' => trim((string)($_POST['directory_description'] ?? '')),
                    'artist_name' => trim((string)($_POST['artist_name'] ?? '')),
                    'website_url' => trim((string)($_POST['website_url'] ?? '')),
                    'youtube_url' => trim((string)($_POST['youtube_url'] ?? '')),
                    'contact_email' => trim((string)($_POST['contact_email'] ?? '')),
                    'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')),
                ]);
            }
        }

        if ($action === 'password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new1 = (string)($_POST['new_password'] ?? '');
            $new2 = (string)($_POST['new_password2'] ?? '');

            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $hash = (string)$stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $err = 'Your current password was incorrect.';
            } elseif (strlen($new1) < 8) {
                $err = 'Your new password must be at least 8 characters.';
            } elseif ($new1 !== $new2) {
                $err = 'Your new passwords do not match.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($new1, PASSWORD_DEFAULT), $userId]);
                $ok = 'Password updated.';
            }
        }

        if ($action === 'delete') {
            $subscription = ns_active_stripe_subscription_for_user($pdo, $userId);
            if ($subscription) {
                $err = 'You have an active subscription. Please cancel your subscription first, then you can delete your account.';
            } else {
                $confirm = trim((string)($_POST['delete_confirmation'] ?? ''));
                if ($confirm !== 'DELETE') {
                    $err = 'Type DELETE to confirm account removal.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                    $stmt->execute([$userId]);
                    Auth::logout();
                    flash_set('success', 'Your account has been deleted.');
                    redirect(base_url('member/register.php'));
                }
            }
        }
    }
}

$subscription = ns_active_stripe_subscription_for_user($pdo, $userId);
$hasActiveStripeSubscription = (bool)$subscription;
$periodEnd = !empty($subscription['current_period_end']) ? strtotime((string)$subscription['current_period_end']) : false;
$cancelScheduled = !empty($subscription['canceled_at']);
$subscriptionLabel = $subscription['plan_name'] ?? 'Ready Set Shows Pro';
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
if (($_GET['brand'] ?? '') === 'rss') {
    $_SESSION['auth_brand'] = 'rss';
}
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true)
    || (($_SESSION['auth_brand'] ?? '') === 'rss')
    || in_array($accountType, ['artist', 'customer'], true);
$settingsBrandParam = $isReadySetShowsHost ? '?brand=rss' : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= $isReadySetShowsHost ? 'Account Settings | Ready Set Shows' : 'Settings • Nick Sanzeri Studio' ?></title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
</head>
<body>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_header.php';
} else {
  include __DIR__ . '/../../includes/header.php';
}
?>
<main class="container" style="padding:3rem 0; max-width:760px;">
 <h2 class="form-title">Account Settings</h2>
  <p class="muted">Manage your password, subscription, and account.</p>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($err) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($ok) ?></div>
  <?php endif; ?>

  <section class="card" style="padding:1.5rem; margin:1.5rem 0;">
    <h3 class="form-title">Account Type</h3>
    <p class="muted">Choose how you primarily use Ready Set Shows.</p>
    <form class="form" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="account_type">
      <div class="form-field">
        <label>Account type</label>
        <select name="account_type">
          <option value="customer" <?= $accountType === 'customer' ? 'selected' : '' ?>>Customer - I book bands for events</option>
          <option value="artist" <?= $accountType === 'artist' ? 'selected' : '' ?>>Artist - I list/manage my band</option>
        </select>
      </div>
      <button class="btn btn-primary" style="margin-top:1.25rem;">Save account type</button>
    </form>
  </section>

  <?php if ($accountType !== 'customer'): ?>
  <section class="card" style="padding:1.5rem; margin:1.5rem 0;">
    <h3 class="form-title">Public Artist Profile</h3>
    <p class="muted">This is the basic discovery profile used by the public artist directory. It is available whether or not you use Pro request pages.</p>
    <form class="form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="public_artist_profile">
      <div class="form-field">
        <label>Artist or band name</label>
        <input type="text" name="artist_name" placeholder="Your stage name or band name" value="<?= e((string)($publicArtistProfile['artist_name'] ?? '')) ?>">
      </div>
      <div class="form-field">
        <label style="margin-top:1rem;">Website</label>
        <input type="text" name="website_url" placeholder="https://your-site.com" value="<?= e((string)($publicArtistProfile['website_url'] ?? '')) ?>">
      </div>
      <div class="form-field">
        <label style="margin-top:1rem;">YouTube</label>
        <input type="text" name="youtube_url" placeholder="https://youtube.com/@yourband" value="<?= e((string)($publicArtistProfile['youtube_url'] ?? '')) ?>">
      </div>
      <div class="form-field">
        <label style="margin-top:1rem;">Contact email</label>
        <input type="email" name="contact_email" placeholder="booking@your-site.com" value="<?= e((string)($publicArtistProfile['contact_email'] ?? '')) ?>">
      </div>
      <div class="form-field">
        <label style="margin-top:1rem;">Contact phone</label>
        <input type="tel" id="contact_phone" name="contact_phone" placeholder="Optional public phone number" value="<?= e((string)($publicArtistProfile['contact_phone'] ?? '')) ?>">
      </div>
      <div class="form-field">
        <label style="margin-top:1rem;">Directory state</label>
        <input type="text" name="directory_state" maxlength="2" placeholder="IL" value="<?= e((string)($publicArtistProfile['directory_state'] ?? '')) ?>">
      </div>
      <fieldset class="form-field" style="margin-top:1rem;">
        <legend>Genres</legend>
        <div class="checkbox-grid">
          <?php $selectedGenres = array_filter(array_map('trim', explode(',', (string)($publicArtistProfile['directory_genres'] ?? '')))); ?>
          <?php foreach ($artistGenreOptions as $genre): ?>
            <label><input type="checkbox" name="directory_genres[]" value="<?= e($genre) ?>" <?= in_array($genre, $selectedGenres, true) ? 'checked' : '' ?>> <?= e($genre) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="form-field">
        <label style="margin-top:1rem;">Band description</label>
        <textarea name="directory_description" rows="3" placeholder="A sentence or two about your band."><?= e((string)($publicArtistProfile['directory_description'] ?? '')) ?></textarea>
      </div>
      <label style="display:flex; gap:.6rem; align-items:flex-start; margin-top:1rem;">
        <input type="checkbox" name="directory_visible" value="1" <?= !array_key_exists('directory_visible', $publicArtistProfile) || !empty($publicArtistProfile['directory_visible']) ? 'checked' : '' ?>>
        <span>Show me in the public artist directory</span>
      </label>
      <label style="display:flex; gap:.6rem; align-items:flex-start; margin-top:.75rem;">
        <input type="checkbox" name="directory_show_song_count" value="1" <?= !array_key_exists('directory_show_song_count', $publicArtistProfile) || !empty($publicArtistProfile['directory_show_song_count']) ? 'checked' : '' ?>>
        <span>Show active song count</span>
      </label>
      <label style="display:flex; gap:.6rem; align-items:flex-start; margin-top:.75rem;">
        <input type="checkbox" name="directory_show_songlist" value="1" <?= !empty($publicArtistProfile['directory_show_songlist']) ? 'checked' : '' ?>>
        <span>Show my active songlist in the directory</span>
      </label>
      <div class="form-field">
        <label style="margin-top:1rem;">Profile image</label>
        <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif">
        <p class="muted small" style="margin:.35rem 0 0;">JPG, PNG, WEBP, or GIF under 2 MB.</p>
      </div>
      <?php if (!empty($publicArtistProfile['logo_path'])): ?>
        <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin-top:1rem;">
          <img src="<?= e(base_url((string)$publicArtistProfile['logo_path'])) ?>" alt="" style="width:72px; height:72px; object-fit:cover; border-radius:8px;">
          <label class="muted" style="display:flex; gap:.45rem; align-items:center;"><input type="checkbox" name="remove_logo" value="1"> Remove image</label>
        </div>
      <?php endif; ?>
      <button class="btn btn-primary" style="margin-top:1.25rem;">Save public profile</button>
    </form>
  </section>

  <section class="card" style="padding:1.5rem; margin:1.5rem 0;">
    <h3 class="form-title">Subscription</h3>

    <?php if ($hasActiveStripeSubscription): ?>
      <p style="margin:.25rem 0 .6rem;"><strong><?= e($subscriptionLabel) ?></strong></p>
      <p class="muted" style="margin:.25rem 0 1rem;">
        <?php if ($cancelScheduled && $periodEnd): ?>
          Your subscription is scheduled to end on <?= e(date('M j, Y', $periodEnd)) ?>. You can manage it in Stripe.
        <?php elseif ($periodEnd): ?>
          Your subscription is active through <?= e(date('M j, Y', $periodEnd)) ?>. You can cancel or update billing in Stripe.
        <?php else: ?>
          Your subscription is active. You can cancel or update billing in Stripe.
        <?php endif; ?>
      </p>
      <div class="alert" id="portalErr" style="display:none; margin:0 0 1rem;"></div>
      <button class="btn btn-primary" type="button" id="manageSubscriptionBtn">Manage / Cancel Subscription</button>
      <p class="muted" style="font-size:.92rem; margin:1rem 0 0;">
        Cancellation is handled securely through Stripe. If you cancel, Pro access usually remains available until the end of the current billing period.
      </p>
    <?php else: ?>
      <p class="muted" style="margin:.25rem 0 1rem;">No active paid subscription was found for this account.</p>
      <a class="btn btn-outline" href="<?= e(base_url('member/pricing.php') . $settingsBrandParam) ?>">View Plans</a>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="card" style="padding:1.5rem; margin:1.5rem 0;">
    <h3 class="form-title">Change Password</h3>
    <form class="form" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="password">
<div class="form-field">
      <label>Current password</label>
      <input type="password" name="current_password" required autocomplete="current-password">
</div>
<div class="form-field">
      <label style="margin-top:1rem;">New password</label>
      <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
</div>
<div class="form-field">
      <label style="margin-top:1rem;">Confirm new password</label>
      <input type="password" name="new_password2" required minlength="8" autocomplete="new-password">
</div>
      <button class="btn btn-primary" style="margin-top:1.25rem;">Save password</button>
    </form>
  </section>

  <section class="card" style="padding:1.5rem; margin:1.5rem 0; border-color:#c77;">
    <h3 class="form-title">Delete Account</h3>
    <?php if ($hasActiveStripeSubscription): ?>
      <p class="muted">You have an active subscription. Cancel your subscription first, then you can delete your account.</p>
      <button class="btn btn-outline" type="button" id="manageSubscriptionBtnDelete">Manage / Cancel Subscription</button>
    <?php else: ?>
      <p class="muted">This removes your login. If you register again later with the same purchase email, My Products can be rebuilt from past purchases.</p>
      <form class="form" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
<div class="form-field">
        <label>Type DELETE to confirm</label>
        <input type="text" name="delete_confirmation" required>
</div>
        <button class="btn btn-outline" style="margin-top:1.25rem;">Delete account</button>
      </form>
    <?php endif; ?>
  </section>
</main>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_footer_lite.php';
} else {
  include __DIR__ . '/../../includes/footer.php';
}
?>

<script>
(function () {
  const phoneInput = document.getElementById('contact_phone');
  if (!phoneInput) return;

  function formatPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    const local = digits.length === 11 && digits.charAt(0) === '1' ? digits.slice(1) : digits;
    if (local.length <= 3) return local;
    if (local.length <= 6) return '(' + local.slice(0, 3) + ') ' + local.slice(3);
    return '(' + local.slice(0, 3) + ') ' + local.slice(3, 6) + '-' + local.slice(6, 10);
  }

  phoneInput.addEventListener('input', function () {
    phoneInput.value = formatPhone(phoneInput.value);
  });
  phoneInput.addEventListener('blur', function () {
    phoneInput.value = formatPhone(phoneInput.value);
  });
})();
</script>

<?php if ($hasActiveStripeSubscription): ?>
<script>
(function () {
  const portalUrl = <?= json_encode($manageSubscriptionUrl) ?>;
  const csrf = <?= json_encode(csrf_token()) ?>;
  const buttons = [
    document.getElementById('manageSubscriptionBtn'),
    document.getElementById('manageSubscriptionBtnDelete')
  ].filter(Boolean);
  const errBox = document.getElementById('portalErr');

  async function openPortal(button) {
    const originalText = button.textContent;
    buttons.forEach(btn => btn.disabled = true);
    button.textContent = 'Opening Stripe...';
    if (errBox) {
      errBox.style.display = 'none';
      errBox.textContent = '';
    }

    try {
      const response = await fetch(portalUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json'
        },
        body: new URLSearchParams({ _csrf: csrf })
      });

      const text = await response.text();
      let data = {};
      try {
        data = JSON.parse(text);
      } catch (e) {
        throw new Error('Stripe portal returned an invalid response.');
      }

      if (data.url) {
        window.location.href = data.url;
        return;
      }

      throw new Error(data.error || data.detail || 'Unable to open the billing portal.');
    } catch (e) {
      if (errBox) {
        errBox.textContent = e.message || 'Unable to open the billing portal.';
        errBox.style.display = 'block';
      } else {
        alert(e.message || 'Unable to open the billing portal.');
      }
      buttons.forEach(btn => btn.disabled = false);
      button.textContent = originalText;
    }
  }

  buttons.forEach(button => {
    button.addEventListener('click', function () {
      openPortal(button);
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
