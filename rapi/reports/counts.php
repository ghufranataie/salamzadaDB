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
        generate_accountStatement();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function generate_accountStatement() {
    global $conn;
    
    
    try {

        $stmt = $conn->prepare("SELECT count(DISTINCT pr.perID) as personals, count(DISTINCT em.empID) as employees, count(DISTINCT ad.actID) as accounts, count(DISTINCT u.usrID) as users
            from personal pr
            left join employees em on em.empPersonal = pr.perID
            left join accountDetails ad on ad.actSignatory = pr.perID
            left join users u on u.usrOwner = pr.perID");
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

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