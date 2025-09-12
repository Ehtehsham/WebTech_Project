<?php
// db.php
date_default_timezone_set('Asia/Dhaka');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '12341234';
$DB_NAME = 'webtech_project';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$mysqli->set_charset('utf8mb4');
// Align MySQL session to BD time (UTC+6) so DATE/TIMESTAMP behave as expected
$mysqli->query("SET time_zone = '+06:00'");
?>
