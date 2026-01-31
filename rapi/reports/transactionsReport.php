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
        get_trnReport();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_trnReport() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;

    $from = $input['fromDate'];
    $to = $input['toDate'];
    $type = $input['type'];
    $status = $input['status'];
    $maker = $input['maker'];
    $checker = $input['checker'];
    $currency = $input['currency'];


    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY timing ASC) AS No, vt.* from vw_allTransactionReports vt
            WHERE date(timing) between :fDate AND :tDate ";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($type)) {
            $sql .= " AND typeCode = :tp";
            $params[':tp'] = $type;
        }

        if (isset($status) && $status !== '') {
            $sql .= " AND trnStatus = :statu";
            $params[':statu'] = $status;
        }

        if (!empty($maker)) { 
            $sql .= " AND maker = :mk";
            $params[':mk'] = $maker;
        }

        if (!empty($checker)) {
            $sql .= " AND checker = :ck";
            $params[':ck'] = $checker;
        }

        if (!empty($currency)) {
            $sql .= " AND currency = :cus";
            $params[':cus'] = $currency;
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