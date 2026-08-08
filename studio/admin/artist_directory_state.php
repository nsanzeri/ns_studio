<?php
// admin/artist_directory_state.php - update missing artist directory states

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';

function ads_env(string $key, $default = null) {
    if (function_exists('env')) {
        $value = env($key);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    $value = $_ENV[$key] ?? getenv($key);
    return ($value !== false && $value !== null && $value !== '') ? $value : $default;
}

function ads_is_local(): bool {
    if (function_exists('is_local')) {
        return (bool)is_local();
    }

    return in_array(strtolower((string)ads_env('APP_ENV', '')), ['local', 'dev', 'development'], true);
}

function ads_require_admin_access(): void {
    $user = (string)ads_env('ADMIN_USER', '');
    $pass = (string)ads_env('ADMIN_PASS', '');
    $token = (string)ads_env('ADMIN_TOKEN', '');

    if ($user !== '' && $pass !== '') {
        $providedUser = $_SERVER['PHP_AUTH_USER'] ?? '';
        $providedPass = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($user, (string)$providedUser) || !hash_equals($pass, (string)$providedPass)) {
            header('WWW-Authenticate: Basic realm="Admin"');
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        return;
    }

    if ($token !== '') {
        $provided = $_GET['k'] ?? ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
        if (!$provided || !hash_equals($token, (string)$provided)) {
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        return;
    }

    if (!ads_is_local()) {
        http_response_code(403);
        echo 'Admin is disabled on production until you set ADMIN_USER/ADMIN_PASS or ADMIN_TOKEN in .env.';
        exit;
    }
}

function ads_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ads_clean_state($value): ?string {
    $state = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
}

ads_require_admin_access();

$message = '';
$error = '';
$adminTokenQuery = isset($_GET['k']) ? '?k=' . urlencode((string)$_GET['k']) : '';

if (is_post()) {
    $profileId = (int)($_POST['profile_id'] ?? 0);
    $state = ads_clean_state($_POST['directory_state'] ?? '');

    if ($profileId <= 0) {
        $error = 'Choose an artist profile to update.';
    } elseif (!$state) {
        $error = 'Use a two-letter state abbreviation.';
    } else {
        $stmt = $pdo->prepare('UPDATE setmaxx_public_profiles SET directory_state = ? WHERE id = ? LIMIT 1');
        $stmt->execute([$state, $profileId]);
        $message = 'Directory state updated.';
    }
}

$rows = [];
$tableReady = false;
try {
    $tableCheck = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' LIMIT 1");
    $tableCheck->execute();
    $tableReady = (bool)$tableCheck->fetchColumn();

    if ($tableReady) {
        $stmt = $pdo->query("
            SELECT
                p.id AS profile_id,
                p.user_id,
                p.artist_name,
                p.contact_email,
                p.directory_state,
                u.email AS account_email,
                u.display_name
            FROM setmaxx_public_profiles p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.directory_state IS NULL OR TRIM(p.directory_state) = ''
            ORDER BY COALESCE(NULLIF(p.artist_name, ''), u.display_name, u.email, CONCAT('User #', p.user_id)) ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Artist Directory State Backfill</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; background: #f7f7fb; color: #171721; }
    main { max-width: 1040px; margin: 0 auto; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { border-bottom: 1px solid #ddd; padding: .7rem; text-align: left; vertical-align: middle; }
    th { background: #ededf5; }
    input[type="text"] { width: 4rem; padding: .45rem; text-transform: uppercase; }
    button { padding: .5rem .8rem; cursor: pointer; }
    .notice { padding: .75rem 1rem; margin: 1rem 0; background: #e8f5ea; border: 1px solid #94c99c; }
    .error { padding: .75rem 1rem; margin: 1rem 0; background: #fff0f0; border: 1px solid #d99090; }
    .muted { color: #666; }
    .inline-form { display: flex; gap: .5rem; align-items: center; }
  </style>
</head>
<body>
<main>
  <p><a href="index.php<?= ads_h($adminTokenQuery) ?>">&larr; Admin dashboard</a></p>
  <h1>Artist Directory State Backfill</h1>
  <p class="muted">Artists listed here have no directory state saved. Enter a two-letter state abbreviation and update each profile.</p>

  <?php if ($message): ?><div class="notice"><?= ads_h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= ads_h($error) ?></div><?php endif; ?>

  <?php if (!$tableReady): ?>
    <div class="error">The public artist profile table does not exist yet.</div>
  <?php elseif (!$rows): ?>
    <div class="notice">All artist directory profiles have a state filled in.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Artist</th>
          <th>Contact</th>
          <th>User ID</th>
          <th>State</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>
              <strong><?= ads_h($row['artist_name'] ?: $row['display_name'] ?: 'Unnamed artist') ?></strong>
              <?php if (empty($row['artist_name'])): ?><div class="muted">No artist name saved</div><?php endif; ?>
            </td>
            <td>
              <?= ads_h($row['contact_email'] ?: $row['account_email'] ?: '') ?>
            </td>
            <td><?= ads_h($row['user_id']) ?></td>
            <td>
              <form class="inline-form" method="post" action="artist_directory_state.php<?= ads_h($adminTokenQuery) ?>">
                <input type="hidden" name="profile_id" value="<?= ads_h($row['profile_id']) ?>">
                <input type="text" name="directory_state" maxlength="2" pattern="[A-Za-z]{2}" required placeholder="IL">
                <button type="submit">Update</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
