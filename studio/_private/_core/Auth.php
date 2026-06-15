<?php

declare(strict_types=1);

class Auth {
  public static function userId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
  }

  public static function isLoggedIn(): bool {
    return self::userId() !== null;
  }

  public static function login(int $userId, ?PDO $pdo = null, ?array $config = null): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    $pdo = $pdo ?? self::globalPdo();
    $config = $config ?? self::globalConfig();
    if ($pdo && $config) {
      self::issueRememberToken($pdo, $config, $userId);
    }
  }

  public static function logout(?PDO $pdo = null, ?array $config = null): void {
    $pdo = $pdo ?? self::globalPdo();
    $config = $config ?? self::globalConfig();
    if ($config) {
      self::forgetRememberedDevice($pdo, $config);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
  }

  public static function attemptRememberLogin(PDO $pdo, array $config): void {
    if (self::isLoggedIn() || !self::rememberEnabled($config) || !self::rememberTableExists($pdo)) {
      return;
    }

    $cookie = (string)($_COOKIE[self::rememberCookieName($config)] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
      return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!preg_match('/^[a-f0-9]{32}$/i', $selector) || !preg_match('/^[a-f0-9]{64}$/i', $validator)) {
      self::clearRememberCookie($config);
      return;
    }

    try {
      self::deleteExpiredRememberTokens($pdo);

      $stmt = $pdo->prepare("
        SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.id AS active_user_id
        FROM auth_remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.selector = ?
        LIMIT 1
      ");
      $stmt->execute([$selector]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        self::clearRememberCookie($config);
        return;
      }

      if (new DateTimeImmutable((string)$row['expires_at']) <= new DateTimeImmutable('now')) {
        $pdo->prepare('DELETE FROM auth_remember_tokens WHERE id = ?')->execute([(int)$row['id']]);
        self::clearRememberCookie($config);
        return;
      }

      if (!hash_equals((string)$row['token_hash'], self::rememberTokenHash($validator))) {
        $pdo->prepare('DELETE FROM auth_remember_tokens WHERE selector = ?')->execute([$selector]);
        self::clearRememberCookie($config);
        return;
      }

      session_regenerate_id(true);
      $_SESSION['user_id'] = (int)$row['user_id'];
      self::touchLogin($pdo, (int)$row['user_id']);
      self::rotateRememberToken($pdo, $config, (int)$row['id']);
    } catch (Throwable $e) {
      self::clearRememberCookie($config);
    }
  }

  public static function forgetRememberedDevice(?PDO $pdo, array $config): void {
    $cookie = (string)($_COOKIE[self::rememberCookieName($config)] ?? '');
    if ($pdo && $cookie !== '' && str_contains($cookie, ':') && self::rememberTableExists($pdo)) {
      [$selector] = explode(':', $cookie, 2);
      if (preg_match('/^[a-f0-9]{32}$/i', $selector)) {
        try {
          $pdo->prepare('DELETE FROM auth_remember_tokens WHERE selector = ?')->execute([$selector]);
        } catch (Throwable $e) {
        }
      }
    }

    self::clearRememberCookie($config);
  }

  public static function requireLogin(string $next = 'member/library.php'): void {
    if (!self::isLoggedIn()) {
      $_SESSION['login_next'] = $next;
      redirect(base_url('studio/member/login.php'));
    }
  }

  public static function currentUser(PDO $pdo): ?array {
    $userId = self::userId();
    if (!$userId) return null;

    $stmt = $pdo->prepare('SELECT id, email, google_sub, display_name, created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
  }

  public static function touchLogin(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$userId]);
  }

  public static function syncEntitlementsByEmail(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $email = (string)($stmt->fetchColumn() ?: '');
    if ($email === '') {
      return;
    }

    $sql = "
      INSERT INTO entitlements (user_id, product_id, source, status, expires_at)
      SELECT DISTINCT ?, p.id, 'purchase', 'active', NULL
      FROM purchases pu
      JOIN download_tokens dt
        ON dt.checkout_session_id = pu.stripe_checkout_session_id
      JOIN products p
        ON p.slug = dt.product_key
      LEFT JOIN entitlements e
        ON e.user_id = ?
       AND e.product_id = p.id
       AND e.status = 'active'
      WHERE pu.purchaser_email = ?
        AND pu.status = 'paid'
        AND e.id IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $email]);
  }

  public static function findUserByEmail(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
  }

  public static function findUserByGoogleSub(PDO $pdo, string $sub): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_sub = ? LIMIT 1');
    $stmt->execute([$sub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
  }

  public static function upsertGoogleUser(PDO $pdo, string $sub, string $email, ?string $displayName = null): int {
    $email = strtolower(trim($email));

    $existingBySub = self::findUserByGoogleSub($pdo, $sub);
    if ($existingBySub) {
      $stmt = $pdo->prepare('UPDATE users SET email = ?, display_name = ?, last_login_at = NOW() WHERE id = ?');
      $stmt->execute([$email, $displayName, (int)$existingBySub['id']]);
      return (int)$existingBySub['id'];
    }

    $existingByEmail = self::findUserByEmail($pdo, $email);
    if ($existingByEmail) {
      $stmt = $pdo->prepare('UPDATE users SET google_sub = ?, display_name = COALESCE(?, display_name), last_login_at = NOW() WHERE id = ?');
      $stmt->execute([$sub, $displayName, (int)$existingByEmail['id']]);
      return (int)$existingByEmail['id'];
    }

    $randomPassword = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, google_sub, display_name, password_hash, last_login_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$email, $sub, $displayName, $randomPassword]);
    return (int)$pdo->lastInsertId();
  }

  private static function globalPdo(): ?PDO {
    return isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : null;
  }

  private static function globalConfig(): ?array {
    return isset($GLOBALS['config']) && is_array($GLOBALS['config']) ? $GLOBALS['config'] : null;
  }

  private static function rememberEnabled(array $config): bool {
    return self::rememberCookieDays($config) > 0;
  }

  private static function rememberCookieName(array $config): string {
    return (string)($config['auth']['remember_cookie_name'] ?? 'ns_studio_remember');
  }

  private static function rememberCookieDays(array $config): int {
    return max(0, (int)($config['auth']['remember_cookie_days'] ?? 30));
  }

  private static function rememberExpiresAt(array $config): DateTimeImmutable {
    return (new DateTimeImmutable('now'))->modify('+' . self::rememberCookieDays($config) . ' days');
  }

  private static function rememberTokenHash(string $validator): string {
    return hash('sha256', $validator);
  }

  private static function rememberCookieSecure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['SERVER_PORT'] ?? '') == '443');
  }

  private static function setRememberCookie(array $config, string $value, DateTimeImmutable $expiresAt): void {
    setcookie(self::rememberCookieName($config), $value, [
      'expires' => $expiresAt->getTimestamp(),
      'path' => '/',
      'domain' => '',
      'secure' => self::rememberCookieSecure(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }

  private static function clearRememberCookie(array $config): void {
    setcookie(self::rememberCookieName($config), '', [
      'expires' => time() - 42000,
      'path' => '/',
      'domain' => '',
      'secure' => self::rememberCookieSecure(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }

  private static function rememberTableExists(PDO $pdo): bool {
    static $exists = null;
    if ($exists !== null) {
      return $exists;
    }

    try {
      $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'auth_remember_tokens' LIMIT 1");
      $stmt->execute();
      $exists = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
      $exists = false;
    }

    return $exists;
  }

  private static function deleteExpiredRememberTokens(PDO $pdo): void {
    $pdo->exec('DELETE FROM auth_remember_tokens WHERE expires_at <= NOW()');
  }

  private static function issueRememberToken(PDO $pdo, array $config, int $userId): void {
    if (!self::rememberEnabled($config) || !self::rememberTableExists($pdo)) {
      return;
    }

    try {
      self::deleteExpiredRememberTokens($pdo);

      $selector = bin2hex(random_bytes(16));
      $validator = bin2hex(random_bytes(32));
      $expiresAt = self::rememberExpiresAt($config);

      $stmt = $pdo->prepare("
        INSERT INTO auth_remember_tokens (user_id, selector, token_hash, expires_at, created_at)
        VALUES (?, ?, ?, ?, NOW())
      ");
      $stmt->execute([
        $userId,
        $selector,
        self::rememberTokenHash($validator),
        $expiresAt->format('Y-m-d H:i:s'),
      ]);

      self::setRememberCookie($config, $selector . ':' . $validator, $expiresAt);
    } catch (Throwable $e) {
      self::clearRememberCookie($config);
    }
  }

  private static function rotateRememberToken(PDO $pdo, array $config, int $tokenId): void {
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = self::rememberExpiresAt($config);

    $stmt = $pdo->prepare("
      UPDATE auth_remember_tokens
      SET selector = ?, token_hash = ?, expires_at = ?, last_used_at = NOW()
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $selector,
      self::rememberTokenHash($validator),
      $expiresAt->format('Y-m-d H:i:s'),
      $tokenId,
    ]);

    self::setRememberCookie($config, $selector . ':' . $validator, $expiresAt);
  }
}
