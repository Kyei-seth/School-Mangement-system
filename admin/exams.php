<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $name = trim($_POST['name'] ?? ''); $term = (int)($_POST['term'] ?? 0);
        if ($name === '' || $term < 1 || $term > 3) flash('Enter an exam name and choose a term.', 'err');
        else {
            q('INSERT INTO examination (Exam_Name,Exam_Type,Academic_Year,Term,Exam_Date) VALUES (?,?,?,?,?)',
              [$name, trim($_POST['type'] ?? 'End of term'), $_POST['year'], $term, $_POST['date'] ?: null]);
            flash('Examination created. Teachers can now enter scores for it.');
        }
    } elseif ($a === 'toggle') {
        q('UPDATE examination SET Is_Published = 1 - Is_Published WHERE Exam_ID=?', [(int)$_POST['id']]);
        flash('Publish status changed.');
    } elseif ($a === 'delete') {
        q('DELETE FROM examination WHERE Exam_ID=?', [(int)$_POST['id']]);
        flash('Examination and its results deleted.');
    }
    redirect('admin/exams.php');
}
$rows = q('SELECT e.*, (SELECT COUNT(*) FROM result r WHERE r.Exam_ID=e.Exam_ID) n FROM examination e ORDER BY Academic_Year DESC, Term DESC, Exam_ID DESC')->fetchAll();
page_start('Examinations'); ?>
<div class="table-wrap"><table>
<tr><th>Examination</th><th>Type</th><th>Year</th><th>Term</th><th>Date</th><th class="num">Results</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['Exam_Name']) ?></td><td><?= h($r['Exam_Type']) ?></td><td><?= h($r['Academic_Year']) ?></td><td><?= $r['Term'] ?></td><td><?= h($r['Exam_Date']) ?></td><td class="num"><?= $r['n'] ?></td>
<td><span class="badge <?= $r['Is_Published'] ? 'pub' : '' ?>"><?= $r['Is_Published'] ? 'Published' : 'Draft' ?></span></td>
<td><div class="actions">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['Exam_ID'] ?>"><button class="btn alt sm"><?= $r['Is_Published'] ? 'Unpublish' : 'Publish results' ?></button></form>
<form method="post" onsubmit="return confirm('Delete this examination and all its results?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['Exam_ID'] ?>"><button class="btn danger sm">Delete</button></form></div></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No examinations yet.</td></tr><?php endif; ?>
</table></div>
<h2>Create an examination</h2>
<form class="panel grid" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div><label>Name</label><input name="name" required placeholder="e.g. Term 1 End of Term Exam"></div>
  <div><label>Type</label><input name="type" value="End of term"></div>
  <div><label>Academic year</label><select name="year"><?php foreach (academic_years() as $y): ?><option <?= $y === default_year() ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
  <div><label>Term</label><select name="term"><option>1</option><option>2</option><option>3</option></select></div>
  <div><label>Exam date</label><input type="date" name="date"></div>
  <div class="actions" style="align-self:end"><button class="btn">Create examination</button></div>
</form>
<?php page_end();
