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
        get_stakeholder_accounts();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_stakeholder_accounts() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $name = $input['accNameSearch'];
    $ccy = $input['currency'];
    $limit = $input['limit'];
    $status = $input['status'];

    try {
        $sql = "SELECT 
                a.accNumber, a.accName, ad.actSignatory as ownerID, concat(p.perName, ' ', p.perLastName) as ownerName, ad.actCurrency, c.ccyName, c.ccySymbol,
                case when ad.actCreditLimit >= 999999999999.9999 then 'Unlimited' else actCreditLimit end as creditLimit,
                case when ad.actStatus = 0 then 'Blocked' else 'Active' end as status
            from accounts a
            join accountDetails ad on ad.actAccount = a.accNumber
            join personal p on p.perID = ad.actSignatory
            join currency c on c.ccyCode = ad.actCurrency
            WHERE a.accName < 10000000";

        $params = [];

        if (!empty($name)) {
            $sql .= " AND a.accName like :accName OR p.perName like :accName OR p.perLastName like :accName";
            $params[':accName'] = "%$name%";
        }

        if (!empty($ccy)) {
            $sql .= " AND ad.actCurrency = :ccy";
            $params[':ccy'] = $ccy;
        }

        if (!empty($limit)) {
            $sql .= " AND ad.actCreditLimit >= :limit";
            $params[':limit'] = $limit;
        }

        if (isset($status) && $status !== '') {
            $sql .= " AND ad.actStatus = :status";
            $params[':status'] = $status;
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