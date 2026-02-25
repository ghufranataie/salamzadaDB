
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *"); // allow all origins (or specify your Flutter domain)
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

require_once "../db.php";
require_once "../functions.php";

$db = new Database();
$conn = $db->getConnection();
$value = new DataValues($conn);
$data = json_decode(file_get_contents("php://input"), true);
$request_method = $_SERVER["REQUEST_METHOD"];
$alfCounter = 0;

switch ($request_method) {
    case 'GET':
        get_login();
        break;
    case 'POST':
        login();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_login() {
    global $conn;
    try {
        if (!empty($_GET['username'])) {

            $sql = "SELECT 
                        userPermissions.uprRole,
                        users.usrName,
                        userPermissions.uprStatus,
                        roleSubGroup.rsgName
                    FROM userPermissions
                    JOIN users ON users.usrID = userPermissions.uprUserID
                    JOIN roleSubGroup ON roleSubGroup.rsgID = userPermissions.uprRole
                    WHERE users.usrName = :username";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $_GET['username'], PDO::PARAM_STR); // FIXED
            $stmt->execute();

            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
        }
    } 
    catch (PDOException $e) {
        echo json_encode(["msg" => $e->getMessage()]);
    }
}

function login(){
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);

    $username = $data['usrName'];
    $password = $data['usrPass'];
    

    try {
        $stmt = $conn->prepare("SELECT * from users where usrName=? or usrEmail=?");
        $stmt->execute([$username, $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $status = $row['usrStatus'];
            $fcp = $row['usrFCP'];
            $hashedPass = $row['usrPass'];
            $verification = $row['usrToken'];
            $verifyPass = password_verify($password, $hashedPass);

            if(!$verifyPass){
                $value->generateUserActivityLog(
                    $username, 
                    'User Login',
                    "Failed: Attemping login with incorrect password"
                );
                echo json_encode(["msg" => "incorrect"]);
                exit;
            }

            if($status == false){
                $value->generateUserActivityLog(
                    $username, 
                    'User Login',
                    "Not Allowed: Attemping login while user is blocked"
                );
                echo json_encode(["msg" => "blocked"]);
                exit;
            }

            if($verification != 'verified'){
                $value->generateUserActivityLog(
                    $username, 
                    'User Login',
                    "Unverified: Attemping login while user is not verified"
                );
                echo json_encode(["msg" => "unverified"]);
                exit;
            }

            if($fcp == 1){
                $value->generateUserActivityLog(
                    $username, 
                    'User Login',
                    "FCP: Login but user forced to change password"
                );
                echo json_encode(["msg" => "fcp"]);
                exit;
            }

            $stmt1 = $conn->prepare("SELECT usrName, usrID, concat(perName, ' ', perLastName) as usrFullName, perPhoto, usrRole, usrEmail, perPhone, usrEntryDate, usrBranch, brcName from users
                                    join branch on branch.brcID = users.usrBranch
                                    join personal on personal.perID = users.usrOwner
                                    Where usrName = ? or usrEmail =?");
            $stmt1->execute([$username, $username]);
            $data1 = $stmt1->fetch(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("SELECT userPermissions.uprRole, userPermissions.uprStatus, roleSubGroup.rsgName FROM userPermissions
                                    JOIN users ON users.usrID = userPermissions.uprUserID
                                    JOIN roleSubGroup ON roleSubGroup.rsgID = userPermissions.uprRole
                                    WHERE users.usrName = ? or usrEmail =?");
            $stmt2->execute([$username, $username]);
            $data2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $stmt3 = $conn->prepare("SELECT comID, comName, comLicenseNo, comSlogan, comDetails, comPHone, comEmail, comWebsite, comOwner, comFB, 
                comInsta, comWhatsapp, comVerify, comLocalCcy, comTimeZone,
                concat(a.addName, ', ', a.addCity, ', ', a.addProvince, ', ', a.addCountry) as comAddress 
                from companyProfile c
                join branch b on b.brcCompany = c.comID
                join users u on u.usrBranch = b.brcID
                join address a on a.addID = c.comAddress
                where u.usrName = ? or u.usrEmail = ?");
            $stmt3->execute([$username, $username]);
            $data3 = $stmt3->fetch(PDO::FETCH_ASSOC);

            $data1["permissions"] = $data2;
            $data1["company"] = $data3;


            $value->generateUserActivityLog(
                $username, 
                'User Login',
                "Success: User Successfully logged in with correct credentials"
            );

            // var_dump($data1);

            // 4️⃣ Output
            echo json_encode($data1, JSON_PRETTY_PRINT);
            
        }else{
            echo json_encode(["msg" => "incorrect"]);
        }
    } catch (\Throwable $th) {
        // $conn->rollBack();
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