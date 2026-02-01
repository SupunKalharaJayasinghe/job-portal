<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$dbname = 'job_portal_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die("Connection Failed: " . $e->getMessage());
}
?>
