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
        load_shippings();
        break;
    case 'POST':
        create_shipping();
        break;
    case 'PUT':
        update_shipping();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_shippings() {
    global $conn;
    try {
        if (isset($_GET['shpID']) && !empty($_GET['shpID'])) {
            $shpID = $_GET['shpID'];
            // Use SQL for single record
            $sql = "SELECT 
                shpID, concat(vclModel, '-', vclPlateNo) as vehicle, vclID,  proName, proID, perID, concat(perName, ' ', perLastName) as customer, 
                shpFrom, shpMovingDate, shpLoadSize, shpUnit, 
                shpTo, shpArriveDate, shpUnloadSize, 
                shpRent, (shpUnloadSize*shpRent) as total, shpStatus, shpRemark
                from shipping sh
                left join product pd on pd.proID = sh.shpProduct
                left join vehicles vc on vc.vclID = sh.shpVehicle
                left join personal pr on pr.perID = sh.shpCustomer where shpID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $shpID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt0 = $conn->prepare("SELECT max(trdReference) as ref from trnDetails 
            join shippingDetails sd on sd.shdTrnRef = trnDetails.trdReference
            where trdAccount = 30303033 and shdShipingID = ?");
            $stmt0->execute([$shpID]);
            $row = $stmt0->fetch(PDO::FETCH_ASSOC);
            $ref = $row['ref'];

            $stmt1 = $conn->prepare("SELECT trdReference,
                MAX(case when trdAccount = 10101010 then trdAmount end) as cashAmount,
                MAX(case when trdAccount < 10000000 then trdAmount end) as cardAmount,
                MAX(case when trdAccount < 10000000 then trdAccount end) as account_customer,
                MAX(CASE WHEN c.accNumber < 10000000 THEN c.accName END) AS accName
            from trnDetails td
            JOIN accounts c ON c.accNumber = td.trdAccount
            join shippingDetails sd on sd.shdTrnRef = td.trdReference
            join shipping sh on sh.shpID = sd.shdShipingID
            where shpID = ? and sd.shdTrnRef = ?
            group by trdReference;");
            $stmt1->execute([$shpID, $ref]);
            $payment = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("SELECT td.trdReference,
                MAX(CASE WHEN td.trdDrCr = 'Dr' THEN td.trdAccount END) AS accNumber,
                MAX(CASE WHEN td.trdDrCr = 'Dr' THEN ac.accName END) AS accName,
                SUM(CASE WHEN td.trdDrCr = 'Dr' THEN td.trdAmount ELSE 0 END) AS amount,
                MAX(CASE WHEN td.trdDrCr = 'Dr' THEN td.trdCcy END) AS currency,
                MAX(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdNarration END) AS narration
            FROM shippingDetails sd
            JOIN transactions tr ON tr.trnReference = sd.shdTrnRef
            JOIN trnDetails td ON td.trdReference = tr.trnReference
            join accounts ac on ac.accNumber = td.trdAccount
            where sd.shdShipingID = ?
            GROUP BY td.trdReference
            HAVING accNumber  NOT IN (30303033, 10101021, 10101010)");
            $stmt2->execute([$_GET['shpID']]);
            $expenses = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $data['pyment'] = $payment;
            $data['expenses'] = $expenses;

        
        } else {
            // Use default SQL for all records
            $sql = "SELECT 
                shpID, concat(vclModel, '-', vclPlateNo) as vehicle, vclID,  proName, proID, perID, concat(perName, ' ', perLastName) as customer, 
                shpFrom, shpMovingDate, shpLoadSize, shpUnit, 
                shpTo, shpArriveDate, shpUnloadSize, 
                shpRent, (shpUnloadSize*shpRent) as total, shpStatus, shpRemark
                from shipping sh
                left join product pd on pd.proID = sh.shpProduct
                left join vehicles vc on vc.vclID = sh.shpVehicle
                left join personal pr on pr.perID = sh.shpCustomer 
                where shpStatus = 0
                    OR (
                        shpStatus = 1
                        AND shpArriveDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                    ) 
                order by shpID DESC";
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

function create_shipping() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $product = $data['shpProduct'];
    $vehicle = $data['shpVehicle'];
    $customer = $data['shpCustomer'];
    $shipFrom = $data['shpFrom'];
    $moveDate = $data['shpMovingDate'];
    $shipTo = $data['shpTo'];
    $toDate = $data['shpArriveDate'];
    $loadSize = (float) $data['shpLoadSize'];
    $unloadSize = (float) $data['shpUnloadSize'];
    $loadUnit = $data['shpUnit'];
    $rentPerUnit = (float) $data['shpRent'];
    $remark = $data['shpRemark'];
    $advance = $data['shpAdvance'];
    $status = 0;
    $entryDateTime = date("Y-m-d H:i:s");


    // $required = [
    //     'shpProduct', 'shpVehicle', 'shpCustomer', 'shpFrom', 'shpMovingDate', 'shpTo',
    //     'shpArriveDate', 'shpLoadSize', 'shpUnloadSize', 'shpUnit', 'shpRent'
    // ];

    // foreach ($required as $key) {
    //     if (!isset($data[$key]) || $data[$key] === "") {
    //         echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
    //         exit;
    //     }
    // }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            INSERT INTO shipping 
            (shpProduct, shpVehicle, shpCustomer, shpFrom, shpMovingDate, shpTo, shpArriveDate, shpLoadSize, shpUnloadSize, shpUnit, shpRent, shpRemark, shpStatus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$product, $vehicle, $customer, $shipFrom, $moveDate, $shipTo, $toDate, $loadSize, $unloadSize, $loadUnit, $rentPerUnit, $remark, $status]);
        $shpID = $conn->lastInsertId();

        $userResult = $value->getUserDetails($user);
        $branch = $userResult['usrBranch'];
        $usrID = $userResult['usrID'];
        $type = "TRPT";
        $defaultCcy = $value->getCompanyAttributes('comLocalCcy');
        $unloadDate = date('Y-m-d', strtotime($toDate));
        $nar = "$shpID, $shipFrom-$shipTo, $unloadDate, $unloadSize/$loadUnit @$rentPerUnit";
        $limit = $value->getBranchAuthLimit($branch, $defaultCcy);

        if($advance > 0){
            $trnRef = $value->generateTrnRef($branch, $type);
            $authUser = NULL;
            $trnStatus = 0;
            $trnStatusText = "Pending";

            if($advance <= $limit){
                $authUser = $usrID;
                $trnStatus = 1;
                $trnStatusText = "Authorized";
            }

            $stmt1 = $conn->prepare("
            INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt1->execute([$trnRef, $type, $trnStatus, $trnStatusText, $usrID, $authUser, $entryDateTime]);

            $stmt2 = $conn->prepare("
            INSERT INTO trnDetails 
            (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt2->execute([$trnRef, $defaultCcy, $branch, 10101020, "Dr", $advance, $nar, $entryDateTime]);
            $stmt2->execute([$trnRef, $defaultCcy, $branch, 10101010, "Cr", $advance, $nar, $entryDateTime]);

            $stmt3 = $conn->prepare("INSERT into shippingDetails (shdShipingID, shdTrnRef) values (?, ?)");
            $stmt3->execute([$shpID, $trnRef]);
        }

        // if(!empty($unloadSize) && !empty($rentPerUnit)){
        //     $trnRef2 = $value->generateTrnRef($branch, $type);
        //     $totalRent = ($unloadSize * $rentPerUnit);
        //     $authUser = NULL;
        //     $trnStatus = 0;
        //     $trnStatusText = "Pending";
        //     if($totalRent <= $limit){
        //         $authUser = $usrID;
        //         $trnStatus = 1;
        //         $trnStatusText = "Authorized";
        //     }

        //     $stmt4 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate)
        //     VALUES (?, ?, ?, ?, ?, ?, ?)");
        //     $stmt4->execute([$trnRef2, $type, $trnStatus, $trnStatusText, $usrID, $authUser, $entryDateTime]);

        //     $stmt5 = $conn->prepare("
        //     INSERT INTO trnDetails 
        //     (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)
        //     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        //     $stmt5->execute([$trnRef2, $defaultCcy, $branch, 30303033, "Cr", $totalRent, $nar, $entryDateTime]);
        //     $stmt5->execute([$trnRef2, $defaultCcy, $branch, 10101021, "Dr", $totalRent, $nar, $entryDateTime]);

        //     $stmt6 = $conn->prepare("INSERT into shippingDetails (shdShipingID, shdTrnRef) values (?, ?)");
        //     $stmt6->execute([$shpID, $trnRef2]);

        // }
         
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

function update_shipping(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $shpID = $data['shpID'];
    $product = $data['shpProduct'];
    $vehicle = $data['shpVehicle'];
    $customer = $data['shpCustomer'];
    $shipFrom = $data['shpFrom'];
    $moveDate = $data['shpMovingDate'];
    $shipTo = $data['shpTo'];
    $toDate = $data['shpArriveDate'];
    $loadSize = (float) $data['shpLoadSize'];
    $unloadSize = (float) $data['shpUnloadSize'];
    $loadUnit = $data['shpUnit'];
    $rentPerUnit = (float) $data['shpRent'];
    $remark = $data['shpRemark'];
    // $advance = $data['shpAdvance'];
    $shpStatus = $data['shpStatus'];
    $entryDateTime = date("Y-m-d H:i:s");

    $totalIncome = ($unloadSize * $rentPerUnit);

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $defaultCcy = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);
    $trnStatus = 0;
    $trnStatusText = "Pending";
    $type = "TRPT";
    $unloadDate = date('Y-m-d', strtotime($toDate));
    $nar = "$shpID, $shipFrom-$shipTo, $unloadDate, $unloadSize/$loadUnit @$rentPerUnit";
    $authUser = NULL;

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE shipping SET 
            shpProduct=?, shpVehicle=?, shpCustomer=?, shpFrom=?, shpMovingDate=?, shpTo=?, shpArriveDate=?, shpLoadSize=?,
            shpUnloadSize=?, shpUnit=?, shpRent=?, shpStatus=?, shpRemark=?  WHERE shpID=?");
        $stmt->execute([
            $product, $vehicle, $customer, $shipFrom, $moveDate, $shipTo, $toDate, $loadSize, $unloadSize, $loadUnit, $rentPerUnit, $shpStatus, $remark, $shpID
        ]);

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

function getShippingRef($account, $shpID, $colName){
    global $conn;
    $sql = "SELECT * from shippingDetails sd
        join shipping sh on sh.shpID = sd.shdShipingID
        join transactions tr on tr.trnReference = sd.shdTrnRef
        join trnDetails td on td.trdReference = tr.trnReference
        where trdAccount = :acc AND shdShipingID = :shp limit 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":acc", $account);
    $stmt->bindParam(":shp", $shpID);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row){
        return $row[$colName];
    }else{
        return NULL;
    }
}
?>