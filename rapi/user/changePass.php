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
        update_password();
        break;
    case 'PUT':
        force_changePassword();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function update_password(){
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);


    $usrName = $data['usrName'];
    $oldPass = $data['usrPass'];
    $newPass = password_hash($data['usrNewPass'], PASSWORD_ARGON2ID);
    $fcp = 0;
    
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT * from users where usrName=? or usrEmail=?");
        $stmt->execute([$usrName, $usrName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $hashedPass = $row['usrPass'];
            $verifyPass = password_verify($oldPass, $hashedPass);
            if(!$verifyPass){
                echo json_encode(["msg" => "incorrect"]);
                exit;
            }else{
                $stmt1 = $conn->prepare("update users set usrPass=?, usrFCP=? where usrName =? or usrEmail=?");
                $result = $stmt1->execute([$newPass, $fcp, $usrName, $usrName]);
                if($result){
                    echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
                }else{
                    echo json_encode(["msg" => "failed"], JSON_PRETTY_PRINT);
                }
            }
        }else{
            echo json_encode(["msg" => "incorrect"]);
        }
        $conn->commit();
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

function force_changePassword() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $usrName = $data['usrName'];
    $newPass = password_hash($data['usrPass'], PASSWORD_ARGON2ID);
    $fcp = 0;
    
    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("update users set usrPass=?, usrFCP=? where usrName =? or usrEmail=?");
        $result = $stmt1->execute([$newPass, $fcp, $usrName, $usrName]);
        if($result){
            echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
        }else{
            echo json_encode(["msg" => "failed"], JSON_PRETTY_PRINT);
        }

        $conn->commit();
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