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
        get_fundTransfer();
        break;
    case 'POST':
        add_fundTransfer();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_fundTransfer() {
    global $conn;

    $ref = $_GET['ref'];

    $stmt = $conn->prepare("select count(*) as total from trnDetails where trdReference = ?");
    $stmt->execute([$ref]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result['total'];
    if($total <= 2){
        $type = "single";
    }else{
        $type = "double";
    }


    try {
        $stmt1 = $conn->prepare("select trnReference, trnType, trnStatus, mk.usrName as maker, ck.usrName as checker, trdNarration, trdBranch, trnStateText, trnEntryDate 
			from transactions tr
            join users mk on mk.usrID = tr.trnMaker
            join trnDetails td on td.trdReference = tr.trnReference
            left join users ck on ck.usrID = tr.trnAuthorizer
            where trnReference =?");
        $stmt1->execute([$ref]);
        $tran = $stmt1->fetch(PDO::FETCH_ASSOC);
        $tran['type'] = $type;

        $stmt2 = $conn->prepare("select trdAccount, accName, trdCcy, trdAmount, trdDrCr 
            from trnDetails td
            join accounts acc on acc.accNumber = td.trdAccount
            where trdDrCr='Dr' and trdReference=?");
        $stmt2->execute([$ref]);
        $record1 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $conn->prepare("select trdAccount, accName, trdCcy, trdAmount, trdDrCr 
            from trnDetails td
            join accounts acc on acc.accNumber = td.trdAccount
            where trdDrCr='Cr' and trdReference=?");
        $stmt2->execute([$ref]);
        $record2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $tran['debit'] = $record1;
        $tran['credit'] = $record2;

       
        echo json_encode($tran, JSON_PRETTY_PRINT);
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

function add_fundTransfer(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $type = "ATAT";
    $status = 0;
    $stateText = "Pending";
    
    $user = $data['usrName'];
    $fromAcc = $data['fromAccount'];
    $fromAccCcy = $data['fromAccCcy'];
    $toAccCcy = $data['toAccCcy'];
    $toAcc = $data['toAccount'];
    $amount = $data['amount'];
    $remark = $data['narration'];
    
    $entgryDateTime = date("Y-m-d H:i:s");
    
    

    if(empty($user) || empty($fromAcc) || empty($toAcc) || empty($amount) || empty($remark)){
        echo json_encode(["msg" => "empty"], JSON_PRETTY_PRINT);
        exit;
    }
    if($fromAcc == $toAcc){
        echo json_encode(["msg" => "same account"], JSON_PRETTY_PRINT);
        exit;
    }

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $authUser = null;
    $trnRef = $value->generateTrnRef($branch, $type);
    
    
    $limit = $value->getBranchAuthLimit($branch, $fromAccCcy);
    if($amount <= $limit){
        $authUser = $usrID;
        $stateText = "Authorized";
        $status = 1;
    }else{
        $authUser = NULL;
        $stateText = "Pending";
        $status = 0;
    }

    $fromAccStatus = $value->getAccountDetails($fromAcc, 'actStatus');
    $toAccStatus = $value->getAccountDetails($toAcc, 'actStatus');
    if($fromAccStatus == 0 || $toAccStatus == 0){
        echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
        exit;
    }

    $fromAcclimit = $value->checkForAccountLimit($fromAcc, $amount);
    if($fromAcclimit != 1){
        echo json_encode(["msg" => "no limit"], JSON_PRETTY_PRINT);
        exit;
    }


    if($fromAccCcy != $toAccCcy){
        echo json_encode(["msg" => "currency unmatch"], JSON_PRETTY_PRINT);
        exit;
    }


    try {

        $conn->beginTransaction();

        // Insert into transactions
        $stmt = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entgryDateTime]);

        // Prepare transaction details insert
        $stmt1 = $conn->prepare("INSERT INTO trnDetails 
            (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $done1 = $stmt1->execute([$trnRef, $fromAccCcy, $branch, $fromAcc, "Dr", $amount, $remark, $entgryDateTime]);
        $done2 = $stmt1->execute([$trnRef, $toAccCcy, $branch, $toAcc, "Cr", $amount, $remark, $entgryDateTime]);

        $conn->commit();

        if($done1 || $done2){
            echo json_encode(["msg" => "success"]);
        }else{
            echo json_encode(["msg" => "failed"]);
        }

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