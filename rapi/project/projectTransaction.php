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
        load_projectTransaction();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_projectTransaction() {
    global $conn;

    $ref = $_GET['ref'];

    try {

        $stmt = $conn->prepare("SELECT 
                p.prjID, p.prjName, concat(pr.perName, ' ', pr.perLastName) as customerName,
                p.prjLocation, p.prjDetails, p.prjDateLine, p.prjStatus, pp.prpType
            FROM projectPayments pp
            JOIN projects p on p.prjID = pp.prpProjectID
            JOIN personal pr on pr.perID = p.prjOwner
            where pp.prpTrnRef = ?");
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


?>