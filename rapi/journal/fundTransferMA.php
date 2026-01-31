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
        add_fundTransfer();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function add_fundTransfer(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    // echo "The data is:". json_encode($data);


    $user = $data['usrName'];
    $records = $data['records'];
    
    $type = "ATAT";
    $status = 0;
    $stateText = "Pending";
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    
    $trnRef = $value->generateTrnRef($branch, $type);
    $currencies = [];

    // Check for Account Limit, Account Status and if there is same currency
    foreach($data['records'] as $record){
        $debit = $record['debit'];
        $account = $record['account'];
        
        $currencies[$record['ccy']] = true;

        if($debit > 0){
            $limit = $value->checkForAccountLimit($account, $debit);
            if($limit != 1){
                echo json_encode(["msg" => "no limit", "account" => $account], JSON_PRETTY_PRINT);
                exit;
            }
        }
        if($account < 10000000){
            $accStatus = $value->getAccountDetails($account, 'actStatus');
            if($accStatus == 0){
                echo json_encode(["msg" => "blocked", "account" => $account], JSON_PRETTY_PRINT);
                exit;
            }
        }

        if (count($currencies) > 1) {
            echo json_encode(["msg" => "dif ccy"], JSON_PRETTY_PRINT);
            break;
        }
    }


    try {

        $conn->beginTransaction();

        // Insert into transactions
        $stmt = $conn->prepare("
            INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnEntryDate)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$trnRef, $type, $status, $stateText, $usrID, $entryDateTime]);

        // Prepare transaction details insert
        $stmt1 = $conn->prepare("
            INSERT INTO trnDetails 
            (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach($data['records'] as $rec){
            $acc = $rec['account'];
            $ccy = $rec['ccy'];
            $debit = $rec['debit'];
            $credit = $rec['credit'];
            $remark = $rec['narration'];
            if($rec['debit'] > 0){
                $stmt1->execute([$trnRef, $ccy, $branch, $acc, "Dr", $debit, $remark, $entryDateTime]);
            }else{
                $stmt1->execute([$trnRef, $ccy, $branch, $acc, "Cr", $credit, $remark, $entryDateTime]);
            }
        }
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