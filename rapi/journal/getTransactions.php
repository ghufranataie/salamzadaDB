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
        get_allTransactions();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_allTransactions() {
    global $conn, $value;

    $status = $_GET['status'];
    $comTZ = $value->getCompanyAttributes('comTimeZone');

    $timezone = new DateTimeZone($comTZ);
    $datetime = new DateTime('now', $timezone);

    $currentDate = $datetime->format('Y-m-d');

    try {
        if ($status == "pending"){
            $stmt = $conn->prepare("SELECT trnReference, trnType, tp.trntName, mk.usrName AS maker, COALESCE(ck.usrName, NULL) AS checker, tr.trnStatus, tr.trnStateText, tr.trnEntryDate 
                FROM transactions tr
                JOIN users mk ON mk.usrID = tr.trnMaker
                LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
                JOIN trnTypes tp ON tp.trntCode = tr.trnType
                WHERE tr.trnStatus = 0
                ORDER BY tr.trnEntryDate DESC");
        }elseif($status == "auth"){
            $stmt = $conn->prepare("SELECT trnReference, trnType, tp.trntName, mk.usrName AS maker, COALESCE(ck.usrName, NULL) AS checker, tr.trnStatus, tr.trnStateText, tr.trnEntryDate 
                FROM transactions tr
                JOIN users mk ON mk.usrID = tr.trnMaker
                LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
                JOIN trnTypes tp ON tp.trntCode = tr.trnType
                WHERE tr.trnStatus = 1 AND DATE(tr.trnEntryDate) = :CD
                ORDER BY tr.trnEntryDate DESC");
                $stmt->bindParam(':CD', $currentDate, PDO::PARAM_STR);
        }elseif($status == "all"){
            $sql = "SELECT trnReference, trnType, tp.trntName, mk.usrName AS maker, COALESCE(ck.usrName, NULL) AS checker, tr.trnStatus, tr.trnStateText, tr.trnEntryDate FROM transactions tr
                JOIN users mk ON mk.usrID = tr.trnMaker
                LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
                JOIN trnTypes tp ON tp.trntCode = tr.trnType
                WHERE 
                    (tr.trnStatus = 0) 
                    OR (tr.trnStatus = 1 AND DATE(tr.trnEntryDate) = :CD)
                ORDER BY tr.trnEntryDate DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':CD', $currentDate, PDO::PARAM_STR);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data, JSON_PRETTY_PRINT);
    } 
    catch (PDOException $e) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

?>