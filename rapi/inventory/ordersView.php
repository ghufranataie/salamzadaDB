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
        load_orders();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_orders() {
    global $conn;
    try {
        if (isset($_GET['ordID']) && !empty($_GET['ordID'])) {
            $ordID = $_GET['ordID'];
            // Use SQL for single record
            $sql = "SELECT ordID, ordName, perID, concat(perName, ' ', perLastName) as personal, ordxRef, ordTrnRef, ordBranch, brcName,
                sum(distinct case when ordName='Purchase' then (stkQuantity*stkPurPrice) else (stkQuantity*stkSalePrice) end) as totalBill,
                sum(distinct case when ordName='Sale' then (stkQuantity*stkSalePrice)-(stkQuantity*stkPurPrice) else 0 end) as benifit,
                trnStateText, ordEntryDate 
                from orders o
                join stock s on s.stkOrder = o.ordID
                left join personal p on p.perID = o.ordPersonal
                left join transactions t on t.trnReference = o.ordTrnRef
                join trnDetails td on td.trdReference = t.trnReference
                join branch b on b.brcID = o.ordBranch
                where ordID = :id
                group by ordID, ordName, perID, personal, ordxRef, ordTrnRef, ordBranch, brcName, trnStateText, ordEntryDate";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $ordID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {
            // Use default SQL for all records
            $sql = "SELECT ordID, ordName, perID, concat(perName, ' ', perLastName) as personal, ordxRef, ordTrnRef, ordBranch, brcName,
                sum(distinct case when ordName='Purchase' then (stkQuantity*stkPurPrice) else (stkQuantity*stkSalePrice) end) as totalBill,
                sum(distinct case when ordName='Sale' then (stkQuantity*stkSalePrice)-(stkQuantity*stkPurPrice) else 0 end) as benifit,
                trnStateText, ordEntryDate 
                from orders o
                join stock s on s.stkOrder = o.ordID
                left join personal p on p.perID = o.ordPersonal
                left join transactions t on t.trnReference = o.ordTrnRef
                join trnDetails td on td.trdReference = t.trnReference
                join branch b on b.brcID = o.ordBranch
                WHERE o.ordName IN ('Sale', 'Purchase') 
                AND (t.trnStateText = 'Pending'
					OR (t.trnStateText = 'Authorized' AND DATE(o.ordEntryDate) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY)	AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH) )
				)
                group by ordID, ordName, perID, personal, ordxRef, ordTrnRef, ordBranch, brcName, trnStateText, ordEntryDate 
                order by ordID DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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