<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = true; $rows = [];
    foreach ($_POST['g'] ?? [] as $grade => $v) {
        $min = (float)$v['min']; $max = (float)$v['max'];
        if ($min < 0 || $max > 100 || $min > $max) { $ok = false; break; }
        $rows[] = [$min, $max, trim($v['remark']), $grade];
    }
    if ($ok) {
        foreach ($rows as $r) q('UPDATE grade_scale SET Min_Score=?, Max_Score=?, Remark=? WHERE Grade=?', $r);
        flash('Grading scale saved. Existing results keep their old grades until the scores are saved again.');
    } else flash('Each range must be between 0 and 100, with the minimum below the maximum.', 'err');
    redirect('admin/settings.php');
}
$g = q('SELECT * FROM grade_scale ORDER BY Min_Score DESC')->fetchAll();
page_start('Grading scale'); ?>
<form class="panel" method="post"><?= csrf_field() ?>
<div class="table-wrap"><table><tr><th>Grade</th><th>From</th><th>To</th><th>Remark</th></tr>
<?php foreach ($g as $r): $k = h($r['Grade']); ?>
<tr><td><b><?= $k ?></b></td>
<td><input type="number" step="0.01" min="0" max="100" name="g[<?= $k ?>][min]" value="<?= h($r['Min_Score']) ?>"></td>
<td><input type="number" step="0.01" min="0" max="100" name="g[<?= $k ?>][max]" value="<?= h($r['Max_Score']) ?>"></td>
<td><input name="g[<?= $k ?>][remark]" value="<?= h($r['Remark']) ?>"></td></tr>
<?php endforeach; ?></table></div>
<p><button class="btn">Save grading scale</button></p></form>
<?php page_end();
