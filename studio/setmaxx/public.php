<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$linkToken = trim((string)($_GET['link'] ?? ''));
$errors = [];
$messages = [];
$session = null;
$stableLinkFound = false;
$publicUserId = 0;
$publicDisplayName = '';
$songs = [];
$lockedSongIds = [];
$availableLetters = [];
$songCount = 0;
$publicProfile = ['website_url' => '', 'review_url' => '', 'logo_path' => '', 'venmo_handle' => '', 'minimum_tip_dollars' => 10, 'price_step_dollars' => 1];
$sessionMinimumDollars = 10;
$priceStepDollars = 1;
$venmoHandle = '';
$venmoAvailable = false;

function setmaxx_public_absolute_url(string $path): string {
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function setmaxx_public_tables_ready(PDO $pdo): bool {
    foreach (['setmaxx_songs', 'setmaxx_gig_sessions', 'setmaxx_requests'] as $tableName) {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        $stmt->execute([$tableName]);
        if (!(bool)$stmt->fetchColumn()) {
            return false;
        }
    }
    return true;
}

function setmaxx_public_table_exists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_public_ensure_suggestions_table(PDO $pdo): void {
    if (setmaxx_public_table_exists($pdo, 'setmaxx_song_suggestions')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_song_suggestions` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `gig_session_id` bigint(20) unsigned DEFAULT NULL,
          `user_id` int(10) unsigned NOT NULL,
          `suggested_title` varchar(190) NOT NULL,
          `suggested_artist` varchar(190) DEFAULT NULL,
          `requester_name` varchar(190) DEFAULT NULL,
          `suggestion_note` varchar(255) DEFAULT NULL,
          `status` enum('new','reviewed','added','dismissed') NOT NULL DEFAULT 'new',
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_setmaxx_suggestions_user` (`user_id`,`status`,`created_at`),
          KEY `idx_setmaxx_suggestions_session` (`gig_session_id`,`created_at`),
          CONSTRAINT `fk_setmaxx_suggestions_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_setmaxx_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_public_ensure_mailing_list_table(PDO $pdo): void {
    if (setmaxx_public_table_exists($pdo, 'setmaxx_mailing_list_signups')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_mailing_list_signups` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `gig_session_id` bigint(20) unsigned DEFAULT NULL,
          `user_id` int(10) unsigned NOT NULL,
          `email` varchar(190) NOT NULL,
          `first_name` varchar(100) DEFAULT NULL,
          `source` varchar(80) NOT NULL DEFAULT 'setmaxx_public_page',
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_mailing_user_email` (`user_id`,`email`),
          KEY `idx_setmaxx_mailing_user_created` (`user_id`,`created_at`),
          KEY `idx_setmaxx_mailing_session` (`gig_session_id`,`created_at`),
          CONSTRAINT `fk_setmaxx_mailing_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_setmaxx_mailing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_public_ensure_profile_table(PDO $pdo): void {
    if (setmaxx_public_table_exists($pdo, 'setmaxx_public_profiles')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_public_profiles` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `website_url` varchar(255) DEFAULT NULL,
          `review_url` varchar(255) DEFAULT NULL,
          `logo_path` varchar(255) DEFAULT NULL,
          `venmo_handle` varchar(80) DEFAULT NULL,
          `minimum_tip_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
          `price_step_dollars` tinyint(3) unsigned NOT NULL DEFAULT 1,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_public_profiles_user` (`user_id`),
          CONSTRAINT `fk_setmaxx_public_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_public_profile_column_exists(PDO $pdo, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = ? LIMIT 1");
    $stmt->execute([$columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_public_ensure_profile_pricing_columns(PDO $pdo): void {
    setmaxx_public_ensure_profile_table($pdo);
    if (!setmaxx_public_profile_column_exists($pdo, 'venmo_handle')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN venmo_handle varchar(80) DEFAULT NULL AFTER logo_path");
    }
    if (!setmaxx_public_profile_column_exists($pdo, 'minimum_tip_dollars')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN minimum_tip_dollars tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER logo_path");
    }
    if (!setmaxx_public_profile_column_exists($pdo, 'price_step_dollars')) {
        $pdo->exec("ALTER TABLE setmaxx_public_profiles ADD COLUMN price_step_dollars tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER minimum_tip_dollars");
    }
}

function setmaxx_public_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_public_ensure_venmo_columns(PDO $pdo): void {
    setmaxx_public_ensure_profile_pricing_columns($pdo);
    if (!setmaxx_public_column_exists($pdo, 'setmaxx_gig_sessions', 'venmo_enabled')) {
        $pdo->exec("ALTER TABLE setmaxx_gig_sessions ADD COLUMN venmo_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER status");
    }
    if (!setmaxx_public_column_exists($pdo, 'setmaxx_gig_sessions', 'minimum_request_dollars')) {
        $pdo->exec("ALTER TABLE setmaxx_gig_sessions ADD COLUMN minimum_request_dollars tinyint(3) unsigned DEFAULT NULL AFTER venmo_enabled");
    }
    if (!setmaxx_public_column_exists($pdo, 'setmaxx_requests', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_requests ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
    setmaxx_public_ensure_general_tips_table($pdo);
    if (!setmaxx_public_column_exists($pdo, 'setmaxx_general_tips', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_general_tips ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
}

function setmaxx_public_ensure_general_tips_table(PDO $pdo): void {
    if (setmaxx_public_table_exists($pdo, 'setmaxx_general_tips')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_general_tips` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `gig_session_id` bigint(20) unsigned DEFAULT NULL,
          `user_id` int(10) unsigned NOT NULL,
          `tipper_name` varchar(190) DEFAULT NULL,
          `tip_note` varchar(255) DEFAULT NULL,
          `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
          `status` enum('paid','refunded') NOT NULL DEFAULT 'paid',
          `payment_method` varchar(24) NOT NULL DEFAULT 'stripe',
          `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_general_tips_pi` (`stripe_payment_intent_id`),
          KEY `idx_setmaxx_general_tips_user` (`user_id`,`created_at`),
          KEY `idx_setmaxx_general_tips_session` (`gig_session_id`,`created_at`),
          CONSTRAINT `fk_setmaxx_general_tips_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_setmaxx_general_tips_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_public_profile(PDO $pdo, int $userId): array {
    setmaxx_public_ensure_profile_pricing_columns($pdo);
    $stmt = $pdo->prepare("SELECT website_url, review_url, logo_path, venmo_handle, minimum_tip_dollars, price_step_dollars FROM setmaxx_public_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['website_url' => '', 'review_url' => '', 'logo_path' => '', 'venmo_handle' => '', 'minimum_tip_dollars' => 10, 'price_step_dollars' => 1];
}

function setmaxx_public_venmo_url(string $handle, int $amountDollars, string $note): string {
    $handle = ltrim(trim($handle), '@');
    $query = http_build_query([
        'txn' => 'pay',
        'amount' => number_format($amountDollars, 2, '.', ''),
        'note' => mb_substr($note, 0, 120),
    ]);
    return 'https://venmo.com/' . rawurlencode($handle) . '?' . $query;
}

function setmaxx_public_price_options(int $minimumDollars, int $stepDollars, int $maxDollars = 100): array {
    $minimumDollars = max(1, min($maxDollars, $minimumDollars));
    $stepDollars = in_array($stepDollars, [1, 5, 10], true) ? $stepDollars : 1;
    $start = $minimumDollars;
    if ($stepDollars > 1) {
        $start = (int)(ceil($minimumDollars / $stepDollars) * $stepDollars);
    }
    $options = [];
    for ($amount = $start; $amount <= $maxDollars; $amount += $stepDollars) {
        if ($amount >= $minimumDollars) $options[] = $amount;
    }
    if (!$options || $options[0] !== $minimumDollars) {
        array_unshift($options, $minimumDollars);
    }
    return array_values(array_unique(array_filter($options, fn($amount) => $amount >= $minimumDollars && $amount <= $maxDollars)));
}

function setmaxx_public_tip_fee_percent(): int {
    return 0;
}

function setmaxx_public_direct_platform_tip_user_ids(): array {
    $raw = (string)env('SETMAXX_DIRECT_PLATFORM_TIP_USER_IDS', '');
    if (trim($raw) === '') return [];
    return array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw) ?: []), fn($id) => $id > 0)));
}

function setmaxx_public_user_uses_direct_platform_tips(int $userId): bool {
    return in_array($userId, setmaxx_public_direct_platform_tip_user_ids(), true);
}

function setmaxx_public_create_performer_checkout_session(array $checkoutPayload, array $connectAccount): \Stripe\Checkout\Session {
    $stripeAccountId = trim((string)($connectAccount['stripe_account_id'] ?? ''));
    if ($stripeAccountId === '') {
        throw new RuntimeException('Missing performer Stripe account.');
    }

    return \Stripe\Checkout\Session::create($checkoutPayload, [
        'stripe_account' => $stripeAccountId,
    ]);
}

$tablesReady = setmaxx_public_tables_ready($pdo);
if ($tablesReady) {
    setmaxx_public_ensure_venmo_columns($pdo);
}

if ($tablesReady && $token !== '') {
    $stmt = $pdo->prepare(
        "SELECT gs.id, gs.user_id, gs.title, gs.venue_name, gs.status, gs.venmo_enabled, gs.minimum_request_dollars, gs.starts_at, u.display_name
         FROM setmaxx_gig_sessions gs
         JOIN users u ON u.id = gs.user_id
         WHERE gs.public_token = ?
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($session) {
        $publicUserId = (int)($session['user_id'] ?? 0);
        $publicDisplayName = (string)($session['display_name'] ?? '');
    }
}

if ($tablesReady && $linkToken !== '' && setmaxx_public_table_exists($pdo, 'setmaxx_public_links')) {
    $linkStmt = $pdo->prepare(
        "SELECT gs.id, gs.user_id, gs.title, gs.venue_name, gs.status, gs.venmo_enabled, gs.minimum_request_dollars, gs.starts_at, u.display_name,
                spl.user_id AS public_user_id, u.display_name AS public_display_name
         FROM setmaxx_public_links spl
         JOIN users u ON u.id = spl.user_id
         LEFT JOIN setmaxx_gig_sessions gs
           ON gs.user_id = spl.user_id
          AND gs.status = 'live'
         WHERE spl.public_token = ?
         ORDER BY COALESCE(gs.starts_at, gs.created_at) DESC, gs.id DESC
         LIMIT 1"
    );
    $linkStmt->execute([$linkToken]);
    $linkRow = $linkStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($linkRow) {
        $stableLinkFound = true;
        $publicUserId = (int)($linkRow['public_user_id'] ?? 0);
        $publicDisplayName = (string)($linkRow['public_display_name'] ?? '');
        if (!empty($linkRow['id'])) {
            $session = $linkRow;
            $publicUserId = (int)($session['user_id'] ?? $publicUserId);
            $publicDisplayName = (string)($session['display_name'] ?? $publicDisplayName);
        }
    }
}

if ($tablesReady && $publicUserId > 0) {
    if (isset($_GET['tip'])) {
        $messages[] = 'Thank you. Your tip was sent to the performer.';
    } elseif (isset($_GET['canceled'])) {
        $errors[] = 'Payment was canceled.';
    } elseif (isset($_GET['paid'])) {
        $messages[] = 'Payment received. Your request is being sent to the performer.';
    }

    try {
        $publicProfile = setmaxx_public_profile($pdo, $publicUserId);
    } catch (Throwable $e) {
        $publicProfile = ['website_url' => '', 'review_url' => '', 'logo_path' => '', 'venmo_handle' => '', 'minimum_tip_dollars' => 10, 'price_step_dollars' => 1];
    }
    $sessionMinimumDollars = max(0, min(100, (int)($publicProfile['minimum_tip_dollars'] ?? 10)));
    if ($session && array_key_exists('minimum_request_dollars', $session) && $session['minimum_request_dollars'] !== null && $session['minimum_request_dollars'] !== '') {
        $sessionMinimumDollars = max(0, min(100, (int)$session['minimum_request_dollars']));
    }
    $priceStepDollars = (int)($publicProfile['price_step_dollars'] ?? 1);
    if (!in_array($priceStepDollars, [1, 5, 10], true)) $priceStepDollars = 1;
    $venmoHandle = ltrim(trim((string)($publicProfile['venmo_handle'] ?? '')), '@');
    $venmoAvailable = $session && !empty($session['venmo_enabled']) && $venmoHandle !== '';
}

if ($session && $tablesReady) {
    $songsStmt = $pdo->prepare(
        "SELECT id, title, artist, tip_amount_cents
         FROM setmaxx_songs
         WHERE user_id = (
            SELECT user_id FROM setmaxx_gig_sessions WHERE id = ?
         )
           AND is_active = 1
         ORDER BY title ASC, artist ASC"
    );
    $songsStmt->execute([(int)$session['id']]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
    $songCount = count($songs);

    foreach ($songs as $song) {
        $first = strtoupper(substr(trim((string)$song['title']), 0, 1));
        $letter = preg_match('/[A-Z]/', $first) ? $first : '#';
        $availableLetters[$letter] = true;
    }
    ksort($availableLetters);

    $lockStmt = $pdo->prepare("SELECT song_id FROM setmaxx_requests WHERE gig_session_id = ? AND active_lock = 1");
    $lockStmt->execute([(int)$session['id']]);
    $lockedSongIds = array_map('intval', array_column($lockStmt->fetchAll(PDO::FETCH_ASSOC), 'song_id'));
}

if (($session || ($stableLinkFound && $publicUserId > 0)) && $tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'request_song');
        if ($action === 'join_mailing_list') {
            $mailingEmail = strtolower(trim((string)($_POST['mailing_email'] ?? '')));
            $mailingFirstName = trim((string)($_POST['mailing_first_name'] ?? ''));

            if ($mailingEmail === '' || !filter_var($mailingEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Add a valid email address to join the list.';
            } else {
                try {
                    setmaxx_public_ensure_mailing_list_table($pdo);
                    $mailingStmt = $pdo->prepare(
                        "INSERT INTO setmaxx_mailing_list_signups
                            (gig_session_id, user_id, email, first_name, source)
                         VALUES
                            (?, ?, ?, ?, 'setmaxx_public_page')
                         ON DUPLICATE KEY UPDATE
                            gig_session_id = VALUES(gig_session_id),
                            first_name = COALESCE(VALUES(first_name), first_name),
                            source = VALUES(source),
                            updated_at = NOW()"
                    );
                    $mailingStmt->execute([
                        $session ? (int)$session['id'] : null,
                        $publicUserId,
                        mb_substr($mailingEmail, 0, 190),
                        $mailingFirstName !== '' ? mb_substr($mailingFirstName, 0, 100) : null,
                    ]);
                    $messages[] = 'You are on the list. Thanks for keeping in touch.';
                } catch (Throwable $e) {
                    $errors[] = 'The mailing list signup could not be saved right now.';
                }
            }
        } elseif ($action === 'suggest_song') {
            $suggestedTitle = trim((string)($_POST['suggested_title'] ?? ''));
            $suggestedArtist = trim((string)($_POST['suggested_artist'] ?? ''));
            $suggestionName = trim((string)($_POST['suggestion_name'] ?? ''));
            $suggestionNote = trim((string)($_POST['suggestion_note'] ?? ''));

            if ($suggestedTitle === '') {
                $errors[] = 'Add a song title for the suggestion.';
            } else {
                try {
                    setmaxx_public_ensure_suggestions_table($pdo);
                    $suggestStmt = $pdo->prepare(
                        "INSERT INTO setmaxx_song_suggestions
                            (gig_session_id, user_id, suggested_title, suggested_artist, requester_name, suggestion_note)
                         VALUES
                            (?, ?, ?, ?, ?, ?)"
                    );
                    $suggestStmt->execute([
                        $session ? (int)$session['id'] : null,
                        $publicUserId,
                        mb_substr($suggestedTitle, 0, 190),
                        $suggestedArtist !== '' ? mb_substr($suggestedArtist, 0, 190) : null,
                        $suggestionName !== '' ? mb_substr($suggestionName, 0, 190) : null,
                        $suggestionNote !== '' ? mb_substr($suggestionNote, 0, 255) : null,
                    ]);
                    $messages[] = 'Suggestion sent to the performer.';
                } catch (Throwable $e) {
                    $errors[] = 'The suggestion could not be sent right now.';
                }
            }
        } elseif ($action === 'general_tip') {
            $tipDollars = (int)($_POST['tip_amount_dollars'] ?? 0);
            $tipperName = trim((string)($_POST['tipper_name'] ?? ''));
            $tipNote = trim((string)($_POST['tip_note'] ?? ''));
            $paymentMethod = (string)($_POST['payment_method'] ?? 'stripe');
            $tipMinimumDollars = max(5, (int)($publicProfile['minimum_tip_dollars'] ?? 5));
            if ($tipDollars < $tipMinimumDollars || $tipDollars > 100) {
                $errors[] = 'Choose a tip amount from $' . $tipMinimumDollars . ' to $100.';
            } elseif ($paymentMethod === 'venmo' && !$venmoAvailable) {
                $errors[] = 'Venmo is not available for this session.';
            } else {
                try {
                    setmaxx_public_ensure_general_tips_table($pdo);
                    if ($paymentMethod === 'venmo') {
                        setmaxx_public_ensure_venmo_columns($pdo);
                        $insertTip = $pdo->prepare(
                            "INSERT INTO setmaxx_general_tips (gig_session_id, user_id, tipper_name, tip_note, amount_cents, status, payment_method)
                             VALUES (?, ?, ?, ?, ?, 'paid', 'venmo')"
                        );
                        $insertTip->execute([
                            $session ? (int)$session['id'] : null,
                            $publicUserId,
                            $tipperName !== '' ? mb_substr($tipperName, 0, 190) : null,
                            $tipNote !== '' ? mb_substr($tipNote, 0, 255) : null,
                            $tipDollars * 100,
                        ]);
                        $note = 'SetMaxx tip' . ($tipperName !== '' ? ' from ' . $tipperName : '');
                        header('Location: ' . setmaxx_public_venmo_url($venmoHandle, $tipDollars, $note));
                        exit;
                    }
                    require_once __DIR__ . '/../_private/config/stripe.php';

                    $performerUserId = $publicUserId;
                    $amountCents = $tipDollars * 100;
                    $checkoutPayload = [
                        'mode' => 'payment',
                        'line_items' => [[
                            'price_data' => [
                                'currency' => 'usd',
                                'product_data' => [
                                    'name' => 'Tip for ' . (string)($publicDisplayName ?: 'the performer'),
                                    'description' => 'Set Maxx performer tip',
                                ],
                                'unit_amount' => $amountCents,
                            ],
                            'quantity' => 1,
                        ]],
                        'success_url' => setmaxx_public_absolute_url(base_url('/setmaxx/public.php?' . ($linkToken !== '' ? 'link=' . rawurlencode($linkToken) : 'token=' . rawurlencode($token)) . '&tip=1')),
                        'cancel_url' => setmaxx_public_absolute_url(base_url('/setmaxx/public.php?' . ($linkToken !== '' ? 'link=' . rawurlencode($linkToken) : 'token=' . rawurlencode($token)) . '&canceled=1')),
                        'metadata' => [
                            'kind' => 'setmaxx_general_tip',
                            'gig_session_id' => $session ? (string)(int)$session['id'] : '',
                            'performer_user_id' => (string)$performerUserId,
                            'tipper_name' => mb_substr($tipperName, 0, 190),
                            'tip_note' => mb_substr($tipNote, 0, 255),
                        ],
                    ];

                    if (!setmaxx_public_user_uses_direct_platform_tips($performerUserId)) {
                        if (!setmaxx_public_table_exists($pdo, 'setmaxx_connect_accounts')) {
                            $errors[] = 'Tips are not ready for this performer yet.';
                        } else {
                            $connectStmt = $pdo->prepare("SELECT stripe_account_id, charges_enabled, payouts_enabled, details_submitted FROM setmaxx_connect_accounts WHERE user_id = ? LIMIT 1");
                            $connectStmt->execute([$performerUserId]);
                            $connectAccount = $connectStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                            if (!$connectAccount || empty($connectAccount['charges_enabled']) || empty($connectAccount['payouts_enabled']) || empty($connectAccount['details_submitted'])) {
                                $errors[] = 'Tips are not ready for this performer yet.';
                            }
                        }
                    }

                    if (!$errors) {
                        if (setmaxx_public_user_uses_direct_platform_tips($performerUserId)) {
                            $checkoutSession = \Stripe\Checkout\Session::create($checkoutPayload);
                        } else {
                            $checkoutSession = setmaxx_public_create_performer_checkout_session($checkoutPayload, $connectAccount ?? []);
                        }
                        header('Location: ' . (string)$checkoutSession->url);
                        exit;
                    }
                } catch (Throwable $e) {
                    error_log('SetMaxx general tip checkout failed: ' . $e->getMessage());
                    $errors[] = 'Tips are not available right now.';
                }
            }
        } else {
        if (!$session || (($session['status'] ?? '') !== 'live')) {
            $errors[] = 'Song requests are closed right now.';
        } else {
        $songId = (int)($_POST['song_id'] ?? 0);
        $requesterName = trim((string)($_POST['requester_name'] ?? ''));
        $requestNote = trim((string)($_POST['request_note'] ?? ''));
        $requestAmountDollars = (int)($_POST['request_amount_dollars'] ?? 0);
        $paymentMethod = (string)($_POST['payment_method'] ?? 'stripe');

        $songStmt = $pdo->prepare(
            "SELECT id, title, artist, tip_amount_cents
             FROM setmaxx_songs
             WHERE id = ?
               AND user_id = (
                  SELECT user_id FROM setmaxx_gig_sessions WHERE id = ?
               )
               AND is_active = 1
             LIMIT 1"
        );
        $songStmt->execute([$songId, (int)$session['id']]);
        $song = $songStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $songMinimumDollars = $song ? (int)ceil(((int)$song['tip_amount_cents']) / 100) : 0;
        $minimumDollars = max(5, min(100, max($songMinimumDollars, $sessionMinimumDollars)));

        if (!($requestAmountDollars === 0 || ($requestAmountDollars >= 5 && $requestAmountDollars <= 100))) {
            $errors[] = 'Choose $' . max(5, $sessionMinimumDollars) . ' to $100 to move your song up the list, or choose $0 for a free request.';
        } elseif ($requestAmountDollars > 0 && $minimumDollars > 0 && $requestAmountDollars < $minimumDollars) {
            $errors[] = 'This song starts at $' . $minimumDollars . '.';
        } elseif (!$song) {
            $errors[] = 'That song is not available for this request page.';
        } elseif ($requestAmountDollars > 0 && in_array($songId, $lockedSongIds, true)) {
            $errors[] = 'That song has already been requested for this gig.';
        } elseif ($requestAmountDollars > 0 && $paymentMethod === 'venmo') {
            if (!$venmoAvailable) {
                $errors[] = 'Venmo is not available for this session.';
            } else {
                try {
                    setmaxx_public_ensure_venmo_columns($pdo);
                    $insert = $pdo->prepare(
                        "INSERT INTO setmaxx_requests (gig_session_id, song_id, requester_name, request_note, amount_cents, status, payment_method, active_lock)
                         VALUES (?, ?, ?, ?, ?, 'pending', 'venmo', 1)"
                    );
                    $insert->execute([
                        (int)$session['id'],
                        $songId,
                        $requesterName !== '' ? $requesterName : null,
                        $requestNote !== '' ? $requestNote : null,
                        $requestAmountDollars * 100,
                    ]);
                    $songLabel = trim((string)$song['title']);
                    $note = 'SetMaxx request: ' . $songLabel;
                    header('Location: ' . setmaxx_public_venmo_url($venmoHandle, $requestAmountDollars, $note));
                    exit;
                } catch (Throwable $e) {
                    $errors[] = 'That song has already been requested for this gig.';
                }
            }
        } elseif ($requestAmountDollars > 0) {
            try {
                require_once __DIR__ . '/../_private/config/stripe.php';

                $performerUserId = (int)($session['user_id'] ?? 0);
                $amountCents = $requestAmountDollars * 100;
                $checkoutPayload = [
                    'mode' => 'payment',
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Song request: ' . (string)$song['title'],
                                'description' => trim((string)($song['artist'] ?? '')) !== '' ? (string)$song['artist'] : 'Set Maxx request',
                            ],
                            'unit_amount' => $amountCents,
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => setmaxx_public_absolute_url(base_url('/setmaxx/public.php?' . ($linkToken !== '' ? 'link=' . rawurlencode($linkToken) : 'token=' . rawurlencode($token)) . '&paid=1')),
                    'cancel_url' => setmaxx_public_absolute_url(base_url('/setmaxx/public.php?' . ($linkToken !== '' ? 'link=' . rawurlencode($linkToken) : 'token=' . rawurlencode($token)) . '&canceled=1')),
                    'metadata' => [
                        'kind' => 'setmaxx_tip',
                        'gig_session_id' => (string)(int)$session['id'],
                        'song_id' => (string)$songId,
                        'performer_user_id' => (string)$performerUserId,
                        'requester_name' => mb_substr($requesterName, 0, 190),
                        'request_note' => mb_substr($requestNote, 0, 255),
                    ],
                ];

                if (!setmaxx_public_user_uses_direct_platform_tips($performerUserId)) {
                    if (!setmaxx_public_table_exists($pdo, 'setmaxx_connect_accounts')) {
                        $errors[] = 'Paid requests are not ready for this performer yet.';
                    } else {
                        $connectStmt = $pdo->prepare("SELECT stripe_account_id, charges_enabled, payouts_enabled, details_submitted FROM setmaxx_connect_accounts WHERE user_id = ? LIMIT 1");
                        $connectStmt->execute([$performerUserId]);
                        $connectAccount = $connectStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                        if (!$connectAccount || empty($connectAccount['charges_enabled']) || empty($connectAccount['payouts_enabled']) || empty($connectAccount['details_submitted'])) {
                            $errors[] = 'Paid requests are not ready for this performer yet.';
                        }
                    }
                }

                if (!$errors) {
                    if (setmaxx_public_user_uses_direct_platform_tips($performerUserId)) {
                        $checkoutSession = \Stripe\Checkout\Session::create($checkoutPayload);
                    } else {
                        $checkoutSession = setmaxx_public_create_performer_checkout_session($checkoutPayload, $connectAccount ?? []);
                    }
                    header('Location: ' . (string)$checkoutSession->url);
                    exit;
                }
            } catch (Throwable $e) {
                error_log('SetMaxx paid request checkout failed: ' . $e->getMessage());
                $errors[] = 'Paid requests are not available right now. Please try a free request or check back shortly.';
            }
        } else {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO setmaxx_requests (gig_session_id, song_id, requester_name, request_note, amount_cents, status, payment_method, active_lock)
                     VALUES (?, ?, ?, ?, ?, 'pending', 'free', NULL)"
                );
                $insert->execute([
                    (int)$session['id'],
                    $songId,
                    $requesterName !== '' ? $requesterName : null,
                    $requestNote !== '' ? $requestNote : null,
                    $requestAmountDollars * 100,
                ]);
                $messages[] = 'Request sent to the performer.';
            } catch (Throwable $e) {
                $errors[] = 'That song has already been requested for this gig.';
            }
        }
        }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($session['title'] ?? 'Set Maxx') ?> | Set Maxx</title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { background: radial-gradient(circle at top, rgba(140,107,255,.22), transparent 35%), #090814; }
    .public-shell { padding: 2rem 0 4rem; }
    .public-card {
      max-width: 1120px; margin: 0 auto; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
      border-radius: 26px; padding: 1.4rem; box-shadow: 0 20px 50px rgba(0,0,0,.28);
    }
    .alpha-menu { position:sticky; top:.5rem; z-index:3; display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin-top:1rem; padding:.65rem; border-radius:16px; background:rgba(9,8,20,.92); border:1px solid rgba(255,255,255,.08); }
    .alpha-label { color:rgba(255,255,255,.68); font-size:.82rem; font-weight:600; padding:0 .25rem; }
    .alpha-spacer { flex:1 1 1rem; }
    .alpha-button, .sort-button { min-width:34px; height:34px; border-radius:10px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.82rem; cursor:pointer; }
    .sort-button { padding:0 .75rem; }
    .alpha-button.active, .sort-button.active, .alpha-button:hover, .sort-button:hover { background:rgba(140,107,255,.24); border-color:rgba(140,107,255,.45); }
    .alpha-button:disabled { opacity:.35; cursor:not-allowed; }
    .catalog-search { flex:1 1 240px; min-width:210px; height:38px; border-radius:12px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.06); color:#fff; font:inherit; padding:0 .85rem; }
    .catalog-search::placeholder { color:rgba(255,255,255,.5); }
    .catalog-empty { display:none; margin-top:.85rem; padding:1rem; border-radius:14px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); color:rgba(255,255,255,.72); }
    .catalog-empty.visible { display:block; }
    .public-grid { display:grid; gap:.45rem; margin-top:.85rem; }
    .song-card { padding:.6rem .7rem; border-radius:14px; background: rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); }
    details.song-card { padding:0; overflow:hidden; }
    .song-card.locked { padding:0; opacity:.6; }
    .song-meta { color: rgba(255,255,255,.72); font-size:.92rem; }
    .song-title { font-weight:600; line-height:1.2; }
    .song-row { display:grid; grid-template-columns:minmax(220px, 1.1fr) minmax(430px, 1.7fr); gap:.75rem; align-items:center; }
    .song-summary, .action-summary { display:flex; align-items:center; justify-content:space-between; gap:.8rem; cursor:pointer; list-style:none; }
    .song-summary { padding:.72rem .8rem; }
    .song-summary > span:first-child { display:grid; gap:.12rem; min-width:0; }
    .song-summary::-webkit-details-marker, .action-summary::-webkit-details-marker { display:none; }
    .song-card[open] .song-summary, .action-card[open] .action-summary { border-bottom:1px solid rgba(255,255,255,.08); }
    .song-chevron, .action-chevron { flex:0 0 auto; color:rgba(255,255,255,.62); font-size:1.15rem; transition:transform .18s ease; }
    .song-card[open] .song-chevron, .action-card[open] .action-chevron { transform:rotate(180deg); }
    .song-request-panel { padding:.75rem .8rem .85rem; }
    .request-form { display:grid; grid-template-columns:105px minmax(120px, 1fr) minmax(150px, 1.2fr) auto; gap:.5rem; align-items:center; }
    .request-input, .request-select { width:100%; padding:.52rem .62rem; border-radius:10px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.9rem; }
    .request-select option { background:#151323; color:#fff; }
    .request-input::placeholder { color:rgba(255,255,255,.52); }
    .request-submit { padding:.54rem .85rem; white-space:nowrap; }
    .request-note { margin-top:1rem; padding:1rem; border-radius:16px; background:rgba(140,107,255,.1); border:1px solid rgba(140,107,255,.16); }
    .public-logo { width:64px; height:64px; object-fit:contain; border-radius:16px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.1); padding:.4rem; }
    .public-quick-links { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.75rem; }
    .public-mini-button { display:inline-flex; align-items:center; min-height:34px; padding:.4rem .75rem; border-radius:999px; border:1px solid rgba(255,255,255,.14); color:#fff; text-decoration:none; font-size:.86rem; background:rgba(255,255,255,.04); }
    .suggestion-card { margin-top:1rem; padding:1rem; border-radius:18px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); }
    .action-card { margin-top:1rem; border-radius:18px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.07); overflow:hidden; }
    .action-summary { padding:1rem; font-weight:600; }
    .action-summary-text { display:grid; gap:.12rem; }
    .action-summary-hint { color:rgba(255,255,255,.62); font-size:.86rem; font-weight:400; }
    .action-panel { padding:1rem; }
    .suggestion-form { display:grid; grid-template-columns:minmax(160px, 1fr) minmax(140px, .9fr) minmax(120px, .8fr) minmax(180px, 1.2fr) auto; gap:.55rem; align-items:center; }
    .mailing-form { display:grid; grid-template-columns:minmax(180px, 1fr) minmax(140px, .75fr) auto; gap:.55rem; align-items:center; }
    .tip-form { display:grid; grid-template-columns:110px minmax(130px, 1fr) minmax(180px, 1.3fr) auto; gap:.55rem; align-items:center; }
    .payment-buttons { display:flex; gap:.45rem; flex-wrap:wrap; }
    .payment-buttons .btn { white-space:nowrap; }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 900px) {
      .song-row, .request-form, .suggestion-form, .mailing-form, .tip-form { grid-template-columns:1fr; }
      .request-submit { width:100%; }
    }
  </style>
</head>
<body>
<main class="container public-shell">
  <div class="public-card">
    <?php foreach ($messages as $message): ?>
      <div class="alert alert-success"><?= e($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if (!$tablesReady): ?>
      <h1 style="margin-top:0;">Set Maxx is not installed yet.</h1>
    <?php elseif (!$session): ?>
      <?php if ($stableLinkFound && $publicUserId > 0): ?>
        <div style="display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
          <?php if (!empty($publicProfile['logo_path'])): ?>
            <img class="public-logo" src="<?= e(base_url((string)$publicProfile['logo_path'])) ?>" alt="">
          <?php endif; ?>
          <div>
            <div style="display:inline-flex; padding:.3rem .7rem; border-radius:999px; background:rgba(140,107,255,.16); color:#efe7ff; font-weight:600;">Requests are taking five</div>
            <h1 style="margin:.8rem 0 .35rem;"><?= e($publicDisplayName !== '' ? $publicDisplayName : 'The performer') ?></h1>
            <p class="song-meta" style="max-width:620px;">The request list is closed right now, but the show energy is still welcome. Drop a tip, leave a song idea for a future set, or keep in touch below.</p>
            <?php if (!empty($publicProfile['website_url']) || !empty($publicProfile['review_url'])): ?>
              <div class="public-quick-links">
                <?php if (!empty($publicProfile['website_url'])): ?><a class="public-mini-button" href="<?= e((string)$publicProfile['website_url']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
                <?php if (!empty($publicProfile['review_url'])): ?><a class="public-mini-button" href="<?= e((string)$publicProfile['review_url']) ?>" target="_blank" rel="noopener">Leave a review</a><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <details class="action-card">
          <summary class="action-summary">
            <span class="action-summary-text">
              <span>Tip the performer</span>
              <span class="action-summary-hint">Open to choose an amount and payment method.</span>
            </span>
            <span class="action-chevron" aria-hidden="true">&darr;</span>
          </summary>
          <div class="action-panel">
            <form method="post" class="tip-form" action="">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="general_tip">
              <select class="request-select" name="tip_amount_dollars" aria-label="Tip amount">
                <?php foreach (setmaxx_public_price_options(max(5, (int)($publicProfile['minimum_tip_dollars'] ?? 5)), $priceStepDollars) as $tipAmount): ?>
                  <option value="<?= $tipAmount ?>">$<?= $tipAmount ?></option>
                <?php endforeach; ?>
              </select>
              <input class="request-input" name="tipper_name" placeholder="Your name">
              <input class="request-input" name="tip_note" placeholder="Optional note">
              <div class="payment-buttons">
                <button class="btn btn-primary request-submit" type="submit" name="payment_method" value="stripe">Tip with card</button>
                <?php if ($venmoAvailable): ?><button class="btn btn-outline request-submit" type="submit" name="payment_method" value="venmo">Tip with Venmo</button><?php endif; ?>
              </div>
            </form>
          </div>
        </details>

        <details class="action-card">
          <summary class="action-summary">
            <span class="action-summary-text">
              <span>Join the list</span>
              <span class="action-summary-hint">Get show dates, music updates, and the occasional heads-up.</span>
            </span>
            <span class="action-chevron" aria-hidden="true">&darr;</span>
          </summary>
          <div class="action-panel">
            <form method="post" class="mailing-form" action="">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="join_mailing_list">
              <input class="request-input" name="mailing_email" type="email" placeholder="Email address" required>
              <input class="request-input" name="mailing_first_name" placeholder="First name">
              <button class="btn btn-outline request-submit" type="submit">Keep me posted</button>
            </form>
          </div>
        </details>

        <details class="action-card">
          <summary class="action-summary">
            <span class="action-summary-text">
              <span>Suggest a song for future shows</span>
              <span class="action-summary-hint">Open if you do not see the song you want.</span>
            </span>
            <span class="action-chevron" aria-hidden="true">&darr;</span>
          </summary>
          <div class="action-panel">
            <form method="post" class="suggestion-form" action="">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="suggest_song">
              <input class="request-input" name="suggested_title" placeholder="Song title" required>
              <input class="request-input" name="suggested_artist" placeholder="Artist">
              <input class="request-input" name="suggestion_name" placeholder="Your name">
              <input class="request-input" name="suggestion_note" placeholder="Optional note">
              <button class="btn btn-outline request-submit" type="submit">Suggest</button>
            </form>
          </div>
        </details>
      <?php else: ?>
        <h1 style="margin-top:0;">Request page not found.</h1>
        <p class="song-meta">This Set Maxx link is not active right now.</p>
      <?php endif; ?>
    <?php else: ?>
      <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
        <div style="display:flex; gap:1rem; align-items:flex-start;">
          <?php if (!empty($publicProfile['logo_path'])): ?>
            <img class="public-logo" src="<?= e(base_url((string)$publicProfile['logo_path'])) ?>" alt="">
          <?php endif; ?>
          <div>
          <div style="display:inline-flex; padding:.3rem .7rem; border-radius:999px; background:rgba(140,107,255,.16); color:#efe7ff; font-weight:600;">Live song requests</div>
          <h1 style="margin:.8rem 0 .35rem;"><?= e($session['title']) ?></h1>
          <div class="song-meta"><?= e((string)($session['venue_name'] ?: 'Tonight\'s show')) ?> &middot; hosted by <?= e((string)($session['display_name'] ?: 'the performer')) ?></div>
          <?php if (!empty($publicProfile['website_url']) || !empty($publicProfile['review_url'])): ?>
            <div class="public-quick-links">
              <?php if (!empty($publicProfile['website_url'])): ?><a class="public-mini-button" href="<?= e((string)$publicProfile['website_url']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
              <?php if (!empty($publicProfile['review_url'])): ?><a class="public-mini-button" href="<?= e((string)$publicProfile['review_url']) ?>" target="_blank" rel="noopener">Leave a review</a><?php endif; ?>
            </div>
          <?php endif; ?>
          </div>
        </div>
        <div class="song-meta" style="max-width:320px;"><?= (int)$songCount ?> active <?= $songCount === 1 ? 'song' : 'songs' ?> available. Paid requests lock the song for tonight; free requests keep it open.</div>
      </div>

      <div class="request-note song-meta">
        Paid requests help move songs up the list. Choose $<?= (int)max(5, $sessionMinimumDollars) ?> to $100, or select $0 for a free request. Requests are still subject to performer discretion.
      </div>

      <details class="action-card">
        <summary class="action-summary">
          <span class="action-summary-text">
            <span>Tip the performer</span>
            <span class="action-summary-hint">Open to choose an amount and payment method.</span>
          </span>
          <span class="action-chevron" aria-hidden="true">&darr;</span>
        </summary>
        <div class="action-panel">
          <form method="post" class="tip-form" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="general_tip">
            <select class="request-select" name="tip_amount_dollars" aria-label="Tip amount">
              <?php foreach (setmaxx_public_price_options(max(5, (int)($publicProfile['minimum_tip_dollars'] ?? 5)), $priceStepDollars) as $tipAmount): ?>
                <option value="<?= $tipAmount ?>">$<?= $tipAmount ?></option>
              <?php endforeach; ?>
            </select>
            <input class="request-input" name="tipper_name" placeholder="Your name">
            <input class="request-input" name="tip_note" placeholder="Optional note">
            <div class="payment-buttons">
              <button class="btn btn-primary request-submit" type="submit" name="payment_method" value="stripe">Tip with card</button>
              <?php if ($venmoAvailable): ?><button class="btn btn-outline request-submit" type="submit" name="payment_method" value="venmo">Tip with Venmo</button><?php endif; ?>
            </div>
          </form>
        </div>
      </details>

      <details class="action-card">
        <summary class="action-summary">
          <span class="action-summary-text">
            <span>Join the list</span>
            <span class="action-summary-hint">Get show dates, music updates, and the occasional heads-up.</span>
          </span>
          <span class="action-chevron" aria-hidden="true">&darr;</span>
        </summary>
        <div class="action-panel">
          <form method="post" class="mailing-form" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="join_mailing_list">
            <input class="request-input" name="mailing_email" type="email" placeholder="Email address" required>
            <input class="request-input" name="mailing_first_name" placeholder="First name">
            <button class="btn btn-outline request-submit" type="submit">Keep me posted</button>
          </form>
        </div>
      </details>

      <details class="action-card">
        <summary class="action-summary">
          <span class="action-summary-text">
            <span>Suggest a song for future shows</span>
            <span class="action-summary-hint">Open if you do not see the song you want.</span>
          </span>
          <span class="action-chevron" aria-hidden="true">&darr;</span>
        </summary>
        <div class="action-panel">
          <form method="post" class="suggestion-form" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="suggest_song">
            <input class="request-input" name="suggested_title" placeholder="Song title" required>
            <input class="request-input" name="suggested_artist" placeholder="Artist">
            <input class="request-input" name="suggestion_name" placeholder="Your name">
            <input class="request-input" name="suggestion_note" placeholder="Optional note">
            <button class="btn btn-outline request-submit" type="submit">Suggest</button>
          </form>
        </div>
      </details>

      <?php if ($songs): ?>
        <div class="alpha-menu" aria-label="Song alphabet filter">
          <input class="catalog-search" id="catalogSearch" type="search" placeholder="Search songs or artists" autocomplete="off" aria-label="Search songs or artists">
          <span class="alpha-label">Filter</span>
          <button class="alpha-button active" type="button" data-letter="all">All</button>
          <?php foreach (array_merge(['#'], range('A', 'Z')) as $letter): ?>
            <button class="alpha-button" type="button" data-letter="<?= e($letter) ?>" <?= isset($availableLetters[$letter]) ? '' : 'disabled' ?>><?= e($letter) ?></button>
          <?php endforeach; ?>
          <span class="alpha-spacer"></span>
          <span class="alpha-label">Sort</span>
          <button class="sort-button active" type="button" data-sort="title">Title</button>
          <button class="sort-button" type="button" data-sort="artist">Artist</button>
        </div>
      <?php endif; ?>

      <div class="public-grid">
        <?php if (!$songs): ?>
          <div class="song-card"><div class="song-meta">No active songs are available for this request page right now.</div></div>
        <?php else: foreach ($songs as $song): ?>
          <?php
            $locked = in_array((int)$song['id'], $lockedSongIds, true);
            $first = strtoupper(substr(trim((string)$song['title']), 0, 1));
            $letter = preg_match('/[A-Z]/', $first) ? $first : '#';
            $artistSort = (string)($song['artist'] ?: $song['title']);
            $artistFirst = strtoupper(substr(trim($artistSort), 0, 1));
            $artistLetter = preg_match('/[A-Z]/', $artistFirst) ? $artistFirst : '#';
            $minimumDollars = max(5, min(100, max((int)ceil(((int)$song['tip_amount_cents']) / 100), $sessionMinimumDollars)));
            $requestAmounts = array_values(array_filter([5, 10, 15, 20, 25, 50, 100], fn($amount) => $amount >= $minimumDollars));
            if (!in_array($minimumDollars, $requestAmounts, true)) {
              array_unshift($requestAmounts, $minimumDollars);
            }
          ?>
          <?php if ($locked): ?>
            <div class="song-card locked" data-letter="<?= e($letter) ?>" data-title-letter="<?= e($letter) ?>" data-artist-letter="<?= e($artistLetter) ?>" data-title="<?= e(strtolower((string)$song['title'])) ?>" data-artist="<?= e(strtolower($artistSort)) ?>">
              <div class="song-summary">
                <span>
                  <span class="song-title"><?= e($song['title']) ?></span>
                  <span class="song-meta"><?= e((string)($song['artist'] ?: 'Artist not listed')) ?></span>
                </span>
                <span class="song-meta"><strong>Already requested tonight.</strong></span>
              </div>
            </div>
          <?php else: ?>
            <details class="song-card" data-letter="<?= e($letter) ?>" data-title-letter="<?= e($letter) ?>" data-artist-letter="<?= e($artistLetter) ?>" data-title="<?= e(strtolower((string)$song['title'])) ?>" data-artist="<?= e(strtolower($artistSort)) ?>">
              <summary class="song-summary">
                <span>
                  <span class="song-title"><?= e($song['title']) ?></span>
                  <span class="song-meta"><?= e((string)($song['artist'] ?: 'Artist not listed')) ?></span>
                </span>
                <span class="song-chevron" aria-hidden="true">&darr;</span>
              </summary>
              <div class="song-request-panel">
                <form method="post" class="request-form" action="">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="request_song">
                  <input type="hidden" name="song_id" value="<?= (int)$song['id'] ?>">
                  <select class="request-select" name="request_amount_dollars" aria-label="Request amount">
                    <?php foreach ($requestAmounts as $amount): ?>
                      <option value="<?= $amount ?>">$<?= $amount ?></option>
                    <?php endforeach; ?>
                    <option value="0">$0 free request</option>
                  </select>
                  <input class="request-input" name="requester_name" placeholder="First name is enough.">
                  <input class="request-input" name="request_note" placeholder="Optional note">
                  <div class="payment-buttons">
                    <button class="btn btn-primary request-submit" type="submit" name="payment_method" value="stripe">Request with card</button>
                    <?php if ($venmoAvailable): ?><button class="btn btn-outline request-submit" type="submit" name="payment_method" value="venmo">Request with Venmo</button><?php endif; ?>
                  </div>
                </form>
              </div>
            </details>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </div>
      <?php if ($songs): ?>
        <div class="catalog-empty" id="catalogEmpty">No matching songs found.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<script>
(function() {
  const buttons = Array.from(document.querySelectorAll('.alpha-button'));
  const sortButtons = Array.from(document.querySelectorAll('.sort-button'));
  const cards = Array.from(document.querySelectorAll('.song-card[data-letter]'));
  const requestDetails = cards.filter(function(card) { return card.tagName.toLowerCase() === 'details'; });
  const requesterNameInputs = Array.from(document.querySelectorAll('input[name="requester_name"]'));
  const rememberedNameKey = 'setmaxxRequesterName';
  const grid = document.querySelector('.public-grid');
  const searchInput = document.getElementById('catalogSearch');
  const emptyState = document.getElementById('catalogEmpty');
  let currentLetter = 'all';
  let currentSort = 'title';
  let currentSearch = '';
  if (!buttons.length || !cards.length) return;

  function rememberedRequesterName() {
    try {
      return localStorage.getItem(rememberedNameKey) || '';
    } catch (error) {
      return '';
    }
  }

  function rememberRequesterName(name) {
    try {
      if (name) localStorage.setItem(rememberedNameKey, name);
    } catch (error) {}
  }

  function fillRequesterNames(name) {
    if (!name) return;
    requesterNameInputs.forEach(function(input) {
      if (!input.value.trim()) input.value = name;
    });
  }

  fillRequesterNames(rememberedRequesterName());
  requesterNameInputs.forEach(function(input) {
    input.addEventListener('input', function() {
      const name = input.value.trim();
      if (name) {
        rememberRequesterName(name);
        fillRequesterNames(name);
      }
    });
  });

  function cardLetter(card) {
    return card.getAttribute('data-' + currentSort + '-letter') || '#';
  }

  function updateAlphabetAvailability() {
    const letters = new Set(cards.filter(cardMatchesSearch).map(cardLetter));
    buttons.forEach(function(button) {
      const letter = button.getAttribute('data-letter');
      if (letter === 'all') {
        button.disabled = false;
      } else {
        button.disabled = !letters.has(letter);
      }
      if (button.disabled && button.classList.contains('active')) {
        currentLetter = 'all';
      }
    });
  }

  function sortCards() {
    cards.sort(function(a, b) {
      const aValue = a.getAttribute('data-' + currentSort) || '';
      const bValue = b.getAttribute('data-' + currentSort) || '';
      return aValue.localeCompare(bValue);
    });
    cards.forEach(function(card) { grid.appendChild(card); });
  }

  function cardMatchesSearch(card) {
    if (!currentSearch) return true;
    const title = card.getAttribute('data-title') || '';
    const artist = card.getAttribute('data-artist') || '';
    return title.includes(currentSearch) || artist.includes(currentSearch);
  }

  function applyCatalogView(shouldScroll) {
    updateAlphabetAvailability();
    sortCards();
    buttons.forEach(function(item) {
      item.classList.toggle('active', item.getAttribute('data-letter') === currentLetter);
    });
    let visibleCount = 0;
    cards.forEach(function(card) {
      const matchesLetter = currentLetter === 'all' || cardLetter(card) === currentLetter;
      const matchesSearch = cardMatchesSearch(card);
      card.hidden = !(matchesLetter && matchesSearch);
      if (card.hidden && card.open) card.open = false;
      if (!card.hidden) visibleCount += 1;
    });
    if (emptyState) emptyState.classList.toggle('visible', visibleCount === 0);
    if (shouldScroll) {
      const firstVisible = cards.find(function(card) { return !card.hidden; });
      if (firstVisible) firstVisible.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
  }

  buttons.forEach(function(button) {
    button.addEventListener('click', function() {
      if (button.disabled) return;
      currentLetter = button.getAttribute('data-letter') || 'all';
      applyCatalogView(true);
    });
  });

  sortButtons.forEach(function(button) {
    button.addEventListener('click', function() {
      currentSort = button.getAttribute('data-sort') || 'title';
      sortButtons.forEach(function(item) { item.classList.toggle('active', item === button); });
      currentLetter = 'all';
      applyCatalogView(false);
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', function() {
      currentSearch = searchInput.value.trim().toLowerCase();
      currentLetter = 'all';
      applyCatalogView(false);
    });
  }

  requestDetails.forEach(function(detail) {
    detail.addEventListener('toggle', function() {
      if (!detail.open) return;
      requestDetails.forEach(function(other) {
        if (other !== detail) other.open = false;
      });
    });
  });

  applyCatalogView(false);
})();
</script>
</body>
</html>
