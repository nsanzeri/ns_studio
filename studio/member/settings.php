<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
Auth::requireLogin(base_url('studio/member/settings.php'));

$user = Auth::currentUser($pdo);
$err = null;
$ok = null;

if (is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $err = 'Security check failed. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new1 = (string)($_POST['new_password'] ?? '');
            $new2 = (string)($_POST['new_password2'] ?? '');

            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $hash = (string)$stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $err = 'Your current password was incorrect.';
            } elseif (strlen($new1) < 8) {
                $err = 'Your new password must be at least 8 characters.';
            } elseif ($new1 !== $new2) {
                $err = 'Your new passwords do not match.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($new1, PASSWORD_DEFAULT), (int)$user['id']]);
                $ok = 'Password updated.';
            }
        }

        if ($action === 'delete') {
            $confirm = trim((string)($_POST['delete_confirmation'] ?? ''));
            if ($confirm !== 'DELETE') {
                $err = 'Type DELETE to confirm account removal.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([(int)$user['id']]);
                Auth::logout();
                flash_set('success', 'Your account has been deleted.');
                redirect(base_url('studio/member/register.php'));
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
  <title>Settings • Nick Sanzeri Studio</title>
  <link rel="stylesheet" href="<?= e(base_url('../../assets/css/style.css')) ?>">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="container" style="padding:3rem 0; max-width:760px;">
 <h2 class="form-title">Account Settings</h2>
  <p class="muted">Manage your password and account.</p>

  <?php if ($err): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($err) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="alert" style="margin:1rem 0;"><?= e($ok) ?></div>
  <?php endif; ?>

  <section class="card" style="padding:1.5rem; margin:1.5rem 0;">
    <h3 class="form-title">Change Password</h3>
    <form class="form" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="password">
<div class="form-field">
      <label>Current password</label>
      <input type="password" name="current_password" required autocomplete="current-password">
</div>
<div class="form-field">
      <label style="margin-top:1rem;">New password</label>
      <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
</div>
<div class="form-field">
      <label style="margin-top:1rem;">Confirm new password</label>
      <input type="password" name="new_password2" required minlength="8" autocomplete="new-password">
</div>
      <button class="btn btn-primary" style="margin-top:1.25rem;">Save password</button>
    </form>
  </section>

  <section class="card" style="padding:1.5rem; margin:1.5rem 0; border-color:#c77;">
    <h3 class="form-title">Delete Account</h3>
    <p class="muted">This removes your login. If you register again later with the same purchase email, your library can be rebuilt from past purchases.</p>
    <form  class="form" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
<div class="form-field">
      <label>Type DELETE to confirm</label>
      <input type="text" name="delete_confirmation" required>
</div>
      <button class="btn btn-outline" style="margin-top:1.25rem;">Delete account</button>
    </form>
  </section>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
