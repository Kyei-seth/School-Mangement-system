<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('teacher');
$tid = (int)$u['Teacher_ID'];
$subs = q('SELECT s.*, c.Class_Name, c.Academic_Year FROM subject s JOIN classroom c ON c.Class_ID=s.Class_ID WHERE s.Teacher_ID=? ORDER BY c.Class_Name, s.Subject_Name', [$tid])->fetchAll();
$exams = q('SELECT * FROM examination ORDER BY Academic_Year DESC, Term DESC, Exam_ID DESC')->fetchAll();
$sid = (int)($_REQUEST['subject'] ?? 0); $eid = (int)($_REQUEST['exam'] ?? 0);
$sub = null; foreach ($subs as $s) if ((int)$s['Subject_ID'] === $sid) $sub = $s;
$exam = $eid ? q('SELECT * FROM examination WHERE Exam_ID=?', [$eid])->fetch() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$sub || !$exam) { flash('Choose one of your subjects and an examination.', 'err'); redirect('teacher/results.php'); }
    if ($exam['Is_Published']) { flash('This examination is published and locked. Ask the administrator to unpublish it to make corrections.', 'err'); redirect("teacher/results.php?subject=$sid&exam=$eid"); }
    $ids = array_column(q('SELECT Student_ID FROM student WHERE Class_ID=?', [$sub['Class_ID']])->fetchAll(), 'Student_ID');
    $saved = 0; $bad = [];
    foreach ($ids as $st) {
        $c = trim($_POST['cs'][$st] ?? ''); $x = trim($_POST['es'][$st] ?? '');
        if ($c === '' && $x === '') continue;
        if (!is_numeric($c) || !is_numeric($x) || $c < 0 || $c > 30 || $x < 0 || $x > 70) { $bad[] = $st; continue; }
        $total = (float)$c + (float)$x; $g = grade_for($total)['Grade'];
        q('INSERT INTO result (Student_ID, Subject_ID, Exam_ID, Class_Score, Score, Total_Score, Grade) VALUES (?,?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE Class_Score=VALUES(Class_Score), Score=VALUES(Score), Total_Score=VALUES(Total_Score), Grade=VALUES(Grade)',
          [$st, $sid, $eid, $c, $x, $total, $g]);
        $saved++;
    }
    flash("$saved score(s) saved or updated.");
    if ($bad) flash(count($bad) . ' row(s) were skipped. Complete both score fields using the allowed ranges.', 'err');
    redirect("teacher/results.php?subject=$sid&exam=$eid");
}

$students = []; $res = [];
if ($sub && $exam) {
    $students = q('SELECT * FROM student WHERE Class_ID=? ORDER BY Last_Name, First_Name', [$sub['Class_ID']])->fetchAll();
    foreach (q('SELECT * FROM result WHERE Subject_ID=? AND Exam_ID=?', [$sid, $eid]) as $r) $res[$r['Student_ID']] = $r;
}
page_start('Exam scores'); ?>
<form class="bar" method="get">
  <div><label>Subject</label><select name="subject" required><?= options($subs, 'Subject_ID', fn($s) => $s['Class_Name'] . ': ' . $s['Subject_Name'], $sid, 'Choose a subject') ?></select></div>
  <div><label>Examination</label><select name="exam" required><?= options($exams, 'Exam_ID', fn($e) => $e['Exam_Name'] . ' (T' . $e['Term'] . ' ' . $e['Academic_Year'] . ')', $eid, 'Choose an exam') ?></select></div>
  <div class="fit"><button class="btn">Load students</button></div>
</form>
<?php if ($sub && $exam): $locked = (bool)$exam['Is_Published']; ?>
  <?php if ($locked): ?><div class="flash err">This examination is published, so scores are locked.</div><?php endif; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="subject" value="<?= $sid ?>"><input type="hidden" name="exam" value="<?= $eid ?>">
  <div class="table-wrap"><table><tr><th>Student no.</th><th>Name</th><th>Class score (30)</th><th>Exam score (70)</th><th>Grade</th></tr>
  <?php foreach ($students as $s): $r = $res[$s['Student_ID']] ?? null; ?>
  <tr><td><?= h($s['Student_No']) ?></td><td><?= h($s['Last_Name'] . ', ' . $s['First_Name']) ?></td>
  <td><input type="number" step="0.01" min="0" max="30" style="max-width:110px" name="cs[<?= $s['Student_ID'] ?>]" value="<?= $r ? h($r['Class_Score'] + 0) : '' ?>" <?= $locked ? 'disabled' : '' ?>></td>
  <td><input type="number" step="0.01" min="0" max="70" style="max-width:110px" name="es[<?= $s['Student_ID'] ?>]" value="<?= $r ? h($r['Score'] + 0) : '' ?>" <?= $locked ? 'disabled' : '' ?>></td>
  <td><b><?= $r ? h($r['Grade']) : '-' ?></b></td></tr>
  <?php endforeach; ?></table></div>
  <?php if ($students && !$locked): ?><p><button class="btn">Save scores</button></p><?php endif; ?>
  </form>
<?php endif; ?>
<?php page_end();
