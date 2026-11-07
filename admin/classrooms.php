<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $f = [trim($_POST['name'] ?? ''), trim($_POST['level'] ?? ''), $_POST['year'] ?? '', $_POST['teacher_id'] ?: null];
        if ($f[0] === '' || $f[1] === '' || $f[2] === '') flash('Class name, level and academic year are required.', 'err');
        else {
            try {
                if ($id) { q('UPDATE classroom SET Class_Name=?,Class_Level=?,Academic_Year=?,Teacher_ID=? WHERE Class_ID=?', [...$f, $id]); flash('Classroom updated.'); }
                else { q('INSERT INTO classroom (Class_Name,Class_Level,Academic_Year,Teacher_ID) VALUES (?,?,?,?)', $f); flash('Classroom created.'); }
            } catch (PDOException $ex) { flash('A class with that name already exists for that academic year.', 'err'); }
        }
    } elseif ($a === 'delete') {
        q('DELETE FROM classroom WHERE Class_ID=?', [(int)$_POST['id']]);
        flash('Classroom deleted. Its subjects and attendance were removed; its students are now unassigned.');
    } elseif ($a === 'assign') {
        $cid = (int)($_POST['class_id'] ?? 0); $ids = array_map('intval', $_POST['students'] ?? []);
        if (!$cid || !$ids) flash('Choose a class and at least one student.', 'err');
        else {
            $in = implode(',', array_fill(0, count($ids), '?'));
            q("UPDATE student SET Class_ID=? WHERE Student_ID IN ($in)", [$cid, ...$ids]);
            flash(count($ids) . ' student(s) assigned.');
        }
    }
    redirect('admin/classrooms.php');
}

$teachers = q('SELECT * FROM teacher ORDER BY Last_Name')->fetchAll();
$rows = q('SELECT c.*, CONCAT(t.First_Name," ",t.Last_Name) tname, (SELECT COUNT(*) FROM student s WHERE s.Class_ID=c.Class_ID) n
           FROM classroom c LEFT JOIN teacher t ON t.Teacher_ID=c.Teacher_ID ORDER BY c.Academic_Year DESC, c.Class_Name')->fetchAll();
$unassigned = q('SELECT * FROM student WHERE Class_ID IS NULL ORDER BY Last_Name, First_Name')->fetchAll();
$edit = isset($_GET['edit']) ? q('SELECT * FROM classroom WHERE Class_ID=?', [(int)$_GET['edit']])->fetch() : null;
$e = $edit ?: [];
$tl = fn($t) => $t['Last_Name'] . ', ' . $t['First_Name'] . ' (' . $t['Staff_No'] . ')';

page_start('Classrooms'); ?>
<div class="table-wrap"><table>
<tr><th>Class</th><th>Level</th><th>Year</th><th>Class teacher</th><th class="num">Students</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['Class_Name']) ?></td><td><?= h($r['Class_Level']) ?></td><td><?= h($r['Academic_Year']) ?></td><td><?= h($r['tname'] ?? 'Not assigned') ?></td><td class="num"><?= $r['n'] ?></td>
<td><div class="actions"><a class="btn alt sm" href="?edit=<?= $r['Class_ID'] ?>#form">Edit</a>
<form method="post" onsubmit="return confirm('Delete this classroom?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['Class_ID'] ?>"><button class="btn danger sm">Delete</button></form></div></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No classrooms yet. Create one below.</td></tr><?php endif; ?>
</table></div>

<h2 id="form"><?= $edit ? 'Edit classroom' : 'Create a classroom' ?></h2>
<form class="panel grid" method="post"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($e['Class_ID'] ?? 0) ?>">
  <div><label>Class name</label><input name="name" required placeholder="e.g. Basic 4A" value="<?= h($e['Class_Name'] ?? '') ?>"></div>
  <div><label>Level</label><input name="level" required placeholder="e.g. Lower Primary" value="<?= h($e['Class_Level'] ?? '') ?>"></div>
  <div><label>Academic year</label><select name="year"><?php foreach (academic_years() as $y): ?><option <?= ($e['Academic_Year'] ?? default_year()) === $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
  <div><label>Class teacher</label><select name="teacher_id"><?= options($teachers, 'Teacher_ID', $tl, $e['Teacher_ID'] ?? '', 'Not assigned') ?></select></div>
  <div class="actions" style="align-self:end"><button class="btn"><?= $edit ? 'Save changes' : 'Create classroom' ?></button><?php if ($edit): ?><a class="btn alt" href="classrooms.php">Cancel</a><?php endif; ?></div>
</form>

<h2>Assign students to a class</h2>
<form class="panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="assign">
<?php if (!$rows): ?><p class="muted">Create a classroom first.</p>
<?php elseif ($unassigned): ?>
  <div class="bar"><div><label>Move selected students into</label><select name="class_id" required><?= options($rows, 'Class_ID', fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')', '', 'Choose a class') ?></select></div></div>
  <p><label><input type="checkbox" style="width:auto" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"> Select all</label></p>
  <div style="columns:2 240px"><?php foreach ($unassigned as $s): ?>
    <label style="font-weight:400"><input class="pick" type="checkbox" style="width:auto" name="students[]" value="<?= $s['Student_ID'] ?>"> <?= h($s['Last_Name'] . ', ' . $s['First_Name']) ?> <span class="muted">(<?= h($s['Student_No']) ?>)</span></label>
  <?php endforeach; ?></div>
  <p><button class="btn">Assign students</button></p>
<?php endif; ?>
</form>
<?php page_end();
