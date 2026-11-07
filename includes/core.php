<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('Cannot connect to the database. Check config.php and that MySQL is running.');
        }
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
        $base = rtrim(str_replace($doc, '', $root), '/');
    }
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $path): never { header('Location: ' . base_url($path)); exit; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(400); die('Invalid form token. Go back, refresh the page and try again.');
        }
    }
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): string {
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) $out .= '<div class="flash ' . h($t) . '">' . $m . '</div>';
    unset($_SESSION['flash']);
    return $out;
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_role(string ...$roles): array {
    $u = current_user();
    if (!$u) redirect('index.php');
    if (!in_array($u['Role'], $roles, true)) { http_response_code(403); die('You do not have permission to view this page.'); }
    csrf_check();
    return $u;
}

function random_password(int $len = 8): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $p = ''; for ($i = 0; $i < $len; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}
function next_number(string $prefix, string $table, string $col): string {
    $year = date('y');
    $last = q("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1", [$prefix . $year . '%'])->fetchColumn();
    $n = $last ? (int)substr($last, strlen($prefix . $year)) + 1 : 1;
    return $prefix . $year . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
function grade_for(float $total): array {
    $row = q('SELECT Grade, Remark FROM grade_scale WHERE ? BETWEEN Min_Score AND Max_Score LIMIT 1', [round($total, 2)])->fetch();
    return $row ?: ['Grade' => '-', 'Remark' => ''];
}
function create_user(string $username, string $role, ?int $studentId, ?int $teacherId): string {
    $plain = random_password();
    q('INSERT INTO users (Username, Password, Role, Student_ID, Teacher_ID) VALUES (?,?,?,?,?)',
      [$username, password_hash($plain, PASSWORD_DEFAULT), $role, $studentId, $teacherId]);
    return $plain;
}
function academic_years(): array {
    $y = (int)date('Y'); $out = [];
    for ($i = $y - 2; $i <= $y + 1; $i++) $out[] = $i . '/' . ($i + 1);
    return $out;
}
function default_year(): string {
    $y = (int)date('Y');
    return date('n') >= 9 ? $y . '/' . ($y + 1) : ($y - 1) . '/' . $y;
}
function options(array $rows, string $valKey, callable $label, $selected = null, string $blank = ''): string {
    $o = $blank !== '' ? '<option value="">' . h($blank) . '</option>' : '';
    foreach ($rows as $r) $o .= '<option value="' . h($r[$valKey]) . '"' . ((string)$selected === (string)$r[$valKey] ? ' selected' : '') . '>' . h($label($r)) . '</option>';
    return $o;
}
function teacher_classes(int $teacherId): array {
    return q('SELECT DISTINCT c.* FROM classroom c LEFT JOIN subject s ON s.Class_ID=c.Class_ID
              WHERE c.Teacher_ID=? OR s.Teacher_ID=? ORDER BY c.Class_Name', [$teacherId, $teacherId])->fetchAll();
}
function log_report(string $type): void {
    $u = current_user(); if ($u) q('INSERT INTO report (Report_Type, Generated_By) VALUES (?,?)', [$type, $u['User_ID']]);
}

function save_profile_photo(string $kind): ?string {
    if (!in_array($kind, ['students', 'teachers'], true)) throw new InvalidArgumentException('Invalid profile photo type.');
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Photo upload failed. Choose the image again and retry.');
    if ($file['size'] > 3 * 1024 * 1024) throw new RuntimeException('The photo must be 3 MB or smaller.');
    $image = @getimagesize($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$image || !isset($extensions[$image['mime']])) throw new RuntimeException('Upload a valid JPEG, PNG or WebP photo.');
    $directory = __DIR__ . '/../multimedia/uploads/' . $kind;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The photo upload directory could not be created.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$image['mime']];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The uploaded photo could not be saved.');
    }
    return 'multimedia/uploads/' . $kind . '/' . $filename;
}

function delete_profile_photo(?string $path, string $kind): void {
    $prefix = 'multimedia/uploads/' . $kind . '/';
    if (!$path || !in_array($kind, ['students', 'teachers'], true) || !str_starts_with($path, $prefix)) return;
    $filename = basename($path);
    if ($filename !== '' && is_file(__DIR__ . '/../' . $prefix . $filename)) unlink(__DIR__ . '/../' . $prefix . $filename);
}

function send_email_notification(string $to, string $subject, string $body): void {
    if (!MAIL_HOST || !MAIL_FROM_ADDRESS) throw new RuntimeException('Email is not configured. Set the SMTP settings in config.php.');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var(MAIL_FROM_ADDRESS, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid sender and guardian email address are required.');
    }
    if (!in_array(MAIL_ENCRYPTION, ['tls', 'ssl', 'none'], true)) throw new RuntimeException('Choose tls, ssl or none for SMTP encryption in config.php.');
    if (MAIL_USERNAME !== '' && MAIL_PASSWORD === '') throw new RuntimeException('Set the SMTP password in config.php.');
    $port = (int)MAIL_PORT;
    $transport = MAIL_ENCRYPTION === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client($transport . MAIL_HOST . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException('Could not connect to the configured SMTP server: ' . $errstr);
    stream_set_timeout($socket, 15);
    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO school-management-system', [250]);
        if (MAIL_ENCRYPTION === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Could not establish SMTP TLS.');
            smtp_command($socket, 'EHLO school-management-system', [250]);
        }
        if (MAIL_USERNAME !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode(MAIL_USERNAME), [334]);
            smtp_command($socket, base64_encode(MAIL_PASSWORD), [235]);
        }
        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM_ADDRESS . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);
        $encode = fn($value) => '=?UTF-8?B?' . base64_encode($value) . '?=';
        $message = 'From: ' . $encode(MAIL_FROM_NAME) . ' <' . MAIL_FROM_ADDRESS . ">\r\n"
            . 'To: <' . $to . ">\r\nSubject: " . $encode($subject) . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);
        $message = preg_replace('/^\./m', '..', $message);
        smtp_write($socket, str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function smtp_expect($socket, array $codes): string {
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) throw new RuntimeException('The SMTP server closed the connection unexpectedly.');
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) throw new RuntimeException('SMTP server rejected a request: ' . trim($response));
    return $response;
}

function smtp_command($socket, string $command, array $codes): string {
    smtp_write($socket, $command . "\r\n");
    return smtp_expect($socket, $codes);
}

function smtp_write($socket, string $data): void {
    $length = strlen($data); $written = 0;
    while ($written < $length) {
        $count = fwrite($socket, substr($data, $written));
        if ($count === false || $count === 0) throw new RuntimeException('Could not write to the SMTP connection.');
        $written += $count;
    }
}

function send_sms_notification(string $to, string $message): void {
    if (!SMS_API_URL || !SMS_API_TOKEN) throw new RuntimeException('SMS is not configured. Set the SMS gateway URL and token in config.php.');
    if (!filter_var(SMS_API_URL, FILTER_VALIDATE_URL) || parse_url(SMS_API_URL, PHP_URL_SCHEME) !== 'https') {
        throw new RuntimeException('Configure an HTTPS SMS gateway URL in config.php.');
    }
    if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for SMS delivery.');
    $curl = curl_init(SMS_API_URL);
    if ($curl === false) throw new RuntimeException('Could not initialize the SMS gateway request.');
    $payload = json_encode(['to' => $to, 'message' => $message], JSON_THROW_ON_ERROR);
    if (!curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . SMS_API_TOKEN],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
    ])) {
        curl_close($curl);
        throw new RuntimeException('Could not configure the SMS gateway request.');
    }
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($response === false) throw new RuntimeException('SMS gateway request failed: ' . $error);
    if ($status < 200 || $status >= 300) throw new RuntimeException('SMS gateway returned HTTP ' . $status . '.');
}

function record_parent_notification(int $studentId, ?int $examId, int $senderId, string $channel, string $recipient, ?string $error): void {
    q('INSERT INTO parent_notification (Student_ID, Exam_ID, Sent_By, Channel, Recipient, Status, Error_Message) VALUES (?,?,?,?,?,?,?)',
        [$studentId, $examId, $senderId, $channel, $recipient, $error === null ? 'sent' : 'failed', $error]);
}

function notify_guardian(array $student, int $senderId, ?int $examId, string $subject, string $message, array $channels): array {
    $result = ['sent' => 0, 'errors' => []];
    foreach ($channels as $channel) {
        $recipient = $channel === 'email' ? trim($student['Guardian_Email'] ?? '') : trim($student['Guardian_Phone'] ?? '');
        try {
            if ($recipient === '') throw new RuntimeException('No guardian ' . $channel . ' contact is recorded.');
            if ($channel === 'email') send_email_notification($recipient, $subject, $message);
            elseif ($channel === 'sms') send_sms_notification($recipient, $message);
            else throw new InvalidArgumentException('Unsupported notification channel.');
            record_parent_notification((int)$student['Student_ID'], $examId, $senderId, $channel, $recipient, null);
            $result['sent']++;
        } catch (RuntimeException $error) {
            record_parent_notification((int)$student['Student_ID'], $examId, $senderId, $channel, $recipient, substr($error->getMessage(), 0, 500));
            $result['errors'][] = ucfirst($channel) . ': ' . $error->getMessage();
        }
    }
    return $result;
}
