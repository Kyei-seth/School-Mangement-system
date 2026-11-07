<?php
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/layout.php';
$u = require_role('admin', 'teacher', 'student');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = q('SELECT Password FROM users WHERE User_ID=?', [$u['User_ID']])->fetch();
    $new = $_POST['new'] ?? '';
    if (!password_verify($_POST['current'] ?? '', $row['Password'])) flash('Your current password is incorrect.', 'err');
    elseif (strlen($new) < 8) flash('The new password must be at least 8 characters.', 'err');
    elseif ($new !== ($_POST['confirm'] ?? '')) flash('The new passwords do not match.', 'err');
    else { q('UPDATE users SET Password=? WHERE User_ID=?', [password_hash($new, PASSWORD_DEFAULT), $u['User_ID']]); flash('Password changed.'); }
    redirect('password.php');
}
page_start('Change password'); ?>
<form class="panel grid" method="post" style="max-width:480px;grid-template-columns:1fr"><?= csrf_field() ?>
  <div><label>Current password</label><input type="password" name="current" required></div>
  <div><label>New password (8+ characters)</label><input type="password" name="new" required minlength="8"></div>
  <div><label>Confirm new password</label><input type="password" name="confirm" required minlength="8"></div>
  <div><button class="btn">Save password</button></div>
</form>
<?php page_end();
