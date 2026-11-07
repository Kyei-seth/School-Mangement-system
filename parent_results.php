<?php
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_role('admin', 'teacher');
$isTeacher = $user['Role'] === 'teacher';
$classes = $isTeacher
    ? teacher_classes((int)$user['Teacher_ID'])
    : q('SELECT * FROM classroom ORDER BY Academic_Year DESC, Class_Name')->fetchAll();
$allowedClassIds = array_map('intval', array_column($classes, 'Class_ID'));
$exams = q('SELECT * FROM examination WHERE Is_Published=1 ORDER BY Academic_Year DESC, Term DESC, Exam_ID DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = (int)($_POST['class'] ?? 0);
    $examId = (int)($_POST['exam'] ?? 0);
    $studentId = (int)($_POST['student'] ?? 0);
    $channels = ['email'];
    $exam = q('SELECT * FROM examination WHERE Exam_ID=? AND Is_Published=1', [$examId])->fetch();
    if (!in_array($classId, $allowedClassIds, true) || !$exam || !$channels) {
        flash('Choose one of your classes, a published examination and at least one delivery method.', 'err');
        redirect('parent_results.php');
    }
    $classTeacher = !$isTeacher || (bool)q('SELECT COUNT(*) FROM classroom WHERE Class_ID=? AND Teacher_ID=?', [$classId, $user['Teacher_ID']])->fetchColumn();
    $subjectRows = [];
    if ($isTeacher && !$classTeacher) {
        $subjectRows = q('SELECT Subject_ID FROM subject WHERE Class_ID=? AND Teacher_ID=?', [$classId, $user['Teacher_ID']])->fetchAll();
        if (!$subjectRows) {
            http_response_code(403);
            die('You are not authorised to send results for this class.');
        }
    }
    $sql = 'SELECT * FROM student WHERE Class_ID=?';
    $params = [$classId];
    if ($studentId) { $sql .= ' AND Student_ID=?'; $params[] = $studentId; }
    $students = q($sql . ' ORDER BY Last_Name, First_Name', $params)->fetchAll();
    if (!$students) {
        flash('Choose a student from the selected class.', 'err');
        redirect('parent_results.php');
    }
    $sent = 0;
    $failures = [];
    $remarks = q('SELECT Grade, Remark FROM grade_scale')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($students as $student) {
        $resultSql = 'SELECT r.Grade, s.Subject_Name FROM result r JOIN subject s ON s.Subject_ID=r.Subject_ID
                      WHERE r.Student_ID=? AND r.Exam_ID=?';
        $resultParams = [$student['Student_ID'], $examId];
        if ($isTeacher && !$classTeacher) {
            $subjectIds = array_map('intval', array_column($subjectRows, 'Subject_ID'));
            $resultSql .= ' AND r.Subject_ID IN (' . implode(',', array_fill(0, count($subjectIds), '?')) . ')';
            $resultParams = [...$resultParams, ...$subjectIds];
        }
        $results = q($resultSql . ' ORDER BY s.Subject_Name', $resultParams)->fetchAll();
        if (!$results) {
            $failures[] = $student['First_Name'] . ' ' . $student['Last_Name'] . ': no results found for this examination.';
            continue;
        }
        $lines = array_map(fn($row) => $row['Subject_Name'] . ': ' . $row['Grade'] . (empty($remarks[$row['Grade']]) ? '' : ' (' . $remarks[$row['Grade']] . ')'), $results);
        $message = 'Examination results for ' . $student['First_Name'] . ' ' . $student['Last_Name'] . ' — ' . $exam['Exam_Name'] . ":\n"
            . implode("\n", $lines) . "\n" . SCHOOL_NAME;
        $notice = notify_guardian($student, (int)$user['User_ID'], $examId, 'Examination results - ' . $exam['Exam_Name'], $message, $channels);
        $sent += $notice['sent'];
        foreach ($notice['errors'] as $error) $failures[] = $student['Student_No'] . ': ' . $error;
    }
    if ($sent) flash('Result notification sent through ' . $sent . ' channel(s).');
    foreach ($failures as $failure) flash(h($failure), 'err');
    if (!$sent && !$failures) flash('No notifications were sent.', 'err');
    redirect('parent_results.php');
}

$selectedClass = (int)($_GET['class'] ?? 0);
$selectedStudent = (int)($_GET['student'] ?? 0);
$selectedExam = (int)($_GET['exam'] ?? 0);
$students = in_array($selectedClass, $allowedClassIds, true)
    ? q('SELECT * FROM student WHERE Class_ID=? ORDER BY Last_Name, First_Name', [$selectedClass])->fetchAll()
    : [];
if ($selectedStudent && !in_array($selectedStudent, array_map('intval', array_column($students, 'Student_ID')), true)) $selectedStudent = 0;

page_start('Send results to guardians'); ?>
<form class="bar" method="get">
  <div><label for="class">Class</label><select id="class" name="class" required><?= options($classes, 'Class_ID', fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')', $selectedClass, 'Choose a class') ?></select></div>
  <div><label for="exam">Published examination</label><select id="exam" name="exam" required><?= options($exams, 'Exam_ID', fn($e) => $e['Exam_Name'] . ' (Term ' . $e['Term'] . ', ' . $e['Academic_Year'] . ')', $selectedExam, 'Choose an examination') ?></select></div>
  <div><label for="student">Student</label><select id="student" name="student"><option value="0">Entire class</option><?= options($students, 'Student_ID', fn($s) => $s['Last_Name'] . ', ' . $s['First_Name'] . ' (' . $s['Student_No'] . ')', $selectedStudent) ?></select></div>
  <div class="fit"><button class="btn alt">Load selection</button></div>
</form>
<?php if (in_array($selectedClass, $allowedClassIds, true) && in_array($selectedExam, array_map('intval', array_column($exams, 'Exam_ID')), true)): ?>
<form class="panel grid" method="post"><?= csrf_field() ?>
  <input type="hidden" name="class" value="<?= $selectedClass ?>"><input type="hidden" name="exam" value="<?= $selectedExam ?>"><input type="hidden" name="student" value="<?= $selectedStudent ?>">
  <div class="full"><button class="btn">Send results to parent/guardian</button></div>
</form>
<?php endif; ?>
<?php page_end();
