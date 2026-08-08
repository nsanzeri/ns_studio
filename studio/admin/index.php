<?php
// admin/index.php - read-only admin dashboard for downloads + tool usage

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_usage.php';

function envv(string $k, $default = null) {
    if (function_exists('env')) {
        $v = env($k);
        if ($v !== null && $v !== '') {
            return $v;
        }
    }
    $v = $_ENV[$k] ?? getenv($k);
    return ($v !== false && $v !== null && $v !== '') ? $v : $default;
}

function is_local_env(): bool {
    if (function_exists('is_local')) {
        return (bool) is_local();
    }
    $app = strtolower((string) envv('APP_ENV', ''));
    return in_array($app, ['local', 'dev', 'development'], true);
}

function require_admin_access(): void {
    $user = (string) envv('ADMIN_USER', '');
    $pass = (string) envv('ADMIN_PASS', '');
    $token = (string) envv('ADMIN_TOKEN', '');

    if ($user !== '' && $pass !== '') {
        $u = $_SERVER['PHP_AUTH_USER'] ?? '';
        $p = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($user, (string) $u) || !hash_equals($pass, (string) $p)) {
            header('WWW-Authenticate: Basic realm="Admin"');
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        return;
    }

    if ($token !== '') {
        $provided = $_GET['k'] ?? ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
        if (!$provided || !hash_equals($token, (string) $provided)) {
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

function q(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function scalar_q(PDO $pdo, string $sql, array $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function h($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function table_exists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $st = $pdo->prepare('SHOW TABLES LIKE ?');
        $st->execute([$table]);
        $cache[$table] = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function signed_download_url(string $base, string $token, ?string $secret): string {
    $url = $base . 'download.php?t=' . urlencode($token);
    if ($secret) {
        $sig = hash_hmac('sha256', $token, $secret);
        $url .= '&s=' . urlencode($sig);
    }
    return $url;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tab = (string) ($_GET['tab'] ?? 'overview');
$limit = max(10, min(500, (int) ($_GET['limit'] ?? 100)));
$search = trim((string) ($_GET['q'] ?? ''));
$product = trim((string) ($_GET['product'] ?? ''));
$result = trim((string) ($_GET['result'] ?? ''));
$feature = trim((string) ($_GET['feature'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$downloadSecret = envv('DOWNLOAD_SECRET', null);
//$toolUsageEnabled = table_exists($pdo, 'tool_usage_log');
$toolUsageEnabled = true;
$base = rtrim((string) base_url('studio/'), '/') . '/';

$overview = [
    'tokens_total'       => table_exists($pdo, 'download_tokens') ? (int) scalar_q($pdo, 'SELECT COUNT(*) FROM download_tokens') : 0,
    'logs_total'         => table_exists($pdo, 'download_log') ? (int) scalar_q($pdo, 'SELECT COUNT(*) FROM download_log') : 0,
    'downloads_success'  => table_exists($pdo, 'download_log') ? (int) scalar_q($pdo, "SELECT COUNT(*) FROM download_log WHERE result='success'") : 0,
    'downloads_blocked'  => 0,
    'tokens_expired'     => table_exists($pdo, 'download_tokens') ? (int) scalar_q($pdo, 'SELECT COUNT(*) FROM download_tokens WHERE expires_at < NOW()') : 0,
    'tokens_exhausted'   => table_exists($pdo, 'download_tokens') ? (int) scalar_q($pdo, 'SELECT COUNT(*) FROM download_tokens WHERE uses_remaining <= 0') : 0,
    'tool_events_total'  => $toolUsageEnabled ? (int) scalar_q($pdo, 'SELECT COUNT(*) FROM tool_usage_log') : 0,
    'tool_success'       => $toolUsageEnabled ? (int) scalar_q($pdo, "SELECT COUNT(*) FROM tool_usage_log WHERE status='success'") : 0,
    'tool_blocked'       => $toolUsageEnabled ? (int) scalar_q($pdo, "SELECT COUNT(*) FROM tool_usage_log WHERE status='blocked'") : 0,
    'tool_error'         => $toolUsageEnabled ? (int) scalar_q($pdo, "SELECT COUNT(*) FROM tool_usage_log WHERE status='error'") : 0,
];

if (table_exists($pdo, 'download_log')) {
    $blockedCount = scalar_q($pdo, "SELECT COUNT(*) FROM download_log WHERE result IN ('blocked','expired','exhausted','revoked','not_found','invalid','error')");
    $overview['downloads_blocked'] = (int) $blockedCount;
}

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (dt.token LIKE :token_q OR dt.checkout_session_id LIKE :checkout_q OR dt.purchaser_email LIKE :purchaser_q OR dt.product_key LIKE :product_q)';
    $params[':token_q'] = '%' . $search . '%';
    $params[':checkout_q'] = '%' . $search . '%';
    $params[':purchaser_q'] = '%' . $search . '%';
    $params[':product_q'] = '%' . $search . '%';
}
if ($product !== '') {
    $where .= ' AND dt.product_key = :product';
    $params[':product'] = $product;
}

$tokens = table_exists($pdo, 'download_tokens') ? q($pdo, "
    SELECT dt.id, dt.token, dt.checkout_session_id, dt.purchaser_email, dt.product_key,
           dt.expires_at, dt.uses_remaining, dt.last_used_at,
           HEX(dt.first_ip) AS first_ip_hex
    FROM download_tokens dt
    WHERE $where
    ORDER BY dt.id DESC
    LIMIT $limit
", $params) : [];

$w2 = '1=1';
$p2 = [];
if ($search !== '') {
    $w2 .= ' AND (dl.checkout_session_id LIKE :log_checkout_q OR dl.purchaser_email LIKE :log_purchaser_q OR dl.product_key LIKE :log_product_q OR dl.note LIKE :log_note_q)';
    $p2[':log_checkout_q'] = '%' . $search . '%';
    $p2[':log_purchaser_q'] = '%' . $search . '%';
    $p2[':log_product_q'] = '%' . $search . '%';
    $p2[':log_note_q'] = '%' . $search . '%';
}
if ($product !== '') {
    $w2 .= ' AND dl.product_key = :product';
    $p2[':product'] = $product;
}
if ($result !== '') {
    $w2 .= ' AND dl.result = :result';
    $p2[':result'] = $result;
}

$logs = table_exists($pdo, 'download_log') ? q($pdo, "
    SELECT dl.id, dl.created_at, dl.token_id, dl.purchase_id, dl.checkout_session_id, dl.purchaser_email,
           dl.product_key, dl.file_path, HEX(dl.ip) AS ip_hex, dl.user_agent, dl.result, dl.note
    FROM download_log dl
    WHERE $w2
    ORDER BY dl.id DESC
    LIMIT $limit
", $p2) : [];

$products = table_exists($pdo, 'download_tokens') ? q($pdo, 'SELECT DISTINCT product_key FROM download_tokens ORDER BY product_key ASC') : [];

$toolRows = [];
$toolFeatureSummary = [];
$toolUserSummary = [];
$featureOptions = [];
if ($toolUsageEnabled) {
    $wt = '1=1';
    $pt = [];

    if ($search !== '') {
        $wt .= ' AND (tu.user_email LIKE :tool_user_q OR tu.feature_key LIKE :tool_feature_q OR tu.action_key LIKE :tool_action_q OR tu.note LIKE :tool_note_q)';
        $pt[':tool_user_q'] = '%' . $search . '%';
        $pt[':tool_feature_q'] = '%' . $search . '%';
        $pt[':tool_action_q'] = '%' . $search . '%';
        $pt[':tool_note_q'] = '%' . $search . '%';
    }
    if ($feature !== '') {
        $wt .= ' AND tu.feature_key = :feature';
        $pt[':feature'] = $feature;
    }
    if ($status !== '') {
        $wt .= ' AND tu.status = :status';
        $pt[':status'] = $status;
    }

    $toolRows = q($pdo, "
        SELECT tu.id, tu.created_at, tu.user_id, tu.user_email, tu.feature_key, tu.action_key,
               tu.calendar_count, tu.result_count, tu.status, tu.note, HEX(tu.ip) AS ip_hex
        FROM tool_usage_log tu
        WHERE $wt
        ORDER BY tu.id DESC
        LIMIT $limit
    ", $pt);

    $toolFeatureSummary = q($pdo, "
        SELECT tu.feature_key,
               COUNT(*) AS uses,
               SUM(CASE WHEN tu.status='success' THEN 1 ELSE 0 END) AS success_count,
               SUM(CASE WHEN tu.status='blocked' THEN 1 ELSE 0 END) AS blocked_count,
               SUM(CASE WHEN tu.status='error' THEN 1 ELSE 0 END) AS error_count,
               COUNT(DISTINCT COALESCE(NULLIF(tu.user_email,''), CONCAT('user#', tu.user_id), 'anonymous')) AS unique_users
        FROM tool_usage_log tu
        WHERE $wt
        GROUP BY tu.feature_key
        ORDER BY uses DESC, tu.feature_key ASC
    ", $pt);

    $toolUserSummary = q($pdo, "
        SELECT COALESCE(NULLIF(tu.user_email,''), CONCAT('user#', tu.user_id), 'anonymous') AS who,
               COUNT(*) AS uses,
               COUNT(DISTINCT tu.feature_key) AS features_used,
               MAX(tu.created_at) AS last_seen
        FROM tool_usage_log tu
        WHERE $wt
        GROUP BY who
        ORDER BY uses DESC, who ASC
        LIMIT 50
    ", $pt);

    $featureOptions = q($pdo, 'SELECT DISTINCT feature_key FROM tool_usage_log ORDER BY feature_key ASC');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin | Downloads + Tool Usage</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#0b0b0f;color:#eaeaf2}
    a{color:#ffd277;text-decoration:none}
    a:hover{text-decoration:underline}
    .wrap{max-width:1280px;margin:0 auto;padding:22px}
    .card{background:#141421;border:1px solid #24243a;border-radius:14px;padding:16px;margin:14px 0;overflow:auto}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .pill{padding:6px 10px;border-radius:999px;background:#1c1c2f;border:1px solid #2b2b45;font-size:12px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{border-bottom:1px solid #26263d;padding:10px;vertical-align:top}
    th{color:#bdbdd6;text-align:left;font-weight:600;white-space:nowrap}
    input,select{background:#0f0f18;color:#eaeaf2;border:1px solid #2b2b45;border-radius:10px;padding:8px 10px}
    .btn{display:inline-block;background:#ffd277;color:#111;padding:8px 12px;border-radius:12px;text-decoration:none;font-weight:700;border:0;cursor:pointer}
    .muted{color:#b3b3c8}
    .tabs a{margin-right:10px}
    .small{font-size:12px}
    code{background:#10101a;padding:2px 6px;border-radius:8px;border:1px solid #2b2b45}
    .notice{padding:12px 14px;border-radius:12px;background:#1b1b2d;border:1px solid #313154}
  </style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 6px 0;">Studio Admin</h1>
  <div class="muted small">This page is read-only. Protect it on production with <code>ADMIN_USER</code>/<code>ADMIN_PASS</code> or <code>ADMIN_TOKEN</code> in <code>.env</code>.</div>

  <div class="card">
    <div class="row">
      <div class="pill">Tokens: <strong><?= h($overview['tokens_total']) ?></strong></div>
      <div class="pill">Download Logs: <strong><?= h($overview['logs_total']) ?></strong></div>
      <div class="pill">Download Success: <strong><?= h($overview['downloads_success']) ?></strong></div>
      <div class="pill">Download Issues: <strong><?= h($overview['downloads_blocked']) ?></strong></div>
      <div class="pill">Tool Events: <strong><?= h($overview['tool_events_total']) ?></strong></div>
      <div class="pill">Tool Success: <strong><?= h($overview['tool_success']) ?></strong></div>
      <div class="pill">Tool Blocked: <strong><?= h($overview['tool_blocked']) ?></strong></div>
      <div class="pill">Tool Errors: <strong><?= h($overview['tool_error']) ?></strong></div>
    </div>
  </div>

  <div class="tabs card">
    <a href="?tab=overview" class="<?= $tab==='overview'?'btn':'' ?>">Overview</a>
    <a href="?tab=tokens" class="<?= $tab==='tokens'?'btn':'' ?>">Tokens</a>
    <a href="?tab=logs" class="<?= $tab==='logs'?'btn':'' ?>">Download Logs</a>
    <a href="?tab=tools" class="<?= $tab==='tools'?'btn':'' ?>">Tool Usage</a>
    <a href="setmaxx_reconcile.php<?= isset($_GET['k']) ? '?k=' . h((string)$_GET['k']) : '' ?>">SetMaxx Stripe Reconcile</a>

    <form method="get" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <input name="q" value="<?= h($search) ?>" placeholder="Search email/session/feature/note">
      <select name="product">
        <option value="">All products</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= h($p['product_key']) ?>" <?= $product===$p['product_key']?'selected':'' ?>><?= h($p['product_key']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="result">
        <option value="">All download results</option>
        <?php foreach (['success','blocked','expired','exhausted','revoked','not_found','invalid','error'] as $r): ?>
          <option value="<?= h($r) ?>" <?= $result===$r?'selected':'' ?>><?= h($r) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="feature">
        <option value="">All tool features</option>
        <?php foreach ($featureOptions as $f): ?>
          <option value="<?= h($f['feature_key']) ?>" <?= $feature===$f['feature_key']?'selected':'' ?>><?= h($f['feature_key']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status">
        <option value="">All tool statuses</option>
        <?php foreach (['success','blocked','error'] as $s): ?>
          <option value="<?= h($s) ?>" <?= $status===$s?'selected':'' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
      <input name="limit" value="<?= h($limit) ?>" style="width:90px" title="10-500">
      <button class="btn" type="submit">Filter</button>
    </form>
  </div>

  <?php if (!$toolUsageEnabled && ($tab === 'overview' || $tab === 'tools')): ?>
    <div class="card notice">
      <strong>Tool usage logging is not live yet.</strong>
      Run <code>studio/_private/sql/2026-04-09-tool-usage-log.sql</code> once, then start calling <code>log_tool_usage(...)</code> from your tool features or post to <code>studio/api/log_tool_usage.php</code>.
    </div>
  <?php endif; ?>

  <?php if ($tab === 'overview'): ?>
    <div class="card">
      <h2 style="margin-top:0;">Top Tool Features</h2>
      <?php if (!$toolUsageEnabled): ?>
        <div class="muted">No tool usage data yet.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr><th>Feature</th><th>Total Uses</th><th>Success</th><th>Blocked</th><th>Error</th><th>Unique Users</th></tr>
        </thead>
        <tbody>
        <?php foreach ($toolFeatureSummary as $row): ?>
          <tr>
            <td><code><?= h($row['feature_key']) ?></code></td>
            <td><?= h($row['uses']) ?></td>
            <td><?= h($row['success_count']) ?></td>
            <td><?= h($row['blocked_count']) ?></td>
            <td><?= h($row['error_count']) ?></td>
            <td><?= h($row['unique_users']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$toolFeatureSummary): ?><tr><td colspan="6" class="muted">No rows yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin-top:0;">Top Users</h2>
      <?php if (!$toolUsageEnabled): ?>
        <div class="muted">No tool usage data yet.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr><th>User</th><th>Total Uses</th><th>Features Used</th><th>Last Seen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($toolUserSummary as $row): ?>
          <tr>
            <td><?= h($row['who']) ?></td>
            <td><?= h($row['uses']) ?></td>
            <td><?= h($row['features_used']) ?></td>
            <td><?= h($row['last_seen']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$toolUserSummary): ?><tr><td colspan="4" class="muted">No rows yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'tokens' || $tab === 'overview'): ?>
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
        <?php if (!$tokens): ?><tr><td colspan="9" class="muted">No tokens found.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div class="muted small" style="margin-top:10px;">If <code>DOWNLOAD_SECRET</code> is set, links shown here include the signature automatically.</div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'logs'): ?>
    <div class="card">
      <h2 style="margin-top:0;">Recent Download Logs</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>When</th><th>Result</th><th>Product</th><th>Email</th><th>Session</th><th>IP</th><th>Note</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $row): ?>
          <tr>
            <td><?= h($row['id']) ?></td>
            <td><?= h($row['created_at']) ?></td>
            <td><code><?= h($row['result']) ?></code></td>
            <td><?= h($row['product_key']) ?></td>
            <td><?= h($row['purchaser_email']) ?></td>
            <td class="small"><?= h($row['checkout_session_id']) ?></td>
            <td class="small"><?= h($row['ip_hex']) ?></td>
            <td><?= h($row['note']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="8" class="muted">No logs found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'tools'): ?>
    <div class="card">
      <h2 style="margin-top:0;">Tool Usage Events</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>When</th><th>User</th><th>Feature</th><th>Action</th><th>Status</th><th>Calendars</th><th>Results</th><th>IP</th><th>Note</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($toolRows as $row): ?>
          <tr>
            <td><?= h($row['id']) ?></td>
            <td><?= h($row['created_at']) ?></td>
            <td><?= h($row['user_email'] ?: ($row['user_id'] ? 'user#' . $row['user_id'] : 'anonymous')) ?></td>
            <td><code><?= h($row['feature_key']) ?></code></td>
            <td><code><?= h($row['action_key']) ?></code></td>
            <td><code><?= h($row['status']) ?></code></td>
            <td><?= h($row['calendar_count']) ?></td>
            <td><?= h($row['result_count']) ?></td>
            <td class="small"><?= h($row['ip_hex']) ?></td>
            <td><?= h($row['note']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$toolRows): ?><tr><td colspan="10" class="muted">No tool usage rows found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2 style="margin-top:0;">Integration Notes</h2>
      <div class="muted">
        Your uploaded zip did not include the actual tool feature pages, so this patch gives you the logger, the API endpoint, the SQL migration, and the admin reporting page. To track a feature, call <code>log_tool_usage($pdo, [...])</code> from the relevant PHP page, or POST JSON to <code>studio/api/log_tool_usage.php</code>.
      </div>
      <pre style="white-space:pre-wrap;background:#10101a;border:1px solid #2b2b45;border-radius:12px;padding:12px;"><code>require_once __DIR__ . '/../_private/_core/tool_usage.php';

log_tool_usage($pdo, [
  'feature_key'    => 'availability_check',
  'action_key'     => 'run',
  'status'         => 'success',
  'calendar_count' => 2,
  'result_count'   => 14,
  'input'          => [
      'from' => '2026-04-01',
      'to'   => '2026-04-30'
  ],
]);</code></pre>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
