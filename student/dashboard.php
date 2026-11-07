<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$s = q('SELECT s.*, c.Class_Name, c.Academic_Year, CONCAT(t.First_Name," ",t.Last_Name) tname FROM student s
        LEFT JOIN classroom c ON c.Class_ID=s.Class_ID LEFT JOIN teacher t ON t.Teacher_ID=c.Teacher_ID WHERE s.Student_ID=?', [$u['Student_ID']])->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'photo') {
    try {
        $path = save_profile_photo('students');
        if ($path !== null) {
            q('UPDATE student SET Photo_Path=? WHERE Student_ID=?', [$path, $u['Student_ID']]);
            delete_profile_photo($s['Photo_Path'] ?? null, 'students');
            flash('Profile photo updated.');
        }
    } catch (RuntimeException $error) {
        flash($error->getMessage(), 'err');
    }
    redirect('student/dashboard.php');
}
$s = q('SELECT s.*, c.Class_Name, c.Academic_Year, CONCAT(t.First_Name," ",t.Last_Name) tname FROM student s
        LEFT JOIN classroom c ON c.Class_ID=s.Class_ID LEFT JOIN teacher t ON t.Teacher_ID=c.Teacher_ID WHERE s.Student_ID=?', [$u['Student_ID']])->fetch();
$att = q('SELECT SUM(Attendance_Status="Present") p, SUM(Attendance_Status="Late") l, SUM(Attendance_Status="Absent") a, COUNT(*) t FROM attendance WHERE Student_ID=?', [$u['Student_ID']])->fetch();
$recent = q('SELECT Attendance_Date, Attendance_Status FROM attendance WHERE Student_ID=? ORDER BY Attendance_Date DESC LIMIT 10', [$u['Student_ID']])->fetchAll();
page_start($s['First_Name'] . ' ' . $s['Last_Name']); ?>
<div class="panel profile-card">
<?php if (!empty($s['Photo_Path'])): ?><img class="profile-photo" src="<?= h(base_url($s['Photo_Path'])) ?>" alt="Your passport photograph"><?php endif; ?>
<form class="photo-form no-print" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="photo">
  <div><label for="photo">Passport photograph</label><input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
  <button class="btn alt">Upload or update photo</button>
</form>
<div class="panel"><table style="max-width:560px">
<tr><th>Student number</th><td><?= h($s['Student_No']) ?></td></tr>
<tr><th>Class</th><td><?= h($s['Class_Name'] ? $s['Class_Name'] . ' (' . $s['Academic_Year'] . ')' : 'Not assigned yet') ?></td></tr>
<tr><th>Class teacher</th><td><?= h($s['tname'] ?? 'Not assigned') ?></td></tr>
<tr><th>Gender</th><td><?= h($s['Gender']) ?></td></tr>
<tr><th>Date of birth</th><td><?= h($s['Date_of_Birth']) ?></td></tr>
<tr><th>Admitted</th><td><?= h($s['Admission_Date']) ?></td></tr></table></div>
</div>
<div class="stats">
  <div class="stat"><b><?= (int)$att['p'] ?></b><span>Days present</span></div>
  <div class="stat"><b><?= (int)$att['l'] ?></b><span>Days late</span></div>
  <div class="stat"><b><?= (int)$att['a'] ?></b><span>Days absent</span></div>
</div>
<?php if ($recent): ?><h2>Recent attendance</h2><div class="table-wrap"><table><tr><th>Date</th><th>Status</th></tr>
<?php foreach ($recent as $r): ?><tr><td><?= h(date('D j M Y', strtotime($r['Attendance_Date']))) ?></td><td><span class="badge <?= h($r['Attendance_Status']) ?>"><?= h($r['Attendance_Status']) ?></span></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php page_end();
