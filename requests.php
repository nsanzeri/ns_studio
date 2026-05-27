<?php
require_once __DIR__ . '/studio/_private/_core/bootstrap.php';

function ns_setmaxx_site_performer_user_id(): int
{
    $configured = (int)env('SETMAXX_SITE_PERFORMER_USER_ID', 0);
    if ($configured > 0) return $configured;

    $directUsers = trim((string)env('SETMAXX_DIRECT_PLATFORM_TIP_USER_IDS', ''));
    if ($directUsers !== '') {
        $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $directUsers) ?: []), fn($id) => $id > 0));
        if ($ids) return (int)$ids[0];
    }

    return 1;
}

function ns_setmaxx_public_links_ready(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_links' LIMIT 1");
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function ns_setmaxx_public_link_token(PDO $pdo, int $userId): ?string
{
    if (!ns_setmaxx_public_links_ready($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT public_token FROM setmaxx_public_links WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $token = (string)($stmt->fetchColumn() ?: '');
    if ($token !== '') return $token;

    $insert = $pdo->prepare("INSERT INTO setmaxx_public_links (user_id, public_token) VALUES (?, ?)");
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(16));
        try {
            $insert->execute([$userId, $token]);
            return $token;
        } catch (Throwable $e) {
            if ($attempt === 4) throw $e;
        }
    }

    return null;
}

$token = ns_setmaxx_public_link_token($pdo, ns_setmaxx_site_performer_user_id());

if ($token) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $siteBase = rtrim(dirname($scriptName), '/');
    if ($siteBase === '/' || $siteBase === '.') $siteBase = '';
    header('Location: ' . $siteBase . '/studio/setmaxx/public.php?link=' . rawurlencode($token));
    exit;
}

http_response_code(503);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Live Requests | Nick Sanzeri</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="section">
  <div class="container">
    <h1>Live requests are warming up.</h1>
    <p class="muted">The request page is not available just yet. Check back at showtime.</p>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
