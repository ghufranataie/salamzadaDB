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
        get_trnTypes();
        break;
    case 'POST':
        add_trnTypes();
        break;
    case 'PUT':
        update_trnTypes();
        break;
    case 'DELETE':
        delete_trnTypes();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_trnTypes() {
    global $conn;
    try {
        if (isset($_GET['trntCode']) && !empty($_GET['trntCode'])) {
            // Use SQL for single record
            $sql = "SELECT * from trnTypes where trntCode = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['trntCode'], PDO::PARAM_STR);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT * from trnTypes";
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

function add_trnTypes() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $code = $data['trntCode'];
    $name= $data['trntName'];
    $details = $data['trntDetails'];
   

    try {
        $stmt = $conn->prepare("select count(*) from trnTypes where trntCode = ?");
        $stmt->execute([$code]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }


        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT into trnTypes (trntCode, trntName, trntDetails) values (?, ?, ?)");
        $stmt1->execute([$code, $name, $details]);
        
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

function update_trnTypes(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);
    $code = $data['trntCode'];
    $name= $data['trntName'];
    $details = $data['trntDetails'];

    $required = ['trntCode','trntName','trntDetails'];

    foreach ($required as $key) {
        if (!isset($data[$key]) || $data[$key] === "") {
            echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
            exit;
        }
    }

    try {

        $stmt = $conn->prepare("select count(*) from trnTypes where trntCode = ?");
        $stmt->execute([$code]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }
        
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE trnTypes SET trntName=?, trntDetails=? WHERE trntCode=?");
        $stmt1->execute([$name, $details, $code]);

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

function delete_trnTypes(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $code = $_GET['trntCode'];
    

    try {       
        $stmt = $conn->prepare("select count(*) from transactions where trnType = ?");
        $result = $stmt->execute([$code]);
        if($result){
             echo json_encode(["msg" => "dependent"]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("DELETE from trnTypes WHERE trntCode=?");
        $stmt1->execute([$code]);

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