
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";


$mail = new PHPMailer(true);

$db = new Database();
$conn = $db->getConnection();
$value = new DataValues($conn);
$data = json_decode(file_get_contents("php://input"), true);
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        get_users();
        break;
    case 'POST':
        add_users();
        break;
    case 'PUT':
        update_users();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_users() {
    global $conn;
    try {
        if (isset($_GET['perID']) && !empty($_GET['perID'])) {
            // Use SQL for single record
            $sql = "SELECT usrID, concat(perName, ' ', perLastName) as usrFullName, perPhoto, usrName, usrRole, usrStatus, usrBranch, usrEmail, perPhone, usrToken, usrFCP, usrEntryDate from users
                    join personal on personal.perID = users.usrOwner
                    where perID = :id and usrRole != 'super'";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['perID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "select usrID, concat(perName, ' ', perLastName) as usrFullName, perPhoto, usrName, usrRole, usrStatus, usrBranch, usrEmail, perPhone, usrToken, usrFCP, usrEntryDate from users
                    join personal on personal.perID = users.usrOwner
                    where usrRole != 'super'";
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
function add_users() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $logedInUser = $data['loggedInUser'];
    $username = $data['usrName'];
    $password = password_hash($data['usrPass'], PASSWORD_ARGON2ID);
    $owner = $data['usrOwner'];
    $role = $data['usrRole'];
    $branch = $data['usrBranch'];;
    $email = $data['usrEmail'];
    $fev = $data['usrFEV'];
    $token = bin2hex(random_bytes(32));
    $fcp = $data['usrFCP'];
    $status = 1;
    $roleStatus = 0;
    $userExist = $value->checkUsernameExistance($username);
    $emailExist = $value->checkUserEmailExistance($email);

    $vMethod = "verified";

    $ceo = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 1],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1],
    ];
    $manager = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 1],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1]
    ];
    $deputy = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1]
    ];
    $admin = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1]
    ];
    $authoriser = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1]
    ];
    $cashier = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1]
    ];
    $officer = [
        ["uprRole" => 1, "uprStatus" => 1],
        ["uprRole" => 2, "uprStatus" => 1],
        ["uprRole" => 3, "uprStatus" => 1],
        ["uprRole" => 4, "uprStatus" => 1],
        ["uprRole" => 5, "uprStatus" => 1],
        ["uprRole" => 6, "uprStatus" => 1],
        ["uprRole" => 7, "uprStatus" => 1],
        ["uprRole" => 8, "uprStatus" => 1],
        ["uprRole" => 9, "uprStatus" => 1],
        ["uprRole" => 10, "uprStatus" => 1],
        ["uprRole" => 11, "uprStatus" => 1],
        ["uprRole" => 12, "uprStatus" => 1],
        ["uprRole" => 13, "uprStatus" => 1],
        ["uprRole" => 14, "uprStatus" => 1],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 1],
        ["uprRole" => 17, "uprStatus" => 1],
        ["uprRole" => 18, "uprStatus" => 1],
        ["uprRole" => 19, "uprStatus" => 1],
        ["uprRole" => 20, "uprStatus" => 1],
        ["uprRole" => 21, "uprStatus" => 1],
        ["uprRole" => 22, "uprStatus" => 1],
        ["uprRole" => 23, "uprStatus" => 1],
        ["uprRole" => 24, "uprStatus" => 1],
        ["uprRole" => 25, "uprStatus" => 1],
        ["uprRole" => 26, "uprStatus" => 1],
        ["uprRole" => 27, "uprStatus" => 1],
        ["uprRole" => 28, "uprStatus" => 1],
        ["uprRole" => 29, "uprStatus" => 1],
        ["uprRole" => 30, "uprStatus" => 1],
        ["uprRole" => 31, "uprStatus" => 1],
        ["uprRole" => 32, "uprStatus" => 1],
        ["uprRole" => 33, "uprStatus" => 1],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 1],
        ["uprRole" => 36, "uprStatus" => 1],
        ["uprRole" => 37, "uprStatus" => 1],
        ["uprRole" => 38, "uprStatus" => 1],
        ["uprRole" => 39, "uprStatus" => 1],
        ["uprRole" => 40, "uprStatus" => 1],
        ["uprRole" => 41, "uprStatus" => 1],
        ["uprRole" => 42, "uprStatus" => 1],
        ["uprRole" => 43, "uprStatus" => 1],
        ["uprRole" => 44, "uprStatus" => 1],
        ["uprRole" => 45, "uprStatus" => 1],
        ["uprRole" => 46, "uprStatus" => 1],
        ["uprRole" => 47, "uprStatus" => 1],
        ["uprRole" => 48, "uprStatus" => 1],
        ["uprRole" => 49, "uprStatus" => 1],
        ["uprRole" => 50, "uprStatus" => 1],
        ["uprRole" => 51, "uprStatus" => 1],
        ["uprRole" => 52, "uprStatus" => 1],
        ["uprRole" => 53, "uprStatus" => 1],
        ["uprRole" => 54, "uprStatus" => 1],
        ["uprRole" => 55, "uprStatus" => 1],
        ["uprRole" => 56, "uprStatus" => 1]
    ];
    $customerService = [
        ["uprRole" => 1, "uprStatus" => 0],
        ["uprRole" => 2, "uprStatus" => 0],
        ["uprRole" => 3, "uprStatus" => 0],
        ["uprRole" => 4, "uprStatus" => 0],
        ["uprRole" => 5, "uprStatus" => 0],
        ["uprRole" => 6, "uprStatus" => 0],
        ["uprRole" => 7, "uprStatus" => 0],
        ["uprRole" => 8, "uprStatus" => 0],
        ["uprRole" => 9, "uprStatus" => 0],
        ["uprRole" => 10, "uprStatus" => 01],
        ["uprRole" => 11, "uprStatus" => 0],
        ["uprRole" => 12, "uprStatus" => 0],
        ["uprRole" => 13, "uprStatus" => 0],
        ["uprRole" => 14, "uprStatus" => 0],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 0],
        ["uprRole" => 17, "uprStatus" => 0],
        ["uprRole" => 18, "uprStatus" => 0],
        ["uprRole" => 19, "uprStatus" => 0],
        ["uprRole" => 20, "uprStatus" => 0],
        ["uprRole" => 21, "uprStatus" => 0],
        ["uprRole" => 22, "uprStatus" => 0],
        ["uprRole" => 23, "uprStatus" => 0],
        ["uprRole" => 24, "uprStatus" => 0],
        ["uprRole" => 25, "uprStatus" => 0],
        ["uprRole" => 26, "uprStatus" => 0],
        ["uprRole" => 27, "uprStatus" => 0],
        ["uprRole" => 28, "uprStatus" => 0],
        ["uprRole" => 29, "uprStatus" => 0],
        ["uprRole" => 30, "uprStatus" => 0],
        ["uprRole" => 31, "uprStatus" => 0],
        ["uprRole" => 32, "uprStatus" => 0],
        ["uprRole" => 33, "uprStatus" => 0],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 0],
        ["uprRole" => 36, "uprStatus" => 0],
        ["uprRole" => 37, "uprStatus" => 0],
        ["uprRole" => 38, "uprStatus" => 0],
        ["uprRole" => 39, "uprStatus" => 0],
        ["uprRole" => 40, "uprStatus" => 0],
        ["uprRole" => 41, "uprStatus" => 0],
        ["uprRole" => 42, "uprStatus" => 0],
        ["uprRole" => 43, "uprStatus" => 0],
        ["uprRole" => 44, "uprStatus" => 0],
        ["uprRole" => 45, "uprStatus" => 0],
        ["uprRole" => 46, "uprStatus" => 0],
        ["uprRole" => 47, "uprStatus" => 0],
        ["uprRole" => 48, "uprStatus" => 0],
        ["uprRole" => 49, "uprStatus" => 0],
        ["uprRole" => 50, "uprStatus" => 0],
        ["uprRole" => 51, "uprStatus" => 0],
        ["uprRole" => 52, "uprStatus" => 0],
        ["uprRole" => 53, "uprStatus" => 0],
        ["uprRole" => 54, "uprStatus" => 0],
        ["uprRole" => 55, "uprStatus" => 0],
        ["uprRole" => 56, "uprStatus" => 0]
    ];
    $customer = [
         ["uprRole" => 1, "uprStatus" => 0],
        ["uprRole" => 2, "uprStatus" => 0],
        ["uprRole" => 3, "uprStatus" => 0],
        ["uprRole" => 4, "uprStatus" => 0],
        ["uprRole" => 5, "uprStatus" => 0],
        ["uprRole" => 6, "uprStatus" => 0],
        ["uprRole" => 7, "uprStatus" => 0],
        ["uprRole" => 8, "uprStatus" => 0],
        ["uprRole" => 9, "uprStatus" => 0],
        ["uprRole" => 10, "uprStatus" => 01],
        ["uprRole" => 11, "uprStatus" => 0],
        ["uprRole" => 12, "uprStatus" => 0],
        ["uprRole" => 13, "uprStatus" => 0],
        ["uprRole" => 14, "uprStatus" => 0],
        ["uprRole" => 15, "uprStatus" => 0],
        ["uprRole" => 16, "uprStatus" => 0],
        ["uprRole" => 17, "uprStatus" => 0],
        ["uprRole" => 18, "uprStatus" => 0],
        ["uprRole" => 19, "uprStatus" => 0],
        ["uprRole" => 20, "uprStatus" => 0],
        ["uprRole" => 21, "uprStatus" => 0],
        ["uprRole" => 22, "uprStatus" => 0],
        ["uprRole" => 23, "uprStatus" => 0],
        ["uprRole" => 24, "uprStatus" => 0],
        ["uprRole" => 25, "uprStatus" => 0],
        ["uprRole" => 26, "uprStatus" => 0],
        ["uprRole" => 27, "uprStatus" => 0],
        ["uprRole" => 28, "uprStatus" => 0],
        ["uprRole" => 29, "uprStatus" => 0],
        ["uprRole" => 30, "uprStatus" => 0],
        ["uprRole" => 31, "uprStatus" => 0],
        ["uprRole" => 32, "uprStatus" => 0],
        ["uprRole" => 33, "uprStatus" => 0],
        ["uprRole" => 34, "uprStatus" => 0],
        ["uprRole" => 35, "uprStatus" => 0],
        ["uprRole" => 36, "uprStatus" => 0],
        ["uprRole" => 37, "uprStatus" => 0],
        ["uprRole" => 38, "uprStatus" => 0],
        ["uprRole" => 39, "uprStatus" => 0],
        ["uprRole" => 40, "uprStatus" => 0],
        ["uprRole" => 41, "uprStatus" => 0],
        ["uprRole" => 42, "uprStatus" => 0],
        ["uprRole" => 43, "uprStatus" => 0],
        ["uprRole" => 44, "uprStatus" => 0],
        ["uprRole" => 45, "uprStatus" => 0],
        ["uprRole" => 46, "uprStatus" => 0],
        ["uprRole" => 47, "uprStatus" => 0],
        ["uprRole" => 48, "uprStatus" => 0],
        ["uprRole" => 49, "uprStatus" => 0],
        ["uprRole" => 50, "uprStatus" => 0],
        ["uprRole" => 51, "uprStatus" => 0],
        ["uprRole" => 52, "uprStatus" => 0],
        ["uprRole" => 53, "uprStatus" => 0],
        ["uprRole" => 54, "uprStatus" => 0],
        ["uprRole" => 55, "uprStatus" => 0],
        ["uprRole" => 56, "uprStatus" => 0]
    ];

    try {
        $conn -> beginTransaction();
        $conn->exec("SET time_zone = '+04:30'");
        date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
        $entryDateTime = date("Y-m-d H:i:s");

        if($userExist){
            echo json_encode(["msg" => "user exists"], JSON_PRETTY_PRINT);
            exit;
        }
        if($emailExist){
            echo json_encode(["msg" => "email exists"], JSON_PRETTY_PRINT);
            exit;
        }
        if($fev == true){
            $stmt1 = $conn->prepare(
            "INSERT INTO users (usrName, usrPass, usrOwner, usrRole, usrBranch, usrEmail, usrToken, usrFCP, usrStatus, usrEntryDate) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt1->execute([$username, $password, $owner, $role, $branch, $email, $token, $fcp, $status, $entryDateTime]);
            sendVerification($email, $username, $token);
            $verify = "required";
         }else{
            $stmt1 = $conn->prepare(
            "INSERT INTO users (usrName, usrPass, usrOwner, usrRole, usrBranch, usrEmail, usrToken, usrFCP, usrStatus, usrEntryDate) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $verify = "verified";
            $stmt1->execute([$username, $password, $owner, $role, $branch, $email, $verify, $fcp, $status, $entryDateTime]);
         }

        $usrID = $conn->lastInsertId();
        $stmt3 = $conn->prepare("insert into userPermissions (uprUserID, uprRole, uprStatus) select ?, rsgID, 0 from roleSubGroup");
        $stmt3->execute([$usrID]);

        $stmt2 = $conn->prepare("UPDATE userPermissions set uprStatus= :uprStatus where uprUserID= :uprUserID and uprRole= :uprRole");
        switch($role){
            case "ceo":
                foreach($ceo as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;

            case "manager":
                foreach($manager as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;

            case "deputy":
                foreach($deputy as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;

            case "admin":
                foreach($admin as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;

            case "authorizer":
                foreach($authorizer as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;

            case "cashier":
                foreach($cashier as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;
            
            case "customer Service":
                foreach($customerService as $item){
                    $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                }
                break;

            case "customer":
                foreach($customer as $item){
                    $result = $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
                    // echo "\n$result is:". $item['uprStatus'];
                }
                break;
            default:
                echo "Invalid Role";
                break;
        }
        
        $conn->commit();

        $fcpMethod = $fcp ? "Yes" : "NO";
        $activation = $status ? "Active" : "Block";
        $value->generateUserActivityLog(
            $logedInUser, 
            'User',
            "New User: $username, Owner ID: $owner, FCP: $fcpMethod, Role: $role, Branch: $branch, Status: $activation, Verification: $verify"
        );
        echo json_encode(["msg" => "success", "username" => $username], JSON_PRETTY_PRINT);
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
function update_users(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $logedInUser = $data['loggedInUser'];
    $username = $data['usrName'];
    $password = password_hash($data['usrPass'], PASSWORD_ARGON2ID);
    $role = $data['usrRole'];
    $branch = $data['usrBranch'];;
    $email = $data['usrEmail'];
    $status = $data['usrStatus'];
    $fcp = $data['usrFCP'];

    try {
        $conn->beginTransaction();
        if(empty($data['usrPass'])){
            $stmt3 = $conn->prepare("UPDATE users SET usrRole=?, usrStatus=?, usrBranch=?, usrFCP=? WHERE usrName=? or usrEmail=?");
            $stmt3->execute([$role, $status, $branch, $fcp, $username, $email]);
            // echo"password Not changed";
        }else{
            $stmt3 = $conn->prepare("UPDATE users SET usrPass=?, usrRole=?, usrStatus=?, usrBranch=?, usrFCP=? WHERE usrName=? or usrEmail=?");
            $stmt3->execute([$password, $role, $status, $branch, $fcp, $username, $email]);
            $tran = $stmt3->fetch(PDO::FETCH_ASSOC);
            // echo"password changed";
        }
        $conn->commit();
        $value->generateUserActivityLog(
            $logedInUser, 
            'User',
            "Modify User: $username, FCP: $fcp, Role: $role, Branch: $branch, Status: $status"
        );
        echo json_encode(["msg" => "success", "username" => $username]);

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
function sendVerification($recieverEmail, $recieverName, $fevToken){
    global $mail;
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply.zaitoon@gmail.com';           // your Gmail address
        $mail->Password   = 'lcpp qwzz mjev ikxp';   // 16-char App Password from Google
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl' on port 465
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('noreply.zaitoon@gmail.com', 'Zaitoon Soft');
        $mail->addAddress($recieverEmail, $recieverName);

        // Content
        $link = "http://52.21.3.100/rapi/HR/verify.php?token=$fevToken";
        $mail->isHTML(false);
        $mail->Subject = 'Please Verify Your Email';
        $mail->Body    = "Hello: recieverName\nClick this link to verify your mail\n $link";

        $mail->send();
        // echo "Message sent\n";
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->getMessage();
    }
}
function updateRole($dataList){

}

?>
