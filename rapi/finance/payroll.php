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
        get_payroll();
        break;
    case 'POST':
        post_payroll();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}


function get_payroll() {
    global $conn;
    try {
        if (isset($_GET['date']) && !empty($_GET['date'])) {
            $sql = "SELECT emaEmployee as perID, fullName, work_month as monthYear, empSalAccount as salaryAccount,
                empSalary as salary, actCurrency as currency, empSalCalcBase as calculationBase, days as totalDays, hoursInMonth, workedHours,
                ROUND(CASE WHEN empSalCalcBase = 'Hourly' THEN LEAST(workedHours, hoursInMonth) * empSalary
                        ELSE LEAST(workedHours, hoursInMonth) * (empSalary / (30 * hoursInMonth / days)) END, 4
                ) AS salaryPayable,
                ROUND(CASE WHEN empSalCalcBase = 'Hourly' THEN GREATEST(workedHours - hoursInMonth, 0) * empSalary
                        ELSE GREATEST(workedHours - hoursInMonth, 0) * (empSalary / (30 * hoursInMonth / days)) END, 4
                ) AS overtimePayable,

                ROUND(CASE WHEN empSalCalcBase = 'Hourly' THEN LEAST(workedHours, hoursInMonth) * empSalary
                        ELSE LEAST(workedHours, hoursInMonth) * (empSalary / (30 * hoursInMonth / days)) END, 4
                ) +
                ROUND(CASE WHEN empSalCalcBase = 'Hourly' THEN GREATEST(workedHours - hoursInMonth, 0) * empSalary
                        ELSE GREATEST(workedHours - hoursInMonth, 0) * (empSalary / (30 * hoursInMonth / days)) END, 4
                ) AS totalPayable,
                payment
            FROM vw_attendenceToPayroll
            WHERE work_month = :dt";

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

function post_payroll() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $records = $data['records'];

    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $type = "SLRY";
    
    $stateText = "Pending";
    $status = 0;
    $authUser = NULL;
    $remainAmount  = 0;
    $salaryExpenses = "40404045";

    $totalAmount = 0;
    $defaultCcy  = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);

    try {
        $conn -> beginTransaction();

        $ym = $data['records'][0]['monthYear'] ?? null;
        $stmt0 = $conn->prepare("SELECT prlTrnRef from payroll where prlMonthYear = ?");
        $stmt0->execute([$ym]);
        $prlData = $stmt0->fetch(PDO::FETCH_ASSOC);

        if ($prlData && !empty($prlData['prlTrnRef'])) {
            $trnRef = $prlData['prlTrnRef'];

            $deletePayroll = $conn->prepare(
                "DELETE FROM payroll WHERE prlMonthYear = ?"
            );
            $deletePayroll->execute([$ym]);

            $deleteTrnDetails = $conn->prepare(
                "DELETE FROM trnDetails WHERE trdReference = ?"
            );
            $deleteTrnDetails->execute([$trnRef]);

        }else{
            $trnRef = $value->generateTrnRef($branch, $type);
            // Insert into transactions
            $stmt1 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt1->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);
        }        

        // Prepare transaction details insert
        $stmt2 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        // Prepare the Payroll Insertion
        $stmt3 = $conn->prepare("INSERT INTO payroll (prlEmployee, prlMonthYear, prlTrnRef, prlEntryTime) VALUES (?,?,?,?)");

        $stmt4 = $conn->prepare("UPDATE transactions set trnStateText=?, trnStatus=?, trnAuthorizer=? where trnReference = ?");

        $stmt5 = $conn->prepare("DELETE from trnDetails where trdReference = ?");

        foreach($data['records'] as $rec){
            $payment = $rec['payment'];
            $perID = $rec['perID'];
            $ccy = $rec['currency'];
            $salAccount = $rec['salaryAccount'];
            $salary = $rec['salaryPayable'];
            $overtime = $rec['overtimePayable'];
            $totalPayable = $rec['totalPayable'];
            $days = $rec['totalDays'];
            $hours = $rec['workedHours'];
            $period = $rec['monthYear'];
            $remark = "Salary: $salary, Overtime: $overtime,  calculated for Total Days: $days, and Hours: $hours, for Period of: $period";

            $totalAmount += $rec['totalPayable']*$value->getCcyRate($ccy, $defaultCcy);

            if($payment == 1){
                $stmt3->execute([$perID, $period, $trnRef, $entryDateTime]);
                $stmt2->execute([$trnRef, $ccy, $branch, $salaryExpenses, 'Dr', $totalPayable, $remark, $entryDateTime]);
                $stmt2->execute([$trnRef, $ccy, $branch, $salAccount, 'Cr', $totalPayable, $remark, $entryDateTime]);
            }
        }
        
        if($totalAmount <= $limit){
            $authUser = $usrID;
            $status = 1;
            $stateText = "Authorized";
            $stmt4->execute([$stateText, $status, $authUser, $trnRef]);
        }
        $conn->commit();

        $value->generateUserActivityLog(
            $user, 
            "Salary",
            "Posting - Ref:$trnRef, For the Period: $period"
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
?>