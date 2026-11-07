<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('teacher');
$classes = teacher_classes((int)$u['Teacher_ID']);
$cid = (int)($_GET['class'] ?? ($classes[0]['Class_ID'] ?? 0));
$allowed = in_array($cid, array_column($classes, 'Class_ID'), false);
$rows = $allowed ? q('SELECT * FROM student WHERE Class_ID=? ORDER BY Last_Name, First_Name', [$cid])->fetchAll() : [];
page_start('Students in my classes'); ?>
<form class="bar" method="get"><div><label>Class</label><select name="class" onchange="this.form.submit()"><?= options($classes, 'Class_ID', fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')', $cid) ?></select></div></form>
<div class="table-wrap"><table><tr><th>Student no.</th><th>Name</th><th>Gender</th><th>Date of birth</th><th>Phone</th></tr>
<?php foreach ($rows as $s): ?><tr><td><?= h($s['Student_No']) ?></td><td><?= h($s['Last_Name'] . ', ' . $s['First_Name']) ?></td><td><?= h($s['Gender']) ?></td><td><?= h($s['Date_of_Birth']) ?></td><td><?= h($s['Phone_Number']) ?></td></tr>
<?php endforeach; ?></table></div>
<?php page_end();
