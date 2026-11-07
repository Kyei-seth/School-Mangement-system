<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$c = fn($t) => (int)q("SELECT COUNT(*) FROM $t")->fetchColumn();
$today = q("SELECT Attendance_Status s, COUNT(*) n FROM attendance WHERE Attendance_Date=CURDATE() GROUP BY s")->fetchAll(PDO::FETCH_KEY_PAIR);
$unassigned = (int)q('SELECT COUNT(*) FROM student WHERE Class_ID IS NULL')->fetchColumn();
$byClass = q('SELECT c.Class_Name, c.Academic_Year, COUNT(s.Student_ID) n FROM classroom c LEFT JOIN student s ON s.Class_ID=c.Class_ID GROUP BY c.Class_ID ORDER BY c.Class_Name')->fetchAll();
page_start('Dashboard'); ?>
<div class="stats">
  <div class="stat"><b><?= $c('student') ?></b><span>Students</span></div>
  <div class="stat"><b><?= $c('teacher') ?></b><span>Teachers</span></div>
  <div class="stat"><b><?= $c('classroom') ?></b><span>Classrooms</span></div>
  <div class="stat"><b><?= $unassigned ?></b><span>Students without a class</span></div>
  <div class="stat"><b><?= (int)($today['Present'] ?? 0) + (int)($today['Late'] ?? 0) ?></b><span>Present or late today</span></div>
  <div class="stat"><b><?= (int)($today['Absent'] ?? 0) ?></b><span>Absent today</span></div>
</div>
<h2>Students per class</h2>
<div class="table-wrap"><table><tr><th>Class</th><th>Year</th><th class="num">Students</th></tr>
<?php foreach ($byClass as $r): ?><tr><td><?= h($r['Class_Name']) ?></td><td><?= h($r['Academic_Year']) ?></td><td class="num"><?= $r['n'] ?></td></tr>
<?php endforeach; if (!$byClass): ?><tr><td colspan="3" class="muted">No classrooms yet. <a href="classrooms.php">Create the first classroom</a>.</td></tr><?php endif; ?>
</table></div>
<?php page_end();
