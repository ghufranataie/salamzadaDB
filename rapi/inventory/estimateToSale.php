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
        change_estimateToSale();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function change_estimateToSale(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $account = 0;
    $amount = 0;
    $totalBill = 0;
    $totalProAmount = 0;
    $av = 0;


    $user = $data['usrName'];
    $ordID = $data['ordID'];
    $perID = $data['ordPersonal'];
    $account = $data['account'];
    $amount = $data['amount'];

    $type = "SALE";
    $status = 0;
    $stateText = "Pending";
    $authUser = NULL;
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $defaultCcy  = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);

    // Check Account Currency and Status 
    if($account < 10000000 && $account > 499999){
        $accStatus = $value->getAccountDetails($account, 'actStatus');
        $accCcy = $value->getAccountDetails($account, 'actCurrency');
        if($accStatus == 0){
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            exit;
        }
        if($accCcy !=  $defaultCcy){
            echo json_encode(["msg" => "invalid ccy"], JSON_PRETTY_PRINT);
            exit;
        }
    }

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("SELECT * FROM tempStock WHERE tstOrder = ?");
        $stmt2 = $conn->prepare("INSERT INTO stock (stkOrder, stkProduct, stkEntryType, stkStorage, stkQuantity, stkPurPrice, stkSalePrice) 
                                    SELECT tstOrder, tstProduct, 'OUT', tstStorage, tstQuantity, tstPurPrice, tstSalePrice FROM tempStock
                                    WHERE tstOrder = ?");
        $stmt3 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?,?,?,?,?,?,?)");
        $stmt4 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?,?,?,?,?,?,?,?)");
        $stmt5 = $conn->prepare("UPDATE orders SET ordTrnRef=?, ordName='Sale' WHERE ordID = ?");
        $stmt6 = $conn->prepare("DELETE FROM tempStock WHERE tstOrder = ?");

        // Insert Into Orders
        $stmt1->execute([$ordID]);
        $rows = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        

        // Check for Availablity
        foreach($rows as $record){
            $proID = $record['tstProduct'];
            $stgID = $record['tstStorage'];
            $qty = $record['tstQuantity'];
            $pPrice = $record['tstPurPrice'];
            $sPrice = $record['tstSalePrice'];
            $totalBill += ($qty*$sPrice);
            $totalProAmount += ($qty*$pPrice);

                $stmt2s = $conn->prepare("SELECT available from vw_availableProducts where proID = ? and stkStorage = ? limit 1");
                $stmt2s->execute([$proID, $stgID]);
                $rec = $stmt2s->fetch(PDO::FETCH_ASSOC);
                $av = $rec['available'];
                if($qty > $av){
                    $conn->rollBack();
                    echo json_encode(["msg" => "not enough", "specific" => "product:$proID, storage:$stgID"], JSON_PRETTY_PRINT);
                    exit;
                }
        }

        // If Availibity check goes perfect the Insert records from tempStock to Stock
        $stmt2->execute([$ordID]);


        $plAccount = ($totalBill < $totalProAmount) ? 40404050 : 30303031;

        if($totalBill <= $limit){
            $authUser = $usrID;
            $status = 1;
            $stateText = "Authorized";
        }
        if($amount > $totalBill){
            $conn->rollBack();
            echo json_encode(["msg" => "large"], JSON_PRETTY_PRINT);
            exit;
        }
        // Insert Into Transactions
        $stmt3->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

        // Prepare transaction details insert
        $remark = "Order ID: $ordID, From: $perID, Total Bill: $totalBill";
        
        
        $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101011, "Cr", $totalProAmount, $remark, $entryDateTime]); //Product
        $stmt4->execute([$trnRef, $defaultCcy, $branch, $plAccount, ($totalBill > $totalProAmount ? "Cr" : "Dr"), abs($totalBill-$totalProAmount), $remark, $entryDateTime]); //Income or Expense
        if($amount < $totalBill && $amount != 0){
            $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]); //Account
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Dr", ($totalBill-$amount), $remark, $entryDateTime]); //Cash
        }elseif($amount == $totalBill){
            $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]); //Account
        }else{
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Dr", $totalBill, $remark, $entryDateTime]); //Cash
        }
        
        // Update transaction reference to created order
        $stmt5->execute([$trnRef, $ordID]);
        $stmt6->execute([$ordID]);

        $value->generateUserActivityLog(
            $user, 
            'Sale', 
            "Order ID: $ordID,\nTrnRef: $trnRef\nFrom Stakeholder: $perID,\nTotal Amount: $totalBill"
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