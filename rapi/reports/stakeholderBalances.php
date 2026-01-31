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

switch ($request_method) {
    case 'POST':
        daily_grossing();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function daily_grossing() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $name = $input['name'];
    $ccy = $input['ccy'];

    try {

        $sql = "SELECT * from vw_personalAccounts where accBalance != 0 ";

        $params = [];

        if (!empty($name)) {
            $sql .= " AND fullName like :fName";
            $params[':fName'] = "%$name%";
        }

        if (!empty($ccy)) {
            $sql .= " AND accCurrency = :ccy";
            $params[':ccy'] = $ccy;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data, JSON_PRETTY_PRINT);
    } 
    catch (PDOException $th) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

?>