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
        get_attendence();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_attendence() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $from     = $input['fromDate'] ?? null;
    $to       = $input['toDate'] ?? null;
    $employee  = $input['empID'] ?? null;
    $status   = $input['status'] ?? null;

    try {
        $sql = "SELECT emaID, emaEmployee, concat(pr.perName, ' ', pr.perLastName) as fullName, empPosition, emaDate, emaCheckedIn, emaCheckedOut, 
            round(TIME_TO_SEC(TIMEDIFF(ea.emaCheckedOut, ea.emaCheckedIn)) / 3600, 2) as totalhours, emaStatus
            from employeeAttendence ea 
            join employees em on em.empID = ea.emaEmployee
            join personal pr on pr.perID = em.empPersonal
            WHERE emaDate between :fDate AND :tDate";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($status)) {
            $sql .= " AND emaStatus = :txt";
            $params[':txt'] = $status;
        }

        if (!empty($employee)) {
            $sql .= " AND empID = :eID";
            $params[':eID'] = $employee;
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

?>