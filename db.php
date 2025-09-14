<?php
// db.php
date_default_timezone_set('Asia/Dhaka');

/* SHOW mysqli errors as exceptions (very important for debugging) */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = new mysqli('localhost', 'root', '12341234', 'webtech_project');
$mysqli->set_charset('utf8mb4');
