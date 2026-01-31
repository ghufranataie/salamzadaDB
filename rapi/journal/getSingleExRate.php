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
        get_Rate();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_Rate() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);
    $fromCcy = $data['ccyFrom'];
    $toCcy = $data['ccyTo'];


    try {
        $sql = "select crExchange from ccyRate where crFrom =:from and crTo = :to order by crDate desc limit 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':from' => $fromCcy,
            ':to' => $toCcy,
        ]);
        $data1 = $stmt->fetch(PDO::FETCH_ASSOC);
        if($data1){
            echo json_encode($data1, JSON_PRETTY_PRINT);
        }else{
            echo json_encode(["crExchange" => "0.00"], JSON_PRETTY_PRINT);
        }
        
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