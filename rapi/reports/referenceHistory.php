
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
        load_trnDescritpion();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_trnDescritpion() {
    global $conn;

    $ref = $_GET['ref'];

    try {

        $stmt = $conn->prepare("SELECT tr.trnID, tr.trnReference, tr.trnType, tt.trntName, mk.usrName as maker, ck.usrName as checker, tr.trnStateText, tr.trnEntryDate from transactions tr
            left join users mk on mk.usrID = tr.trnMaker
            left join users ck on ck.usrID = tr.trnAuthorizer
            join trnTypes tt on tt.trntCode = tr.trnType
            where trnReference = ?");
        $stmt->execute([$ref]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);


        $stmt1 = $conn->prepare("SELECT td.trdID,
                case 
                    when trdDrCr = 'Dr' then 'Debit'
                    when TrdDrCr = 'Cr' then 'Credit'
                end as Debit_Credit,
                td.trdAccount, ac.accName, td.trdAmount, td.trdCcy,
                trdNarration, trdEntryDate    
            from trnDetails td
            join branch br on br.brcID = td.trdBranch
            join currency cy on cy.ccyCode = td.trdCcy
            join accounts ac on ac.accNumber = td.trdAccount
            where trdReference = ?");
        $stmt1->execute([$ref]);
        $data1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $data['records'] = $data1;
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