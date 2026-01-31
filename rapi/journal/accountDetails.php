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
        get_accountDetails();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_accountDetails() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $value = $data['searchValue'];
    try {
        $sql = "SELECT accnumber, accName, actCurrency, ccySymbol, actCreditLimit,
            COALESCE(sum(case when trdDrCr='Cr' then trdAmount else -trdAmount end), 0) as curBalance,
            sum(case when trnStatus = 1 then case when trdDrCr='Cr' and trnStatus=1 then trdAmount else -trdAmount end else 0 end) as avilBalance,
            actStatus from accounts
            join accountDetails on accountDetails.actAccount = accounts.accNumber
            left join trnDetails on trnDetails.trdAccount = accounts.accNumber
            left join transactions on transactions.trnReference = trnDetails.trdReference
            join currency on currency.ccyCode = accountDetails.actCurrency
            where accName like '%$value%' or accNumber like '%$value%'
            group by accNumber";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $data1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data1, JSON_PRETTY_PRINT);
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