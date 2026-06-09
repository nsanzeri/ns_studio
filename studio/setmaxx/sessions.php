<?php
require_once __DIR__ . '/_common.php';

function setmaxx_ensure_public_profile_table(PDO $pdo): void {
	if (setmaxx_table_exists($pdo, 'setmaxx_public_profiles')) return;
	$pdo->exec("
		CREATE TABLE IF NOT EXISTS `setmaxx_public_profiles` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `user_id` int(10) unsigned NOT NULL,
		  `website_url` varchar(255) DEFAULT NULL,
		  `review_url` varchar(255) DEFAULT NULL,
		  `logo_path` varchar(255) DEFAULT NULL,
		  `venmo_handle` varchar(80) DEFAULT NULL,
		  `minimum_tip_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
		  `suggested_request_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
		  `price_step_dollars` tinyint(3) unsigned NOT NULL DEFAULT 1,
		  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
		  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `uq_setmaxx_public_profiles_user` (`user_id`),
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
	if (!setmaxx_profile_column_exists($pdo, 'venmo_handle')) {
		$pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN venmo_handle varchar(80) DEFAULT NULL AFTER logo_path");
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

function setmaxx_clean_public_url($value): ?string {
	$url = trim((string)$value);
	if ($url === '') return null;
	if (!preg_match('#^https?://#i', $url)) {
		$url = 'https://' . $url;
	}
	return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 255) : null;
}

function setmaxx_public_profile(PDO $pdo, int $userId): array {
	setmaxx_ensure_public_profile_pricing_columns($pdo);
	$stmt = $pdo->prepare("SELECT website_url, review_url, logo_path, venmo_handle, minimum_tip_dollars, suggested_request_dollars, price_step_dollars FROM setmaxx_public_profiles WHERE user_id = ? LIMIT 1");
	$stmt->execute([$userId]);
	return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['website_url' => '', 'review_url' => '', 'logo_path' => '', 'venmo_handle' => '', 'minimum_tip_dollars' => 10, 'suggested_request_dollars' => 10, 'price_step_dollars' => 1];
}

$stablePublicUrl = '';
$stableQrUrl = '';
$publicProfile = ['website_url' => '', 'review_url' => '', 'logo_path' => ''];
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
		$errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
	} else {
		$action = (string)($_POST['action'] ?? '');
		try {
			if ($action === 'create_session') {
				$title = trim((string)($_POST['session_title'] ?? ''));
				$venue = trim((string)($_POST['venue_name'] ?? ''));
				$goLive = isset($_POST['go_live']) ? 1 : 0;
				$venmoEnabled = isset($_POST['venmo_enabled']) ? 1 : 0;
				if ($title === '') throw new RuntimeException('Session title is required.');
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
				$stmt = $pdo->prepare("INSERT INTO setmaxx_gig_sessions (user_id, title, venue_name, session_slug, public_token, status, venmo_enabled, starts_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$stmt->execute([$userId, $title, $venue !== '' ? $venue : null, $sessionSlug, $publicToken, $status, $venmoEnabled, $goLive ? date('Y-m-d H:i:s') : null]);
				$pdo->commit();
				setmaxx_enforce_single_live_session($pdo, $userId);
				$messages[] = $goLive ? 'New live session created.' : 'Session created in draft mode.';
			}
			if ($action === 'save_public_profile') {
				setmaxx_ensure_public_profile_pricing_columns($pdo);
				$websiteUrl = setmaxx_clean_public_url($_POST['website_url'] ?? '');
				$reviewUrl = setmaxx_clean_public_url($_POST['review_url'] ?? '');
				$venmoHandle = setmaxx_clean_venmo_handle($_POST['venmo_handle'] ?? '');
				$minimumTipDollars = max(0, min(100, (int)($_POST['minimum_tip_dollars'] ?? 10)));
				$suggestedRequestDollars = max(0, min(100, (int)($_POST['suggested_request_dollars'] ?? 10)));
				$priceStepDollars = (int)($_POST['price_step_dollars'] ?? 1);
				if (!in_array($priceStepDollars, [1, 5, 10], true)) $priceStepDollars = 1;
				$logoPath = trim((string)($publicProfile['logo_path'] ?? ''));
				
				if (trim((string)($_POST['website_url'] ?? '')) !== '' && $websiteUrl === null) throw new RuntimeException('Website link is not valid.');
				if (trim((string)($_POST['review_url'] ?? '')) !== '' && $reviewUrl === null) throw new RuntimeException('Review link is not valid.');
				if (trim((string)($_POST['venmo_handle'] ?? '')) !== '' && $venmoHandle === null) throw new RuntimeException('Venmo handle can use letters, numbers, dots, underscores, or hyphens.');
				
				if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
					$tmpPath = (string)$_FILES['logo_file']['tmp_name'];
					$size = (int)($_FILES['logo_file']['size'] ?? 0);
					if ($size <= 0 || $size > 2 * 1024 * 1024) throw new RuntimeException('Logo must be under 2 MB.');
					$info = @getimagesize($tmpPath);
					$mime = $info['mime'] ?? '';
					$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
					if (!isset($extensions[$mime])) throw new RuntimeException('Logo must be a JPG, PNG, WEBP, or GIF.');
					$uploadDir = dirname(__DIR__, 2) . '/assets/uploads/setmaxx';
					if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) throw new RuntimeException('Logo upload folder could not be created.');
					$fileName = 'setmaxx-logo-' . $userId . '-' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
					$targetPath = $uploadDir . '/' . $fileName;
					if (!move_uploaded_file($tmpPath, $targetPath)) throw new RuntimeException('Logo could not be saved.');
					$logoPath = '../assets/uploads/setmaxx/' . $fileName;
				}
				
				if (!empty($_POST['remove_logo'])) {
					$logoPath = '';
				}
				
				$pdo->prepare(
					"INSERT INTO setmaxx_public_profiles (user_id, website_url, review_url, logo_path, venmo_handle, minimum_tip_dollars, suggested_request_dollars, price_step_dollars)
					 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
					 ON DUPLICATE KEY UPDATE website_url = VALUES(website_url), review_url = VALUES(review_url), logo_path = VALUES(logo_path), venmo_handle = VALUES(venmo_handle), minimum_tip_dollars = VALUES(minimum_tip_dollars), suggested_request_dollars = VALUES(suggested_request_dollars), price_step_dollars = VALUES(price_step_dollars)"
				)->execute([$userId, $websiteUrl, $reviewUrl, $logoPath !== '' ? $logoPath : null, $venmoHandle, $minimumTipDollars, $suggestedRequestDollars, $priceStepDollars]);
				$publicProfile = setmaxx_public_profile($pdo, $userId);
				$messages[] = 'Public page settings saved.';
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
			if ($action === 'session_venmo') {
				$sessionId = (int)($_POST['session_id'] ?? 0);
				$venmoEnabled = isset($_POST['venmo_enabled']) ? 1 : 0;
				if ($sessionId <= 0) throw new RuntimeException('Invalid session update.');
				setmaxx_ensure_session_venmo_column($pdo);
				$pdo->prepare("UPDATE setmaxx_gig_sessions SET venmo_enabled = ? WHERE id = ? AND user_id = ?")->execute([$venmoEnabled, $sessionId, $userId]);
				$messages[] = $venmoEnabled ? 'Venmo is on for that session.' : 'Venmo is off for that session.';
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
	$sessionsStmt = $pdo->prepare("SELECT id, title, venue_name, session_slug, public_token, status, venmo_enabled, starts_at, ends_at, created_at FROM setmaxx_gig_sessions WHERE user_id = ? ORDER BY FIELD(status, 'live', 'draft', 'closed'), created_at DESC LIMIT 20");
	$sessionsStmt->execute([$userId]);
	$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
}
setmaxx_page_head('Set Maxx | Gig Sessions');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Gig Sessions</div>
    <h1 style="margin:.8rem 0 .35rem;">Create request pages for each show</h1>
    <p class="setmaxx-help">Only one session can be live at a time. Going live automatically closes any other live session.</p>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">New session</h2>
      <form method="post" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_session">
        <div class="setmaxx-form-grid">
          <div class="setmaxx-field"><label for="session_title">Session title</label><input class="setmaxx-input" id="session_title" name="session_title" placeholder="Friday at Moretti's" required></div>
          <div class="setmaxx-field"><label for="venue_name">Venue</label><input class="setmaxx-input" id="venue_name" name="venue_name" placeholder="Moretti's Rosemont"></div>
        </div>
        <label style="display:flex; gap:.6rem; align-items:center;"><input type="checkbox" name="go_live" value="1" checked><span class="setmaxx-help">Make this the live request page now</span></label>
        <label style="display:flex; gap:.6rem; align-items:center;"><input type="checkbox" name="venmo_enabled" value="1" <?= !empty($publicProfile['venmo_handle']) ? 'checked' : '' ?>><span class="setmaxx-help">Show Venmo on this session<?= empty($publicProfile['venmo_handle']) ? ' after you add a handle below' : '' ?></span></label>
        <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Create session</button></div>
      </form>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Permanent request QR</h2>
      <p class="setmaxx-help">This QR code stays the same. It always opens whichever session is currently live.</p>
      <?php if ($stablePublicUrl): ?>
        <div class="setmaxx-qr-wrap">
          <img class="setmaxx-qr-img" src="<?= e($stableQrUrl) ?>" alt="Set Maxx request QR code">
          <div class="setmaxx-link-box"><strong>Public page</strong><code><?= e($stablePublicUrl) ?></code><a class="btn btn-outline" href="<?= e($stablePublicUrl) ?>" target="_blank" rel="noopener">Open</a></div>
        </div>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/requests.php')) ?>">Open Request Dashboard</a>
    </div>
  </section>
  <div class="setmaxx-card" style="margin-top:1rem;">
    <h2 style="margin-top:0;">Public page settings</h2>
    <form method="post" enctype="multipart/form-data" class="setmaxx-stack" action="">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_public_profile">
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field"><label for="website_url">Performer website</label><input class="setmaxx-input" id="website_url" name="website_url" placeholder="https://your-site.com" value="<?= e((string)($publicProfile['website_url'] ?? '')) ?>"></div>
        <div class="setmaxx-field"><label for="review_url">Review link</label><input class="setmaxx-input" id="review_url" name="review_url" placeholder="Google review page" value="<?= e((string)($publicProfile['review_url'] ?? '')) ?>"></div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field"><label for="venmo_handle">Venmo handle</label><input class="setmaxx-input" id="venmo_handle" name="venmo_handle" placeholder="@your-venmo" value="<?= e((string)($publicProfile['venmo_handle'] ?? '')) ?>"></div>
        <div class="setmaxx-field"><label>Venmo tracking</label><div class="setmaxx-help">When Venmo is turned on for a session, Set Maxx records those amounts separately as Venmo recorded.</div></div>
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
        <div class="setmaxx-field"><label>Request pricing</label><div class="setmaxx-help">The minimum is the lowest allowed paid request. The suggested price is what the public dropdown selects first.</div></div>
      </div>
      <div class="setmaxx-form-grid">
        <div class="setmaxx-field"><label for="logo_file">Public page logo</label><input class="setmaxx-input" id="logo_file" name="logo_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></div>
        <div class="setmaxx-field">
          <label>Current logo</label>
          <?php if (!empty($publicProfile['logo_path'])): ?>
            <div class="setmaxx-actions"><img class="setmaxx-profile-logo-preview" src="<?= e(base_url((string)$publicProfile['logo_path'])) ?>" alt="Current public logo"><label class="setmaxx-help"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label></div>
          <?php else: ?>
            <div class="setmaxx-help">No logo uploaded yet.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Save public settings</button></div>
    </form>
  </div>
  <div class="setmaxx-card" style="margin-top:1rem;">
    <h2 style="margin-top:0;">Sessions</h2>
    <div class="setmaxx-list">
      <?php if (!$sessions): ?>
        <div class="setmaxx-row"><div class="setmaxx-meta">No gig sessions yet.</div></div>
      <?php else: foreach ($sessions as $session): ?>
        <div class="setmaxx-row">
          <div style="min-width:0; flex:1;">
            <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;"><div style="font-weight:600;"><?= e($session['title']) ?></div><?= setmaxx_status_pill((string)$session['status']) ?></div>
            <div class="setmaxx-meta"><?= e((string)($session['venue_name'] ?: 'Venue not set')) ?></div>
            <div class="setmaxx-meta">Venmo: <?= !empty($session['venmo_enabled']) ? 'On' : 'Off' ?></div>
            <?php if (($session['status'] ?? '') === 'live' && $stablePublicUrl): ?>
              <div class="setmaxx-link-box" style="margin-top:.7rem;"><strong>Live public page</strong><code><?= e($stablePublicUrl) ?></code><a class="btn btn-outline" href="<?= e($stablePublicUrl) ?>" target="_blank" rel="noopener">Open</a></div>
            <?php else: ?>
              <div style="margin-top:.7rem;"><a class="btn btn-outline" href="<?= e(base_url('/setmaxx/session.php?id=' . (int)$session['id'])) ?>">Open history</a></div>
            <?php endif; ?>
          </div>
          <div class="setmaxx-actions">
            <form method="post" action="">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="session_venmo">
              <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
              <label class="setmaxx-help" style="display:flex; gap:.45rem; align-items:center;">
                <input type="checkbox" name="venmo_enabled" value="1" <?= !empty($session['venmo_enabled']) ? 'checked' : '' ?> onchange="this.form.submit()" <?= $isProUser ? '' : 'disabled' ?>>
                Venmo
              </label>
            </form>
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
  <?php endif; ?>
</main>
<style>
  .setmaxx-qr-wrap { display:grid; gap:.9rem; margin:1rem 0; }
  .setmaxx-qr-img { width:180px; max-width:100%; border-radius:14px; background:#fff; padding:.45rem; }
  .setmaxx-profile-logo-preview { width:58px; height:58px; object-fit:contain; border-radius:12px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.1); padding:.35rem; }
</style>
<?php setmaxx_page_foot(); ?>
