
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
        get_reminders();
        break;
    case 'POST':
        add_reminders();
        break;
    case 'PUT':
        update_reminders();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_reminders() {
    global $conn, $value;
    $params=[];
    try {
        $sql = "SELECT rm.*, ad.actCurrency as currency, br.brcName, concat(pr.perName, ' ', pr.perLastName) as fullName, pr.perPhone, pr.perEmail
            from reminders rm
            join branch br on br.brcID = rm.rmdBranch
            left join accounts ac on ac.accNumber = rm.rmdAccount
            left join accountDetails ad on ad.actAccount = ac.accNumber
            left join personal pr on pr.perID = ad.actSignatory";

        if (isset($_GET['rmdID']) && !empty($_GET['rmdID'])) {        
            $sql .= " WHERE rmdID = :id order by rmdID ASC";
            $params[':id'] = $_GET['rmdID'];
        
        }elseif(isset($_GET['alerts']) && !empty($_GET['alerts'])){

            date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
            $currentDate = date("Y-m-d");
            $sql .= " WHERE rmdAlertDate <= :cDate and rmdStatus = 0 order by rmdID ASC";
            $params[':cDate'] = $currentDate;
        }else{
            $sql .= " WHERE rmdStatus = 0 or (rmdStatus = 1 AND rmdAlertDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH))";
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
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

function add_reminders() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['user'];
    $name = $data['rmdName'];
    $account = $data['rmdAccount'];
    $amount = $data['rmdAmount'];
    $details = $data['rmdDetails'];
    $date = $data['rmdAlertDate'];

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];

    try {
        $conn -> beginTransaction();

        $stmt = $conn->prepare("INSERT into reminders (rmdBranch, rmdName, rmdAccount, rmdAmount, rmdDetails, rmdAlertDate) values (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$branch, $name, $account, $amount, $details, $date]);

        $value->generateUserActivityLog(
            $user, 
            "Reminder",
            "Reminder Set for account: $account, Amount: $amount, Alert Date: $date"
        );
        
        $conn->commit();

        echo json_encode(["msg" => "success", "reminder" => $name], JSON_PRETTY_PRINT);
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
function update_reminders(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['user'];
    $id = $data['rmdID'];
    $name = $data['rmdName'];
    $account = $data['rmdAccount'];
    $amount = $data['rmdAmount'];
    $details = $data['rmdDetails'];
    $date = $data['rmdAlertDate'];
    $status = $data['rmdStatus'];

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE reminders SET rmdName=?, rmdAccount=?, rmdAmount=?, rmdDetails=?, rmdAlertDate=?, rmdStatus=? WHERE rmdID=?");
        $stmt->execute([$name, $account, $amount, $details, $date, $status, $id]);

        $value->generateUserActivityLog(
            $user, 
            "Reminder",
            "Reminder Modify for ID: $id, Account: $account, Amount: $amount, Alert Date: $date"
        );

        $conn->commit();
        echo json_encode(["msg" => "success", "reminder updated" => $name]);
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