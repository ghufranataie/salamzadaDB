<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../db.php";
require_once "../functions.php";

$db = new Database();
$conn = $db->getConnection();
$value = new DataValues($conn);
$data = json_decode(file_get_contents("php://input"), true);
$request_method = $_SERVER["REQUEST_METHOD"];


function download_mysql_data_backup()
{
    // DB credentials (example)
    $dbHost = "ztechdb.cyb0o8u2oqp9.us-east-1.rds.amazonaws.com";
    $dbUser = "ghufranataie";
    $dbPass = "DefaultGTRPassDBac1";
    $dbName = "zaitoon";

    $tmpDir = sys_get_temp_dir();
    $timestamp = date("Ymd_His");
    $backupFile = "$tmpDir/backup_data_{$dbName}_{$timestamp}.sql";

    // Data-only mysqldump
    $command = sprintf(
        'mysqldump --no-create-info -h %s -u %s -p%s %s > %s',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );

    exec($command, $output, $status);

    if ($status !== 0 || !file_exists($backupFile)) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Data-only backup failed"
        ]);
        exit;
    }

    header("Content-Type: application/sql");
    header("Content-Disposition: attachment; filename=\"" . basename($backupFile) . "\"");
    header("Content-Length: " . filesize($backupFile));

    readfile($backupFile);
    unlink($backupFile);
    exit;
}

download_mysql_data_backup();




?>