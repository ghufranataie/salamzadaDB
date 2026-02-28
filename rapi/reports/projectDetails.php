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
    case 'GET':
        get_projectDetails();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_projectDetails() {
    global $conn, $value;

    try {
        if(isset($_GET['prjID']) && !empty($_GET['prjID'])) {
            $id = $_GET['prjID'];
            $params = [':id' => $id];
        
            $sql1 = "SELECT prjID, prjName, prjLocation, prjDetails, prjDateLine, prjEntryDate,
                    p.prjOwner, concat(pr.perName, ' ', pr.perLastName) AS prjOwnerfullName, prjOwnerAccount, ad.actCurrency,
                    p.prjStatus
                FROM projects p
                JOIN accounts a ON a.accNumber = p.prjOwnerAccount
                JOIN accountDetails ad ON ad.actAccount = a.accNumber
                JOIN personal pr ON pr.perID = p.prjOwner
                WHERE p.prjID = :id";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->execute($params);
            $data1 = $stmt1->fetch(PDO::FETCH_ASSOC);
            
            $sql2 = "SELECT
                    pd.pjdID,
                    pd.pjdServices as srvID, ps.srvName, 
                    pd.pjdQuantity, pd.pjdPricePerQty, (pd.pjdQuantity * pd.pjdPricePerQty) as total,
                    pp.prpTrnRef, pp.prpID as paymentID, pd.pjdRemark, pd.pjdStatus
                FROM projectDetails pd
                JOIN projects p on p.prjID = pd.pjdProject
                JOIN projectServices ps on ps.srvID = pd.pjdServices
                join projectPayments pp on pp.prpID = pd.pjdPaymentId
                WHERE p.prjID = :id";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->execute($params);
            $data2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            $sql3 = "SELECT p.prjID, pp.prpType, pp.prpTrnRef, t.trnStateText, t.trnEntryDate,
                    trdCcy,
                    sum(case when pp.prpType = 'Payment' then td.trdAmount else 0 end)/2 as payments,
                    sum(case when pp.prpType = 'Expense' then td.trdAmount else 0 end)/2 as expenses
                FROM projectPayments pp
                JOIN transactions t on t.trnReference = pp.prpTrnRef
                JOIN trnDetails td on td.trdReference = t.trnReference
                JOIN projects p on p.prjID = pp.prpProjectID
                WHERE p.prjID = :id
                GROUP BY pp.prpTrnRef order by pp.prpType ASC";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->execute($params);
            $data3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            $data1["projectServices"] = $data2;
            $data1["projectPayments"] = $data3;
            echo json_encode($data1, JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                "msg" => "failed",
                "error" => "Project ID is required"
            ]);
        }
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