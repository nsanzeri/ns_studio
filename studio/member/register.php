<?php
require __DIR__ . '/../_private/_core/bootstrap.php';

if (Auth::isLoggedIn()) {
	redirect(base_url('library.php'));
}

$email_prefill = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$err = null;
$googleClientId = env('GOOGLE_CLIENT_ID', '');
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
$loginUrl = base_url('member/login.php');
$trialUrl = base_url('member/pricing.php');

if (is_post()) {
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
			$existing = Auth::findUserByEmail($pdo, $email);
			
			if ($existing) {
				$err = 'That email already has an account. Please log in instead.';
			} else {
				$hash = password_hash($pass1, PASSWORD_DEFAULT);
				
				$stmt = $pdo->prepare('INSERT INTO users (email, password_hash, last_login_at) VALUES (?, ?, NOW())');
				$stmt->execute([$email, $hash]);
				$userId = (int)$pdo->lastInsertId();
				
				sync_user_entitlements($pdo, $userId);
				Auth::login($userId);
				
				$next = $_SESSION['login_next'] ?? base_url('member/library.php');
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
  <title><?= $isReadySetShowsHost ? 'Create Account | Ready Set Shows' : 'Create Studio Login • Nick Sanzeri' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
</head>
<body>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_header_lite.php';
} else {
  include __DIR__ . '/../../includes/header.php';
}
?>
<main class="container" style="padding:3rem 0; max-width:720px;">
  <h1>Create your account</h1>
  <p class="muted"><?= $isReadySetShowsHost ? 'Create your Ready Set Shows account to use the tool suite and manage your plan.' : 'Use the same email address you used at checkout and your past purchases will show up automatically.' ?></p>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($err) ?></div>
  <?php endif; ?>

  <form class="form" method="post" style="max-width:520px;">
  	<h2 class="form-title">Create An Account</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>" />
	<div class="form-field">
    <label>Email</label>
    <input name="email" type="email" required value="<?= e($email_prefill) ?>" autocomplete="email" />
</div>
<div class="form-field">
    <label style="margin-top:1rem;">Password</label>
    <input name="password" type="password" required minlength="8" autocomplete="new-password" />
				</div>
				<div class="form-field">
    <label style="margin-top:1rem;">Confirm password</label>
    <input name="password2" type="password" required minlength="8" autocomplete="new-password" />
				</div>
				<div class="form-field">
    <button class="btn btn-primary" style="margin-top:1.25rem;">Create account</button>
  </form>

  <?php if ($googleClientId): ?>
    <div style="margin:1.25rem 0; font-size:.95rem; color:#666;">or</div>
    <a class="btn btn-outline" href="<?= e(base_url('member/google_start.php')) ?>">Sign up with Google</a>
  <?php endif; ?>

  <div style="margin-top:0.9rem;">
    <a class="text-link" href="<?= e(base_url('member/login.php')) ?>">Already have an account? Log in</a>
  </div>
</main>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_footer_lite.php';
} else {
  include __DIR__ . '/../../includes/footer.php';
}
?>
</body>
</html>
