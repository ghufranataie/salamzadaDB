
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
        get_glAccounts();
        break;
    case 'POST':
        add_glAccounts();
        break;
    case 'PUT':
        update_glAccounts();
        break;
    case 'DELETE':
        delete_glAccounts();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_glAccounts() {
    global $conn;

    try {

        if (isset($_GET['acc']) && !empty($_GET['acc'])) {
            // Use SQL for single record
            $sql = "SELECT ac.accNumber, ac.accName, ag.acgCategory as accCategory, ag.acgName
                from accounts ac
                join accountCategory ag on ag.acgID = ac.accCategory
                where ac.accCategory != 8 AND accNumber = :acc";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':acc', $_GET['acc'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT ac.accNumber, ac.accName, ag.acgCategory as accCategory, ag.acgName
                from accounts ac
                join accountCategory ag on ag.acgID = ac.accCategory
                where ac.accCategory != 8";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data, JSON_PRETTY_PRINT);



        // if (isset($_GET['local'])) {
        //     $local = $_GET['local'];

        //     if($local == 'en'){
        //         $sql = "SELECT * from accounts where accCategory !=5";
        //         $stmt = $conn->prepare($sql);
             
        //     }elseif ($local == 'fa'){
        //         $sql = "select accNumber, accCategory, acnName as accName from accounts
        //                 join accountsName on accountsName.acnNumber = accounts.accNumber
        //                 where accCategory !=5 and acnLocal = :local";
        //         $stmt = $conn->prepare($sql);
        //         $stmt->bindParam(':local', $_GET['local'], PDO::PARAM_STR);
        //     }else{
        //         $sql = "select accNumber, accCategory, acnName as accName from accounts
        //                 join accountsName on accountsName.acnNumber = accounts.accNumber
        //                 where accCategory !=5 and acnLocal = :local";
        //         $stmt = $conn->prepare($sql);
        //         $stmt->bindParam(':local', $_GET['local'], PDO::PARAM_STR);
        //     }
        //     $stmt->execute();
        //     $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //     echo json_encode($data, JSON_PRETTY_PRINT);
        // } else {
        //     echo json_encode(["msg" => "No local provided"]);
        // }
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

function add_glAccounts() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $accName = $data['accName'];
    $accCategory = $data['accCategory'];
    $newAcc = $value->getNewAccount($accCategory);

    try {
        $stmt = $conn->prepare("SELECT count(*) from accounts where accName = ?");
        $stmt->execute([$accName]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }

        $conn -> beginTransaction();

        $stmt1 = $conn->prepare("INSERT into accounts (accNumber, accName, accCategory) values (?, ?, ?)");
        $stmt1->execute([$newAcc, $accName, $accCategory]);

        $value->generateUserActivityLog(
            $user, 
            "GL Add",
            "$newAcc, $accName, $accCategory"
        );
        $conn->commit();
        echo json_encode(["msg" => "success", "accountNo" => $newAcc], JSON_PRETTY_PRINT);
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

function update_glAccounts(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $accNumber = $data['accNumber'];
    $accName = $data['accName'];

    try {
        $stmt = $conn->prepare("select count(*) from accounts where accName = ?");
        $stmt->execute([$accName]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE accounts SET accName=? WHERE accNumber=?");
        $stmt1->execute([$accName, $accNumber]);

        $value->generateUserActivityLog(
            $user, 
            "GL Update",
            "$accNumber, $accName"
        );

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

// function delete_glAccounts(){
//     global $conn;
//     $gl = $_GET['gl'];

//     $stmt = $conn->prepare("select count(*) as total from trnDetails where trdAccount = ?");
//     $stmt->execute([$gl]);
//     $result = $stmt->fetch(PDO::FETCH_ASSOC);
//     $total = $result['total'];
//     if($total > 0){
//         echo json_encode(["msg" => "dependent", "error" => "the account you are trying to delete is have transaction record and not possible to delete"]);
//         exit();
//     }
//     try {
//         $conn->beginTransaction();
        
//         $stmt = $conn->prepare("DELETE from accounts where accNumber=?");
//         $stmt->execute([$gl]);

//         $conn->commit();

//         echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);

//     } catch (\Throwable $th) {
//         $conn->rollBack();
//         echo json_encode([
//             "msg" => "failed",
//             "error" => $th->getMessage(),
//             "line" => $th->getLine(),
//             "file" => $th->getFile()
//         ]);
//     }

function delete_glAccounts() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);
    $gl = $data['acc'];

    try {
        // Check dependencies
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM trnDetails WHERE trdAccount = ?"
        );
        $stmt->execute([$gl]);
        $total = $stmt->fetchColumn();

        if ($total > 0) {
            echo json_encode([
                "msg" => "dependent",
                "error" => "Account has transaction records and cannot be deleted"
            ]);
            return;
        }

        // Delete account
        $stmt = $conn->prepare(
            "DELETE FROM accounts WHERE accNumber = ?"
        );
        $stmt->execute([$gl]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Account not found");
        }

        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);

    } catch (Throwable $th) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage()
        ]);
    }
}// }

?>