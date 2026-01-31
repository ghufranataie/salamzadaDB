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
        get_storage();
        break;
    case 'POST':
        add_storage();
        break;
    case 'PUT':
        update_storage();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_storage() {
    global $conn;
    try {
        if (isset($_GET['stgID']) && !empty($_GET['stgID'])) {
            // Use SQL for single record
            $sql = "SELECT * from storages where stgID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['stgID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT * from storages order by stgStatus DESC, stgID DESC";
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
function add_storage() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $name = $data['stgName'];
    $details= $data['stgDetails'];
    $location = $data['stgLocation'];
    $status = 1;

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT into storages (stgName, stgDetails, stgLocation, stgStatus) values (?, ?, ?, ?)");
        $stmt1->execute([$name, $details, $location, $status]);
        
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
function update_storage(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);
    $stgID = $data['stgID'];
    $name = $data['stgName'];
    $details= $data['stgDetails'];
    $location = $data['stgLocation'];
    $status = $data['stgStatus'];

    $required = ['stgID','stgName','stgDetails','stgLocation','stgStatus'];

    foreach ($required as $key) {
        if (!isset($data[$key]) || $data[$key] === "") {
            echo json_encode(["msg" => "empty", "details" => "$key is missing or empty"]);
            exit;
        }
    }

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE storages SET stgName=?, stgDetails=?, stgLocation=?, stgStatus=? WHERE stgID=?");
        $stmt1->execute([$name, $details, $location, $status, $stgID]);

        $conn->commit();
        echo json_encode(["msg" => "success", "branch ID" => "Storage with ID $stgID is updated"]);

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