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
        generate_accountStatement();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function generate_accountStatement() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    $from = $data['fromDate'];
    $to = $data['toDate'];
    
    
    try {
        if(!empty($data['usrName'])){
            $user = $data['usrName'];
            $userDetails = $value->getUserDetails($user);
            $usrID = $userDetails['usrID'];

            $stmt = $conn->prepare("SELECT ualID, usrID, usrName, concat(perName, ' ', perLastName) as fullName, usrRole, usrBranch, ualType, ualDetails, ualIP, ualDevice, ualTiming from userActivityLog ul
            join users on users.usrID = ul.ualUser
            join personal pr on pr.perID = users.usrOwner
            where ualUser = ? and DATE(ualTiming) BETWEEN ? AND ?
            order by ualID desc");
            $stmt->execute([$usrID, $from, $to]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }else{

            $stmt = $conn->prepare("SELECT ualID, usrID, usrName, concat(perName, ' ', perLastName) as fullName, usrRole, usrBranch, ualType, ualDetails, ualIP, ualDevice, ualTiming from userActivityLog ul
            join users on users.usrID = ul.ualUser
            join personal pr on pr.perID = users.usrOwner
            where DATE(ualTiming) BETWEEN ? AND ?
            order by ualID desc");
            $stmt->execute([$from, $to]);
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