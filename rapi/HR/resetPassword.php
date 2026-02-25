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
        search_OTP();
        break;
    case 'POST':
        search_account();
        break;
    case 'PUT':
        change_Password();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function search_OTP() {
    global $conn, $value;
   
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    
    $otp = $_GET['otp'];

    try {
        if(isset($_GET['otp']) && !empty($_GET['otp'])){
            $otp = $_GET['otp'];
            $stmt = $conn->prepare("SELECT rstExpiry, rstStatus, usrName, usrEmail, concat(perName, ' ', perLastName) as fullName 
                from resetPassword rp 
                join users on rp.rstUserID = users.usrID 
                join personal on users.usrOwner = personal.perID 
                where rstOTP = ?");
            $stmt->execute([$otp]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row){
                echo json_encode(["msg" => "invalid"], JSON_PRETTY_PRINT);
                exit;
            }else{
                $entryDateTime = date("Y-m-d H:i:s");
                // echo "Current Time: $entryDateTime \nExpiry Time: " . $row['rstExpiry'] . "\n";
                if($row['rstExpiry'] > $entryDateTime && $row['rstStatus'] == 1){
                    echo json_encode($row, JSON_PRETTY_PRINT);
                    $value->generateUserActivityLog(
                        $row['usrName'], 
                        "User",
                        "User Successfully Used OTP: $otp and ready to change password"
                    );
                }else{
                    echo json_encode(["msg" => "expired"], JSON_PRETTY_PRINT);
                    exit;
                }
            }
        }
    } catch (\Throwable $th) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile(),
            "trace" => $th->getTrace()
        ]);
    }
}

function search_account() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $usrName = $data['identity'];

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $entryDateTime = date("Y-m-d H:i:s");
    
    try {
        $stmt = $conn->prepare("SELECT * from users join personal on users.usrOwner = personal.perID where usrName=? or usrEmail=?");
        $stmt->execute([$usrName, $usrName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $fullName = $row['perName'] . " " . $row['perLastName'];
            $otp = random_int(100000, 999999);
            $stmt1 = $conn->prepare("INSERT Into resetPassword (rstUserID, rstOTP, rstExpiry, rstEntryTime) values (?, ?, ?, ?)");
            $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
            $stmt1->execute([$row['usrID'], $otp, $expiry, $entryDateTime]);
            $sendEmail = sendEmail($row['usrEmail'], $fullName, $otp);
            if(!$sendEmail){
                echo json_encode(["msg" => "failed to Send Email"], JSON_PRETTY_PRINT);
                exit;
            }else{
                echo json_encode(["msg" => "success", "timeLimit" => $expiry, "email" => $row['usrEmail']], JSON_PRETTY_PRINT);
                $value->generateUserActivityLog(
                    $row['usrName'], 
                    "User",
                    "User Request OTP to Reset Password Requested OTP: $otp"
                );
            }
        }else{
            echo json_encode(["msg" => "not found"], JSON_PRETTY_PRINT);
        }
    } catch (\Throwable $th) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile(),
            "trace" => $th->getTrace()
        ]);
    }
}

function change_Password() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $otp = $data['otp'];
    $usrName = $data['usrName'];
    $newPass = password_hash($data['usrPass'], PASSWORD_ARGON2ID);
    $fcp = 0;
    $alf = 0;
    
    try {
        $conn->beginTransaction();

        $stmt0 = $conn->prepare("UPDATE resetPassword set rstStatus = 0 where rstOTP = ?");
        $stmt0->execute([$otp]);

        $stmt1 = $conn->prepare("UPDATE users set usrPass=?, usrFCP=?, usrALFCounter=? where usrName =? or usrEmail=?");
        $result = $stmt1->execute([$newPass, $fcp, $alf, $usrName, $usrName]);
        if($result){
            echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);
        }else{
            echo json_encode(["msg" => "failed"], JSON_PRETTY_PRINT);
            $conn->rollBack();
            exit;
        }
        $value->generateUserActivityLog(
            $usrName, 
            "User",
            "User Changed Password using OTP: $otp with changeing ALF Counter to $alf and FCP to $fcp"
        );

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


function sendEmail($recieverEmail, $recieverName, $fevToken) {
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

        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Verification Code - Zaitoon Soft';

        $mail->Body = "
        <!DOCTYPE html>
            <html>
                <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>OTP Verification</title>
                </head>
                <body style='margin:0; padding:0; background-color:#f4f6f9; font-family: Arial, sans-serif;'>

                <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f4f6f9; padding:40px 0;'>
                <tr>
                <td align='center'>

                <table width='500' cellpadding='0' cellspacing='0' style='background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 5px 20px rgba(0,0,0,0.08);'>

                <tr>
                <td style='background:#1f2937; padding:20px; text-align:center; color:#ffffff; font-size:20px; font-weight:bold;'>
                Zaitoon Soft
                </td>
                </tr>

                <tr>
                <td style='padding:40px 30px; text-align:center;'>

                <h2 style='margin:0; color:#111827;'>OTP Verification</h2>

                <p style='color:#6b7280; font-size:15px; margin-top:15px;'>
                Hello <strong>$recieverName</strong>,
                </p>

                <p style='color:#6b7280; font-size:15px;'>
                Use the verification code below to complete your request.
                This code will expire in 10 minutes.
                </p>

                <div style='margin:30px 0;'>
                <span style='display:inline-block; background:#f3f4f6; padding:15px 30px; font-size:28px; letter-spacing:5px; font-weight:bold; color:#111827; border-radius:8px;'>
                $fevToken
                </span>
                </div>

                <p style='color:#9ca3af; font-size:13px;'>
                If you did not request this code, please ignore this email.
                </p>

                </td>
                </tr>

                <tr>
                <td style='background:#f9fafb; padding:15px; text-align:center; font-size:12px; color:#9ca3af;'>
                © " . date('Y') . " Zaitoon Soft. All rights reserved.
                </td>
                </tr>

                </table>

                </td>
                </tr>
                </table>
                </body>
            </html>
        ";

        $mail->send();
        return true;
    }  catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}

?>