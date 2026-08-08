<?php
require __DIR__ . '/../_private/_core/bootstrap.php';

Auth::requireLogin(base_url('member/artist_onboarding.php'));
$_SESSION['auth_brand'] = 'rss';

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$err = null;
$artistGenreOptions = ['tribute', 'variety', 'pop', 'rock', 'originals', 'acoustic', 'alternative', 'reggae', 'country', 'blues', 'funk', 'jazz', 'dj', 'polka', 'mariachi', 'R&B', 'bluegrass'];

if (Auth::accountTypeColumnExists($pdo) && Auth::accountTypeForUser($pdo, $userId) !== 'artist') {
	Auth::updateAccountType($pdo, $userId, 'artist');
	$user = Auth::currentUser($pdo);
}

function rss_onboarding_table_exists(PDO $pdo, string $tableName): bool
{
	$stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
	$stmt->execute([$tableName]);
	return (bool)$stmt->fetchColumn();
}

function rss_onboarding_column_exists(PDO $pdo, string $columnName): bool
{
	$stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = ? LIMIT 1");
	$stmt->execute([$columnName]);
	return (bool)$stmt->fetchColumn();
}

function rss_onboarding_clean_text($value, int $maxLength = 190): ?string
{
	$text = trim(preg_replace('/\s+/', ' ', (string)$value) ?? '');
	return $text === '' ? null : mb_substr($text, 0, $maxLength);
}

function rss_onboarding_clean_url($value): ?string
{
	$url = trim((string)$value);
	if ($url === '') return null;
	if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
	return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 255) : null;
}

function rss_onboarding_clean_email($value): ?string
{
	$email = strtolower(trim((string)$value));
	if ($email === '') return null;
	return filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 190) : null;
}

function rss_onboarding_clean_state($value): ?string
{
	$state = strtoupper(trim((string)$value));
	return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
}

$profileReady = rss_onboarding_table_exists($pdo, 'setmaxx_public_profiles')
	&& rss_onboarding_column_exists($pdo, 'directory_visible')
	&& rss_onboarding_column_exists($pdo, 'directory_state')
	&& rss_onboarding_column_exists($pdo, 'artist_name')
	&& rss_onboarding_column_exists($pdo, 'website_url')
	&& rss_onboarding_column_exists($pdo, 'contact_email')
	&& rss_onboarding_column_exists($pdo, 'logo_path')
	&& rss_onboarding_column_exists($pdo, 'directory_genres')
	&& rss_onboarding_column_exists($pdo, 'directory_description');

if (is_post()) {
	if (!csrf_verify($_POST['_csrf'] ?? null)) {
		$err = 'Security check failed. Please try again.';
	} elseif (!$profileReady) {
		$err = 'The public artist profile migration needs to be applied before setup can be saved.';
	} else {
		try {
			$artistName = rss_onboarding_clean_text($_POST['artist_name'] ?? '', 190);
			$websiteUrl = rss_onboarding_clean_url($_POST['website_url'] ?? '');
			$contactEmail = rss_onboarding_clean_email($_POST['contact_email'] ?? '');
			$directoryState = rss_onboarding_clean_state($_POST['directory_state'] ?? '');
			$postedGenres = isset($_POST['directory_genres']) && is_array($_POST['directory_genres']) ? $_POST['directory_genres'] : [];
			$directoryGenres = array_values(array_intersect($artistGenreOptions, array_map('strval', $postedGenres)));
			$directoryGenresText = implode(',', $directoryGenres);
			$directoryDescription = rss_onboarding_clean_text($_POST['directory_description'] ?? '', 700);
			$logoPath = null;

			if (!$artistName) throw new RuntimeException('Add your artist or band name.');
			if (trim((string)($_POST['website_url'] ?? '')) !== '' && $websiteUrl === null) throw new RuntimeException('Website link is not valid.');
			if (!$contactEmail) throw new RuntimeException('Add a valid contact email.');
			if (!$directoryState) throw new RuntimeException('Add your two-letter directory state.');

			if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
				$tmpPath = (string)$_FILES['logo_file']['tmp_name'];
				$size = (int)($_FILES['logo_file']['size'] ?? 0);
				if ($size <= 0 || $size > 2 * 1024 * 1024) throw new RuntimeException('Image must be under 2 MB.');
				$info = @getimagesize($tmpPath);
				$mime = $info['mime'] ?? '';
				$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
				if (!isset($extensions[$mime])) throw new RuntimeException('Image must be a JPG, PNG, WEBP, or GIF.');
				$uploadDir = dirname(__DIR__, 2) . '/assets/uploads/setmaxx';
				if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) throw new RuntimeException('Image upload folder could not be created.');
				$fileName = 'setmaxx-logo-' . $userId . '-' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
				$targetPath = $uploadDir . '/' . $fileName;
				if (!move_uploaded_file($tmpPath, $targetPath)) throw new RuntimeException('Image could not be saved.');
				$logoPath = '../assets/uploads/setmaxx/' . $fileName;
			}

			$pdo->prepare(
				"INSERT INTO setmaxx_public_profiles (user_id, directory_visible, directory_state, directory_show_song_count, directory_show_songlist, directory_genres, directory_description, artist_name, website_url, contact_email, logo_path)
				 VALUES (?, 1, ?, 1, 0, ?, ?, ?, ?, ?, ?)
				 ON DUPLICATE KEY UPDATE directory_visible = VALUES(directory_visible), directory_state = VALUES(directory_state), directory_genres = VALUES(directory_genres), directory_description = VALUES(directory_description), artist_name = VALUES(artist_name), website_url = VALUES(website_url), contact_email = VALUES(contact_email), logo_path = COALESCE(VALUES(logo_path), logo_path)"
			)->execute([$userId, $directoryState, $directoryGenresText !== '' ? $directoryGenresText : null, $directoryDescription, $artistName, $websiteUrl, $contactEmail, $logoPath]);

			flash_set('success', 'Your artist profile is ready.');
			redirect(base_url('member/settings.php?brand=rss'));
		} catch (Throwable $e) {
			$err = $e->getMessage();
		}
	}
}

$loginUrl = base_url('member/login.php?brand=rss');
$trialUrl = base_url('member/pricing.php');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Set Up Artist Profile | Ready Set Shows</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header_lite.php'; ?>
<main class="container" style="padding:3rem 0; max-width:760px;">
  <h1>Set up your artist profile</h1>
  <p class="muted">Add the basics now so your band can appear in the directory and receive booking requests.</p>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($err) ?></div>
  <?php endif; ?>

  <?php if (!$profileReady): ?>
    <div class="alert" style="margin:1rem 0;">The public artist profile migration needs to be applied before setup can be saved.</div>
  <?php endif; ?>

  <form class="form" method="post" enctype="multipart/form-data" style="max-width:560px;">
    <h2 class="form-title">Artist Directory Basics</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-field">
      <label>Artist or band name*</label>
      <input type="text" name="artist_name" required placeholder="Your stage name or band name" value="<?= e((string)($_POST['artist_name'] ?? ($user['display_name'] ?? ''))) ?>">
    </div>
    <div class="form-field">
      <label style="margin-top:1rem;">Website</label>
      <input type="text" name="website_url" placeholder="https://your-site.com" value="<?= e((string)($_POST['website_url'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label style="margin-top:1rem;">Contact email*</label>
      <input type="email" name="contact_email" required placeholder="booking@your-site.com" value="<?= e((string)($_POST['contact_email'] ?? ($user['email'] ?? ''))) ?>">
    </div>
    <div class="form-field">
      <label style="margin-top:1rem;">Directory state*</label>
      <input type="text" name="directory_state" required maxlength="2" pattern="[A-Za-z]{2}" placeholder="IL" value="<?= e((string)($_POST['directory_state'] ?? '')) ?>">
    </div>
    <fieldset class="form-field" style="margin-top:1rem;">
      <legend>Genres</legend>
      <div class="checkbox-grid">
        <?php $selectedGenres = isset($_POST['directory_genres']) && is_array($_POST['directory_genres']) ? array_map('strval', $_POST['directory_genres']) : []; ?>
        <?php foreach ($artistGenreOptions as $genre): ?>
          <label><input type="checkbox" name="directory_genres[]" value="<?= e($genre) ?>" <?= in_array($genre, $selectedGenres, true) ? 'checked' : '' ?>> <?= e($genre) ?></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="form-field">
      <label style="margin-top:1rem;">Band description</label>
      <textarea name="directory_description" rows="3" placeholder="A sentence or two about your band."><?= e((string)($_POST['directory_description'] ?? '')) ?></textarea>
    </div>
    <div class="form-field">
      <label style="margin-top:1rem;">Profile image</label>
      <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif">
    </div>
    <button class="btn btn-primary" style="margin-top:1.25rem;">Save Artist Profile</button>
    <a class="text-link" style="display:inline-block; margin-left:1rem;" href="<?= e(base_url('member/library.php')) ?>">Skip for now</a>
  </form>
</main>
<?php include __DIR__ . '/../../includes/tools_footer_lite.php'; ?>
</body>
</html>
