<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $f = [trim($_POST['first'] ?? ''), trim($_POST['last'] ?? ''), $_POST['gender'] ?? '', $_POST['dob'] ?: null,
              trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['guardian_name'] ?? ''),
              trim($_POST['guardian_phone'] ?? ''), trim($_POST['guardian_email'] ?? ''), $_POST['admitted'] ?: null, $_POST['class_id'] ?: null];
        if ($f[0] === '' || $f[1] === '' || !in_array($f[2], ['Male', 'Female'], true)) {
            flash('First name, last name and gender are required.', 'err');
        } elseif ($f[6] === '' || $f[7] === '') {
            flash('Enter a parent or guardian name and phone number.', 'err');
        } elseif ($f[8] !== '' && !filter_var($f[8], FILTER_VALIDATE_EMAIL)) {
            flash('Enter a valid guardian email address.', 'err');
        } elseif ($id) {
            q('UPDATE student SET First_Name=?,Last_Name=?,Gender=?,Date_of_Birth=?,Address=?,Phone_Number=?,Guardian_Name=?,Guardian_Phone=?,Guardian_Email=?,Admission_Date=?,Class_ID=? WHERE Student_ID=?', [...$f, $id]);
            flash('Student updated.');
        } else {
            $no = next_number('APS', 'student', 'Student_No');
            q('INSERT INTO student (Student_No,First_Name,Last_Name,Gender,Date_of_Birth,Address,Phone_Number,Guardian_Name,Guardian_Phone,Guardian_Email,Admission_Date,Class_ID) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$no, ...$f]);
            $sid = (int)db()->lastInsertId();
            $pw = create_user($no, 'student', $sid, null);
            flash('Student registered. Login: <b>' . h($no) . '</b> / password: <b>' . h($pw) . '</b>. Write this down now, it is shown once.');
        }
    } elseif ($a === 'delete') {
        $id = (int)$_POST['id'];
        $photo = q('SELECT Photo_Path FROM student WHERE Student_ID=?', [$id])->fetchColumn();
        q('DELETE FROM student WHERE Student_ID=?', [$id]);
        delete_profile_photo($photo ?: null, 'students');
        flash('Student deleted, along with their attendance, results and login.');
    } elseif ($a === 'reset') {
        $pw = random_password();
        q('UPDATE users SET Password=? WHERE Student_ID=?', [password_hash($pw, PASSWORD_DEFAULT), (int)$_POST['id']]);
        flash('New password: <b>' . h($pw) . '</b>. Write this down now, it is shown once.');
    }
    redirect('admin/students.php');
}

$classes = q('SELECT * FROM classroom ORDER BY Academic_Year DESC, Class_Name')->fetchAll();
$search = trim($_GET['s'] ?? ''); $cf = $_GET['class'] ?? '';
$sql = 'SELECT s.*, c.Class_Name FROM student s LEFT JOIN classroom c ON c.Class_ID=s.Class_ID WHERE 1';
$p = [];
if ($search !== '') { $sql .= ' AND (s.First_Name LIKE ? OR s.Last_Name LIKE ? OR s.Student_No LIKE ? OR CONCAT(s.First_Name," ",s.Last_Name) LIKE ?)'; $p = array_fill(0, 4, "%$search%"); }
if ($cf === 'none') $sql .= ' AND s.Class_ID IS NULL'; elseif ($cf !== '') { $sql .= ' AND s.Class_ID=?'; $p[] = (int)$cf; }
$rows = q($sql . ' ORDER BY s.Last_Name, s.First_Name', $p)->fetchAll();
$edit = isset($_GET['edit']) ? q('SELECT * FROM student WHERE Student_ID=?', [(int)$_GET['edit']])->fetch() : null;
$e = $edit ?: [];
$cl = fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')';

page_start('Students'); ?>
<form class="bar no-print" method="get">
  <div><label>Search by name or student number</label><input name="s" value="<?= h($search) ?>"></div>
  <div><label>Class</label><select name="class"><option value="">All classes</option><option value="none" <?= $cf === 'none' ? 'selected' : '' ?>>No class yet</option><?= options($classes, 'Class_ID', $cl, $cf) ?></select></div>
  <div class="fit"><button class="btn">Search</button></div>
</form>
<div class="table-wrap"><table>
<tr><th>Student no.</th><th>Name</th><th>Gender</th><th>Class</th><th>Phone</th><th class="no-print"></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['Student_No']) ?></td><td><?= h($r['Last_Name'] . ', ' . $r['First_Name']) ?></td><td><?= h($r['Gender']) ?></td>
<td><?= h($r['Class_Name'] ?? 'Not assigned') ?></td><td><?= h($r['Phone_Number']) ?></td>
<td class="no-print"><div class="actions">
  <a class="btn alt sm" href="?edit=<?= $r['Student_ID'] ?>#form">Edit</a>
  <form method="post" onsubmit="return confirm('Reset this student\'s password?')"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $r['Student_ID'] ?>"><button class="btn alt sm">Reset password</button></form>
  <form method="post" onsubmit="return confirm('Delete this student and all their records?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['Student_ID'] ?>"><button class="btn danger sm">Delete</button></form>
</div></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No students found.</td></tr><?php endif; ?>
</table></div>

<h2 id="form"><?= $edit ? 'Edit student ' . h($e['Student_No']) : 'Register a new student' ?></h2>
<form class="panel grid" method="post"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($e['Student_ID'] ?? 0) ?>">
  <div><label>First name</label><input name="first" required value="<?= h($e['First_Name'] ?? '') ?>"></div>
  <div><label>Last name</label><input name="last" required value="<?= h($e['Last_Name'] ?? '') ?>"></div>
  <div><label>Gender</label><select name="gender" required><option value="">Select</option>
    <?php foreach (['Male', 'Female'] as $g): ?><option <?= ($e['Gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
  <div><label>Date of birth</label><input type="date" name="dob" value="<?= h($e['Date_of_Birth'] ?? '') ?>"></div>
  <div><label>Student phone number</label><input name="phone" value="<?= h($e['Phone_Number'] ?? '') ?>"></div>
  <div><label>Address</label><input name="address" value="<?= h($e['Address'] ?? '') ?>"></div>
  <div><label>Parent/guardian name</label><input name="guardian_name" required value="<?= h($e['Guardian_Name'] ?? '') ?>"></div>
  <div><label>Parent/guardian phone</label><input name="guardian_phone" required value="<?= h($e['Guardian_Phone'] ?? '') ?>"></div>
  <div><label>Parent/guardian email (optional)</label><input type="email" name="guardian_email" value="<?= h($e['Guardian_Email'] ?? '') ?>"></div>
  <div><label>Admission date</label><input type="date" name="admitted" value="<?= h($e['Admission_Date'] ?? date('Y-m-d')) ?>"></div>
  <div><label>Class</label><select name="class_id"><?= options($classes, 'Class_ID', $cl, $e['Class_ID'] ?? '', 'Not assigned') ?></select></div>
  <div class="actions" style="align-self:end"><button class="btn"><?= $edit ? 'Save changes' : 'Register student' ?></button><?php if ($edit): ?><a class="btn alt" href="students.php">Cancel</a><?php endif; ?></div>
</form>
<?php page_end();
