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
        get_branchLimit();
        break;
    case 'POST':
        add_branchLimit();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_branchLimit() {
    global $conn;
    try {
        if (isset($_GET['code']) && !empty($_GET['code'])) {
            // Use SQL for single record
            $sql = "select * from branchAuthLimit where balBranch = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['code'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "select * from branchAuthLimit";
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

function add_branchLimit() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    $code = $data['balBranch'];
    $ccy= $data['balCurrency'];
    $limit = $data['balLimitAmount'];

    if(empty($code)){
        echo json_encode(["msg" => "empty"], JSON_PRETTY_PRINT);
        exit;
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("select balID from branchAuthLimit where balBranch=? and balCurrency=?");
        $stmt->execute([$code, $ccy]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $id = $row['balID'];
        }else{
            $id = null;
        }
        

        if($id){
            $stmt1 = $conn->prepare("update branchAuthLimit set balLimitAmount=? where balID=?");
            $stmt1->execute([$limit, $id]);
            echo json_encode(["msg" => "updated"], JSON_PRETTY_PRINT);
        }else{
            $stmt1 = $conn->prepare("INSERT into branchAuthLimit (balBranch, balCurrency, balLimitAmount) values (?, ?, ?)");
            $stmt1->execute([$code, $ccy, $limit]); 
            echo json_encode(["msg" => "added"], JSON_PRETTY_PRINT);
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