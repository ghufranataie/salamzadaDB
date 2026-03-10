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
$envFile = __DIR__ . '/../user/.env';

$db = new Database();
$conn = $db->getConnection();
$value = new DataValues($conn);
$data = json_decode(file_get_contents("php://input"), true);
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        get_subscription();
        break;
    case 'POST':
        add_subscription();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_subscription() {
    global $conn;
    try {
        $sql = "SELECT * from subscription order by subID DESC limit 1";
        $stmt = $conn->prepare($sql);
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

function add_subscription() {
    global $conn, $envFile;
    $data = json_decode(file_get_contents("php://input"), true);

    $oldKey = $data['oldKey'];
    $newKey = $data['newKey'];
    $date= $data['subExpireDate'];

    $envContent = file_get_contents($envFile);
    $stmtSub = $conn->prepare("SELECT * from subscription order by subID desc limit 1");
    $stmtSub->execute();
    $subResult = $stmtSub->fetch(PDO::FETCH_ASSOC);
    if($subResult){
        $hashedKey = $subResult['subKey'];
        $expiryDate = $subResult['subExpireDate'];
        $verifySub = password_verify($oldKey, $hashedKey);
        if(!$verifySub){
            echo json_encode(["msg" => "mismatch"]);
            exit;
        }

        $envContent = preg_replace(
            '/^SUB_KEY\s*=\s*".*"$/m', 
            'SUB_KEY = "' . $newKey . '"',
            $envContent
        );

        if (file_put_contents($envFile, $envContent) === false) {
            echo json_encode(["msg" => "failed"]);
            exit;
        }
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE subscription set subKey = 'Expired'");
        $stmt->execute();

        $hashedKey = password_hash($newKey, PASSWORD_ARGON2ID);

        $stmt1 = $conn->prepare("INSERT into subscription (subKey, subExpireDate, subEntryDate) values (?, ?, CURDATE())");
        $stmt1->execute([$hashedKey, $date]);
        
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
?>