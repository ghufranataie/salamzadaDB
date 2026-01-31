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
        get_allOrders();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_allOrders() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;

    $from = $input['fromDate'];
    $to = $input['toDate'];
    $name = $input['orderName'];
    $customer = (INT)$input['customer'];
    $branch = (INT)$input['branch'];
    $ordID = (INT)$input['ordID'];


    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY ao.ordID DESC) as No,  ao.* from vw_allOrders ao
            where timing between :fDate AND :tDate";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($name)) {
            $sql .= " AND ao.ordName = :oName";
            $params[':oName'] = $name;
        }

        if (!empty($customer)) {
            $sql .= " AND ao.ordPersonal = :cus";
            $params[':cus'] = $customer;
        }

        if (!empty($branch)) {
            $sql .= " AND ao.ordBranch = :br";
            $params[':br'] = $branch;
        }

        if (!empty($ordID)) {
            $sql .= " AND ao.ordID = :id";
            $params[':id'] = $ordID;
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