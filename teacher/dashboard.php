<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('teacher');
$tid = (int)$u['Teacher_ID'];
$me = q('SELECT * FROM teacher WHERE Teacher_ID=?', [$tid])->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'photo') {
    try {
        $path = save_profile_photo('teachers');
        if ($path !== null) {
            q('UPDATE teacher SET Photo_Path=? WHERE Teacher_ID=?', [$path, $tid]);
            delete_profile_photo($me['Photo_Path'] ?? null, 'teachers');
            flash('Profile photo updated.');
        }
    } catch (RuntimeException $error) {
        flash($error->getMessage(), 'err');
    }
    redirect('teacher/dashboard.php');
}
$me = q('SELECT * FROM teacher WHERE Teacher_ID=?', [$tid])->fetch();
$classes = teacher_classes($tid);
$subs = q('SELECT s.*, c.Class_Name FROM subject s JOIN classroom c ON c.Class_ID=s.Class_ID WHERE s.Teacher_ID=? ORDER BY c.Class_Name, s.Subject_Name', [$tid])->fetchAll();
page_start('Welcome, ' . $me['First_Name']); ?>
<div class="panel profile-card">
<?php if (!empty($me['Photo_Path'])): ?><img class="profile-photo" src="<?= h(base_url($me['Photo_Path'])) ?>" alt="Your passport photograph"><?php endif; ?>
<div><strong><?= h($me['First_Name'] . ' ' . $me['Last_Name']) ?></strong><div class="muted">Staff number: <?= h($me['Staff_No']) ?></div></div>
<form class="photo-form no-print" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="photo">
  <div><label for="photo">Passport photograph</label><input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
  <button class="btn alt">Upload or update photo</button>
</form>
</div>
<h2>Your classes</h2>
<div class="table-wrap"><table><tr><th>Class</th><th>Year</th><th>Role</th><th class="num">Students</th><th></th></tr>
<?php foreach ($classes as $c): $n = (int)q('SELECT COUNT(*) FROM student WHERE Class_ID=?', [$c['Class_ID']])->fetchColumn(); ?>
<tr><td><?= h($c['Class_Name']) ?></td><td><?= h($c['Academic_Year']) ?></td><td><?= (int)$c['Teacher_ID'] === $tid ? 'Class teacher' : 'Subject teacher' ?></td><td class="num"><?= $n ?></td>
<td><a class="btn alt sm" href="attendance.php?class=<?= $c['Class_ID'] ?>">Take attendance</a></td></tr>
<?php endforeach; ?>
</table></div>
<h2>Subjects you teach</h2>
<div class="table-wrap"><table><tr><th>Subject</th><th>Class</th><th></th></tr>
<?php foreach ($subs as $s): ?><tr><td><?= h($s['Subject_Name']) ?></td><td><?= h($s['Class_Name']) ?></td><td><a class="btn alt sm" href="results.php?subject=<?= $s['Subject_ID'] ?>">Enter scores</a></td></tr>
<?php endforeach; ?>
</table></div>
<?php page_end();
