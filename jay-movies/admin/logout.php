<?php
require_once __DIR__ . '/../includes/functions.php';
$_SESSION['admin_logged_in'] = false;
unset($_SESSION['admin_logged_in']);
session_write_close();
redirect('login.php');
?>
