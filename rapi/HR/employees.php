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
        get_employee();
        break;
    case 'POST':
        add_employee();
        break;
    case 'PUT':
        update_employee();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}


function get_employee() {
    global $conn;
    try {

        if (isset($_GET['empID']) && !empty($_GET['empID'])) {

            // Use SQL for single record
            $sql = "SELECT * from employees emp
                    join personal pr on pr.perID = emp.empPersonal WHERE emp.empID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['empID'], PDO::PARAM_INT);
        
        }elseif(isset($_GET['cat']) && !empty($_GET['cat'])){
            // Use SQL for single record
            $sql = "SELECT * from employees emp
                    join personal pr on pr.perID = emp.empPersonal WHERE empPosition = :cat";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':cat', $_GET['cat']);
        } else {
            // Use default SQL for all records
            $sql = "SELECT * from employees emp
                    join personal pr on pr.perID = emp.empPersonal order by emp.empID desc";
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

function add_employee() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $personal = $data['empPersonal'];
    $account = $data['empSalAccount'];
    $email = $data['empEmail'];
    $hiredDate = $data['empHireDate'];
    $department = $data['empDepartment'];
    $position = $data['empPosition'];
    $salCalcBase = $data['empSalCalcBase'];
    $paymentMethod = $data['empPmntMethod'];
    $salaryAmount = $data['empSalary'];
    $TIN = $data['empTaxInfo'];
    $status = 1;

    try {
        $stmt0 = $conn->prepare("SELECT count(*) from employees where empPersonal = ?");
        $stmt0->execute([$personal]);
        $result = $stmt0->fetchColumn();

        if($result > 0){
            echo json_encode(["msg" => "exist"], JSON_PRETTY_PRINT);
            exit();
        }


        $conn -> beginTransaction();

        $stmt1 = $conn->prepare("INSERT into employees (empPersonal, empSalAccount, empEmail, empHireDate, empDepartment, empPosition, empSalCalcBase, empPmntMethod, empSalary, empTaxInfo, empStatus) 
        values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt1->execute([$personal, $account, $email, $hiredDate, $department, $position, $salCalcBase, $paymentMethod, $salaryAmount, $TIN, $status]);
        
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

function update_employee() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $empID = $data['empID'];
    $account = $data['empSalAccount'];
    $email = $data['empEmail'];
    $hiredDate = $data['empHireDate'];
    $department = $data['empDepartment'];
    $position = $data['empPosition'];
    $salCalcBase = $data['empSalCalcBase'];
    $paymentMethod = $data['empPmntMethod'];
    $fingerPrint = $data['empFingerprint'];
    $salaryAmount = $data['empSalary'];
    $TIN = $data['empTaxInfo'];
    $status = $data['empStatus'];
    $endDate = $data['empEndDate'];

    // if (
    //     empty($data['empID']) ||
    //     empty($data['empSalAccount']) ||
    //     empty($data['empEmail']) ||
    //     empty($data['empHireDate']) ||
    //     empty($data['empDepartment']) ||
    //     empty($data['empPosition']) ||
    //     empty($data['empSalCalcBase']) ||
    //     empty($data['empPmntMethod']) ||
    //     empty($data['empFingerprint']) ||
    //     empty($data['empSalary']) ||
    //     empty($data['empTaxInfo']) ||
    //     empty($data['empStatus']) ||
    //     empty($data['empEndDate'])
    // ) {
    //     echo json_encode(["msg" => "empty"]);
    //     exit;
    // }


    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE employees set empSalAccount=?, empEmail=?, empHireDate=?, empDepartment=?, empPosition=?, empSalCalcBase=?, empPmntMethod=?,
        empFingerprint=?, empSalary=?, empTaxInfo=?, empStatus=?, empEndDate=? where empID=?");
        $stmt->execute([$account, $email, $hiredDate, $department, $position, $salCalcBase, $paymentMethod, $fingerPrint, $salaryAmount, $TIN, $status, $endDate, $empID]);
        $conn->commit();
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