<?php

declare(strict_types=1);

class Auth {
  public static function userId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
  }

  public static function isLoggedIn(): bool {
    return self::userId() !== null;
  }

  public static function login(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
  }

  public static function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
  }

  public static function requireLogin(string $next = '/studio/member/library.php'): void {
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
}
