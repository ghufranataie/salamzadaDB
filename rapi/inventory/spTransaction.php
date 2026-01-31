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
        load_salePurchase();
        break;
    case 'POST':
        add_salePurchase();
        break;
    case 'PUT':
        update_salePurchase();
        break;
    case 'DELETE':
        delete_salePurchase();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_salePurchase() {
    global $conn;

    $ref = $_GET['ref'];

    try {

        $stmt = $conn->prepare("SELECT tr.trnReference, tr.trnType, tp.trntName, tr.trnStatus, tr.trnStateText, mk.usrName as maker, ck.usrName as checker, tr.trnEntryDate,
            round((sum(trdAmount)/2),2) as total_bill,
            max(case when td.trdID then cy.ccySymbol end) as ccy_symbol,
            max(case when td.trdID then td.trdCcy end) as ccy,
            max(case when td.trdID then cy.ccyName end) as ccy_name,
            max(case when td.trdID then br.brcName end) as branch,
            max(case when td.trdID then td.trdNarration end) as remark
            From transactions tr
            left join users mk on mk.usrID = tr.trnMaker
            left join users ck on ck.usrID = tr.trnAuthorizer
            left join trnTypes tp on tp.trntCode = tr.trnType
            left join trnDetails td on td.trdReference = tr.trnReference
            left join currency cy on cy.ccyCode = td.trdCcy
            left join branch br on br.brcID = td.trdBranch
            where trnReference = ?");
        $stmt->execute([$ref]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt1 = $conn->prepare("SELECT ac.accName as account_name, td.trdAccount as account_number, td.trdAmount as amount, 
            case when trdDrCr='Dr' then 'Debit' else 'Credit' end as debit_credit
            from trnDetails td
            join accounts ac on ac.accNumber = td.trdAccount
            where trdReference = ?");
        $stmt1->execute([$ref]);
        $data1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $data['records'] = $data1;

        $stmt2 = $conn->prepare("SELECT sg.stgName as storage_name, pr.proName as product_name, sk.stkQuantity as quantity,
            case when sk.stkEntryType='OUT' then sk.stkSalePrice else sk.stkPurPrice end as unit_price,
            case when sk.stkEntryType='OUT' then (sk.stkQuantity * sk.stkSalePrice) else (sk.stkQuantity * sk.stkPurPrice) end as total_price
            from stock sk
            join storages sg on sg.stgID = sk.stkStorage
            join product pr on pr.proID = sk.stkProduct
            join orders od on od.ordID = sk.stkOrder
            where od.ordTrnRef = ?");
        $stmt2->execute([$ref]);
        $data2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $data['bill'] = $data2;
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


function add_salePurchase() {
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

function update_salePurchase() {
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

function delete_salePurchase() {
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