<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $f = [trim($_POST['first'] ?? ''), trim($_POST['last'] ?? ''), $_POST['gender'] ?? '', trim($_POST['phone'] ?? ''),
              trim($_POST['email'] ?? ''), trim($_POST['address'] ?? ''), $_POST['employed'] ?: null];
        if ($f[0] === '' || $f[1] === '' || !in_array($f[2], ['Male', 'Female'], true)) {
            flash('First name, last name and gender are required.', 'err');
        } elseif ($f[4] !== '' && !filter_var($f[4], FILTER_VALIDATE_EMAIL)) {
            flash('Enter a valid email address.', 'err');
        } elseif ($id) {
            q('UPDATE teacher SET First_Name=?,Last_Name=?,Gender=?,Phone_Number=?,Email=?,Address=?,Date_Employed=? WHERE Teacher_ID=?', [...$f, $id]);
            flash('Teacher updated.');
        } else {
            $no = next_number('TCH', 'teacher', 'Staff_No');
            q('INSERT INTO teacher (Staff_No,First_Name,Last_Name,Gender,Phone_Number,Email,Address,Date_Employed) VALUES (?,?,?,?,?,?,?,?)', [$no, ...$f]);
            $pw = create_user($no, 'teacher', null, (int)db()->lastInsertId());
            flash('Teacher registered. Login: <b>' . h($no) . '</b> / password: <b>' . h($pw) . '</b>. Write this down now, it is shown once.');
        }
    } elseif ($a === 'delete') {
        $id = (int)$_POST['id'];
        $assigned = (int)q('SELECT COUNT(*) FROM subject WHERE Teacher_ID=?', [$id])->fetchColumn();
        if ($assigned) flash('Reassign this teacher\'s subjects before deleting the teacher.', 'err');
        else {
            $photo = q('SELECT Photo_Path FROM teacher WHERE Teacher_ID=?', [$id])->fetchColumn();
            q('DELETE FROM teacher WHERE Teacher_ID=?', [$id]);
            delete_profile_photo($photo ?: null, 'teachers');
            flash('Teacher deleted.');
        }
    } elseif ($a === 'reset') {
        $pw = random_password();
        q('UPDATE users SET Password=? WHERE Teacher_ID=?', [password_hash($pw, PASSWORD_DEFAULT), (int)$_POST['id']]);
        flash('New password: <b>' . h($pw) . '</b>. Write this down now, it is shown once.');
    }
    redirect('admin/teachers.php');
}

$search = trim($_GET['s'] ?? '');
$sql = 'SELECT * FROM teacher'; $p = [];
if ($search !== '') { $sql .= ' WHERE First_Name LIKE ? OR Last_Name LIKE ? OR Staff_No LIKE ? OR CONCAT(First_Name," ",Last_Name) LIKE ?'; $p = array_fill(0, 4, "%$search%"); }
$rows = q($sql . ' ORDER BY Last_Name, First_Name', $p)->fetchAll();
$edit = isset($_GET['edit']) ? q('SELECT * FROM teacher WHERE Teacher_ID=?', [(int)$_GET['edit']])->fetch() : null;
$e = $edit ?: [];

page_start('Teachers'); ?>
<form class="bar no-print" method="get">
  <div><label>Search by name or staff number</label><input name="s" value="<?= h($search) ?>"></div>
  <div class="fit"><button class="btn">Search</button></div>
</form>
<div class="table-wrap"><table>
<tr><th>Staff no.</th><th>Name</th><th>Gender</th><th>Phone</th><th>Email</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['Staff_No']) ?></td><td><?= h($r['Last_Name'] . ', ' . $r['First_Name']) ?></td><td><?= h($r['Gender']) ?></td><td><?= h($r['Phone_Number']) ?></td><td><?= h($r['Email']) ?></td>
<td><div class="actions">
  <a class="btn alt sm" href="?edit=<?= $r['Teacher_ID'] ?>#form">Edit</a>
  <form method="post" onsubmit="return confirm('Reset this teacher\'s password?')"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $r['Teacher_ID'] ?>"><button class="btn alt sm">Reset password</button></form>
  <form method="post" onsubmit="return confirm('Delete this teacher?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['Teacher_ID'] ?>"><button class="btn danger sm">Delete</button></form>
</div></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No teachers found.</td></tr><?php endif; ?>
</table></div>

<h2 id="form"><?= $edit ? 'Edit teacher ' . h($e['Staff_No']) : 'Register a new teacher' ?></h2>
<form class="panel grid" method="post"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($e['Teacher_ID'] ?? 0) ?>">
  <div><label>First name</label><input name="first" required value="<?= h($e['First_Name'] ?? '') ?>"></div>
  <div><label>Last name</label><input name="last" required value="<?= h($e['Last_Name'] ?? '') ?>"></div>
  <div><label>Gender</label><select name="gender" required><option value="">Select</option>
    <?php foreach (['Male', 'Female'] as $g): ?><option <?= ($e['Gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
  <div><label>Phone number</label><input name="phone" value="<?= h($e['Phone_Number'] ?? '') ?>"></div>
  <div><label>Email</label><input type="email" name="email" value="<?= h($e['Email'] ?? '') ?>"></div>
  <div><label>Address</label><input name="address" value="<?= h($e['Address'] ?? '') ?>"></div>
  <div><label>Date employed</label><input type="date" name="employed" value="<?= h($e['Date_Employed'] ?? '') ?>"></div>
  <div class="actions" style="align-self:end"><button class="btn"><?= $edit ? 'Save changes' : 'Register teacher' ?></button><?php if ($edit): ?><a class="btn alt" href="teachers.php">Cancel</a><?php endif; ?></div>
</form>
<?php page_end();
