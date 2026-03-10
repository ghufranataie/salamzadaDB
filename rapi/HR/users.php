
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

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // __DIR__ = /var/www/html/rapi/HR
$dotenv->load();


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
    // case 'DELETE':
    //     sendVerification("ghufranataie@hotmail.com", "Ghufran", "1234567890abcdef");
    //     break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function sendVerification($recieverEmail, $recieverName, $fevToken) {
    $mail = new PHPMailer(true);
    $apiKey = $_ENV['SENDGRID_API_KEY'] ?? null;
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.sendgrid.net';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'apikey'; // literally 'apikey'
        $mail->Password   = $apiKey;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('noreply.zaitoon@gmail.com', 'Zaitoon Soft');
        $mail->addAddress($recieverEmail, $recieverName);

        $link = "http://52.21.3.100/rapi/HR/verify.php?token=$fevToken";
        $mail->isHTML(false);
        $mail->Subject = 'Please Verify Your Email';
        $mail->Body    = "Hello $recieverName,\nClick this link to verify your email:\n$link";

        $mail->send();
        return true;
    }  catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}

function get_users() {
    global $conn;
    try {
        if (isset($_GET['perID']) && !empty($_GET['perID'])) {
            // Use SQL for single record
            $sql = "SELECT usrID, concat(perName, ' ', perLastName) as usrFullName, perPhoto, usrName, rolName as usrRole, rl.rolID, usrStatus, usrBranch, usrEmail, perPhone, usrToken, usrFCP, usrEntryDate from users
                join personal on personal.perID = users.usrOwner
                join roles rl on rl.rolID = users.usrRole
                where perID = :id and usrRole != 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['perID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT usrID, concat(perName, ' ', perLastName) as usrFullName, perPhoto, usrName, rolName as usrRole, rl.rolID, usrStatus, usrBranch, usrEmail, perPhone, usrToken, usrFCP, usrEntryDate from users
                join personal on personal.perID = users.usrOwner
                join roles rl on rl.rolID = users.usrRole
                where usrRole != 1";
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
    $role = $data['rolID'];
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

        $stmt = $conn->prepare("SELECT rp.rpRoleNameID as uprRole, rp.rpStatus as uprStatus from rolePermission rp
            join roles r on r.rolID = rp.rpRoleID
            where rolID = ?");
        $stmt->execute([$role]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt1 = $conn->prepare(
            "INSERT INTO users (usrName, usrPass, usrOwner, usrRole, usrBranch, usrEmail, usrToken, usrFCP, usrStatus, usrEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if($fev == true){
            $stmt1->execute([$username, $password, $owner, $role, $branch, $email, $token, $fcp, $status, $entryDateTime]);
            sendVerification($email, $username, $token);
            $verify = "required";
         }else{
            $verify = "verified";
            $stmt1->execute([$username, $password, $owner, $role, $branch, $email, $verify, $fcp, $status, $entryDateTime]);
         }

        $usrID = $conn->lastInsertId();
        $stmt3 = $conn->prepare("INSERT into userPermissions (uprUserID, uprRole, uprStatus) select ?, rsgID, 0 from roleSubGroup");
        $stmt3->execute([$usrID]);

        $stmt2 = $conn->prepare("UPDATE userPermissions set uprStatus= :uprStatus where uprUserID= :uprUserID and uprRole= :uprRole");
        foreach($permissions as $item){
            $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
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
    $role = $data['rolID'];
    $branch = $data['usrBranch'];;
    $email = $data['usrEmail'];
    $status = $data['usrStatus'];
    $fcp = $data['usrFCP'];

    try {
        $stmtUsers = $conn->prepare("SELECT * from users where usrName = ?");
        $stmtUsers->execute([$username]);
        $userResult = $stmtUsers->fetch(PDO::FETCH_ASSOC);
        $oldRole = $userResult['usrRole'];
        $usrID = $userResult['usrID'];
        
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

        if(!empty($role) && $role != $oldRole){
            $stmt = $conn->prepare("SELECT rp.rpRoleNameID as uprRole, rp.rpStatus as uprStatus from rolePermission rp
                join roles r on r.rolID = rp.rpRoleID
                where rolID = ?");
            $stmt->execute([$role]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("UPDATE userPermissions set uprStatus= :uprStatus where uprUserID= :uprUserID and uprRole= :uprRole");
            foreach($permissions as $item){
                $stmt2->execute([":uprStatus" => $item["uprStatus"], ":uprUserID" => $usrID, ":uprRole" => $item["uprRole"]]);
            }
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

?>