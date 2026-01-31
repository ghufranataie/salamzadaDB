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
        get_accounts();
        break;
    case 'POST':
        add_accounts();
        break;
    case 'PUT':
        update_accounts();
        break;
    case 'DELETE':
        delete_accounts();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_accounts() {
    global $conn;
    try {
        if (isset($_GET['perID']) && !empty($_GET['perID'])) {
            // Use SQL for single record
            $sql = "select * from accounts
                    join accountDetails on accountDetails.actAccount = accounts.accNumber
                    where actSignatory = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['perID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "select * from accounts
                    join accountDetails on accountDetails.actAccount = accounts.accNumber";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data, JSON_PRETTY_PRINT);
    } 
    catch (PDOException $e) {
        echo json_encode(["msg" => $e->getMessage()]);
    }
}
function add_accounts() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $accName = $data['accName'];
    $actCurrency = $data['actCurrency'];
    $actCreditLimit = $data['actCreditLimit'];
    $actSignatory = $data['actSignatory'];
    $accCategory = 8;
    $newAcc = $value->getNewAccount($accCategory);
    $com = 1;

    try {
        $conn->beginTransaction();

        $stmt3 = $conn->prepare("insert into accounts (accNumber, accName, accCategory) values (?, ?, ?)");
        $stmt3->execute([$newAcc, $accName, $accCategory]);

        $stmt4 = $conn->prepare("insert into accountDetails (actAccount, actCurrency, actCreditlimit, actSignatory, actCompany) values (?, ?, ?, ?, ?)");
        $stmt4->execute([$newAcc, $actCurrency, $actCreditLimit, $actSignatory, $com]);
        
        $conn->commit();

        echo json_encode(["msg" => "success", "accountNo" => $newAcc], JSON_PRETTY_PRINT);
    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode(["msg" => "failed", "error" => $th->getMessage()], JSON_PRETTY_PRINT);
    }
}
function update_accounts(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['accNumber'])) {
        echo json_encode(["msg" => "invalid json"]);
        return;
    }

    $accNumber      = $data['accNumber'];
    $accName        = $data['accName'];
    $actCreditLimit = $data['actCreditLimit'];
    $actSignatory   = $data['actSignatory'];
    $actStatus      = $data['actStatus'];

    try {
        $conn->beginTransaction();

        $stmt3 = $conn->prepare(
            "UPDATE accounts SET accName=? WHERE accNumber=?"
        );
        $stmt3->execute([$accName, $accNumber]);

        $stmt4 = $conn->prepare(
            "UPDATE accountDetails SET actCreditLimit=?, actSignatory=?, actStatus=? 
             WHERE actAccount=?"
        );
        $stmt4->execute([$actCreditLimit, $actSignatory, $actStatus, $accNumber]);

        $conn->commit();
        echo json_encode(["msg" => "success", "accountNo" => $accNumber]);

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
function delete_accounts(){
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);
    try {
        $conn->beginTransaction();
        if (isset($_GET['accNo']) && !empty($_GET['accNo'])){
            $stmt1 = $conn->prepare(
                "delete from accounts where accNumber = ?"
            );
            $stmt1->execute([$accName]);
        }else{

        }
        

    } catch (\Throwable $th) {
        //throw $th;
    }

}

?>