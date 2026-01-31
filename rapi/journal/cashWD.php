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
        cash_Transaction();
        break;
    case 'PUT':
        edit_Transactions();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function cash_Transaction(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $type = $data['trnType'];
    $user = $data['usrName'];
    $account = $data['account'];
    $accCcy = $data['accCcy'];
    $amount = $data['amount'];
    $remark = $data['narration'];
    
    $entryDateTime = date("Y-m-d H:i:s");
    
    
    if(empty($account) || empty($type) || empty($user) || empty($accCcy) || empty($amount) || empty($remark)){
        echo json_encode(["msg" => "empty"], JSON_PRETTY_PRINT);
        exit;
    }

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    
    $limit = $value->getBranchAuthLimit($branch, $accCcy);
    $valueGL = 10101010;
    if($amount <= $limit){
        $authUser = $usrID;
        $stateText = "Authorized";
        $status = 1;
    }else{
        $authUser = NULL;
        $stateText = "Pending";
        $status = 0;
    }

    
    if($account < 600000){
        $accStatus = $value->getAccountDetails($account, 'actStatus');
        $acclimit = $value->checkForAccountLimit($account, $amount);
    }
    
    // echo "The Limit is $limit";
    // exit;

    try {

        $conn->beginTransaction();

        // Insert into transactions
        $stmt = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

        // Prepare transaction details insert
        $stmt1 = $conn->prepare("INSERT INTO trnDetails 
            (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        switch ($type) {

            case 'CHDP': // deposit
                if($accStatus != 1){
                    echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
                    exit;
                }
                $done1 = $stmt1->execute([$trnRef, $accCcy, $branch, $valueGL, "Dr", $amount, $remark, $entryDateTime]);
                $done2 = $stmt1->execute([$trnRef, $accCcy, $branch, $account, "Cr", $amount, $remark, $entryDateTime]);
                break;

            case 'CHWL': // withdraw
                if ($acclimit != 1) {
                    echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
                    exit;
                }elseif($accStatus != 1){
                    echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
                    exit;
                }
                $done1 = $stmt1->execute([$trnRef, $accCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]);
                $done2 = $stmt1->execute([$trnRef, $accCcy, $branch, $valueGL, "Cr", $amount, $remark, $entryDateTime]);
                break;

            case 'INCM':
                $done1 = $stmt1->execute([$trnRef, $accCcy, $branch, $valueGL, "Dr", $amount, $remark, $entryDateTime]);
                $done2 = $stmt1->execute([$trnRef, $accCcy, $branch, $account, "Cr", $amount, $remark, $entryDateTime]);
                break;

            case 'XPNS':
                $done1 = $stmt1->execute([$trnRef, $accCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]);
                $done2 = $stmt1->execute([$trnRef, $accCcy, $branch, $valueGL, "Cr", $amount, $remark, $entryDateTime]);
                break;

            case 'GLDR':
                $done1 = $stmt1->execute([$trnRef, $accCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]);
                $done2 = $stmt1->execute([$trnRef, $accCcy, $branch, $valueGL, "Cr", $amount, $remark, $entryDateTime]);
                break;

            case 'GLCR':
                $done1 = $stmt1->execute([$trnRef, $accCcy, $branch, $valueGL, "Dr", $amount, $remark, $entryDateTime]);
                $done2 = $stmt1->execute([$trnRef, $accCcy, $branch, $account, "Cr", $amount, $remark, $entryDateTime]);
                break;

            default:
                echo json_encode(["msg" => "failed type"], JSON_PRETTY_PRINT);
                return;
        }
        $conn->commit();
        
        if($done1 || $done2){

            $value->generateUserActivityLog(
                $user, 
                $type,
                "$trnRef,\n$account,\n$amount-$accCcy"
            );
            
            $printResult = $value->printCashTransaction($trnRef);
            echo json_encode($printResult, JSON_PRETTY_PRINT);
            // echo json_encode(["msg" => "success"]);

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


function edit_Transactions(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $ref = $data['reference'];
    $user = $data['usrName'];
    $amount = $data['amount'];
    $accCcy = $data['accCcy'];
    $remark = $data['narration'];
    $entryDateTime = date("Y-m-d H:i:s");
    


    if(empty($ref) || empty($user) || empty($accCcy) || empty($amount) || empty($remark)){
        echo json_encode(["msg" => "empty"], JSON_PRETTY_PRINT);
        exit;
    }

    $usrDetails = $value->getUserDetails($user);
    $checkerID = $usrDetails['usrID'];
    $branch = $usrDetails['usrBranch'];

    $limit = $value->getBranchAuthLimit($branch, $accCcy);

    if($amount <= $limit){
        $authUser = $checkerID;
        $status =1;
        $statusText = "Authorized";
    }else{
        $authUser = NULL;
        $status = 0;
        $statusText = "Pending";
    }

    try {
        $conn->beginTransaction();

        $stmt0 = $conn->prepare("SELECT * from transactions where trnReference = ?");
        $stmt0->execute([$ref]);
        $row = $stmt0->fetch(PDO::FETCH_ASSOC);
        if($row){
            $makerID = $row['trnMaker'];
            $trnStatus = $row['trnStatus'];
            $trnStatusText = $row['trnStateText'];
        }
        if($makerID != $checkerID){
            echo json_encode(["msg" => "invalid user"], JSON_PRETTY_PRINT);
            exit;
        }
        if($trnStatus != 0){
            echo json_encode(["msg" => "invalid action"], JSON_PRETTY_PRINT);
            exit;
        }
        if($trnStatusText == 'Reversed' || $trnStatusText == 'Authorized'){
            echo json_encode(["msg" => "invalid action"], JSON_PRETTY_PRINT);
            exit;
        }

        $stmt1 = $conn->prepare("update transactions set trnStatus=?, trnStateText=?, trnAuthorizer=?, trnEntryDate=? where trnReference=?");
        $done1 = $stmt1->execute([$status, $statusText, $authUser, $entryDateTime, $ref]);

        $stmt2 = $conn->prepare("update trnDetails set trdAmount=?, trdNarration=? where trdReference=?");
        $done2 = $stmt2->execute([$amount, $remark, $ref]);

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