<?php
require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

if (!empty($_SESSION['user_id'])) {
    audit('logout', 'user', (int) $_SESSION['user_id']);
}
$_SESSION = [];
session_destroy();
redirect('login.php');
