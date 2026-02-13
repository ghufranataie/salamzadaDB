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
        get_reminders();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_reminders() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $department = $input['department'] ?? null;
    $account  = $input['account'] ?? null;
    $currency = $input['currency'] ?? null;
    $status   = $input['status'] ?? null;

    try {
        $sql = "SELECT 
            e.empID as eID,
            concat(p.perName, ' ', p.perLastName) as fullname,
            p.perDob as birthDate,
            p.perGender as gender,
            p.perENIDNo as nationalID,
            p.perPhone as phone,
            e.empEmail as email,
            e.empDepartment as department,
            e.empPosition as position,
            e.empSalary as salaryAmount,
            e.empSalAccount as salaryAccount,
            e.empSalCalcBase as salaryBasedOn,
            e.empTaxInfo as taxNo,
            e.empHireDate as hiredDate,
            case when empStatus = 1 then 'Hired' else 'Fired' end as state,
            e.empEndDate as firedDate
        from employees e
        join personal p on p.perID = e.empPersonal
        join address a on a.addID = p.perAddress
        WHERE e.empID != 0 ";

        $params = [];

        if (!empty($type)) {
            $sql .= " AND rm.rmdName = :rType";
            $params[':rType'] = $type;
        }

        if (isset($status) && $status !== '') {
            $sql .= " AND rm.rmdStatus = :statu";
            $params[':statu'] = $status;
        }

        if (!empty($account)) {
            $sql .= " AND rm.rmdAccount = :acc";
            $params[':acc'] = $account;
        }

        if (!empty($currency)) {
            $sql .= " AND ad.actCurrency = :ccy";
            $params[':ccy'] = $currency;
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