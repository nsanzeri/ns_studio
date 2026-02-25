<?php
require __DIR__ . '/../_core/bootstrap.php';

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $err = 'Security check failed. Please try again.';
  } else {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pass, $u['password_hash'])) {
      $err = 'Invalid email or password.';
    } else {
      Auth::login((int)$u['id']);
      $next = $_SESSION['login_next'] ?? '/studio/library.php';
      unset($_SESSION['login_next']);
      redirect($next);
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Studio Login • Nick Sanzeri</title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>
<main class="container" style="padding:3rem 0;">
  <h1>Studio Login</h1>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?php echo e($err); ?></div>
  <?php endif; ?>

  <form method="post" style="max-width:520px;">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>" />

    <label>Email</label>
    <input name="email" type="email" required />

    <label style="margin-top:1rem;">Password</label>
    <input name="password" type="password" required />

    <button class="btn btn-primary" style="margin-top:1.25rem;">Log in</button>
    <div style="margin-top:0.9rem;">
      <a class="text-link" href="/studio/register.php">Create an account</a>
    </div>
  </form>
</main>
</body>
</html>