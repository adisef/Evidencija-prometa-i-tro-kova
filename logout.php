<?php
require_once 'includes/db.php';
require_once 'includes/auth.class.php';

$auth = new Auth($conn);
$auth->logout();

header('Location: login.php');
exit;
?>