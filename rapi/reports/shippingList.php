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

    $from = $input['fromDate'];
    $to = $input['toDate'];
    $vcl = $input['vehicle'];
    $customer = $input['customer'];
    $driver = $input['driver'];
    $status = $input['status'];


    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY sh.shpID DESC) as No, sh.shpID, vc.vclModel as vehicle,
                concat(pe.perName, ' ', pe.perLastName) as driverName, pd.proName, concat(pc.perName, ' ', pc.perLastName) as customerName, sh.shpFrom,
                sh.shpMovingDate, sh.shpLoadSize, sh.shpUnit, sh.shpArriveDate, sh.shpUnloadSize, sh.shpRent, (sh.shpUnloadSize * sh.shpRent) as total, sh.shpStatus    
            from shipping sh
            left join vehicles vc on vc.vclID = sh.shpVehicle
            left join employees em on em.empID = vc.vclDriver
            left join personal pc on pc.perID = sh.shpCustomer
            left join personal pe on pe.perID = em.empPersonal
            left join product pd on pd.proID = sh.shpProduct
            WHERE date(sh.shpArriveDate) between :fDate AND :tDate";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($vcl)) {
            $sql .= " AND sh.shpVehicle = :vcl";
            $params[':vcl'] = $vcl;
        }

        if (isset($status) && $status !== '') {
            $sql .= " AND sh.shpStatus = :statu";
            $params[':statu'] = $status;
        }

        if (!empty($customer)) {
            $sql .= " AND sh.shpCustomer = :cus";
            $params[':cus'] = $customer;
        }

        if (!empty($driver)) {
            $sql .= " AND vc.vclDriver = :dr";
            $params[':dr'] = $driver;
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