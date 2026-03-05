<?php
// /_core/Auth.php

class Auth {
  public static function userId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
  }

  public static function requireLogin(string $next = '/studio/library.php'): void {
    if (!self::userId()) {
      $_SESSION['login_next'] = $next;
      redirect('/studio/login.php');
    }
  }

  public static function login(int $userId): void {
    $_SESSION['user_id'] = $userId;
  }

  public static function logout(): void {
    unset($_SESSION['user_id']);
  }
}