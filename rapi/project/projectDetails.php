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
        load_projectDetails();
        break;
    case 'POST':
        create_projectDetails();
        break;
    case 'PUT':
        update_projectDetails();
        break;
    case 'DELETE':
        delete_projectDetails();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_projectDetails() {
    global $conn;
    try {
        $sql = "SELECT
                pd.pjdID,
                p.prjID, p.prjName,
                pd.pjdServices as srvID, ps.srvName, 
                pd.pjdQuantity, pd.pjdPricePerQty, (pd.pjdQuantity * pd.pjdPricePerQty) as total,
                pp.prpTrnRef, pp.prpID as paymentID, pd.pjdRemark, pd.pjdStatus
            FROM projectDetails pd
            JOIN projects p on p.prjID = pd.pjdProject
            JOIN projectServices ps on ps.srvID = pd.pjdServices
            join projectPayments pp on pp.prpID = pd.pjdPaymentId";
        if (isset($_GET['prjID']) && !empty($_GET['prjID'])) {
            $prjID = $_GET['prjID'];
            $sql .= " WHERE pjdProject = :id ORDER BY pjdID ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $prjID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchALL(PDO::FETCH_ASSOC);
        } else {
            $sql .= " ORDER BY pjdID ASC";
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

function create_projectDetails() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $pID = $data['prjID'];
    $sID = $data['srvID'];
    $qty = $data['pjdQuantity'];
    $price = $data['pjdPricePerQty'];
    $narration = $data['pjdRemark'];
    $status = 0;
    $entryDateTime = date("Y-m-d H:i:s");

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO projectDetails (pjdProject, pjdServices, pjdQuantity, pjdPricePerQty, pjdRemark, pjdStatus) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$pID, $sID, $qty, $price, $narration, $status]);
        $pjdID = $conn->lastInsertId();

        if($price > 0){
            $type = "PRJT";
            $userResult = $value->getUserDetails($user);
            $branch = $userResult['usrBranch'];
            $usrID = $userResult['usrID'];
            $trnRef = $value->generateTrnRef($branch, $type);

            $stmt0 = $conn->prepare("SELECT prjOwnerAccount from projects WHERE prjID = :id");
            $stmt0->bindParam(':id', $pID, PDO::PARAM_INT);
            $stmt0->execute();
            $project = $stmt0->fetch(PDO::FETCH_ASSOC);
            $account = $project['prjOwnerAccount'];

            if($account < 600000){
                $accStatus = $value->getAccountDetails($account, 'actStatus');
                $accCcy = $value->getAccountDetails($account, 'actCurrency');
                $acclimit = $value->checkForAccountLimit($account, $price);
            }
            if ($acclimit != 1) {
                echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
                $conn->rollBack();
                exit;
            }elseif($accStatus != 1){
                echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
                $conn->rollBack();
                exit;
            }

            $limit = $value->getBranchAuthLimit($branch, $accCcy);
            $incomGL = 20202027;
            if($price <= $limit){
                $authUser = $usrID;
                $stateText = "Authorized";
                $trnStatus = 1;
            }else{
                $authUser = NULL;
                $stateText = "Pending";
                $trnStatus = 0;
            }

            $remark = "Project Amount Posted from Service: $sID, Quantity: $qty, Project ID: $pID";

            $stmt1 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt1->execute([$trnRef, $type, $trnStatus, $stateText, $usrID, $authUser, $entryDateTime]);

            $stmt2 = $conn->prepare("INSERT INTO projectPayments (prpProjectID, prpType, prpTrnRef) VALUES (?, ?, ?)");
            $stmt2->execute([$pID, 'Entry', $trnRef]);
            $payID = $conn->lastInsertId();

            $stmt3 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt3->execute([$trnRef, $accCcy, $branch, $account, 'Dr', $price*$qty, $remark, $entryDateTime]);
            $stmt3->execute([$trnRef, $accCcy, $branch, $incomGL, 'Cr', $price*$qty, $remark, $entryDateTime]);

            $stmt4 = $conn->prepare("UPDATE projectDetails SET pjdPaymentID = ? WHERE pjdID = ?");
            $stmt4->execute([$payID, $pjdID]);

            $conn->commit();
            echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
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

function update_projectDetails(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $pdID = $data['pjdID'];
    $pID = $data['prjID'];
    $sID = $data['srvID'];
    $qty = $data['pjdQuantity'];
    $price = $data['pjdPricePerQty'];
    $narration = $data['pjdRemark'];
    $status = 0;
    $entryDateTime = date("Y-m-d H:i:s");

    try {
        $conn->beginTransaction();
        $stmt0 = $conn->prepare("SELECT * from projectDetails pd
            JOIN projectPayments pp on pp.prpID = pd.pjdPaymentID
            WHERE pjdID = ?");
        $stmt0->execute([$pdID]);
        $project = $stmt0->fetch(PDO::FETCH_ASSOC);
        if(!$project){
            echo json_encode(["msg" => "not found"], JSON_PRETTY_PRINT);
            exit;
        }
        $oldPrice = $project['pjdPricePerQty'];
        $trnRef = $project['prpTrnRef'];
        $qty = $project['pjdQuantity'];
        if($oldPrice != $price){

            $userResult = $value->getUserDetails($user);
            $branch = $userResult['usrBranch'];
            $usrID = $userResult['usrID'];

            $stmt1 = $conn->prepare("SELECT prjOwnerAccount from projects WHERE prjID = :id");
            $stmt1->bindParam(':id', $pID, PDO::PARAM_INT);
            $stmt1->execute();
            $project = $stmt1->fetch(PDO::FETCH_ASSOC);
            $account = $project['prjOwnerAccount'];

            if($account < 600000){
                $accStatus = $value->getAccountDetails($account, 'actStatus');
                $accCcy = $value->getAccountDetails($account, 'actCurrency');
                $acclimit = $value->checkForAccountLimit($account, $price);
            }
            if ($acclimit != 1) {
                echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
                $conn->rollBack();
                exit;
            }elseif($accStatus != 1){
                echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
                $conn->rollBack();
                exit;
            }

            $limit = $value->getBranchAuthLimit($branch, $accCcy);
            if($price <= $limit){
                $authUser = $usrID;
                $stateText = "Authorized";
                $trnStatus = 1;
            }else{
                $authUser = NULL;
                $stateText = "Pending";
                $trnStatus = 0;
            }
            $stmt2 = $conn->prepare("UPDATE trnDetails SET trdAmount = ? WHERE trdReference = ?");
            $stmt2->execute([$price*$qty, $trnRef]);

            $stmt3 = $conn->prepare("UPDATE transactions SET trnStatus = ?, trnStateText = ?, trnAuthorizer = ?, trnEntryDate = ? WHERE trnReference = ?");
            $stmt3->execute([$trnStatus, $stateText, $authUser, $entryDateTime, $trnRef]);
        }

        $stmt = $conn->prepare("UPDATE projectDetails SET pjdQuantity = ?, pjdPricePerQty = ?, pjdRemark = ? WHERE pjdID = ?");
        $stmt->execute([$qty, $price, $narration, $pdID]);

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

function delete_projectDetails(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $pdID = $data['pjdID'];

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT * from projectDetails pd
            JOIN projectPayments pp on pp.prpID = pd.pjdPaymentID
            WHERE pjdID = ?");
        $stmt->execute([$pdID]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$project){
            echo json_encode(["msg" => "not found"], JSON_PRETTY_PRINT);
            $conn->rollBack();
            exit;
        }else{
            $trnRef = $project['prpTrnRef'];

            $stmt1 = $conn->prepare("DELETE FROM projectDetails WHERE pjdID = ?");
            $stmt1->execute([$project['pjdID']]);

            $stmt2 = $conn->prepare("DELETE FROM projectPayments WHERE prpID = ?");
            $stmt2->execute([$project['prpID']]);

            $stmt3 = $conn->prepare("DELETE FROM trnDetails WHERE trdReference = ?");
            $stmt3->execute([$project['prpTrnRef']]);

            $stmt4 = $conn->prepare("UPDATE transactions SET trnStatus = 1, trnStateText = 'Deleted' WHERE trnReference = ?");
            $stmt4->execute([$project['prpTrnRef']]);
        }
        $value->generateUserActivityLog(
            $user, 
            "Project",
            "Deleted - projectDetailID: $pdID with Transaction Reference: $trnRef"
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