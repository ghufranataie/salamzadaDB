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
        reverse_transaction();
        break;
    case 'PUT':
        auth_transaction();
        break;
    case 'DELETE':
        delete_transaction();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function reverse_transaction() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    $ref = $data['reference'];
    $user= $data['username'];
    $status = 1;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $localDateTime = date("Y-m-d H:i:s");


    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT * from transactions where trnReference = ?");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $makerID = $row['trnMaker'];
            $usrDetails = $value->getUserDetails($user);
            $checkerID = $usrDetails['usrID'];
            $trnStatus = $row['trnStatus'];
            $trnStatusText = $row['trnStateText'];
        }
        if($trnStatus == 0){
            echo json_encode(["msg" => "pending"], JSON_PRETTY_PRINT);
            exit;
        }
        if($trnStatusText == 'Reversed'){
            echo json_encode(["msg" => "already reversed"], JSON_PRETTY_PRINT);
            exit;
        }
        if($trnStatusText == 'Deleted'){
            echo json_encode(["msg" => "deleted"], JSON_PRETTY_PRINT);
            exit;
        }

        if($makerID == $checkerID){
            $statusText = "Reversed";
            $stmt1 = $conn->prepare("INSERT into trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate)
                SELECT trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, -trdAmount, concat('Reversed: ', trdNarration), ? from trnDetails
                where trdReference = ?");
            $stmt1->execute([$localDateTime, $ref]);

            $stmt2 = $conn->prepare("UPDATE transactions set trnStatus = ?, trnStateText=? where trnReference=?");
            $stmt2->execute([$status, $statusText, $ref]);

            echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
        }else{
            echo json_encode(["msg" => "invalid"], JSON_PRETTY_PRINT);
        }

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

function auth_transaction() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    $ref = $data['reference'];
    $user= $data['username'];
    $status = 1;
    $statusText = "Authorized";

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("select * from transactions where trnReference = ?");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $makerID = $row['trnMaker'];
            $usrDetails = $value->getUserDetails($user);
            $checkerID = $usrDetails['usrID'];
            $tst = $row['trnStateText'];
        }

        if($tst == "Reversed"){
            $statusText = "Reversed";
        }

        if($makerID != $checkerID){
            $stmt1 = $conn->prepare("update transactions set trnStatus=?, trnStateText=?, trnAuthorizer =? where trnReference =?");
            $stmt1->execute([$status, $statusText, $checkerID, $ref]);
            echo json_encode(["msg" => "authorized"], JSON_PRETTY_PRINT);
        }else{
            echo json_encode(["msg" => "invalid"], JSON_PRETTY_PRINT);
        }

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

function delete_transaction() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    $ref = $data['reference'];
    $user= $data['username'];
    

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT * from transactions where trnReference = ?");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $trnStatus = $row['trnStatus'];
            $trnType = $row['trnType'];
            if($trnStatus == 1){
                echo json_encode(["msg" => "authorized"], JSON_PRETTY_PRINT);
                exit;
            }
            $makerID = $row['trnMaker'];
            $usrDetails = $value->getUserDetails($user);
            $usrRole = $usrDetails['usrRole'];
            $checkerID = $usrDetails['usrID'];
        }
        if($makerID == $checkerID || $usrRole == "Super"){
            if($trnType == 'TRPT'){
                $stmt3 = $conn->prepare("delete from shippingDetails where shdTrnRef =?");
                $stmt3->execute([$ref]);
            }
            $stmt = $conn->prepare("DELETE from trnDetails where trdReference =?");
            $stmt->execute([$ref]);
            $stmt1 = $conn->prepare("UPDATE transactions set trnStateText='Deleted', trnStatus = 1, trnAuthorizer=? where trnReference =?");
            $stmt1->execute([$checkerID, $ref]);
            echo json_encode(["msg" => "deleted"], JSON_PRETTY_PRINT);
        }else{
            echo json_encode(["msg" => "invalid"], JSON_PRETTY_PRINT);
        }

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
?>