<?php
// Shared report screen. Include after require_role(); $user is the logged-in user.
require_once __DIR__ . '/layout.php';

$classes = $user['Role'] === 'admin'
    ? q('SELECT * FROM classroom ORDER BY Academic_Year DESC, Class_Name')->fetchAll()
    : teacher_classes((int)$user['Teacher_ID']);
$exams = q('SELECT * FROM examination ORDER BY Academic_Year DESC, Term DESC')->fetchAll();
$type = $_GET['type'] ?? 'students';
$classId = (int)($_GET['class'] ?? 0);
$studentId = (int)($_GET['student'] ?? 0);
$examId = (int)($_GET['exam'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$run = isset($_GET['run']);
$allowed = array_column($classes, 'Class_ID');
$classStudents = in_array($classId, array_map('intval', $allowed), true)
    ? q('SELECT Student_ID, Student_No, First_Name, Last_Name FROM student WHERE Class_ID=? ORDER BY Last_Name, First_Name', [$classId])->fetchAll()
    : [];
if ($studentId && !in_array($studentId, array_map('intval', array_column($classStudents, 'Student_ID')), true)) $studentId = 0;
$title = ['students' => 'Student information report', 'attendance' => 'Attendance report', 'performance' => 'Academic performance report'][$type] ?? 'Reports';
$headers = []; $data = []; $subtitle = ''; $error = ''; $reportExam = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_comment') {
    if ($user['Role'] !== 'teacher') { http_response_code(403); die('Only teachers can save student comments.'); }
    $commentStudent = (int)($_POST['student'] ?? 0);
    $commentExam = (int)($_POST['exam'] ?? 0);
    $commentClass = q('SELECT Class_ID FROM student WHERE Student_ID=?', [$commentStudent])->fetchColumn();
    $teacherClassIds = array_map('intval', array_column($classes, 'Class_ID'));
    if (!$commentClass || !in_array((int)$commentClass, $teacherClassIds, true) || !q('SELECT 1 FROM examination WHERE Exam_ID=?', [$commentExam])->fetchColumn()) {
        http_response_code(403); die('You are not authorised to comment on this student or examination.');
    }
    $academic = trim($_POST['academic_comment'] ?? '');
    $progress = trim($_POST['progress_comment'] ?? '');
    if (strlen($academic) > 5000 || strlen($progress) > 5000 || ($academic === '' && $progress === '')) {
        flash('Enter at least one comment; each comment must be 5,000 characters or fewer.', 'err');
    } else {
        q('INSERT INTO teacher_comment (Student_ID, Exam_ID, Teacher_ID, Academic_Comment, Progress_Comment) VALUES (?,?,?,?,?)
           ON DUPLICATE KEY UPDATE Academic_Comment=VALUES(Academic_Comment), Progress_Comment=VALUES(Progress_Comment)',
            [$commentStudent, $commentExam, $user['Teacher_ID'], $academic, $progress]);
        flash('Student progress comments saved.');
    }
    redirect('teacher/reports.php?' . http_build_query(['type' => 'performance', 'class' => (int)$commentClass, 'student' => $commentStudent, 'exam' => $commentExam, 'run' => 1]));
}

if ($run) {
    if (!in_array($classId, array_map('intval', $allowed), true)) { $error = 'Choose one of your classes.'; }
    else {
        $cls = q('SELECT * FROM classroom WHERE Class_ID=?', [$classId])->fetch();
        $subtitle = $cls['Class_Name'] . ', ' . $cls['Academic_Year'];
        if ($type === 'students') {
            $headers = ['Student no.', 'Name', 'Gender', 'Date of birth', 'Phone', 'Address'];
            $studentSql = 'SELECT * FROM student WHERE Class_ID=?'; $studentParams = [$classId];
            if ($studentId) { $studentSql .= ' AND Student_ID=?'; $studentParams[] = $studentId; }
            foreach (q($studentSql . ' ORDER BY Last_Name, First_Name', $studentParams) as $s)
                $data[] = [$s['Student_No'], $s['Last_Name'] . ', ' . $s['First_Name'], $s['Gender'], $s['Date_of_Birth'], $s['Phone_Number'], $s['Address']];
        } elseif ($type === 'attendance') {
            $subtitle .= ", $from to $to";
            $headers = ['Student no.', 'Name', 'Present', 'Late', 'Absent', 'Days recorded', 'Attendance %'];
            $attendanceSql = 'SELECT s.Student_No, s.First_Name, s.Last_Name,
                         SUM(a.Attendance_Status="Present") p, SUM(a.Attendance_Status="Late") l, SUM(a.Attendance_Status="Absent") ab, COUNT(a.Attendance_ID) t
                       FROM student s LEFT JOIN attendance a ON a.Student_ID=s.Student_ID AND a.Attendance_Date BETWEEN ? AND ?
                       WHERE s.Class_ID=?';
            $attendanceParams = [$from, $to, $classId];
            if ($studentId) { $attendanceSql .= ' AND s.Student_ID=?'; $attendanceParams[] = $studentId; }
            $rows = q($attendanceSql . ' GROUP BY s.Student_ID ORDER BY s.Last_Name, s.First_Name', $attendanceParams)->fetchAll();
            foreach ($rows as $r) {
                $t = (int)$r['t'];
                $data[] = [$r['Student_No'], $r['Last_Name'] . ', ' . $r['First_Name'], (int)$r['p'], (int)$r['l'], (int)$r['ab'], $t,
                           $t ? round((($r['p'] + $r['l']) / $t) * 100) . '%' : '-'];
            }
        } else {
            $exam = q('SELECT * FROM examination WHERE Exam_ID=?', [$examId])->fetch();
            if (!$exam) $error = 'Choose an examination.';
            else {
                $reportExam = $exam;
                $subtitle .= ', ' . $exam['Exam_Name'] . ' (Term ' . $exam['Term'] . ', ' . $exam['Academic_Year'] . ')';
                $subs = q('SELECT * FROM subject WHERE Class_ID=? ORDER BY Subject_Name', [$classId])->fetchAll();
                $studentSql = 'SELECT * FROM student WHERE Class_ID=?'; $studentParams = [$classId];
                if ($studentId) { $studentSql .= ' AND Student_ID=?'; $studentParams[] = $studentId; }
                $students = q($studentSql . ' ORDER BY Last_Name, First_Name', $studentParams)->fetchAll();
                $res = [];
                foreach (q('SELECT * FROM result WHERE Exam_ID=?', [$examId]) as $r) $res[$r['Student_ID']][$r['Subject_ID']] = $r;
                $headers = ['Student no.', 'Name'];
                foreach ($subs as $s) $headers[] = $s['Subject_Name'];
                $headers[] = 'Position';
                $tmp = [];
                foreach ($students as $st) {
                    $row = [$st['Student_No'], $st['Last_Name'] . ', ' . $st['First_Name']]; $sum = 0;
                    foreach ($subs as $s) {
                        $r = $res[$st['Student_ID']][$s['Subject_ID']] ?? null;
                        if ($r) { $row[] = $r['Grade']; $sum += $r['Total_Score']; } else $row[] = '-';
                    }
                    $tmp[] = ['row' => $row, 'sum' => $sum];
                }
                usort($tmp, fn($a, $b) => $b['sum'] <=> $a['sum']);
                $pos = 0; $prev = null; $i = 0;
                foreach ($tmp as $t) {
                    $i++; if ($prev === null || $t['sum'] != $prev) $pos = $i; $prev = $t['sum'];
                    $data[] = [...$t['row'], $pos];
                }
            }
        }
        if (!$error && isset($_GET['csv'])) {
            log_report($type);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $type . '-report-' . date('Ymd') . '.csv"');
            $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, [SCHOOL_NAME, $title, $subtitle]); fputcsv($o, $headers);
            foreach ($data as $r) fputcsv($o, $r);
            exit;
        }
        if (!$error && !isset($_GET['logged'])) log_report($type);
    }
}

page_start('Reports'); ?>
<form class="bar no-print" method="get">
  <input type="hidden" name="run" value="1">
  <div><label>Report</label><select name="type" onchange="document.querySelectorAll('.f-att').forEach(e=>e.style.display=this.value=='attendance'?'':'none');document.querySelectorAll('.f-perf').forEach(e=>e.style.display=this.value=='performance'?'':'none')">
    <option value="students" <?= $type === 'students' ? 'selected' : '' ?>>Student information</option>
    <option value="attendance" <?= $type === 'attendance' ? 'selected' : '' ?>>Attendance</option>
    <option value="performance" <?= $type === 'performance' ? 'selected' : '' ?>>Academic performance</option></select></div>
  <div><label>Class</label><select name="class" required><?= options($classes, 'Class_ID', fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')', $classId, 'Choose a class') ?></select></div>
  <div><label>Student</label><select name="student"><option value="0">Entire class</option><?= options($classStudents, 'Student_ID', fn($s) => $s['Last_Name'] . ', ' . $s['First_Name'] . ' (' . $s['Student_No'] . ')', $studentId) ?></select></div>
  <div class="f-att" style="<?= $type === 'attendance' ? '' : 'display:none' ?>"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
  <div class="f-att" style="<?= $type === 'attendance' ? '' : 'display:none' ?>"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
  <div class="f-perf" style="<?= $type === 'performance' ? '' : 'display:none' ?>"><label>Examination</label><select name="exam"><?= options($exams, 'Exam_ID', fn($e) => $e['Exam_Name'] . ' (T' . $e['Term'] . ' ' . $e['Academic_Year'] . ')', $examId, 'Choose an exam') ?></select></div>
  <div class="fit"><button class="btn">Generate report</button></div>
</form>
<?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
<?php if ($run && !$error): ?>
  <div class="report-head"><h2><?= h(SCHOOL_NAME) ?></h2><div><?= h(SCHOOL_PLACE) ?></div></div>
  <h2><?= h($title) ?></h2><p class="muted"><?= h($subtitle) ?> <span class="no-print"> | Generated <?= date('j M Y, H:i') ?></span></p>
  <p class="no-print actions"><button class="btn alt" onclick="window.print()">Print</button>
    <a class="btn alt" href="?<?= h(http_build_query(array_merge($_GET, ['csv' => 1]))) ?>">Download CSV (opens in Excel)</a></p>
  <div class="table-wrap"><table><tr><?php foreach ($headers as $hd): ?><th><?= h($hd) ?></th><?php endforeach; ?></tr>
  <?php foreach ($data as $r): ?><tr><?php foreach ($r as $c): ?><td><?= h($c) ?></td><?php endforeach; ?></tr>
  <?php endforeach; if (!$data): ?><tr><td colspan="<?= count($headers) ?>" class="muted">No records for this selection.</td></tr><?php endif; ?></table></div>
  <?php if ($user['Role'] === 'teacher' && $type === 'performance' && $studentId && $reportExam):
    $comment = q('SELECT Academic_Comment, Progress_Comment FROM teacher_comment WHERE Teacher_ID=? AND Student_ID=? AND Exam_ID=?',
        [$user['Teacher_ID'], $studentId, $examId])->fetch() ?: ['Academic_Comment' => '', 'Progress_Comment' => '']; ?>
    <h2>Student academic and progress comments</h2>
    <?php if ($comment['Academic_Comment'] !== '' || $comment['Progress_Comment'] !== ''): ?>
      <div class="panel"><h3>Academic performance</h3><p><?= nl2br(h($comment['Academic_Comment'])) ?></p><h3>General progress</h3><p><?= nl2br(h($comment['Progress_Comment'])) ?></p></div>
    <?php endif; ?>
    <form class="panel grid no-print" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_comment">
      <input type="hidden" name="student" value="<?= $studentId ?>"><input type="hidden" name="exam" value="<?= $examId ?>">
      <div class="full"><label for="academic-comment">Academic performance</label><textarea id="academic-comment" name="academic_comment" rows="4" maxlength="5000"><?= h($comment['Academic_Comment']) ?></textarea></div>
      <div class="full"><label for="progress-comment">General progress</label><textarea id="progress-comment" name="progress_comment" rows="4" maxlength="5000"><?= h($comment['Progress_Comment']) ?></textarea></div>
      <div><button class="btn">Save comments</button></div>
    </form>
  <?php endif; ?>
<?php endif; ?>
<?php page_end();
