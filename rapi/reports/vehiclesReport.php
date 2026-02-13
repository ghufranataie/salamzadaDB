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
        get_userRolePermission();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_userRolePermission() {
    global $conn, $value;

    $input = json_decode(file_get_contents("php://input"), true);
     date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    if (!$input) exit;
    $expired = $input['regExpired'];
    $currentDate = date("Y-m-d");

    try {
        $sql = "SELECT v.*, concat(p.perName, ' ', p.perLastName) as driverName
            from vehicles v
            join employees e on e.empID = v.vclDriver
            join personal p on p.perID = e.empPersonal";

        $params = [];

        if (!empty($expired)) {
            $sql .= " WHERE v.vclExpireDate < :dt";
            $params[':dt'] = $currentDate;
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