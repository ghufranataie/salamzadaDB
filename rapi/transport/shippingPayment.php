
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
        add_paymentOption();
        break;
    case 'PUT':
        update_paymentOption();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function add_paymentOption() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $shpID = $data['shpID'];
    $pType = $data['pType'];
    $cash = $data['cashAmount'];
    $card = $data['cardAmount'];
    $acc = $data['account'];
    
    if($pType == "dual"){
        $amount = ($cash + $card);
    }elseif($pType == "cash"){
        $amount = $cash;
    }else{
        $amount = $card;
    }

    if(!empty($acc)){
        $accountLimit = $value->checkForAccountLimit($acc, $card);
        $accStatus = $value->getAccountDetails($acc, 'actStatus');
        if ($accountLimit != 1) {
            echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
            exit;
        }elseif($accStatus != 1){
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    $type = "TRPT";
    $trnStatus = 0;
    $stateText = "Pending";
    $purAccount = null;

    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $authID = NULL;
    $defaultCcy = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);
    $trnRef = $value->generateTrnRef($branch, $type);
    if($amount <= $limit){
        $trnStatus = 1;
        $authID = $usrID;
        $stateText = "Authorized";
    }

    try {
        $stmt = $conn->prepare("select concat(shpID, ', ', shpFrom, '-', shpTo, ', ', cast(shpArriveDate as Date), ', ', shpUnloadSize, '/', shpUnit, ', @', round(shpRent, 2),'/', shpUnit, ' Total: ', round((shpUnloadSize*shpRent), 2) ) as narration, 
        shpStatus from shipping where shpID = ? limit 1");
        $stmt->execute([$shpID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = $result['shpStatus'];
        $remark = $result['narration'];
        if($status){
            echo json_encode(["msg" => "delivered"], JSON_PRETTY_PRINT);
            exit;
        }
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt2 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3 = $conn->prepare("INSERT INTO shippingDetails (shdShipingID, shdTrnRef) VALUES (?, ?)");

        $stmt1->execute([$trnRef, $type, $trnStatus, $stateText, $usrID, $authID, $entryDateTime]);

        $stmt2->execute([$trnRef, $defaultCcy, $branch, 30303033, 'Cr', $amount, $remark, $entryDateTime]);
        if($pType == 'dual'){
            $stmt2->execute([$trnRef, $defaultCcy, $branch, 10101010, 'Dr', $cash, $remark, $entryDateTime]);
            $stmt2->execute([$trnRef, $defaultCcy, $branch, $acc, 'Dr', $card, $remark, $entryDateTime]);
        }elseif($pType == 'card'){
            $stmt2->execute([$trnRef, $defaultCcy, $branch, $acc, 'Dr', $card, $remark, $entryDateTime]);
        }else{
            $stmt2->execute([$trnRef, $defaultCcy, $branch, 10101010, 'Dr', $cash, $remark, $entryDateTime]);
        }
        
        
        $stmt3->execute([$shpID, $trnRef]);

        $conn->commit();

        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
    } 
    catch (PDOException $th) {
        // $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

function update_paymentOption() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $ref = $data['trdReference'];
    $shpID = $data['shpID'];
    $pType = $data['pType'];
    $cash = $data['cashAmount'];
    $card = $data['cardAmount'];
    $acc = $data['account'];

    if($pType == "dual"){
        $amount = ($cash + $card);
    }elseif($pType == "cash"){
        $amount = $cash;
    }else{
        $amount = $card;
    }

    if(!empty($acc)){
        $accountLimit = $value->checkForAccountLimit($acc, $card);
        $accStatus = $value->getAccountDetails($acc, 'actStatus');
        if ($accountLimit != 1) {
            echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
            exit;
        }elseif($accStatus != 1){
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            exit;
        }
    }

    $trnStatus = 0;
    $stateText = "Pending";
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $authID = NULL;
    $defaultCcy = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);

    if($amount <= $limit){
        $trnStatus = 1;
        $authID = $usrID;
        $stateText = "Authorized";
    }

    try {

        $stmt = $conn->prepare("select concat(shpID, ', ', shpFrom, '-', shpTo, ', ', cast(shpArriveDate as Date), ', ', shpUnloadSize, '/', shpUnit, ', @', round(shpRent, 2),'/', shpUnit, ' Total: ', round((shpUnloadSize*shpRent), 2) ) as narration, 
        shpStatus from shipping where shpID = ? limit 1");
        $stmt->execute([$shpID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = $result['shpStatus'];
        $remark = $result['narration'];
        if($status){
            echo json_encode(["msg" => "delivered"], JSON_PRETTY_PRINT);
            exit;
        }
        
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE transactions SET trnStatus=?, trnStateText=?, trnMaker=?, trnAuthorizer=? WHERE trnReference=?");
        $stmt1->execute([$trnStatus, $stateText, $usrID, $authID, $ref]);

        $stmt3 = $conn->prepare("DELETE from trnDetails where trdReference=?");
        $stmt3->execute([$ref]);

        
        $stmt2 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt2->execute([$ref, $defaultCcy, $branch, 30303033, 'Cr', $amount, $remark, $entryDateTime]);
        if($pType == "dual"){
            $stmt2->execute([$ref, $defaultCcy, $branch, 10101010, 'Dr', $cash, $remark, $entryDateTime]);
            $stmt2->execute([$ref, $defaultCcy, $branch, $acc, 'Dr', $card, $remark, $entryDateTime]);
        }elseif($pType == "cash"){
            $stmt2->execute([$ref, $defaultCcy, $branch, 10101010, 'Dr', $cash, $remark, $entryDateTime]);
        }else{
            $stmt2->execute([$ref, $defaultCcy, $branch, $acc, 'Dr', $card, $remark, $entryDateTime]);
        }

        

        $conn->commit();

        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
    } 
    catch (PDOException $th) {
        // $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}
?>