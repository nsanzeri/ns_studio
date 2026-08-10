<?php
require_once __DIR__ . '/_common.php';

function setmaxx_ensure_public_profile_table(PDO $pdo): void {
	if (setmaxx_table_exists($pdo, 'setmaxx_public_profiles')) return;
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
		  `free_request_limit` tinyint(3) unsigned NOT NULL DEFAULT 2,
		  `request_badge_1_dollars` tinyint(3) unsigned NOT NULL DEFAULT 5,
		  `request_badge_2_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
		  `request_badge_3_dollars` tinyint(3) unsigned NOT NULL DEFAULT 20,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
		  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `uq_setmaxx_public_profiles_user` (`user_id`),
		  KEY `idx_setmaxx_directory` (`directory_visible`,`directory_state`,`artist_name`),
		  CONSTRAINT `fk_setmaxx_public_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
	");
}

function setmaxx_profile_column_exists(PDO $pdo, string $columnName): bool {
	$stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = ? LIMIT 1");
	$stmt->execute([$columnName]);
	return (bool)$stmt->fetchColumn();
}

function setmaxx_ensure_public_profile_pricing_columns(PDO $pdo): void {
	setmaxx_ensure_public_profile_table($pdo);
	if (!setmaxx_profile_column_exists($pdo, 'directory_visible')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_visible tinyint(1) NOT NULL DEFAULT 1 AFTER user_id");
	}
	if (!setmaxx_profile_column_exists($pdo, 'directory_state')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_state char(2) DEFAULT NULL AFTER directory_visible");
	}
	if (!setmaxx_profile_column_exists($pdo, 'directory_show_song_count')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_show_song_count tinyint(1) NOT NULL DEFAULT 1 AFTER directory_state");
	}
	if (!setmaxx_profile_column_exists($pdo, 'directory_show_songlist')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN directory_show_songlist tinyint(1) NOT NULL DEFAULT 0 AFTER directory_show_song_count");
	}
	if (!setmaxx_profile_column_exists($pdo, 'artist_name')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN artist_name varchar(190) DEFAULT NULL AFTER user_id");
	}
	if (!setmaxx_profile_column_exists($pdo, 'venmo_handle')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN venmo_handle varchar(80) DEFAULT NULL AFTER logo_path");
	}
	if (!setmaxx_profile_column_exists($pdo, 'booking_url')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN booking_url varchar(255) DEFAULT NULL AFTER review_url");
	}
	if (!setmaxx_profile_column_exists($pdo, 'minimum_tip_dollars')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN minimum_tip_dollars tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER logo_path");
	}
	if (!setmaxx_profile_column_exists($pdo, 'suggested_request_dollars')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN suggested_request_dollars tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER minimum_tip_dollars");
	}
	if (!setmaxx_profile_column_exists($pdo, 'price_step_dollars')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN price_step_dollars tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER suggested_request_dollars");
	}
	if (!setmaxx_profile_column_exists($pdo, 'free_request_limit')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN free_request_limit tinyint(3) unsigned NOT NULL DEFAULT 2 AFTER price_step_dollars");
	}
	if (!setmaxx_profile_column_exists($pdo, 'request_badge_1_dollars')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN request_badge_1_dollars tinyint(3) unsigned NOT NULL DEFAULT 5 AFTER free_request_limit");
	}
	if (!setmaxx_profile_column_exists($pdo, 'request_badge_2_dollars')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN request_badge_2_dollars tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER request_badge_1_dollars");
	}
	if (!setmaxx_profile_column_exists($pdo, 'request_badge_3_dollars')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN request_badge_3_dollars tinyint(3) unsigned NOT NULL DEFAULT 20 AFTER request_badge_2_dollars");
	}
}

function setmaxx_session_column_exists(PDO $pdo, string $columnName): bool {
	$stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_gig_sessions' AND column_name = ? LIMIT 1");
	$stmt->execute([$columnName]);
	return (bool)$stmt->fetchColumn();
}

function setmaxx_ensure_session_venmo_column(PDO $pdo): void {
	if (!setmaxx_session_column_exists($pdo, 'venmo_enabled')) {
		$pdo->exec("ALTER TABLE setmaxx_gig_sessions ADD COLUMN venmo_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER status");
	}
}

function setmaxx_clean_venmo_handle($value): ?string {
	$handle = ltrim(trim((string)$value), '@');
	if ($handle === '') return null;
	return preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $handle) ? $handle : null;
}

function setmaxx_clean_public_text($value, int $maxLength = 190): ?string {
	$text = trim(preg_replace('/\s+/', ' ', (string)$value) ?? '');
	if ($text === '') return null;
	return mb_substr($text, 0, $maxLength);
}

function setmaxx_clean_public_url($value): ?string {
	$url = trim((string)$value);
	if ($url === '') return null;
	if (!preg_match('#^https?://#i', $url)) {
		$url = 'https://' . $url;
	}
	return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 255) : null;
}

function setmaxx_clean_directory_state($value): ?string {
	$state = strtoupper(trim((string)$value));
	return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
}

function setmaxx_public_profile(PDO $pdo, int $userId): array {
	setmaxx_ensure_public_profile_pricing_columns($pdo);
	$stmt = $pdo->prepare("SELECT directory_visible, directory_state, directory_show_song_count, directory_show_songlist, artist_name, website_url, review_url, booking_url, logo_path, venmo_handle, minimum_tip_dollars, suggested_request_dollars, price_step_dollars, free_request_limit, request_badge_1_dollars, request_badge_2_dollars, request_badge_3_dollars FROM setmaxx_public_profiles WHERE user_id = ? LIMIT 1");
	$stmt->execute([$userId]);
	return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['directory_visible' => 1, 'directory_state' => '', 'directory_show_song_count' => 1, 'directory_show_songlist' => 0, 'artist_name' => '', 'website_url' => '', 'review_url' => '', 'booking_url' => '', 'logo_path' => '', 'venmo_handle' => '', 'minimum_tip_dollars' => 10, 'suggested_request_dollars' => 10, 'price_step_dollars' => 1, 'free_request_limit' => 2, 'request_badge_1_dollars' => 5, 'request_badge_2_dollars' => 10, 'request_badge_3_dollars' => 20];
}

$stablePublicUrl = '';
$stableQrUrl = '';
$publicProfile = ['artist_name' => '', 'website_url' => '', 'review_url' => '', 'booking_url' => '', 'logo_path' => ''];
if ($tablesReady) {
	try {
		setmaxx_ensure_session_venmo_column($pdo);
		setmaxx_enforce_single_live_session($pdo, $userId);
		$stableToken = setmaxx_public_link_token($pdo, $userId);
		$stablePublicUrl = setmaxx_absolute_url($stableSessionLinkBase . rawurlencode($stableToken));
		$stableQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=10&data=' . rawurlencode($stablePublicUrl);
		$publicProfile = setmaxx_public_profile($pdo, $userId);
	} catch (Throwable $e) {
		$errors[] = 'Could not prepare your stable public request link.';
	}
}

if ($tablesReady && is_post()) {
	if (!csrf_verify($_POST['_csrf'] ?? null)) {
		$errors[] = 'Your session expired. Refresh the page and try again.';
	} elseif (!$isProUser) {
		$errors[] = 'Live sessions and public request pages are included with Pro.';
	} else {
		$action = (string)($_POST['action'] ?? '');
		try {
			if ($action === 'create_session') {
				$title = trim((string)($_POST['session_title'] ?? ''));
				$venue = trim((string)($_POST['venue_name'] ?? ''));
				$goLive = isset($_POST['go_live']) ? 1 : 0;
				if ($title === '') throw new RuntimeException('Show title is required.');
				$baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'gig';
				$sessionSlug = $baseSlug . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
				$publicToken = bin2hex(random_bytes(16));
				$status = $goLive ? 'live' : 'draft';
				$pdo->beginTransaction();
				$lockStmt = $pdo->prepare("SELECT id FROM setmaxx_gig_sessions WHERE user_id = ? FOR UPDATE");
				$lockStmt->execute([$userId]);
				if ($goLive) {
					$pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live'")->execute([$userId]);
				}
				$stmt = $pdo->prepare("INSERT INTO setmaxx_gig_sessions (user_id, title, venue_name, session_slug, public_token, status, starts_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
				$stmt->execute([$userId, $title, $venue !== '' ? $venue : null, $sessionSlug, $publicToken, $status, $goLive ? date('Y-m-d H:i:s') : null]);
				$pdo->commit();
				setmaxx_enforce_single_live_session($pdo, $userId);
				$messages[] = $goLive ? 'New live session created.' : 'Session created in draft mode.';
			}
			if ($action === 'save_public_profile') {
				setmaxx_ensure_public_profile_pricing_columns($pdo);
				$reviewUrl = setmaxx_clean_public_url($_POST['review_url'] ?? '');
				$bookingUrl = setmaxx_clean_public_url($_POST['booking_url'] ?? '');
				$venmoHandle = setmaxx_clean_venmo_handle($_POST['venmo_handle'] ?? '');
				$minimumTipDollars = max(0, min(100, (int)($_POST['minimum_tip_dollars'] ?? 10)));
				$suggestedRequestDollars = max(0, min(100, (int)($_POST['suggested_request_dollars'] ?? 10)));
				$priceStepDollars = (int)($_POST['price_step_dollars'] ?? 1);
				if (!in_array($priceStepDollars, [1, 5, 10], true)) $priceStepDollars = 1;
				$freeRequestLimit = max(0, min(25, (int)($_POST['free_request_limit'] ?? 2)));
				$requestBadge1Dollars = max(1, min(100, (int)($_POST['request_badge_1_dollars'] ?? 5)));
				$requestBadge2Dollars = max(1, min(100, (int)($_POST['request_badge_2_dollars'] ?? 10)));
				$requestBadge3Dollars = max(1, min(100, (int)($_POST['request_badge_3_dollars'] ?? 20)));
				$directoryVisible = !empty($publicProfile['directory_visible']) ? 1 : 0;
				$directoryState = setmaxx_clean_directory_state($publicProfile['directory_state'] ?? '');
				$artistName = setmaxx_clean_public_text($publicProfile['artist_name'] ?? '', 190);
				$websiteUrl = setmaxx_clean_public_url($publicProfile['website_url'] ?? '');
				$logoPath = trim((string)($publicProfile['logo_path'] ?? ''));
				
				if (trim((string)($_POST['review_url'] ?? '')) !== '' && $reviewUrl === null) throw new RuntimeException('Review link is not valid.');
				if (trim((string)($_POST['booking_url'] ?? '')) !== '' && $bookingUrl === null) throw new RuntimeException('Booking link is not valid.');
				if (trim((string)($_POST['venmo_handle'] ?? '')) !== '' && $venmoHandle === null) throw new RuntimeException('Venmo handle can use letters, numbers, dots, underscores, or hyphens.');
				
				$pdo->prepare(
					"INSERT INTO setmaxx_public_profiles (user_id, directory_visible, directory_state, artist_name, website_url, review_url, booking_url, logo_path, venmo_handle, minimum_tip_dollars, suggested_request_dollars, price_step_dollars, free_request_limit, request_badge_1_dollars, request_badge_2_dollars, request_badge_3_dollars)
					 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
					 ON DUPLICATE KEY UPDATE directory_visible = VALUES(directory_visible), directory_state = VALUES(directory_state), artist_name = VALUES(artist_name), website_url = VALUES(website_url), review_url = VALUES(review_url), booking_url = VALUES(booking_url), logo_path = VALUES(logo_path), venmo_handle = VALUES(venmo_handle), minimum_tip_dollars = VALUES(minimum_tip_dollars), suggested_request_dollars = VALUES(suggested_request_dollars), price_step_dollars = VALUES(price_step_dollars), free_request_limit = VALUES(free_request_limit), request_badge_1_dollars = VALUES(request_badge_1_dollars), request_badge_2_dollars = VALUES(request_badge_2_dollars), request_badge_3_dollars = VALUES(request_badge_3_dollars)"
				)->execute([$userId, $directoryVisible, $directoryState, $artistName, $websiteUrl, $reviewUrl, $bookingUrl, $logoPath !== '' ? $logoPath : null, $venmoHandle, $minimumTipDollars, $suggestedRequestDollars, $priceStepDollars, $freeRequestLimit, $requestBadge1Dollars, $requestBadge2Dollars, $requestBadge3Dollars]);
				$publicProfile = setmaxx_public_profile($pdo, $userId);
				$messages[] = 'Request page settings saved.';
			}
			if ($action === 'session_status') {
				$sessionId = (int)($_POST['session_id'] ?? 0);
				$newStatus = (string)($_POST['new_status'] ?? '');
				if ($sessionId <= 0 || !in_array($newStatus, ['live', 'closed'], true)) throw new RuntimeException('Invalid session update.');
				$pdo->beginTransaction();
				$lockStmt = $pdo->prepare("SELECT id FROM setmaxx_gig_sessions WHERE user_id = ? FOR UPDATE");
				$lockStmt->execute([$userId]);
				if ($newStatus === 'live') {
					$pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE user_id = ? AND status = 'live' AND id <> ?")->execute([$userId, $sessionId]);
					$pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'live', starts_at = COALESCE(starts_at, NOW()), ends_at = NULL WHERE id = ? AND user_id = ?")->execute([$sessionId, $userId]);
					$messages[] = 'Session is now live.';
				} else {
					$pdo->prepare("UPDATE setmaxx_gig_sessions SET status = 'closed', ends_at = NOW() WHERE id = ? AND user_id = ?")->execute([$sessionId, $userId]);
					$messages[] = 'Session closed.';
				}
				$pdo->commit();
				setmaxx_enforce_single_live_session($pdo, $userId);
			}
			if ($action === 'delete_session') {
				$sessionId = (int)($_POST['session_id'] ?? 0);
				if ($sessionId <= 0) throw new RuntimeException('Invalid session delete request.');
				
				$pdo->beginTransaction();
				
				$ownStmt = $pdo->prepare("SELECT id, title FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ? LIMIT 1");
				$ownStmt->execute([$sessionId, $userId]);
				$sessionToDelete = $ownStmt->fetch(PDO::FETCH_ASSOC);
				if (!$sessionToDelete) {
					throw new RuntimeException('Session not found.');
				}
				
				// Delete child requests first so this works whether or not the database has ON DELETE CASCADE.
				$pdo->prepare("DELETE r FROM setmaxx_requests r JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id WHERE r.gig_session_id = ? AND gs.user_id = ?")->execute([$sessionId, $userId]);
				$pdo->prepare("DELETE FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ?")->execute([$sessionId, $userId]);
				
				$pdo->commit();
				$messages[] = 'Session deleted.';
			}
		} catch (Throwable $e) {
			if ($pdo->inTransaction()) $pdo->rollBack();
			$errors[] = $e->getMessage();
		}
	}
}

$sessions = [];
if ($tablesReady) {
	setmaxx_enforce_single_live_session($pdo, $userId);
	setmaxx_ensure_session_venmo_column($pdo);
	$sessionsStmt = $pdo->prepare("SELECT id, title, venue_name, session_slug, public_token, status, starts_at, ends_at, created_at FROM setmaxx_gig_sessions WHERE user_id = ? AND status <> 'closed' ORDER BY FIELD(status, 'live', 'draft'), created_at DESC LIMIT 20");
	$sessionsStmt->execute([$userId]);
	$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
}
setmaxx_page_head('Set Maxx | Show Setup');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Show setup</div>
    <h1 style="margin:.8rem 0 .35rem;">Configure and create a request page for your next show</h1>
    <p class="setmaxx-help">Set up the public request page, QR link, notifications, and pricing before you go live. Catalogs and setlists are free; live public request pages are Pro.</p>
    <?php if (!$isProUser): ?>
      <div class="setmaxx-actions" style="margin-top:1rem;">
        <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade for live request pages</a>
        <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/songs.php')) ?>">Use free song catalog</a>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <div class="setmaxx-section-head">
        <h2>Next show</h2>
        <button class="setmaxx-help-button" type="button" id="setmaxxNewSessionHelpBtn" aria-label="Show new session help" aria-haspopup="dialog">?</button>
      </div>
      <form method="post" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_session">
        <div class="setmaxx-form-grid">
          <div class="setmaxx-field"><label for="session_title">Show title</label><input class="setmaxx-input" id="session_title" name="session_title" placeholder="Friday night show" required></div>
          <div class="setmaxx-field"><label for="venue_name">Venue</label><input class="setmaxx-input" id="venue_name" name="venue_name" placeholder="Venue name"></div>
        </div>
        <label style="display:flex; gap:.6rem; align-items:center;"><input type="checkbox" name="go_live" value="1" checked><span class="setmaxx-help">Make this the live request page now</span></label>
        <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Create request page</button></div>
      </form>
    </div>
    <div class="setmaxx-card">
      <div class="setmaxx-section-head">
        <h2>Permanent request QR</h2>
        <button class="setmaxx-help-button" type="button" id="setmaxxQrHelpBtn" aria-label="Show QR help" aria-haspopup="dialog">?</button>
      </div>
      <p class="setmaxx-help">This QR code stays the same. It always opens whichever session is currently live.</p>
      <?php if (!$isProUser): ?>
        <div class="setmaxx-row"><div class="setmaxx-meta">Permanent QR links and public request pages are included with Pro.</div></div>
      <?php elseif ($stablePublicUrl): ?>
        <div class="setmaxx-qr-wrap">
          <img class="setmaxx-qr-img" src="<?= e($stableQrUrl) ?>" alt="Set Maxx request QR code">
          <div class="setmaxx-link-box"><strong>Public page</strong><code><?= e($stablePublicUrl) ?></code><a class="btn btn-outline" href="<?= e($stablePublicUrl) ?>" target="_blank" rel="noopener">Open</a></div>
        </div>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= e($isProUser ? base_url('/setmaxx/requests.php') : $upgradeUrl) ?>"><?= $isProUser ? 'Open Request Dashboard' : 'Upgrade for Request Dashboard' ?></a>
    </div>
    <div class="setmaxx-card setmaxx-notification-card">
      <div>
        <div class="setmaxx-pill">Show setup</div>
        <div class="setmaxx-section-head setmaxx-notification-head">
          <h2>Request notifications</h2>
          <button class="setmaxx-help-button" type="button" id="setmaxxNotificationsHelpBtn" aria-label="Show notification setup help" aria-haspopup="dialog">?</button>
        </div>
        <p class="setmaxx-help" id="setmaxxPushHelp" style="margin:0;">Enable notifications on the phone you use during the show so new requests can pop up while other apps are open.</p>
      </div>
      <div class="setmaxx-actions">
        <button class="btn btn-outline" type="button" id="setmaxxPushDisableBtn" hidden>Disable on this device</button>
        <button class="btn btn-primary" type="button" id="setmaxxPushEnableBtn">Enable notifications</button>
      </div>
    </div>
  </section>
  <div class="setmaxx-card" style="margin-top:1rem;">
    <div class="setmaxx-section-head">
      <h2>Public page settings</h2>
      <button class="setmaxx-help-button" type="button" id="setmaxxPublicSettingsHelpBtn" aria-label="Show public page settings help" aria-haspopup="dialog">?</button>
    </div>
    <p class="setmaxx-help">Your artist name, image, website, directory visibility, and state are managed in <a href="<?= e(base_url('/member/settings.php')) ?>">Account Settings</a>.</p>
    <form method="post" class="setmaxx-stack" action="">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_public_profile">
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field"><label for="review_url">Review link</label><input class="setmaxx-input" id="review_url" name="review_url" placeholder="Google review page" value="<?= e((string)($publicProfile['review_url'] ?? '')) ?>"></div>
        <div class="setmaxx-field"><label for="booking_url">Booking link</label><input class="setmaxx-input" id="booking_url" name="booking_url" placeholder="Your booking form or calendar link" value="<?= e((string)($publicProfile['booking_url'] ?? '')) ?>"></div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field"><label for="venmo_handle">Venmo handle</label><input class="setmaxx-input" id="venmo_handle" name="venmo_handle" placeholder="@your-venmo" value="<?= e((string)($publicProfile['venmo_handle'] ?? '')) ?>"></div>
        <div class="setmaxx-field"><label>Booking badge</label><div class="setmaxx-help">Shown on the public request page only when a booking link is saved.</div></div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field">
          <label for="minimum_tip_dollars">Lowest paid amount</label>
          <input class="setmaxx-input" id="minimum_tip_dollars" name="minimum_tip_dollars" type="number" min="0" max="100" step="1" value="<?= e((string)((int)($publicProfile['minimum_tip_dollars'] ?? 10))) ?>">
        </div>
        <div class="setmaxx-field">
          <label for="suggested_request_dollars">Suggested price</label>
          <input class="setmaxx-input" id="suggested_request_dollars" name="suggested_request_dollars" type="number" min="0" max="100" step="1" value="<?= e((string)((int)($publicProfile['suggested_request_dollars'] ?? 10))) ?>">
        </div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field">
          <label for="price_step_dollars">Price increments</label>
          <select class="setmaxx-select" id="price_step_dollars" name="price_step_dollars">
            <?php foreach ([1, 5, 10] as $step): ?>
              <option value="<?= $step ?>" <?= (int)($publicProfile['price_step_dollars'] ?? 1) === $step ? 'selected' : '' ?>>$<?= $step ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="setmaxx-field"><label>Request pricing</label><div class="setmaxx-help">The minimum is the lowest allowed paid request. The badge amounts below control the quick-pick buttons.</div></div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field">
          <label for="free_request_limit">Free requests per person</label>
          <input class="setmaxx-input" id="free_request_limit" name="free_request_limit" type="number" min="0" max="25" step="1" value="<?= e((string)((int)($publicProfile['free_request_limit'] ?? 2))) ?>">
        </div>
        <div class="setmaxx-field"><label>Free request limit</label><div class="setmaxx-help">Set to 0 to remove the no-tip option for everyone.</div></div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field">
          <label for="request_badge_1_dollars">Badge amount 1</label>
          <input class="setmaxx-input" id="request_badge_1_dollars" name="request_badge_1_dollars" type="number" min="1" max="100" step="1" value="<?= e((string)((int)($publicProfile['request_badge_1_dollars'] ?? 5))) ?>">
        </div>
        <div class="setmaxx-field">
          <label for="request_badge_2_dollars">Badge amount 2</label>
          <input class="setmaxx-input" id="request_badge_2_dollars" name="request_badge_2_dollars" type="number" min="1" max="100" step="1" value="<?= e((string)((int)($publicProfile['request_badge_2_dollars'] ?? 10))) ?>">
        </div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field">
          <label for="request_badge_3_dollars">Badge amount 3</label>
          <input class="setmaxx-input" id="request_badge_3_dollars" name="request_badge_3_dollars" type="number" min="1" max="100" step="1" value="<?= e((string)((int)($publicProfile['request_badge_3_dollars'] ?? 20))) ?>">
        </div>
        <div class="setmaxx-field"><label>Request badges</label><div class="setmaxx-help">The public page shows these three amounts plus Other.</div></div>
      </div>
      <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Save public settings</button></div>
    </form>
  </div>
  <div class="setmaxx-card" style="margin-top:1rem;">
    <div class="setmaxx-section-head">
      <h2>Current session</h2>
      <button class="setmaxx-help-button" type="button" id="setmaxxSessionsHelpBtn" aria-label="Show current session help" aria-haspopup="dialog">?</button>
    </div>
    <div class="setmaxx-actions" style="margin-bottom:1rem;">
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/history.php')) ?>">View history</a>
    </div>
    <div class="setmaxx-list">
      <?php if (!$sessions): ?>
        <div class="setmaxx-row"><div class="setmaxx-meta">No current or draft request page right now.</div></div>
      <?php else: foreach ($sessions as $session): ?>
        <div class="setmaxx-row setmaxx-session-row">
          <div class="setmaxx-session-main">
            <div class="setmaxx-session-title-row"><div style="font-weight:600;"><?= e($session['title']) ?></div><?= setmaxx_status_pill((string)$session['status']) ?></div>
            <div class="setmaxx-meta"><?= e((string)($session['venue_name'] ?: 'Venue not set')) ?></div>
            <?php if (($session['status'] ?? '') === 'live' && $stablePublicUrl): ?>
              <div class="setmaxx-link-box setmaxx-session-link"><strong>Live public page</strong><code><?= e($stablePublicUrl) ?></code><a class="btn btn-outline" href="<?= e($stablePublicUrl) ?>" target="_blank" rel="noopener">Open</a></div>
            <?php else: ?>
              <div style="margin-top:.7rem;"><a class="btn btn-outline" href="<?= e(base_url('/setmaxx/session.php?id=' . (int)$session['id'])) ?>">Open history</a></div>
            <?php endif; ?>
          </div>
          <div class="setmaxx-actions setmaxx-session-actions">
            <?php if (($session['status'] ?? '') !== 'live'): ?>
              <form method="post" action=""><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="session_status"><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><input type="hidden" name="new_status" value="live"><button class="btn btn-outline" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Go live</button></form>
            <?php else: ?>
              <form method="post" action=""><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="session_status"><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><input type="hidden" name="new_status" value="closed"><button class="btn btn-outline" type="submit">Close</button></form>
            <?php endif; ?>
            <form method="post" action="" onsubmit="return confirm('Delete this session and all requests attached to it? This cannot be undone.');">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_session">
              <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
              <button class="btn btn-outline" style="border-color:rgba(255,120,120,.45); color:#ffb3b3;" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <dialog class="setmaxx-dialog" id="setmaxxNewSessionHelpDialog" aria-labelledby="setmaxxNewSessionHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Show setup help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxNewSessionHelpTitle">Creating a request page</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxNewSessionHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li>A request page is for one show, date, room, or event.</li>
        <li>The show title appears on the public request page and helps you recognize the show later.</li>
        <li>The venue also appears on your public request page and also appears in your session list and request history.</li>
        <li>If "Make this live" is checked, this show immediately becomes the public request page.</li>
        <li>Only one request page can be live at a time. Going live automatically closes any other live page.</li>
        <li>Venmo is controlled by the global Venmo handle in Public page settings.</li>
      </ul>
    </div>
  </dialog>
  <dialog class="setmaxx-dialog" id="setmaxxQrHelpDialog" aria-labelledby="setmaxxQrHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">QR help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxQrHelpTitle">How the permanent QR works</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxQrHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li>This QR code is meant for signs, table tents, business cards, and repeat use.</li>
        <li>The QR code does not change from show to show.</li>
        <li>It routes fans to whichever session is currently live.</li>
        <li>If no session is live, fans will not have an active show request page to use.</li>
        <li>Use "Open Request Dashboard" to watch incoming requests and tips during the show.</li>
      </ul>
    </div>
  </dialog>
  <dialog class="setmaxx-dialog" id="setmaxxPublicSettingsHelpDialog" aria-labelledby="setmaxxPublicSettingsHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Public page help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxPublicSettingsHelpTitle">Public page settings</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxPublicSettingsHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li>Public artist or band name is what fans see on the request page. It can be different from the account name you use to log in.</li>
        <li>The website and review links appear on the public request page so fans can find you again.</li>
        <li>The Venmo handle is saved globally. Leave it blank if you do not want Venmo shown publicly.</li>
        <li>Lowest paid amount is the minimum a fan can choose for a paid request.  For instance, this could be 0, 5 or 10 dollars.  If not 0 that means there will be no free request option for that session.</li>
        <li>Suggested price is the amount selected first in the public request dropdown.  This is to help nudge patrons to pay the suggested amount, even though they could also select lesser amounts in the drop-down if they so choose.  But it is a "nudge" the direction you want them to go.</li>
        <li>Price increments control the dropdown steps, such as $1, $5, or $10 jumps.  This doesn't go up in a linear fashion but in a way that makes the price choices less overwhelming.</li>
        <li>The logo appears on the public request page and is a further way to customize the public facing request page.</li>
      </ul>
    </div>
  </dialog>
  <dialog class="setmaxx-dialog" id="setmaxxSessionsHelpDialog" aria-labelledby="setmaxxSessionsHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Current session help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxSessionsHelpTitle">Managing the current request page</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxSessionsHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li>The live request page is currently receiving public requests through the permanent QR link.</li>
        <li>Draft request pages can be made live with "Go live." That closes any other live page.</li>
        <li>Close ends the active request page and preserves the show history.</li>
        <li>Closed shows move to History so this page stays focused on the next show.</li>
        <li>Delete removes the request page and its attached requests, so use it only for mistakes or tests.</li>
      </ul>
    </div>
  </dialog>
  <dialog class="setmaxx-dialog" id="setmaxxNotificationsHelpDialog" aria-labelledby="setmaxxNotificationsHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Notification help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxNotificationsHelpTitle">Phone notification setup</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxNotificationsHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li>Android usually works from Chrome after you tap Enable notifications and allow the site notification prompt.</li>
        <li>If Android only shows a tiny icon or labels it as possible spam, open Android Settings, then Apps, Chrome, Notifications, and make sure readysetshows.com is allowed and not set to Silent.</li>
        <li>iPhone web notifications usually need Safari, iOS 16.4 or newer, and the site added to the Home Screen before notifications behave like app alerts.</li>
        <li>Enable notifications on the actual phone you will use during the show. Each device has to be enabled separately.</li>
        <li>Browser notifications cannot fully force a large pop-up over every app. The phone still controls quiet mode, focus mode, battery restrictions, and notification style.</li>
      </ul>
    </div>
  </dialog>
  <?php endif; ?>
</main>
<style>
  .setmaxx-qr-wrap { display:grid; gap:.9rem; margin:1rem 0; }
  .setmaxx-qr-img { width:180px; max-width:100%; border-radius:14px; background:#fff; padding:.45rem; }
  .setmaxx-notification-card { align-content:start; }
  .setmaxx-notification-head { margin:.5rem 0 .35rem; }
  .setmaxx-session-row { display:grid; grid-template-columns:minmax(0, 1fr); gap:.85rem; }
  .setmaxx-session-main { min-width:0; }
  .setmaxx-session-title-row { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
  .setmaxx-session-link { margin-top:.7rem; width:100%; align-items:flex-start; }
  .setmaxx-session-link code { display:block; flex:1 1 240px; min-width:0; overflow-wrap:anywhere; word-break:break-word; }
  .setmaxx-session-actions { justify-content:flex-start; padding-top:.7rem; border-top:1px solid rgba(255,255,255,.08); }
  .setmaxx-session-actions form { margin:0; }
  .setmaxx-profile-logo-preview { width:58px; height:58px; object-fit:contain; border-radius:12px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.1); padding:.35rem; }
  .setmaxx-section-head { display:flex; align-items:center; gap:.65rem; margin-bottom:1rem; }
  .setmaxx-section-head h2 { margin:0; }
  .setmaxx-help-button { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:999px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.06); color:#efe7ff; font-weight:700; cursor:pointer; }
  .setmaxx-help-button:hover, .setmaxx-help-button:focus-visible { border-color:rgba(140,107,255,.55); background:rgba(140,107,255,.18); outline:none; }
  .setmaxx-dialog { width:min(560px, calc(100vw - 2rem)); border:1px solid rgba(255,255,255,.12); border-radius:18px; padding:0; background:#151323; color:#fff; box-shadow:0 24px 70px rgba(0,0,0,.55); }
  .setmaxx-dialog::backdrop { background:rgba(0,0,0,.62); backdrop-filter:blur(4px); }
  .setmaxx-dialog-inner { padding:1.15rem; display:grid; gap:1rem; }
  .setmaxx-dialog-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
  .setmaxx-dialog-title { margin:0; font-size:1.15rem; }
  .setmaxx-dialog-close { border:1px solid rgba(255,255,255,.14); border-radius:999px; width:36px; height:36px; background:rgba(255,255,255,.05); color:#fff; cursor:pointer; font-size:1.35rem; line-height:1; }
  .setmaxx-format-list { margin:0; padding-left:1.2rem; color:rgba(255,255,255,.82); line-height:1.75; }
</style>
<script>
(function() {
  const enableBtn = document.getElementById('setmaxxPushEnableBtn');
  const disableBtn = document.getElementById('setmaxxPushDisableBtn');
  const help = document.getElementById('setmaxxPushHelp');
  const endpoint = <?= json_encode(base_url('/setmaxx/push.php')) ?>;
  const swUrl = <?= json_encode(base_url('/sw.js')) ?>;
  const csrf = <?= json_encode(csrf_token()) ?>;

  if (!enableBtn || !help) return;

  function setHelp(text) {
    help.textContent = text;
  }

  function setEnabledUi(enabled) {
    enableBtn.hidden = enabled;
    if (disableBtn) disableBtn.hidden = !enabled;
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  }

  async function registrationAndSubscription() {
    const registration = await navigator.serviceWorker.register(swUrl);
    const subscription = await registration.pushManager.getSubscription();
    return { registration, subscription };
  }

  async function getPublicKey() {
    const response = await fetch(endpoint + '?action=key', { credentials: 'same-origin', cache: 'no-store' });
    const data = await response.json();
    if (!data || !data.success || !data.publicKey) throw new Error('Missing push key.');
    return data.publicKey;
  }

  async function saveSubscription(subscription) {
    const response = await fetch(endpoint + '?action=subscribe', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        _csrf: csrf,
        subscription: JSON.stringify(subscription)
      })
    });
    const data = await response.json();
    if (!data || !data.success) throw new Error((data && data.error) || 'Could not save notification setup.');
    return data;
  }

  async function disableSubscription(subscription) {
    if (!subscription) return;
    await fetch(endpoint + '?action=unsubscribe', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _csrf: csrf, endpoint: subscription.endpoint || '' })
    });
    await subscription.unsubscribe();
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    enableBtn.disabled = true;
    setHelp('This browser does not support web push notifications. On iPhone, add Ready Set Shows to the Home Screen and use iOS 16.4 or newer.');
    return;
  }

  registrationAndSubscription().then(function(result) {
    if (result.subscription) {
      setEnabledUi(true);
      setHelp('Notifications are enabled on this device.');
    } else if (Notification.permission === 'denied') {
      enableBtn.disabled = true;
      setHelp('Notifications are blocked for this site. Re-enable them in your browser or device settings.');
    }
  }).catch(function() {});

  enableBtn.addEventListener('click', async function() {
    enableBtn.disabled = true;
    setHelp('Setting up notifications...');
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        setHelp('Notifications were not enabled. You can try again anytime.');
        return;
      }
      const publicKey = await getPublicKey();
      const result = await registrationAndSubscription();
      const subscription = result.subscription || await result.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey)
      });
      await saveSubscription(subscription);
      setEnabledUi(true);
      setHelp('Notifications are enabled on this device.');
    } catch (error) {
      setHelp('Notification setup is not available right now.');
    } finally {
      enableBtn.disabled = false;
    }
  });

  if (disableBtn) {
    disableBtn.addEventListener('click', async function() {
      disableBtn.disabled = true;
      try {
        const result = await registrationAndSubscription();
        await disableSubscription(result.subscription);
        setEnabledUi(false);
        setHelp('Notifications are disabled on this device.');
      } catch (error) {
        setHelp('Could not disable notifications right now.');
      } finally {
        disableBtn.disabled = false;
      }
    });
  }
})();

(function() {
  function setupDialog(buttonId, dialogId, closeId) {
    const openButton = document.getElementById(buttonId);
    const dialog = document.getElementById(dialogId);
    const closeButton = document.getElementById(closeId);
    if (!openButton || !dialog) return;

    function closeDialog() {
      if (typeof dialog.close === 'function') {
        dialog.close();
      } else {
        dialog.setAttribute('hidden', '');
      }
    }

    openButton.addEventListener('click', function() {
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.removeAttribute('hidden');
      }
    });

    if (closeButton) closeButton.addEventListener('click', closeDialog);
    dialog.addEventListener('click', function(event) {
      if (event.target === dialog) closeDialog();
    });
  }

  setupDialog('setmaxxNewSessionHelpBtn', 'setmaxxNewSessionHelpDialog', 'setmaxxNewSessionHelpClose');
  setupDialog('setmaxxQrHelpBtn', 'setmaxxQrHelpDialog', 'setmaxxQrHelpClose');
  setupDialog('setmaxxPublicSettingsHelpBtn', 'setmaxxPublicSettingsHelpDialog', 'setmaxxPublicSettingsHelpClose');
  setupDialog('setmaxxSessionsHelpBtn', 'setmaxxSessionsHelpDialog', 'setmaxxSessionsHelpClose');
  setupDialog('setmaxxNotificationsHelpBtn', 'setmaxxNotificationsHelpDialog', 'setmaxxNotificationsHelpClose');
})();
</script>
<?php setmaxx_page_foot(); ?>
