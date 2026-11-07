<?php
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_role('admin', 'teacher', 'student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['Role'] === 'student') {
        http_response_code(403);
        die('Students cannot send announcements.');
    }
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $audience = $_POST['audience'] ?? '';
    $allowed = $user['Role'] === 'admin' ? ['all', 'students', 'teachers'] : ['students'];
    if ($title === '' || strlen($title) > 160 || $body === '' || !in_array($audience, $allowed, true)) {
        flash('Enter a title, message and valid audience.', 'err');
    } else {
        q('INSERT INTO announcement (Title, Body, Audience, Created_By) VALUES (?,?,?,?)',
            [$title, $body, $audience, $user['User_ID']]);
        flash('Announcement posted.');
    }
    redirect('announcements.php');
}

$audienceFilter = $user['Role'] === 'student' ? "Audience IN ('all','students')"
    : ($user['Role'] === 'teacher' ? "Audience IN ('all','teachers')" : '1=1');
$announcements = q("SELECT a.*, u.Username FROM announcement a JOIN users u ON u.User_ID=a.Created_By
                    WHERE $audienceFilter ORDER BY a.Created_At DESC, a.Announcement_ID DESC")->fetchAll();

page_start('Announcements'); ?>
<?php if ($user['Role'] !== 'student'): ?>
<h2>Post an announcement</h2>
<form class="panel grid" method="post"><?= csrf_field() ?>
  <div><label for="title">Title</label><input id="title" name="title" maxlength="160" required></div>
  <div><label for="audience">Send to</label><select id="audience" name="audience" required>
    <?php if ($user['Role'] === 'admin'): ?><option value="all">Students and teachers</option><?php endif; ?>
    <option value="students">Students</option>
    <?php if ($user['Role'] === 'admin'): ?><option value="teachers">Teachers</option><?php endif; ?>
  </select></div>
  <div class="full"><label for="body">Message</label><textarea id="body" name="body" rows="5" required></textarea></div>
  <div><button class="btn">Post announcement</button></div>
</form>
<?php endif; ?>
<h2>School announcements</h2>
<?php foreach ($announcements as $item): ?>
<article class="panel announcement">
  <h3><?= h($item['Title']) ?></h3>
  <p><?= nl2br(h($item['Body'])) ?></p>
  <small class="muted"><?= h(ucfirst($item['Audience'])) ?> · <?= h($item['Username']) ?> · <?= h(date('j M Y, H:i', strtotime($item['Created_At']))) ?></small>
</article>
<?php endforeach; ?>
<?php page_end();
