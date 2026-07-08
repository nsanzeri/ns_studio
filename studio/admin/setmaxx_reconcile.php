<?php
// admin/setmaxx_reconcile.php - Stripe Connect to SetMaxx reconciliation

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';

function envv(string $k, $default = null) {
    if (function_exists('env')) {
        $v = env($k);
        if ($v !== null && $v !== '') return $v;
    }
    $v = $_ENV[$k] ?? getenv($k);
    return ($v !== false && $v !== null && $v !== '') ? $v : $default;
}

function is_local_env(): bool {
    if (function_exists('is_local')) return (bool)is_local();
    $app = strtolower((string)envv('APP_ENV', ''));
    return in_array($app, ['local', 'dev', 'development'], true);
}

function require_admin_access(): void {
    $user = (string)envv('ADMIN_USER', '');
    $pass = (string)envv('ADMIN_PASS', '');
    $token = (string)envv('ADMIN_TOKEN', '');

    if ($user !== '' && $pass !== '') {
        $u = $_SERVER['PHP_AUTH_USER'] ?? '';
        $p = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($user, (string)$u) || !hash_equals($pass, (string)$p)) {
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

    if (!is_local_env()) {
        http_response_code(403);
        echo 'Admin is disabled on production until you set ADMIN_USER/ADMIN_PASS or ADMIN_TOKEN in .env.';
        exit;
    }
}

require_admin_access();

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function table_exists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return $cache[$table] = (bool)$stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function money_from_cents(int $cents): string {
    return '$' . number_format($cents / 100, 2);
}

function stripe_meta_value($session, string $key): string {
    return trim((string)($session->metadata->{$key} ?? ''));
}

function stripe_customer_value($session, string $key): string {
    return trim((string)($session->customer_details->{$key} ?? ''));
}

function request_exists_for_pi(PDO $pdo, string $paymentIntentId): bool {
    if ($paymentIntentId === '' || !column_exists($pdo, 'setmaxx_requests', 'stripe_payment_intent_id')) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM setmaxx_requests WHERE stripe_payment_intent_id = ? LIMIT 1");
    $stmt->execute([$paymentIntentId]);
    return (bool)$stmt->fetchColumn();
}

function general_tip_exists_for_pi(PDO $pdo, string $paymentIntentId): bool {
    if ($paymentIntentId === '' || !table_exists($pdo, 'setmaxx_general_tips')) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM setmaxx_general_tips WHERE stripe_payment_intent_id = ? LIMIT 1");
    $stmt->execute([$paymentIntentId]);
    return (bool)$stmt->fetchColumn();
}

function local_session_exists(PDO $pdo, int $sessionId, int $userId): bool {
    if ($sessionId <= 0) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$sessionId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function local_song_exists(PDO $pdo, int $songId, int $userId): bool {
    if ($songId <= 0) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM setmaxx_songs WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$songId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function connected_account_for_user(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare(
        "SELECT sca.*, u.email, u.display_name
         FROM setmaxx_connect_accounts sca
         JOIN users u ON u.id = sca.user_id
         WHERE sca.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function connected_accounts(PDO $pdo): array {
    $stmt = $pdo->query(
        "SELECT sca.user_id, sca.stripe_account_id, sca.charges_enabled, sca.payouts_enabled, u.email, u.display_name
         FROM setmaxx_connect_accounts sca
         JOIN users u ON u.id = sca.user_id
         ORDER BY sca.updated_at DESC, sca.created_at DESC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$messages = [];
$errors = [];

if (empty($_SESSION['setmaxx_reconcile_csrf'])) {
    $_SESSION['setmaxx_reconcile_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['setmaxx_reconcile_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
            throw new RuntimeException('Refresh and try again.');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $account = connected_account_for_user($pdo, $userId);
        if (!$account) throw new RuntimeException('Connected account not found.');

        $kind = trim((string)($_POST['kind'] ?? ''));
        $paymentIntentId = trim((string)($_POST['payment_intent_id'] ?? ''));
        $checkoutSessionId = trim((string)($_POST['checkout_session_id'] ?? ''));
        $localSessionId = (int)($_POST['local_session_id'] ?? 0);
        $songId = (int)($_POST['song_id'] ?? 0);
        $amountCents = max(0, (int)($_POST['amount_cents'] ?? 0));
        $createdAt = date('Y-m-d H:i:s', max(1, (int)($_POST['created'] ?? time())));
        $payerName = trim((string)($_POST['payer_name'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($paymentIntentId === '' || $amountCents <= 0) throw new RuntimeException('Missing payment details.');
        if (!local_session_exists($pdo, $localSessionId, $userId)) throw new RuntimeException('Choose a valid local session for this artist.');

        if ($kind === 'setmaxx_tip') {
            if (request_exists_for_pi($pdo, $paymentIntentId)) throw new RuntimeException('That request is already recorded.');
            if (!local_song_exists($pdo, $songId, $userId)) throw new RuntimeException('Song does not belong to this artist.');

            $stmt = $pdo->prepare(
                "INSERT INTO setmaxx_requests
                    (gig_session_id, song_id, requester_name, request_note, amount_cents, status, active_lock, payment_method, stripe_payment_intent_id, created_at)
                 VALUES
                    (?, ?, ?, ?, ?, 'pending', 1, 'stripe', ?, ?)"
            );
            $stmt->execute([
                $localSessionId,
                $songId,
                $payerName !== '' ? mb_substr($payerName, 0, 190) : null,
                $note !== '' ? mb_substr($note, 0, 255) : null,
                $amountCents,
                $paymentIntentId,
                $createdAt,
            ]);
            $messages[] = 'Backfilled song request for ' . money_from_cents($amountCents) . '.';
        } elseif ($kind === 'setmaxx_general_tip') {
            if (!table_exists($pdo, 'setmaxx_general_tips')) throw new RuntimeException('General tips table does not exist.');
            if (general_tip_exists_for_pi($pdo, $paymentIntentId)) throw new RuntimeException('That general tip is already recorded.');

            $stmt = $pdo->prepare(
                "INSERT INTO setmaxx_general_tips
                    (gig_session_id, user_id, tipper_name, tip_note, amount_cents, status, payment_method, stripe_payment_intent_id, created_at)
                 VALUES
                    (?, ?, ?, ?, ?, 'paid', 'stripe', ?, ?)"
            );
            $stmt->execute([
                $localSessionId,
                $userId,
                $payerName !== '' ? mb_substr($payerName, 0, 190) : null,
                $note !== '' ? mb_substr($note, 0, 255) : null,
                $amountCents,
                $paymentIntentId,
                $createdAt,
            ]);
            $messages[] = 'Backfilled general tip for ' . money_from_cents($amountCents) . '.';
        } else {
            throw new RuntimeException('Only SetMaxx request and general tip payments can be backfilled here.');
        }

        if (column_exists($pdo, 'stripe_webhook_events', 'payload_json')) {
            error_log('SetMaxx reconcile backfill: user=' . $userId . ' pi=' . $paymentIntentId . ' checkout=' . $checkoutSessionId);
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$accounts = connected_accounts($pdo);
$selectedUserId = (int)($_GET['user_id'] ?? ($_POST['user_id'] ?? 0));
$selectedAccount = $selectedUserId > 0 ? connected_account_for_user($pdo, $selectedUserId) : null;
$lookbackDays = max(1, min(180, (int)($_GET['days'] ?? 30)));
$stripeRows = [];
$localSessions = [];

if ($selectedAccount) {
    $sessionsStmt = $pdo->prepare(
        "SELECT id, title, venue_name, status, starts_at, ends_at, created_at
         FROM setmaxx_gig_sessions
         WHERE user_id = ?
         ORDER BY created_at DESC"
    );
    $sessionsStmt->execute([$selectedUserId]);
    $localSessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stripeSessions = \Stripe\Checkout\Session::all(
            [
                'limit' => 100,
                'created' => ['gte' => time() - ($lookbackDays * 86400)],
            ],
            ['stripe_account' => (string)$selectedAccount['stripe_account_id']]
        );

        foreach ($stripeSessions->data as $session) {
            $kind = stripe_meta_value($session, 'kind');
            if (!in_array($kind, ['setmaxx_tip', 'setmaxx_general_tip'], true)) continue;
            if ((string)($session->payment_status ?? '') !== 'paid') continue;

            $paymentIntentId = trim((string)($session->payment_intent ?? ''));
            $metadataSessionId = (int)stripe_meta_value($session, 'gig_session_id');
            $songId = (int)stripe_meta_value($session, 'song_id');
            $amountCents = (int)($session->amount_total ?? 0);
            $exists = $kind === 'setmaxx_general_tip'
                ? general_tip_exists_for_pi($pdo, $paymentIntentId)
                : request_exists_for_pi($pdo, $paymentIntentId);

            $stripeRows[] = [
                'checkout_session_id' => (string)$session->id,
                'payment_intent_id' => $paymentIntentId,
                'kind' => $kind,
                'metadata_session_id' => $metadataSessionId,
                'metadata_session_exists' => local_session_exists($pdo, $metadataSessionId, $selectedUserId),
                'song_id' => $songId,
                'song_exists' => $kind !== 'setmaxx_tip' || local_song_exists($pdo, $songId, $selectedUserId),
                'amount_cents' => $amountCents,
                'payer_name' => stripe_meta_value($session, 'requester_name') ?: stripe_meta_value($session, 'tipper_name') ?: stripe_customer_value($session, 'name'),
                'note' => stripe_meta_value($session, 'request_note') ?: stripe_meta_value($session, 'tip_note'),
                'created' => (int)($session->created ?? 0),
                'exists' => $exists,
            ];
        }
    } catch (Throwable $e) {
        $errors[] = 'Stripe sessions could not be loaded: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SetMaxx Stripe Reconciliation</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;margin:24px;background:#f7f8fb;color:#14213d;}
    a{color:#3157d5;} .wrap{max-width:1280px;margin:0 auto;} .card{background:#fff;border:1px solid #dfe5ef;border-radius:12px;padding:18px;margin:0 0 16px;box-shadow:0 8px 24px rgba(16,24,40,.06);}
    label{display:block;font-weight:700;margin-bottom:6px;} select,input{font:inherit;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;} button{font:inherit;font-weight:700;padding:8px 12px;border:0;border-radius:8px;background:#3157d5;color:#fff;cursor:pointer;}
    table{width:100%;border-collapse:collapse;background:#fff;} th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;font-size:14px;} th{background:#eef2f7;font-size:12px;text-transform:uppercase;letter-spacing:.04em;}
    .muted{color:#64748b;} .ok{color:#157347;font-weight:700;} .warn{color:#b45309;font-weight:700;} .bad{color:#b91c1c;font-weight:700;} .flash{padding:10px 12px;border-radius:8px;margin-bottom:10px;} .success{background:#dcfce7;color:#14532d;} .error{background:#fee2e2;color:#7f1d1d;}
    .rowform{display:flex;gap:8px;align-items:center;flex-wrap:wrap;} .filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;}
    code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;}
  </style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php<?= isset($_GET['k']) ? '?k=' . h((string)$_GET['k']) : '' ?>">&larr; Admin dashboard</a></p>
  <h1>SetMaxx Stripe Reconciliation</h1>
  <p class="muted">Compares connected-account Stripe Checkout Sessions with local SetMaxx request and tip rows.</p>

  <?php foreach ($messages as $message): ?><div class="flash success"><?= h($message) ?></div><?php endforeach; ?>
  <?php foreach ($errors as $error): ?><div class="flash error"><?= h($error) ?></div><?php endforeach; ?>

  <div class="card">
    <form class="filters" method="get" action="">
      <?php if (isset($_GET['k'])): ?><input type="hidden" name="k" value="<?= h((string)$_GET['k']) ?>"><?php endif; ?>
      <div>
        <label for="user_id">Connected account</label>
        <select id="user_id" name="user_id">
          <option value="0">Choose an artist</option>
          <?php foreach ($accounts as $account): ?>
            <option value="<?= (int)$account['user_id'] ?>" <?= (int)$account['user_id'] === $selectedUserId ? 'selected' : '' ?>>
              #<?= (int)$account['user_id'] ?> - <?= h((string)($account['display_name'] ?: $account['email'])) ?> - <?= h((string)$account['stripe_account_id']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="days">Lookback days</label>
        <input id="days" name="days" type="number" min="1" max="180" value="<?= (int)$lookbackDays ?>">
      </div>
      <button type="submit">Load Stripe sessions</button>
    </form>
  </div>

  <?php if ($selectedAccount): ?>
    <div class="card">
      <strong><?= h((string)($selectedAccount['display_name'] ?: $selectedAccount['email'])) ?></strong>
      <span class="muted">User #<?= (int)$selectedAccount['user_id'] ?>, account <?= h((string)$selectedAccount['stripe_account_id']) ?></span>
    </div>

    <table>
      <thead>
        <tr>
          <th>Stripe payment</th>
          <th>Kind</th>
          <th>Amount</th>
          <th>Metadata</th>
          <th>Local status</th>
          <th>Backfill</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$stripeRows): ?>
          <tr><td colspan="6" class="muted">No paid SetMaxx Checkout Sessions found in this connected account for this window.</td></tr>
        <?php else: foreach ($stripeRows as $row): ?>
          <tr>
            <td>
              <div><code><?= h($row['payment_intent_id']) ?></code></div>
              <div class="muted"><code><?= h($row['checkout_session_id']) ?></code></div>
              <div class="muted"><?= h(date('M j, Y g:i A', (int)$row['created'])) ?></div>
            </td>
            <td><?= h($row['kind']) ?></td>
            <td><?= h(money_from_cents((int)$row['amount_cents'])) ?></td>
            <td>
              <div>Session: <?= (int)$row['metadata_session_id'] ?> <?= $row['metadata_session_exists'] ? '<span class="ok">local</span>' : '<span class="warn">missing</span>' ?></div>
              <?php if ($row['kind'] === 'setmaxx_tip'): ?>
                <div>Song: <?= (int)$row['song_id'] ?> <?= $row['song_exists'] ? '<span class="ok">local</span>' : '<span class="bad">missing</span>' ?></div>
              <?php endif; ?>
              <div class="muted">Payer: <?= h($row['payer_name'] ?: 'Not set') ?></div>
            </td>
            <td><?= $row['exists'] ? '<span class="ok">Recorded</span>' : '<span class="bad">Missing locally</span>' ?></td>
            <td>
              <?php if (!$row['exists']): ?>
                <form class="rowform" method="post" action="">
                  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$selectedUserId ?>">
                  <input type="hidden" name="kind" value="<?= h($row['kind']) ?>">
                  <input type="hidden" name="payment_intent_id" value="<?= h($row['payment_intent_id']) ?>">
                  <input type="hidden" name="checkout_session_id" value="<?= h($row['checkout_session_id']) ?>">
                  <input type="hidden" name="song_id" value="<?= (int)$row['song_id'] ?>">
                  <input type="hidden" name="amount_cents" value="<?= (int)$row['amount_cents'] ?>">
                  <input type="hidden" name="created" value="<?= (int)$row['created'] ?>">
                  <input type="hidden" name="payer_name" value="<?= h($row['payer_name']) ?>">
                  <input type="hidden" name="note" value="<?= h($row['note']) ?>">
                  <select name="local_session_id" required>
                    <?php foreach ($localSessions as $session): ?>
                      <option value="<?= (int)$session['id'] ?>" <?= (int)$session['id'] === (int)$row['metadata_session_id'] ? 'selected' : '' ?>>
                        #<?= (int)$session['id'] ?> - <?= h((string)$session['title']) ?> (<?= h((string)$session['status']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" <?= ($row['kind'] === 'setmaxx_tip' && !$row['song_exists']) ? 'disabled' : '' ?>>Backfill</button>
                </form>
                <?php if ($row['kind'] === 'setmaxx_tip' && !$row['song_exists']): ?>
                  <div class="bad">Song must exist locally before backfill.</div>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted">No action needed</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
