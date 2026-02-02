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
        get_shifting();
        break;
    case 'POST':
        create_shifting();
        break;
    case 'DELETE':
        delete_shifting();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_shifting() {
    global $conn;
    try {
        if (isset($_GET['ordID']) && !empty($_GET['ordID'])) {
            $shpID = $_GET['ordID'];
            // Use SQL for single record
            $sql = "SELECT ordID, ordName, ordPersonal, concat(perName, ' ', perLastName) as ordPersonalName, ordxRef, ordTrnRef,
                max(case when td.trdAccount > 10000000 then td.trdAccount else NULL end) as account,
                max(case when td.trdAccount > 10000000 then td.trdAmount else 0 end) as amount,
                trnStateText, ordEntryDate 
                from orders o
                join stock s on s.stkOrder = o.ordID
                left join personal p on p.perID = o.ordPersonal
                left join transactions t on t.trnReference = o.ordTrnRef
                left join trnDetails td on td.trdReference = t.trnReference
                join branch b on b.brcID = o.ordBranch
                where ordID = :id AND o.ordName = 'Shifting'
                group by ordID, ordName, ordPersonal, ordPersonalName, ordxRef, ordTrnRef, trnStateText, ordEntryDate";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $shpID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$data){
                echo json_encode(["msg" => "not found"], JSON_PRETTY_PRINT);
                exit;
            }

            $stmt1 = $conn->prepare("SELECT stkID,  stkProduct, p.proName, k.stkEntryType, stkStorage, s.stgName, k.stkQuantity, k.stkPurPrice
                from stock k
                join product p on p.proID = k.stkProduct
                join storages s on s.stgID = k.stkStorage where stkOrder = ?");
            $stmt1->execute([$_GET['ordID']]);
            $records = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            $data['records'] = $records;

        } else {
            // Use default SQL for all records
            $sql = "SELECT ordID, ordName, ordPersonal, concat(perName, ' ', perLastName) as ordPersonalName, ordxRef, ordTrnRef,
                max(case when td.trdAccount > 10000000 then td.trdAccount else 0 end) as account,
                max(case when td.trdAccount > 10000000 then td.trdAmount else 0 end) as amount,
                trnStateText, ordEntryDate 
                from orders o
                join stock s on s.stkOrder = o.ordID
                left join personal p on p.perID = o.ordPersonal
                left join transactions t on t.trnReference = o.ordTrnRef
                left join trnDetails td on td.trdReference = t.trnReference
                join branch b on b.brcID = o.ordBranch
                WHERE o.ordName = 'Shifting'
                group by ordID, ordName, ordPersonal, ordPersonalName, ordxRef, ordTrnRef, trnStateText, ordEntryDate 
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

function create_shifting(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $oName = "Shifting";
    $perID = NULL;
    $xRef = NULL;


    $user = $data['usrName'];
    $account = $data['account'];
    $amount = $data['amount'];
    $records = $data['records'];

    $type = "XPNS";
    $status = 0;
    $stateText = "Pending";
    $authUser = NULL;
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $defaultCcy  = $value->getCompanyAttributes('comLocalCcy');
    $branchLimit = $value->getBranchAuthLimit($branch, $defaultCcy);


    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("INSERT INTO orders (ordPersonal, ordName, ordxRef, ordBranch, ordEntryDate) VALUES (?,?,?,?,?)");
        $stmt2 = $conn->prepare("INSERT INTO stock (stkOrder, stkProduct, stkEntryType, stkStorage, stkQuantity, stkPurPrice, stkSalePrice) VALUES (?,?,?,?,?,?,?)");
        $stmt2s = $conn->prepare("SELECT available from vw_availableProducts where proID = ? and stkStorage = ? limit 1");

        $stmt3 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?,?,?,?,?,?,?)");
        $stmt4 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?,?,?,?,?,?,?,?)");
        $stmt5 = $conn->prepare("UPDATE orders SET ordTrnRef=? WHERE ordID = ?");

        // Insert Into Orders
        $stmt1->execute([$perID, $oName, $xRef, $branch, $entryDateTime]);
        $ordID= $conn->lastInsertId();

        // Insert Into Stock
        foreach($data['records'] as $record){
            $proID = $record['stkProduct'];
            $fromStorage = $record['fromStorage'];
            $toStorage = $record['toStorage'];
            $qty = $record['stkQuantity'];
            $pPrice = $record['stkPurPrice'];

            $stmt2s->execute([$proID, $fromStorage]);
            $rec = $stmt2s->fetch(PDO::FETCH_ASSOC);
            $av = $rec['available'];
            if($qty > $av){
                $conn->rollBack();
                echo json_encode(["msg" => "not enough", "specific" => "product:$proID, storage:$fromStorage"], JSON_PRETTY_PRINT);
                exit;
            }
            $stmt2->execute([$ordID, $proID, 'OUT',  $fromStorage, round($qty, 3), round($pPrice, 4), round($pPrice, 4)]);
            $stmt2->execute([$ordID, $proID, 'IN', $toStorage, round($qty, 3), round($pPrice, 4), 0]);
        }

        if(!empty($account) && !empty($amount)){
            if($amount <= $branchLimit){
                $authUser = $usrID;
                $status = 1;
                $stateText = "Authorized";
            }
            $entryDateTime = date("Y-m-d H:i:s");

            // Insert Into Transactions
            $stmt3->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

            // Prepare transaction details insert
            $remark = "$oName with Order ID: $ordID, Total Expense: $amount";
            $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]);
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Cr", $amount, $remark, $entryDateTime]);

            // Update transaction reference to created order
            $stmt5->execute([$trnRef, $ordID]);
        }

        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Order ID: $ordID,\nTrnRef: $trnRef\n\nExpense Amount: $amount"
        );

        $conn->commit();
        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

function delete_shifting(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    

    if (!$data || !isset($data['usrName'], $data['ordID'])) {
        echo json_encode(["msg" => "no data"]);
        exit;
    }

    $user = $data['usrName'];
    $ordID = $data['ordID'];


    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];

    try {

        $stmtCheck = $conn->prepare("SELECT trnMaker from transactions where trnReference = ?");

        $conn->beginTransaction();

        $stmt0 = $conn->prepare("SELECT * from orders where ordID=?");

        $stmt1 = $conn->prepare("DELETE FROM trnDetails WHERE trdReference = ?");
        $stmt2 = $conn->prepare("DELETE FROM stock WHERE stkOrder = ?");
        $stmt3 = $conn->prepare("DELETE FROM orders WHERE ordID = ?");
        $stmt4 = $conn->prepare("UPDATE transactions SET trnStateText='Deleted', trnStatus=1 WHERE trnReference = ?");
        
        $stmt0->execute([$ordID]);
        $result = $stmt0->fetch(PDO::FETCH_ASSOC);
        

        if(!$result){
            echo json_encode(["msg" => "no order"], JSON_PRETTY_PRINT);
            exit();
        }

        $ordTrnRef = $result['ordTrnRef'];
        $oName = $result['ordName'];

        $stmtCheck->execute([$ordTrnRef]);
        $usrResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $creatorUser = $usrResult['trnMaker'];
        
        if($creatorUser != $usrID){
            echo json_encode(["msg" => "not allowed"], JSON_PRETTY_PRINT);
            exit();
        }

        if(!empty($ordTrnRef)){
            $stmt1->execute([$ordTrnRef]);
            $stmt4->execute([$ordTrnRef]);
        }
        $stmt2->execute([$ordID]);
        $stmt3->execute([$ordID]);
        
        
        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Deleted - Order ID: $ordID,\nTrn Reference: $ordTrnRef"
        );

        $conn->commit();
        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

?>