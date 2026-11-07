<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $f = [trim($_POST['name'] ?? ''), strtoupper(trim($_POST['code'] ?? '')), (int)($_POST['class_id'] ?? 0), $_POST['teacher_id'] ?: null];
        if ($f[0] === '' || $f[1] === '' || !$f[2] || !$f[3]) flash('Subject name, code, class and teacher are required.', 'err');
        else {
            try {
                if ($id) { q('UPDATE subject SET Subject_Name=?,Subject_Code=?,Class_ID=?,Teacher_ID=? WHERE Subject_ID=?', [...$f, $id]); flash('Subject updated.'); }
                else { q('INSERT INTO subject (Subject_Name,Subject_Code,Class_ID,Teacher_ID) VALUES (?,?,?,?)', $f); flash('Subject added to the class.'); }
            } catch (PDOException $ex) { flash('That subject code already exists in this class.', 'err'); }
        }
    } elseif ($a === 'delete') {
        q('DELETE FROM subject WHERE Subject_ID=?', [(int)$_POST['id']]);
        flash('Subject deleted along with its results.');
    }
    redirect('admin/subjects.php');
}

$classes = q('SELECT * FROM classroom ORDER BY Academic_Year DESC, Class_Name')->fetchAll();
$teachers = q('SELECT * FROM teacher ORDER BY Last_Name')->fetchAll();
$cf = (int)($_GET['class'] ?? 0);
$rows = q('SELECT s.*, c.Class_Name, c.Academic_Year, CONCAT(t.First_Name," ",t.Last_Name) tname FROM subject s
           JOIN classroom c ON c.Class_ID=s.Class_ID LEFT JOIN teacher t ON t.Teacher_ID=s.Teacher_ID
           WHERE (? = 0 OR s.Class_ID=?) ORDER BY c.Class_Name, s.Subject_Name', [$cf, $cf])->fetchAll();
$edit = isset($_GET['edit']) ? q('SELECT * FROM subject WHERE Subject_ID=?', [(int)$_GET['edit']])->fetch() : null;
$e = $edit ?: [];
$cl = fn($c) => $c['Class_Name'] . ' (' . $c['Academic_Year'] . ')';
$tl = fn($t) => $t['Last_Name'] . ', ' . $t['First_Name'];

page_start('Subjects'); ?>
<form class="bar" method="get"><div><label>Show class</label><select name="class" onchange="this.form.submit()"><?= options($classes, 'Class_ID', $cl, $cf, 'All classes') ?></select></div></form>
<div class="table-wrap"><table>
<tr><th>Subject</th><th>Code</th><th>Class</th><th>Teacher</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['Subject_Name']) ?></td><td><?= h($r['Subject_Code']) ?></td><td><?= h($r['Class_Name'] . ' (' . $r['Academic_Year'] . ')') ?></td><td><?= h($r['tname'] ?? 'Not assigned') ?></td>
<td><div class="actions"><a class="btn alt sm" href="?edit=<?= $r['Subject_ID'] ?>#form">Edit</a>
<form method="post" onsubmit="return confirm('Delete this subject and its results?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['Subject_ID'] ?>"><button class="btn danger sm">Delete</button></form></div></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">No subjects yet.</td></tr><?php endif; ?>
</table></div>

<h2 id="form"><?= $edit ? 'Edit subject' : 'Add a subject to a class' ?></h2>
<?php if (!$classes): ?><p class="muted">Create a classroom first.</p><?php elseif (!$teachers): ?><p class="muted">Register a teacher before assigning subjects.</p><?php else: ?>
<form class="panel grid" method="post"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($e['Subject_ID'] ?? 0) ?>">
  <div><label>Subject name</label><input name="name" required placeholder="e.g. Mathematics" value="<?= h($e['Subject_Name'] ?? '') ?>"></div>
  <div><label>Subject code</label><input name="code" required placeholder="e.g. MATH" value="<?= h($e['Subject_Code'] ?? '') ?>"></div>
  <div><label>Class</label><select name="class_id" required><?= options($classes, 'Class_ID', $cl, $e['Class_ID'] ?? $cf, 'Choose a class') ?></select></div>
  <div><label>Teacher</label><select name="teacher_id" required><?= options($teachers, 'Teacher_ID', $tl, $e['Teacher_ID'] ?? '', 'Choose a teacher') ?></select></div>
  <div class="actions" style="align-self:end"><button class="btn"><?= $edit ? 'Save changes' : 'Add subject' ?></button><?php if ($edit): ?><a class="btn alt" href="subjects.php">Cancel</a><?php endif; ?></div>
</form><?php endif; ?>
<?php page_end();
