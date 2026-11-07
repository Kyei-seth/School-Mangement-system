<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('student');
$sid = (int)$u['Student_ID'];
$me = q('SELECT * FROM student WHERE Student_ID=?', [$sid])->fetch();
// Only published examinations that this student has results in
$exams = q('SELECT DISTINCT e.* FROM examination e JOIN result r ON r.Exam_ID=e.Exam_ID WHERE r.Student_ID=? AND e.Is_Published=1 ORDER BY e.Academic_Year DESC, e.Term DESC', [$sid])->fetchAll();
$eid = (int)($_GET['exam'] ?? ($exams[0]['Exam_ID'] ?? 0));
$exam = null; foreach ($exams as $e) if ((int)$e['Exam_ID'] === $eid) $exam = $e;
$rows = $exam ? q('SELECT r.*, s.Subject_Name FROM result r JOIN subject s ON s.Subject_ID=r.Subject_ID WHERE r.Student_ID=? AND r.Exam_ID=? ORDER BY s.Subject_Name', [$sid, $eid])->fetchAll() : [];
$remarks = q('SELECT Grade, Remark FROM grade_scale')->fetchAll(PDO::FETCH_KEY_PAIR);
page_start('My results'); ?>
<?php if (!$exams): ?><p class="muted">No results have been published for you yet.</p>
<?php else: ?>
<form class="bar no-print" method="get"><div><label>Examination</label><select name="exam" onchange="this.form.submit()"><?= options($exams, 'Exam_ID', fn($e) => $e['Exam_Name'] . ' (Term ' . $e['Term'] . ', ' . $e['Academic_Year'] . ')', $eid) ?></select></div></form>
<div class="report-head"><h2><?= h(SCHOOL_NAME) ?></h2><div><?= h(SCHOOL_PLACE) ?></div><div><?= h($me['First_Name'] . ' ' . $me['Last_Name'] . ' (' . $me['Student_No'] . ')') ?> | <?= h($exam['Exam_Name']) ?></div></div>
<div class="table-wrap"><table><tr><th>Subject</th><th>Grade</th><th>Remark</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['Subject_Name']) ?></td><td><b><?= h($r['Grade']) ?></b></td><td><?= h($remarks[$r['Grade']] ?? '') ?></td></tr>
<?php endforeach; ?></table></div>
<p class="no-print"><button class="btn alt" onclick="window.print()">Print result slip</button></p>
<?php endif; ?>
<?php page_end();
