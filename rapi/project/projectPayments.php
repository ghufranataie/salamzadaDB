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
        load_projectPayments();
        break;
    case 'POST':
        create_projectPayments();
        break;
    case 'PUT':
        update_projectPayments();
        break;
    case 'DELETE':
        delete_projectPayments();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_projectPayments() {
    global $conn;
    try {
        $sql1 = "SELECT pp.prpType, p.prjID, trdCcy,
                sum(case when pp.prpType = 'Entry' and td.trdDrCr = 'Cr' then td.trdAmount else 0 end) as total_project_amount
            FROM projectPayments pp
            JOIN transactions t on t.trnReference = pp.prpTrnRef
            JOIN trnDetails td on td.trdReference = t.trnReference
            JOIN projects p on p.prjID = pp.prpProjectID";
        
        $sql2 = "SELECT pp.prpType, pp.prpTrnRef, t.trnStateText, t.trnEntryDate,
                trdCcy,
                sum(case when pp.prpType = 'Payment' then td.trdAmount else 0 end)/2 as payments,
                sum(case when pp.prpType = 'Expense' then td.trdAmount else 0 end)/2 as expenses
            FROM projectPayments pp
            JOIN transactions t on t.trnReference = pp.prpTrnRef
            JOIN trnDetails td on td.trdReference = t.trnReference
            JOIN projects p on p.prjID = pp.prpProjectID";

        if (isset($_GET['prjID']) && !empty($_GET['prjID'])) {
            $prjID = $_GET['prjID'];
            $sql1 .= " WHERE pp.prpType = 'Entry' AND p.prjID = :id GROUP BY pp.prpType, p.prjID, trdCcy";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bindParam(':id', $prjID, PDO::PARAM_INT);
            $stmt1->execute();
            $data = $stmt1->fetch(PDO::FETCH_ASSOC);
            $sql2 .= " WHERE pp.prpType != 'Entry' AND p.prjID = :id GROUP BY pp.prpType, pp.prpTrnRef";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':id', $prjID, PDO::PARAM_INT);
            $stmt2->execute();
            $data2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $data ["payments"] = $data2;
            
        } else {
            $sql1 .= " WHERE pp.prpType = 'Entry' GROUP BY pp.prpType, p.prjID, trdCcy";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->execute();
            $data = $stmt1->fetchAll(PDO::FETCH_ASSOC);
            foreach($data as &$item){
                $item['payments'] = [];
                $stmt2 = $conn->prepare($sql2 . " WHERE pp.prpType != 'Entry' AND p.prjID = :id GROUP BY pp.prpType, pp.prpTrnRef");
                $stmt2->bindParam(':id', $item['prjID'], PDO::PARAM_INT);
                $stmt2->execute();
                $item['payments'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }
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

function create_projectPayments() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $pID = $data['prjID'];
    $payType = $data['prpType'];
    $account = $data['account'];
    $amount = $data['Amount'];
    $ccy = $data['currency'];
    $remark = $data['ppRemark'];

    $entryDateTime = date("Y-m-d H:i:s");
    $type = "PRJT";
    $vaultGL = 10101010;
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $limit = $value->getBranchAuthLimit($branch, $ccy);

    if($payType == "Payment"){
        if($account < 600000){
            $accStatus = $value->getAccountDetails($account, 'actStatus');
            $accCcy = $value->getAccountDetails($account, 'actCurrency');

            if($accStatus != 1){
                echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
                $conn->rollBack();
                exit;
            }
        }
    }

    if($amount <= $limit){
        $authUser = $usrID;
        $stateText = "Authorized";
        $trnStatus = 1;
    }else{
        $authUser = NULL;
        $stateText = "Pending";
        $trnStatus = 0;
    }

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt1->execute([$trnRef, $type, $trnStatus, $stateText, $usrID, $authUser, $entryDateTime]);

        $stmt2 = $conn->prepare("INSERT INTO projectPayments (prpProjectID, prpType, prpTrnRef) VALUES (?, ?, ?)");
        $stmt2->execute([$pID, $payType, $trnRef]);
        $payID = $conn->lastInsertId();

        $stmt3 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3->execute([$trnRef, $ccy, $branch, $account, ($payType == "Income" ? "Cr" : "Dr"), $amount, $remark, $entryDateTime]);
        $stmt3->execute([$trnRef, $ccy, $branch, $vaultGL, ($payType == "Income" ? "Dr" : "Cr"), $amount, $remark, $entryDateTime]);

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

function update_projectPayments(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $ppTrnRef = $data['prpTrnRef'];
    $amount = $data['Amount'];
    $ccy = $data['currency'];
    $entryDateTime = date("Y-m-d H:i:s");

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $limit = $value->getBranchAuthLimit($branch, $ccy);


    if($amount <= $limit){
        $authUser = $usrID;
        $stateText = "Authorized";
        $trnStatus = 1;
    }else{
        $authUser = NULL;
        $stateText = "Pending";
        $trnStatus = 0;
    }

    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("UPDATE trnDetails SET trdAmount = ? WHERE trdReference = ?");
        $stmt1->execute([$amount, $ppTrnRef]);

        $stmt2 = $conn->prepare("UPDATE transactions SET trnStatus = ?, trnStateText = ?, trnAuthorizer = ?, trnEntryDate = ? WHERE trnReference = ?");
        $stmt2->execute([$trnStatus, $stateText, $authUser, $entryDateTime, $ppTrnRef]);

        $conn->commit();
        echo json_encode(["msg" => "success"]);

    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile(),
            "trace" => $th->getTrace()
        ]);
    }
}

function delete_projectPayments(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $ppTrnRef = $data['prpTrnRef'];


    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];

    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("DELETE FROM trnDetails WHERE trdReference = ?");
        $stmt1->execute([$ppTrnRef]);

        $stmt2 = $conn->prepare("UPDATE transactions SET trnStatus = 1, trnStateText = 'Deleted' WHERE trnReference = ?");
        $stmt2->execute([$ppTrnRef]);

        $stmt3 = $conn->prepare("DELETE FROM projectPayments WHERE prpTrnRef = ?");
        $stmt3->execute([$ppTrnRef]);
        
        $value->generateUserActivityLog(
            $user, 
            "Project",
            "Deleted - Project Payment Reference: $ppTrnRef"
        );
        $conn->commit();
        echo json_encode(["msg" => "success"]);
    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile(),
            "trace" => $th->getTrace()
        ]);
    }
}  
?>