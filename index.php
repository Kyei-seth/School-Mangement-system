<?php
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/layout.php';

function home_for(string $role): string { return ['admin'=>'admin/dashboard.php','teacher'=>'teacher/dashboard.php','student'=>'student/dashboard.php'][$role]; }

if (current_user()) redirect(home_for(current_user()['Role']));

// First run: no accounts yet -> go to setup
if ((int)q('SELECT COUNT(*) FROM users')->fetchColumn() === 0) redirect('setup.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login = trim($_POST['username'] ?? '');
    $u = q('SELECT u.* FROM users u
            LEFT JOIN student s ON s.Student_ID=u.Student_ID
            LEFT JOIN teacher t ON t.Teacher_ID=u.Teacher_ID
            WHERE (u.Role="admin" AND u.Username=?)
               OR (u.Role="student" AND s.Student_No=?)
               OR (u.Role="teacher" AND t.Staff_No=?)',
        [$login, $login, $login])->fetch();
    // Simple login throttle: 5 failed attempts -> 5 minute lock for this session
    if (($_SESSION['fails'] ?? 0) >= 5 && time() < ($_SESSION['lock_until'] ?? 0)) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif ($u && password_verify($_POST['password'] ?? '', $u['Password'])) {
        session_regenerate_id(true);
        unset($u['Password']);
        $_SESSION['user'] = $u; $_SESSION['fails'] = 0;
        redirect(home_for($u['Role']));
    } else {
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) $_SESSION['lock_until'] = time() + 300;
        $error = 'Incorrect username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log in - <?= h(SCHOOL_NAME) ?></title><link rel="stylesheet" href="<?= h(base_url('css/style.css')) ?>"></head><body>
<div class="login">
  <div class="head"><img class="login-logo" src="<?= h(base_url('multimedia/aps%20logo.jpg')) ?>" alt="<?= h(SCHOOL_NAME) ?> logo"><h1><?= h(SCHOOL_NAME) ?></h1><p><?= h(SCHOOL_PLACE) ?></p></div>
  <form class="box" method="post">
    <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div><label for="username">Username</label><input id="username" name="username" required autofocus autocomplete="username"></div>
    <div><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn" style="width:100%">Log in</button>
  </form>
</div></body></html>
