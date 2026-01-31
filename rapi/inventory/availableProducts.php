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
        view_availableProducts();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function view_availableProducts() {
    global $conn;
    try {
        if (isset($_GET['proID']) && !empty($_GET['proID'])) {
            $proID = $_GET['proID'];
            // Use SQL for single record
            $sql = "SELECT * from vw_availableProducts where proID=:id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $proID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif(isset($_GET['av']) && !empty($_GET['av'])) {
            $avail = $_GET['av'];
          
            $sql = "SELECT * from vw_availableProducts where available >= :av";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':av', $avail, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }else{
            $sql = "SELECT * from vw_availableProducts";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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