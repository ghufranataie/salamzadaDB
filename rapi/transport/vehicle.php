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
        get_vehicle();
        break;
    case 'POST':
        add_vehicle();
        break;
    case 'PUT':
        update_vehicle();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_vehicle() {
    global $conn;
    try {
        if (isset($_GET['vclID']) && !empty($_GET['vclID'])) {
            // Use SQL for single record
            $sql = "SELECT vcl.vclID, vcl.vclModel, vcl.vclYear, vcl.vclVinNo, vcl.vclFuelType, vcl.vclEnginPower, vcl.vclBodyType, vcl.vclPlateNo, 
                        vcl.vclRegNo, vcl.vclExpireDate, vcl.vclOwnership,
                        vcl.vclOdoMeter, vcl.vclPurchaseAmount, vcl.vclPurchaseAccount, vcl.vclPurchaseTrnRef, 
                        concat(per.perName, ' ', per.perLastName) as driver, vclDriver, vcl.vclStatus
                    from vehicles vcl
                    join employees emp on emp.empID = vcl.vclDriver
                    join personal per on per.perID = emp.empPersonal where vclID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['vclID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT vcl.vclID, vcl.vclModel, vcl.vclYear, vcl.vclVinNo, vcl.vclFuelType, vcl.vclEnginPower, vcl.vclBodyType, vcl.vclPlateNo, 
                        vcl.vclRegNo, vcl.vclExpireDate, vcl.vclOwnership,
                        vcl.vclOdoMeter, vcl.vclPurchaseAmount, vcl.vclPurchaseAccount, vcl.vclPurchaseTrnRef, 
                        concat(per.perName, ' ', per.perLastName) as driver, vclDriver, vcl.vclStatus
                    from vehicles vcl
                    join employees emp on emp.empID = vcl.vclDriver
                    join personal per on per.perID = emp.empPersonal order by vclID DESC";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
function add_vehicle() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['user'];
    $model= $data['vclModel'];
    $year = $data['vclYear'];
    $vinNo = $data['vclVinNo'];
    $fuelType = $data['vclFuelType'];
    $enginPower = $data['vclEnginPower'];
    $bodyType = $data['vclBodyType'];
    $regNo = $data['vclRegNo'];
    $expireDate = $data['vclExpireDate'];
    $plateNo = $data['vclPlateNo'];
    $odoMeter = $data['vclOdoMeter'];
    $ownership = $data['vclOwnership'];
    $purAmount = $data['vclPurchaseAmount'] ?? 0;
    $driver = $data['vclDriver'];

    // $required = [
    //     'user',
    //     'vclRegNo',
    //     'vclExpireDate',
    //     'vclModel',
    //     'vclYear',
    //     'vclVinNo',
    //     'vclFuelType',
    //     'vclBodyType',
    //     'vclPlateNo',
    //     'vclOdoMeter',
    //     'vclOwnership',
    //     'vclPurchaseAmount',
    //     'vclDriver',
    //     'vclEnginPower'
    // ];

    // foreach ($required as $key) {
    //     if (!isset($data[$key]) || $data[$key] === "") {
    //         echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
    //         exit;
    //     }
    // }

    $type = "GLAT";
    $trnStatus = 0;
    $stateText = "Pending";
    $purAccount = null;

    $vclStatus = 1;
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $authID = NULL;
    $defaultCcy = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);
    $trnRef = $value->generateTrnRef($branch, $type);
    if($purAmount <= $limit){
        $trnStatus = 1;
        $authID = $usrID;
    }

    $purReference = NULL;

    try {
        $conn->beginTransaction();

        if($purAmount > 0){
            $purAccount = "10101016";
            $remark = $model.' '.$year.', VIN: '.$vinNo.', Plate No: '.$plateNo;
            $stmt = $conn->prepare("
                INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$trnRef, $type, $trnStatus, $stateText, $usrID, $authID, $entryDateTime]);


            $stmt1 = $conn->prepare("
                INSERT INTO trnDetails 
                (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt1->execute([$trnRef, $defaultCcy, $branch, $purAccount, "Dr", $purAmount, $remark, $entryDateTime]);
            $stmt1->execute([$trnRef, $defaultCcy, $branch, "20202022", "Cr", $purAmount, $remark, $entryDateTime]);

            $purReference = $trnRef;
        }

        $stmt2 = $conn->prepare(" INSERT INTO vehicles (
            vclModel, vclYear, vclVinNo, vclFuelType, vclEnginPower, vclBodyType, vclRegNo, vclExpireDate, vclPlateNo,
            vclOdoMeter, vclOwnership, vclPurchaseAmount, vclPurchaseAccount, vclPurchaseTrnRef, vclEntryDate, vclStatus, vclDriver
        ) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$model, $year, $vinNo, $fuelType, $enginPower, $bodyType, $regNo, $expireDate, $plateNo, $odoMeter, $ownership, $purAmount,
            $purAccount, $purReference, $entryDateTime, $vclStatus, $driver
        ]);

        // if($purReference != NULL){
            
        //     $stmt = $conn->prepare("SELECT vcl.vclID, vcl.vclModel, vcl.vclYear, vcl.vclVinNo, vcl.vclFuelType, vcl.vclEnginPower, vcl.vclBodyType, vcl.vclPlateNo, 
        //             vcl.vclRegNo, vcl.vclExpireDate, vcl.vclOdoMeter, vcl.vclPurchaseAmount, 
        //             concat(per.perName, ' ', per.perLastName) as driver, vcl.vclStatus
        //         from vehicles vcl
        //         join employees emp on emp.empID = vcl.vclDriver
        //         join personal per on per.perID = emp.empPersonal
        //         where vcl.vclPurchaseTrnRef = ?");
        //     $stmt->execute([$trnRef]);
        //     $data = $stmt->fetch(PDO::FETCH_ASSOC);

        //     $stmt1 = $conn->prepare("SELECT td.trdReference AS trnReference,
        //             MAX(CASE WHEN trdDrCr = 'Dr' THEN trdAmount END) AS purchaseAmount,
        //             MAX(CASE WHEN trdDrCr = 'Dr' THEN trdCcy END) AS purchaseCurrency,
        //             MAX(CASE WHEN trdDrCr = 'Dr' THEN trdAccount END) AS debitAccount,
        //             MAX(CASE WHEN trdDrCr = 'Cr' THEN trdAccount END) AS creditAccount,
        //             mk.usrName AS maker,
        //             ck.usrName AS checker,
        //             MAX(CASE WHEN trdDrCr = 'Dr' THEN trdNarration END) AS narration, trnStatus, trnStateText
        //         FROM trnDetails td
        //         JOIN transactions tr ON tr.trnReference = td.trdReference
        //         LEFT JOIN users mk ON mk.usrID = tr.trnMaker
        //         LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
        //         WHERE td.trdReference = ?
        //         GROUP BY td.trdReference, mk.usrName, ck.usrName");
        //     $stmt1->execute([$trnRef]);
        //     $data1 = $stmt1->fetch(PDO::FETCH_ASSOC);
            
        //     echo json_encode(["msg" => "owned"], JSON_PRETTY_PRINT);
        //     $data['transaction'] = $data1;

        //     echo json_encode($data, JSON_PRETTY_PRINT);
        // }else{
        //     echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
        // }
        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
        $conn->commit();

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
function update_vehicle(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $id= $data['vclID'];
    $model= $data['vclModel'];
    $year = $data['vclYear'];
    $vinNo = $data['vclVinNo'];
    $fuelType = $data['vclFuelType'];
    $enginPower = $data['vclEnginPower'];
    $bodyType = $data['vclBodyType'];
    $regNo = $data['vclRegNo'];
    $expireDate = $data['vclExpireDate'];
    $plateNo = $data['vclPlateNo'];
    $odoMeter = $data['vclOdoMeter'];
    $driver = $data['vclDriver'];
    $status = $data['vclStatus'];

    // $required = [
    //     'vclID',
    //     'vclModel',
    //     'vclYear',
    //     'vclVinNo',
    //     'vclFuelType',
    //     'vclEnginPower',
    //     'vclBodyType',
    //     'vclRegNo',
    //     'vclExpireDate',
    //     'vclPlateNo',
    //     'vclOdoMeter',
    //     'vclDriver',
    //     'vclStatus'
    // ];

    // foreach ($required as $key) {
    //     if (!isset($data[$key]) || $data[$key] === "") {
    //         echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
    //         exit;
    //     }
    // }

    

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE vehicles SET 
            vclModel=?, 
            vclYear=?, 
            vclVinNo=?, 
            vclFuelType=?,
            vclEnginPower=?,
            vclBodyType=?,
            vclRegNo=?,
            vclExpireDate=?,
            vclPlateNo=?,
            vclOdoMeter=?,
            vclDriver=?,
            vclStatus=?
            WHERE vclID=?");
        $stmt1->execute([
            $model,
            $year,
            $vinNo,
            $fuelType,
            $enginPower,
            $bodyType,
            $regNo,
            $expireDate,
            $plateNo,
            $odoMeter,
            $driver,
            $status,
            $id
        ]);

        $conn->commit();
        echo json_encode(["msg" => "success", "branch ID" => "Vehicle with ID ($id) is updated"]);

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