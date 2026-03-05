<?php
// admin.php - lightweight download/admin dashboard (read-only)
// SECURITY: protect with either ADMIN_USER/ADMIN_PASS (Basic Auth) OR ADMIN_TOKEN (query/header).
require_once __DIR__ . '/../_private/_core/bootstrap.php';

function envv(string $k, $default=null) {
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
    return in_array($app, ['local','dev','development'], true);
}

function require_admin_access(): void {
    $user = (string)envv('ADMIN_USER', '');
    $pass = (string)envv('ADMIN_PASS', '');
    $token = (string)envv('ADMIN_TOKEN', '');

    // 1) Basic Auth
    if ($user !== '' && $pass !== '') {
        $u = $_SERVER['PHP_AUTH_USER'] ?? '';
        $p = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($user, $u) || !hash_equals($pass, $p)) {
            header('WWW-Authenticate: Basic realm="Admin"');
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        return;
    }

    // 2) Token
    if ($token !== '') {
        $provided = $_GET['k'] ?? ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
        if (!$provided || !hash_equals($token, (string)$provided)) {
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        return;
    }

    // 3) Fallback: allow only on local
    if (!is_local_env()) {
        http_response_code(403);
        echo 'Admin is disabled on production until you set ADMIN_USER/ADMIN_PASS or ADMIN_TOKEN in .env.';
        exit;
    }
}

require_admin_access();

// ---- DB helpers ----
function q(PDO $pdo, string $sql, array $params=[]): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tab = $_GET['tab'] ?? 'overview';
$limit = max(10, min(500, (int)($_GET['limit'] ?? 100)));

$search = trim((string)($_GET['q'] ?? ''));
$product = trim((string)($_GET['product'] ?? ''));
$result = trim((string)($_GET['result'] ?? ''));

$downloadSecret = envv('DOWNLOAD_SECRET', null);

// Base URL helper (best-effort)
$base = '';
if (function_exists('base_url')) {
    $base = rtrim((string)base_url(''), '/') . '/';
} else {
    // naive
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
}

function signed_download_url(string $base, string $token, ?string $secret): string {
    $url = $base . 'download.php?t=' . urlencode($token);
    if ($secret) {
        $sig = hash_hmac('sha256', $token, $secret);
        $url .= '&s=' . urlencode($sig);
    }
    return $url;
}

// ---- Queries ----
$overview = q($pdo, "
    SELECT
        (SELECT COUNT(*) FROM download_tokens) AS tokens_total,
        (SELECT COUNT(*) FROM download_log) AS logs_total,
        (SELECT COUNT(*) FROM download_log WHERE result='success') AS downloads_success,
        (SELECT COUNT(*) FROM download_log WHERE result='blocked') AS downloads_blocked,
        (SELECT COUNT(*) FROM download_tokens WHERE expires_at < NOW()) AS tokens_expired,
        (SELECT COUNT(*) FROM download_tokens WHERE uses_remaining <= 0) AS tokens_exhausted
");

$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (
        dt.token LIKE :q OR dt.checkout_session_id LIKE :q OR dt.purchaser_email LIKE :q
        OR dt.product_key LIKE :q
    )";
    $params[':q'] = '%' . $search . '%';
}
if ($product !== '') {
    $where .= " AND dt.product_key = :product";
    $params[':product'] = $product;
}

$tokens = q($pdo, "
    SELECT dt.id, dt.token, dt.checkout_session_id, dt.purchaser_email, dt.product_key,
           dt.expires_at, dt.uses_remaining, dt.last_used_at,
           HEX(dt.first_ip) AS first_ip_hex
    FROM download_tokens dt
    WHERE $where
    ORDER BY dt.id DESC
    LIMIT $limit
", $params);

// Logs tab filters
$w2 = "1=1";
$p2 = [];
if ($search !== '') {
    $w2 .= " AND (
        dl.checkout_session_id LIKE :q OR dl.purchaser_email LIKE :q OR dl.product_key LIKE :q
        OR dl.note LIKE :q
    )";
    $p2[':q'] = '%' . $search . '%';
}
if ($product !== '') {
    $w2 .= " AND dl.product_key = :product";
    $p2[':product'] = $product;
}
if ($result !== '') {
    $w2 .= " AND dl.result = :result";
    $p2[':result'] = $result;
}

$logs = q($pdo, "
    SELECT dl.id, dl.created_at, dl.token_id, dl.purchase_id, dl.checkout_session_id, dl.purchaser_email,
           dl.product_key, dl.file_path, HEX(dl.ip) AS ip_hex, dl.user_agent, dl.result, dl.note
    FROM download_log dl
    WHERE $w2
    ORDER BY dl.id DESC
    LIMIT $limit
", $p2);

// Distinct product keys for dropdown
$products = q($pdo, "SELECT DISTINCT product_key FROM download_tokens ORDER BY product_key ASC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin | Downloads</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#0b0b0f;color:#eaeaf2}
    a{color:#ffd277}
    .wrap{max-width:1200px;margin:0 auto;padding:22px}
    .card{background:#141421;border:1px solid #24243a;border-radius:14px;padding:16px;margin:14px 0}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .pill{padding:6px 10px;border-radius:999px;background:#1c1c2f;border:1px solid #2b2b45;font-size:12px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{border-bottom:1px solid #26263d;padding:10px;vertical-align:top}
    th{color:#bdbdd6;text-align:left;font-weight:600}
    input,select{background:#0f0f18;color:#eaeaf2;border:1px solid #2b2b45;border-radius:10px;padding:8px 10px}
    .btn{display:inline-block;background:#ffd277;color:#111;padding:8px 12px;border-radius:12px;text-decoration:none;font-weight:700;border:0;cursor:pointer}
    .muted{color:#b3b3c8}
    .tabs a{margin-right:10px}
    .small{font-size:12px}
    code{background:#10101a;padding:2px 6px;border-radius:8px;border:1px solid #2b2b45}
  </style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 6px 0;">Downloads Admin</h1>
  <div class="muted small">Set <code>ADMIN_USER</code>/<code>ADMIN_PASS</code> or <code>ADMIN_TOKEN</code> in .env for production access.</div>

  <div class="card">
    <div class="row">
      <div class="pill">Tokens: <strong><?= h($overview[0]['tokens_total'] ?? 0) ?></strong></div>
      <div class="pill">Logs: <strong><?= h($overview[0]['logs_total'] ?? 0) ?></strong></div>
      <div class="pill">Success: <strong><?= h($overview[0]['downloads_success'] ?? 0) ?></strong></div>
      <div class="pill">Blocked: <strong><?= h($overview[0]['downloads_blocked'] ?? 0) ?></strong></div>
      <div class="pill">Expired: <strong><?= h($overview[0]['tokens_expired'] ?? 0) ?></strong></div>
      <div class="pill">Exhausted: <strong><?= h($overview[0]['tokens_exhausted'] ?? 0) ?></strong></div>
    </div>
  </div>

  <div class="tabs card">
    <a href="?tab=overview" class="<?= $tab==='overview'?'btn':'' ?>">Overview</a>
    <a href="?tab=tokens" class="<?= $tab==='tokens'?'btn':'' ?>">Tokens</a>
    <a href="?tab=logs" class="<?= $tab==='logs'?'btn':'' ?>">Logs</a>

    <form method="get" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <input name="q" value="<?= h($search) ?>" placeholder="Search email/session/token/note">
      <select name="product">
        <option value="">All products</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= h($p['product_key']) ?>" <?= $product===$p['product_key']?'selected':'' ?>>
            <?= h($p['product_key']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="result">
        <option value="">All results</option>
        <?php foreach (['success','blocked','expired','exhausted','not_found','invalid','error'] as $r): ?>
          <option value="<?= h($r) ?>" <?= $result===$r?'selected':'' ?>><?= h($r) ?></option>
        <?php endforeach; ?>
      </select>
      <input name="limit" value="<?= h($limit) ?>" style="width:90px" title="10-500">
      <button class="btn" type="submit">Filter</button>
    </form>
  </div>

  <?php if ($tab === 'overview' || $tab === 'tokens'): ?>
    <div class="card">
      <h2 style="margin-top:0;">Recent Tokens</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Product</th><th>Email</th><th>Session</th><th>Expires</th><th>Uses</th><th>Last</th><th>First IP</th><th>Download</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($tokens as $t): ?>
          <?php $dl = signed_download_url($base, $t['token'], $downloadSecret); ?>
          <tr>
            <td><?= h($t['id']) ?></td>
            <td><code><?= h($t['product_key']) ?></code></td>
            <td><?= h($t['purchaser_email']) ?></td>
            <td class="small"><?= h($t['checkout_session_id']) ?></td>
            <td><?= h($t['expires_at']) ?></td>
            <td><?= h($t['uses_remaining']) ?></td>
            <td><?= h($t['last_used_at']) ?></td>
            <td class="small"><?= h($t['first_ip_hex']) ?></td>
            <td><a class="btn" href="<?= h($dl) ?>" target="_blank" rel="noopener">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="muted small" style="margin-top:10px;">
        Tip: If <code>DOWNLOAD_SECRET</code> is set, links shown here include the signature automatically.
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'logs'): ?>
    <div class="card">
      <h2 style="margin-top:0;">Recent Logs</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>When</th><th>Result</th><th>Product</th><th>Email</th><th>Session</th><th>IP</th><th>Note</th><th>User Agent</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><?= h($l['id']) ?></td>
            <td class="small"><?= h($l['created_at']) ?></td>
            <td><code><?= h($l['result']) ?></code></td>
            <td><code><?= h($l['product_key']) ?></code></td>
            <td><?= h($l['purchaser_email']) ?></td>
            <td class="small"><?= h($l['checkout_session_id']) ?></td>
            <td class="small"><?= h($l['ip_hex']) ?></td>
            <td class="small"><?= h($l['note']) ?></td>
            <td class="small"><?= h($l['user_agent']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
