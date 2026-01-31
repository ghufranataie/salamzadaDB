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
        get_productCategory();
        break;
    case 'POST':
        add_productCategory();
        break;
    case 'PUT':
        update_productCategory();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_productCategory() {
    global $conn;
    try {
        if (isset($_GET['pcID']) && !empty($_GET['pcID'])) {
            // Use SQL for single record
            $sql = "SELECT * from productCategory where pcID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['pcID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT * from productCategory order by pcID ASC";
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
function add_productCategory() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $name = $data['pcName'];
    $description = $data['pcDescription'];
    $status = 1;

    try {
        $stmt = $conn->prepare("select count(*) from productCategory where pcName = ?");
        $stmt->execute([$name]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT into productCategory (pcName, pcDescription, pcStatus) values (?, ?, ?)");
        $stmt1->execute([$name, $description, $status]);
        
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
function update_productCategory(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);
    $pcID = $data['pcID'];
    $name = $data['pcName'];
    $description = $data['pcDescription'];
    $status = $data['pcStatus'];

    $required = ['pcID','pcName','pcDescription','pcStatus'];

    foreach ($required as $key) {
        if (!isset($data[$key]) || $data[$key] === "") {
            echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
            exit;
        }
    }

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("select count(*) from productCategory where pcName = ? and pcID = ?");
        $stmt->execute([$name, $pcID]);
        $count = $stmt->fetchColumn();
        if($count > 0){
            $stmt1 = $conn->prepare("UPDATE productCategory SET pcDescription=?, pcStatus=? WHERE pcID=?");
            $stmt1->execute([$description, $status, $pcID]);
        }else{
            $stmt1 = $conn->prepare("UPDATE productCategory SET pcName=?, pcDescription=?, pcStatus=? WHERE pcID=?");
            $stmt1->execute([$name, $description, $status, $pcID]);
        }

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