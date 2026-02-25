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
        get_attendence();
        break;
    case 'POST':
        add_attendence();
        break;
    case 'PUT':
        update_attendence();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}


function get_attendence() {
    global $conn;
    try {
        if (isset($_GET['date']) && !empty($_GET['date'])) {
            $sql = "SELECT  
                emaID, emaEmployee, empPosition, concat(pr.perName, ' ', pr.perLastName) as fullName, emaDate, emaCheckedIn, emaCheckedOut, emaStatus
                from employeeAttendence ea 
                join employees em on em.empID = ea.emaEmployee
                join personal pr on pr.perID = em.empPersonal
                WHERE emaDate = :dt";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':dt', $_GET['date']);
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

function add_attendence() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $dt = $data['emaDate'];
    $checkIn = $data['emaCheckedIn'];
    $checkOut = $data['empCheckedOut'];

    $entryDateTime = date("Y-m-d H:i:s");

    $start = strtotime($checkIn);
    $end   = strtotime($checkOut);

    $hours = ($end - $start) / 3600;

    try {
        $stmt0 = $conn->prepare("SELECT count(*) from employeeAttendence where emaDate = ?");
        $stmt0->execute([$dt]);
        $result = $stmt0->fetchColumn();
        if($result > 0){
            echo json_encode(["msg" => "exist"], JSON_PRETTY_PRINT);
            exit();
        }


        $conn -> beginTransaction();

        $stmt1 = $conn->prepare("INSERT INTO employeeAttendence (emaEmployee, emaDate, emaCheckedIn, emaCheckedOut, emaWorkHours, emaStatus, emaEntryTime) 
        SELECT empID, ?, ?, ?, ?, 'Present', ? FROM employees WHERE empStatus = 1");
        $stmt1->execute([$dt, $checkIn, $checkOut, $hours, $entryDateTime]);
        
        $conn->commit();

        $value->generateUserActivityLog(
            $user, 
            "Attendence",
            "Add - Date: $dt, Checkin Time: $checkIn, CheckOut Time: $checkOut, Total Hours: $hours"
        );

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

function update_attendence() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $records = $data['records'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE employeeAttendence set emaEmployee=?, emaCheckedIn=?, emaCheckedOut=?, emaStatus=? where emaID=?");

        foreach($data['records'] as $rec){
            $emaStatus = $rec['emaStatus'];

            if($emaStatus == "Absent"){
                 $stmt->execute([$rec['emaEmployee'], '00:00:00', '00:00:00', $rec['emaStatus'], $rec['emaID']]);
            }else{
                $stmt->execute([$rec['emaEmployee'], $rec['emaCheckedIn'], $rec['emaCheckedOut'], $rec['emaStatus'], $rec['emaID']]);
            }
        }
       
        $conn->commit();

        $value->generateUserActivityLog(
            $user, 
            "Attendence",
            "Modify"
        );

        echo json_encode(["msg" => "success"]);
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