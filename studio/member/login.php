<?php
require __DIR__ . '/../_private/_core/bootstrap.php';

if (Auth::isLoggedIn()) {
	redirect(base_url('member/library.php'));
}

$err = null;
$googleClientId = env('GOOGLE_CLIENT_ID', '');

if (is_post()) {
	if (!csrf_verify($_POST['_csrf'] ?? null)) {
		$err = 'Security check failed. Please try again.';
	} else {
		$email = strtolower(trim((string)($_POST['email'] ?? '')));
		$pass  = (string)($_POST['password'] ?? '');
		
		$stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
		$stmt->execute([$email]);
		$u = $stmt->fetch(PDO::FETCH_ASSOC);
		
		if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
			$err = 'Invalid email or password.';
		} else {
			$userId = (int)$u['id'];
			Auth::login($userId);
			Auth::touchLogin($pdo, $userId);
			sync_user_entitlements($pdo, $userId);
			$next = $_SESSION['login_next'] ?? base_url('member/library.php');
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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
   
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="container" style="padding:3rem 0; max-width:720px;">
  <h1>Log in</h1>
  <p class="muted">Access My Products, re-download past purchases, and manage your account.</p>

  <?php if ($msg = flash_get('success')): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($msg) ?></div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($err) ?></div>
  <?php endif; ?>

		<form class="form" method="post" style="max-width: 520px; margin-top: 1.5rem;">
			<h2 class="form-title">Login</h2>

				<div class="form-field">
					<label>Email</label> 
					<input name="email" type="email" required autocomplete="email" />
				</div>
				<div class="form-field">
					<label style="margin-top: 1rem;">Password</label> 
					<input name="password" type="password" required	autocomplete="current-password" />
				</div>
				<button class="btn btn-primary" style="margin-top: 1.25rem;">Log in</button>
				<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>" />
		</form>

		<?php if ($googleClientId): ?>
    <div style="margin:1.25rem 0; font-size:.95rem; color:#666;">or</div>
    <a class="btn btn-outline" href="<?= e(base_url('member/google_start.php')) ?>">Continue with Google</a>
  <?php endif; ?>

  <div style="margin-top:1rem;">
    <a class="text-link" href="<?= e(base_url('member/register.php')) ?>">Create an account</a>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
