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
        get_projects();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_projects() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $from = $input['fromDate'];
    $to = $input['toDate'];
    $customer = $input['customer'];
    $status = $input['status'];

    try {
        $sql = "SELECT p.prjID, p.prjName, p.prjLocation,
                CONCAT(pr.perName, ' ', pr.perLastName) AS customerName,
                p.prjOwnerAccount, 
                p.prjDateLine, DATE(p.prjEntryDate) AS prjEntryDate,
                COALESCE(pd.totalAmount, 0) AS totalAmount,
                COALESCE(pay.totalPayments, 0) AS totalPayments,
                ad.actCurrency, c.ccySymbol,
                CASE WHEN p.prjStatus = 0 THEN 'Processing' ELSE 'Complete' END AS prjStatus
            FROM projects p
            JOIN personal pr 
                ON pr.perID = p.prjOwner
            LEFT JOIN (SELECT pjdProject, SUM(pjdQuantity * pjdPricePerQty) AS totalAmount FROM projectDetails GROUP BY pjdProject) pd  ON pd.pjdProject = p.prjID
            LEFT JOIN (
                SELECT 
                    pp.prpProjectID, 
                    SUM(CASE WHEN pp.prpType = 'Payment' AND td.trdDrCr = 'Cr' THEN td.trdAmount ELSE 0 END) AS totalPayments
                FROM projectPayments pp
                JOIN transactions t ON t.trnReference = pp.prpTrnRef
                JOIN trnDetails td ON td.trdReference = t.trnReference
                GROUP BY pp.prpProjectID) pay ON pay.prpProjectID = p.prjID
            join accounts a on a.accNumber = p.prjOwnerAccount
            join accountDetails ad on ad.actAccount = a.accNumber
            join currency c on c.ccyCode = ad.actCurrency
            WHERE DATE(p.prjEntryDate) BETWEEN :fDate AND :tDate";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($customer)) {
            $sql .= " AND prjOwner = :cus";
            $params[':cus'] = $customer;
        }

        if (isset($status) && $status !== '') {
            $sql .= " AND p.prjStatus = :statu";
            $params[':statu'] = $status;
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