<?php
require __DIR__ . '/../_core/bootstrap.php';

$email_prefill = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $err = 'Security check failed. Please try again.';
  } else {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass1 = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = 'Please enter a valid email.';
    } elseif (strlen($pass1) < 8) {
      $err = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
      $err = 'Passwords do not match.';
    } else {
      // Create user if not exists
      $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
      $stmt->execute([$email]);
      $existing = $stmt->fetch();

      if ($existing) {
        $err = 'That email already has an account. Please log in instead.';
      } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
        $stmt->execute([$email, $hash]);

        Auth::login((int)$pdo->lastInsertId());

        $next = $_SESSION['login_next'] ?? '/studio/library.php';
        unset($_SESSION['login_next']);
        redirect($next);
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Create Studio Login • Nick Sanzeri</title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>
<main class="container" style="padding:3rem 0;">
  <h1>Create your Studio login</h1>
  <p class="muted">Takes ~15 seconds. This lets you access <strong>My Library</strong> and download purchases anytime.</p>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?php echo e($err); ?></div>
  <?php endif; ?>

  <form method="post" style="max-width:520px;">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>" />
    <label>Email</label>
    <input name="email" type="email" required value="<?php echo e($email_prefill); ?>" />

    <label style="margin-top:1rem;">Password</label>
    <input name="password" type="password" required minlength="8" />

    <label style="margin-top:1rem;">Confirm password</label>
    <input name="password2" type="password" required minlength="8" />

    <button class="btn btn-primary" style="margin-top:1.25rem;">Create account</button>
    <div style="margin-top:0.9rem;">
      <a class="text-link" href="/studio/login.php">Already have an account? Log in</a>
    </div>
  </form>
</main>
</body>
</html>