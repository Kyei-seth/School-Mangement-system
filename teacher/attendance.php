<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('teacher');
$classes = teacher_classes((int)$u['Teacher_ID']);
$allowedIds = array_column($classes, 'Class_ID');
$cid = (int)($_REQUEST['class'] ?? 0);
$date = $_REQUEST['date'] ?? date('Y-m-d');
$validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= date('Y-m-d') && strtotime($date) !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($cid, $allowedIds, false) || !$validDate) { flash('Choose one of your classes and a date that is not in the future.', 'err'); redirect('teacher/attendance.php'); }
    $n = 0;
    $ids = array_column(q('SELECT Student_ID FROM student WHERE Class_ID=?', [$cid])->fetchAll(), 'Student_ID');
    foreach ($_POST['status'] ?? [] as $sid => $st) {
        if (!in_array((int)$sid, $ids, false) || !in_array($st, ['Present', 'Absent', 'Late'], true)) continue;
        q('INSERT INTO attendance (Student_ID, Class_ID, Attendance_Date, Attendance_Status) VALUES (?,?,?,?)
           ON DUPLICATE KEY UPDATE Attendance_Status=VALUES(Attendance_Status), Class_ID=VALUES(Class_ID)', [(int)$sid, $cid, $date, $st]);
        $n++;
    }
    flash("Attendance saved for $n student(s) on " . h(date('j M Y', strtotime($date))) . '.');
    redirect('teacher/attendance.php?class=' . $cid . '&date=' . $date);
}

$students = []; $existing = [];
if (in_array($cid, $allowedIds, false) && $validDate) {
    $students = q('SELECT * FROM student WHERE Class_ID=? ORDER BY Last_Name, First_Name', [$cid])->fetchAll();
    $existing = q('SELECT Student_ID, Attendance_Status FROM attendance WHERE Class_ID=? AND Attendance_Date=?', [$cid, $date])->fetchAll(PDO::FETCH_KEY_PAIR);
}
page_start('Attendance'); ?>
<form class="bar" method="get">
  <div><label>Class</label><select name="class" required><?= options($classes, 'Class_ID', fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')', $cid, 'Choose a class') ?></select></div>
  <div><label>Date</label><input type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= h($date) ?>"></div>
  <div class="fit"><button class="btn">Load class list</button></div>
</form>
<?php if ($cid && !$validDate): ?><div class="flash err">Choose a date that is today or earlier.</div><?php endif; ?>
<?php if ($students): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="class" value="<?= $cid ?>"><input type="hidden" name="date" value="<?= h($date) ?>">
  <p class="actions"><?php if ($existing): ?><span class="badge">Already recorded. Saving will update it.</span><?php endif; ?>
  <button type="button" class="btn alt sm" onclick="document.querySelectorAll('input[value=Present]').forEach(r=>r.checked=true)">Mark everyone present</button></p>
  <div class="table-wrap"><table><tr><th>Student no.</th><th>Name</th><th>Present</th><th>Late</th><th>Absent</th></tr>
  <?php foreach ($students as $s): $cur = $existing[$s['Student_ID']] ?? 'Present'; ?>
  <tr><td><?= h($s['Student_No']) ?></td><td><?= h($s['Last_Name'] . ', ' . $s['First_Name']) ?></td>
  <?php foreach (['Present', 'Late', 'Absent'] as $st): ?><td><input type="radio" style="width:auto" name="status[<?= $s['Student_ID'] ?>]" value="<?= $st ?>" aria-label="<?= $st ?>" <?= $cur === $st ? 'checked' : '' ?>></td><?php endforeach; ?></tr>
  <?php endforeach; ?></table></div>
  <p><button class="btn">Save attendance</button></p>
</form>
<?php endif; ?>
<?php page_end();
