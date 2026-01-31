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
        get_shippings();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_shippings() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $product = (INT)$input['product'];
    $storage = (INT)$input['storage'];
    $availability = $input['availability'];


    $zero = 0;


    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY sa.proID ASC) as No, sa.* from vw_stockAvailability sa WHERE proCode != :zero";

        $params = [
            ':zero' => $zero
        ];

        if (!empty($product)) {
            $sql .= " AND sa.proID = :pro";
            $params[':pro'] = $product;
        }

        if (!empty($storage)) {
            $sql .= " AND sa.stkStorage = :stg";
            $params[':stg'] = $storage;
        }

        if ($availability == 1) {
            $sql .= " AND sa.available_quantity > :av";
            $params[':av'] = 0;
        }elseif($availability == 2){
            $sql .= " AND sa.available_quantity = :av";
            $params[':av'] = 0;
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