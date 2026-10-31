<?php
// === DATABASE CONFIGURATION ===
$DB_HOST = "localhost";
$DB_USER = "forbitto_haz_user";  // Change this
$DB_PASS = "FORBIThaz66";  // Change this
$DB_NAME = "forbitto_haz_db";  // Change this

mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(["status" => "error", "message" => "DB Connection failed: " . $conn->connect_error]));
    }
} catch (Exception $e) {
    die(json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]));
}
?>