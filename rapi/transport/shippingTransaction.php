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
        load_shipingTransaction();
        break;
    case 'POST':
        add_shipingTransaction();
        break;
    case 'PUT':
        update_shipingTransaction();
        break;
    case 'DELETE':
        delete_shipingTransaction();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_shipingTransaction() {
    global $conn;

    $ref = $_GET['ref'];

    try {

        $stmt = $conn->prepare("SELECT shpID, concat(vclModel, '-', vclPlateNo) as vehicle,  proName, concat(perName, ' ', perLastName) as customer, 
            shpFrom, shpMovingDate, shpLoadSize, shpUnit, 
            shpTo, shpArriveDate, shpUnloadSize, 
            shpRent, (shpUnloadSize*shpRent) as total, shpStatus, shdTrnRef
            from shipping sh
            left join product pd on pd.proID = sh.shpProduct
            left join vehicles vc on vc.vclID = sh.shpVehicle
            left join personal pr on pr.perID = sh.shpCustomer
            join shippingDetails sd on sd.shdShipingID = sh.shpID
            where shdTrnRef = ?");
        $stmt->execute([$ref]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt1 = $conn->prepare("SELECT td.trdReference AS trnReference,
                MAX(CASE WHEN trdDrCr = 'Dr' THEN trdAmount END) AS amount,
                MAX(CASE WHEN trdDrCr = 'Dr' THEN trdCcy END) AS currency,
                MAX(CASE WHEN trdDrCr = 'Dr' THEN trdAccount END) AS debitAccount,
                MAX(CASE WHEN trdDrCr = 'Cr' THEN trdAccount END) AS creditAccount,
                mk.usrName AS maker,
                ck.usrName AS checker,
                MAX(CASE WHEN trdDrCr = 'Dr' THEN trdNarration END) AS narration, trnStatus, trnStateText
            FROM trnDetails td
            JOIN transactions tr ON tr.trnReference = td.trdReference
            LEFT JOIN users mk ON mk.usrID = tr.trnMaker
            LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
            WHERE td.trdReference = ?
            GROUP BY td.trdReference, mk.usrName, ck.usrName");
        $stmt1->execute([$ref]);
        $data1 = $stmt1->fetch(PDO::FETCH_ASSOC);

        $data['transaction'] = $data1;
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


function add_shipingTransaction() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $shpID = $data['shpID'];
    $account = $data['accNumber'];
    $amount = $data['amount'];
    $remark = $data['narration'];


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
        $stmt = $conn->prepare("select shpStatus from shipping where shpID = ? limit 1");
        $stmt->execute([$shpID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = $result['shpStatus'];
        if($status){
            echo json_encode(["msg" => "delivered"], JSON_PRETTY_PRINT);
            exit;
        }
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt2 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3 = $conn->prepare("INSERT INTO shippingDetails (shdShipingID, shdTrnRef) VALUES (?, ?)");

        $stmt1->execute([$trnRef, $type, $trnStatus, $stateText, $usrID, $authID, $entryDateTime]);
        $stmt2->execute([$trnRef, $defaultCcy, $branch, $account, 'Dr', $amount, $remark, $entryDateTime]);
        $stmt2->execute([$trnRef, $defaultCcy, $branch, 10101020, 'Cr', $amount, $remark, $entryDateTime]);
        $stmt3->execute([$shpID, $trnRef]);

        $conn->commit();

        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
    } 
    catch (PDOException $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

function update_shipingTransaction() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $shpID = $data['shpID'];
    $trnRef = $data['trnReference'];
    $amount = $data['amount'];
    $remark = $data['narration'];

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

    if($amount <= $limit){
        $trnStatus = 1;
        $authID = $usrID;
        $stateText = "Authorized";
    }

    try {

        $stmt = $conn->prepare("select shpStatus from shipping where shpID = ? limit 1");
        $stmt->execute([$shpID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = $result['shpStatus'];
        if($status){
            echo json_encode(["msg" => "delivered"], JSON_PRETTY_PRINT);
            exit;
        }
        
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE transactions SET trnStatus=?, trnStateText=?, trnMaker=?, trnAuthorizer=? WHERE trnReference=?");
        $stmt2 = $conn->prepare("UPDATE trnDetails SET trdAmount = ?, trdNarration=? WHERE trdReference=?");

        $stmt1->execute([$trnStatus, $stateText, $usrID, $authID, $trnRef]);
        $stmt2->execute([$amount, $remark, $trnRef]);

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

function delete_shipingTransaction() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $shpID = $data['shpID'];
    $trnRef = $data['trnReference'];

  
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
   

    try {

        $stmt = $conn->prepare("select shpStatus from shipping where shpID = ? limit 1");
        $stmt->execute([$shpID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = $result['shpStatus'];
        if($status){
            echo json_encode(["msg" => "delivered"], JSON_PRETTY_PRINT);
            exit;
        }
        
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("DELETE from shippingDetails where shdTrnRef=? and shdShipingID=?");
        $stmt2 = $conn->prepare("DELETE from trnDetails where trdReference = ?");
        $stmt3 = $conn->prepare("DELETE from transactions where trnReference = ?");

        $stmt1->execute([$trnRef, $shpID]);
        $stmt2->execute([$trnRef]);
        $stmt3->execute([$trnRef]);

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