<?php
require_once __DIR__ . '/includes/core.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
$_SESSION = [];
session_destroy();
redirect('index.php');
