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
    case 'PUT':
        unauth_transaction();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function unauth_transaction() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    $ref = $data['reference'];
    $user= $data['username'];
    $status = 0;
    $statusText = "Pending";

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

        if($makerID == $checkerID){
            $stmt1 = $conn->prepare("update transactions set trnStatus=?, trnStateText=?, trnAuthorizer =? where trnReference =?");
            $stmt1->execute([$status, $statusText, $checkerID, $ref]);
            echo json_encode(["msg" => "pending"], JSON_PRETTY_PRINT);
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