<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "db.php";
require_once "functions.php";

// connect
$db = new Database();
$conn = $db->getConnection();

$value = new DataValues($conn);

echo "The Rate is: " . $value->getCcyRate('AFN', 'USD');
?>