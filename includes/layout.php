<?php
function page_start(string $title): void {
    $u = current_user();
    $nav = [];
    if ($u) {
        if ($u['Role'] === 'admin') $nav = [
            'admin/dashboard.php' => 'Dashboard', 'admin/students.php' => 'Students', 'admin/teachers.php' => 'Teachers',
            'admin/classrooms.php' => 'Classrooms', 'admin/subjects.php' => 'Subjects', 'admin/exams.php' => 'Exams',
            'admin/reports.php' => 'Reports', 'admin/settings.php' => 'Grading',
            'announcements.php' => 'Announcements', 'parent_results.php' => 'Send parent results'];
        elseif ($u['Role'] === 'teacher') $nav = [
            'teacher/dashboard.php' => 'My classes', 'teacher/attendance.php' => 'Attendance',
            'teacher/results.php' => 'Exam scores', 'teacher/students.php' => 'Students', 'teacher/reports.php' => 'Reports',
            'announcements.php' => 'Announcements', 'parent_results.php' => 'Send parent results'];
        else $nav = ['student/dashboard.php' => 'My profile', 'student/results.php' => 'My results', 'announcements.php' => 'Announcements'];
    }
    $here = ltrim(str_replace(base_url(), '', $_SERVER['SCRIPT_NAME']), '/');
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> - <?= h(SCHOOL_NAME) ?></title>
<link rel="stylesheet" href="<?= h(base_url('css/style.css')) ?>"></head><body>
<?php if ($u): ?>
<div class="app-shell">
<aside class="sidebar no-print" id="sidebar">
  <div class="brand"><img class="brand-logo" src="<?= h(base_url('multimedia/aps%20logo.jpg')) ?>" alt="<?= h(SCHOOL_NAME) ?> logo"><div><strong><?= h(SCHOOL_NAME) ?></strong><small><?= h(SCHOOL_PLACE) ?></small></div></div>
  <nav aria-label="Main navigation">
    <?php foreach ($nav as $href => $label): ?>
      <a href="<?= h(base_url($href)) ?>" class="<?= $here === $href ? 'on' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= h(base_url('password.php')) ?>" class="<?= $here === 'password.php' ? 'on' : '' ?>">Change password</a>
  </nav>
  <form class="sidebar-logout" method="post" action="<?= h(base_url('logout.php')) ?>"><?= csrf_field() ?><button>Log out (<?= h($u['Username']) ?>)</button></form>
</aside>
<div class="app-content">
  <header class="mobile-header no-print">
    <button class="menu-btn" type="button" aria-label="Open navigation" onclick="document.body.classList.toggle('nav-open'); this.setAttribute('aria-expanded', document.body.classList.contains('nav-open')); this.setAttribute('aria-label', document.body.classList.contains('nav-open') ? 'Close navigation' : 'Open navigation')" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button>
    <strong><?= h(SCHOOL_NAME) ?></strong>
  </header>
  <button class="sidebar-overlay no-print" type="button" aria-label="Close navigation" onclick="document.body.classList.remove('nav-open'); document.querySelector('.menu-btn').setAttribute('aria-expanded', 'false'); document.querySelector('.menu-btn').setAttribute('aria-label', 'Open navigation')"></button>
<?php endif; ?>
<main>
<h1 class="page-title"><?= h($title) ?></h1>
<?= flashes() ?>
<?php }
function page_end(): void { ?>
</main>
<footer class="no-print"><?= h(SCHOOL_NAME) ?>, <?= h(SCHOOL_PLACE) ?></footer>
<?php if (current_user()): ?></div></div><?php endif; ?>
</body></html>
<?php }
