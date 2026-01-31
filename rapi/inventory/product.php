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
        get_product();
        break;
    case 'POST':
        add_product();
        break;
    case 'PUT':
        update_product();
        break;
    case 'DELETE':
        delete_product();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_product() {
    global $conn;
    try {
        if (isset($_GET['proID']) && !empty($_GET['proID'])) {
            // Use SQL for single record
            $sql = "SELECT * from product where proID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['proID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT * from product";
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

function add_product() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $code = $data['proCode'];
    $category= $data['proCategory'];
    $name = $data['proName'];
    $madeIn = $data['proMadeIn'];
    $details = $data['proDetails'];
   

    try {

        $stmt = $conn->prepare("select count(*) from product where proCode = ?");
        $stmt->execute([$code]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT into product (proCode, proCategory, proName, proMadeIn, proDetails) values (?, ?, ?, ?, ?)");
        $stmt1->execute([$code, $category, $name, $madeIn, $details]);
        
        $conn->commit();
        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);

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

function update_product(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['proID'];
    $code = $data['proCode'];
    $category= $data['proCategory'];
    $name = $data['proName'];
    $madeIn = $data['proMadeIn'];
    $details = $data['proDetails'];
    $status = $data['proStatus'];

    $required = ['proID','proCode','proCategory','proName','proMadeIn','proDetails', 'proStatus'];

    foreach ($required as $key) {
        if (!isset($data[$key]) || $data[$key] === "") {
            echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
            exit;
        }
    }

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE product SET proCategory=?, proName=?, proMadeIn=?, proDetails=?, proCode=?, proStatus=? WHERE proID=?");
        $stmt1->execute([$category, $name, $madeIn, $details, $code, $status, $id]);

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

function delete_product(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $id = $_GET['proID'];
    

    try {       
        $stmt = $conn->prepare("select count(*) from stock where stkProduct = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "dependent"]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("DELETE from product WHERE proID=?");
        $stmt1->execute([$id]);

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