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
        load_projects();
        break;
    case 'POST':
        create_project();
        break;
    case 'PUT':
        update_project();
        break;
    case 'DELETE':
        delete_project();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_projects() {
    global $conn;
    try {
        $sql = "SELECT prjID, prjName, prjLocation, prjDetails, prjDateLine, prjEntryDate,
                p.prjOwner, concat(pr.perName, ' ', pr.perLastName) AS prjOwnerfullName, prjOwnerAccount, ad.actCurrency,
                p.prjStatus
            FROM projects p
            JOIN accounts a ON a.accNumber = p.prjOwnerAccount
            JOIN accountDetails ad ON ad.actAccount = a.accNumber
            JOIN personal pr ON pr.perID = p.prjOwner";
        if (isset($_GET['prjID']) && !empty($_GET['prjID'])) {
            $prjID = $_GET['prjID'];
            $sql .= " WHERE prjID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $prjID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        } else {
            $sql .= " ORDER BY prjID DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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

function create_project() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $user = $data['usrName'];
    $name = $data['prjName'];
    $location = $data['prjLocation'];
    $customer = $data['prjOwner'];
    $account = $data['prjOwnerAccount'];
    $details = $data['prjDetails'];
    $dateline = $data['prjDateLine'];
    $entryDateTime = date("Y-m-d H:i:s");
    $status = 0;
    


    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO projects (prjName, prjLocation, prjOwner, prjOwnerAccount, prjDetails, prjDateLine, prjEntryDate, prjStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $location, $customer, $account, $details, $dateline, $entryDateTime, $status]);

        $value->generateUserActivityLog(
            $user, 
            "Project",
            "Created - Name: $name, Location: $location, Owner: $customer, Account: $account, Details: $details, Dateline: $dateline"
        );

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

function update_project(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $prjID = $data['prjID'];
    $name = $data['prjName'];
    $location = $data['prjLocation'];
    $customer = $data['prjOwner'];
    $account = $data['prjOwnerAccount'];
    $details = $data['prjDetails'];
    $dateline = $data['prjDateLine'];
    $status = $data['prjStatus'];
    $entryDateTime = date("Y-m-d H:i:s");
    

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE projects SET prjName = ?, prjLocation = ?, prjOwner = ?, prjOwnerAccount = ?, prjDetails = ?, prjDateLine = ?, prjEntryDate = ?, prjStatus = ? WHERE prjID = ?");
        $stmt->execute([$name, $location, $customer, $account, $details, $dateline, $entryDateTime, $status, $prjID]);

        if($status == 1){
            $stmt1 = $conn->prepare("SELECT p.prjID, trdCcy,
                    sum(case when pp.prpType = 'Income' and td.trdDrCr = 'Cr' then td.trdAmount else 0 end) as paid,
                    sum(case when pp.prpType = 'Entry' and td.trdDrCr = 'Dr' then td.trdAmount else 0 end) as actual
                FROM projectPayments pp
                JOIN transactions t on t.trnReference = pp.prpTrnRef
                JOIN trnDetails td on td.trdReference = t.trnReference
                JOIN projects p on p.prjID = pp.prpProjectID
                WHERE pp.prpType != 'Expense' and p.prjID = ?
                GROUP BY p.prjID, trdCcy");
            $stmt1->execute([$prjID]);
            $paymentData = $stmt1->fetch(PDO::FETCH_ASSOC);
            $paid = $paymentData['paid'] ?? 0;
            $actual = $paymentData['actual'] ?? 0;
            if($paid == $actual){
                $type = "PRJT";
                $payableGL = 20202027;
                $incomeGL = 30303036;
                $userResult = $value->getUserDetails($user);
                $branch = $userResult['usrBranch'];
                $usrID = $userResult['usrID'];
                $trnRef = $value->generateTrnRef($branch, $type);
                $limit = $value->getBranchAuthLimit($branch, $ccy);
                if($paid <= $limit){
                    $authUser = $usrID;
                    $stateText = "Authorized";
                    $trnStatus = 1;
                }else{
                    $authUser = NULL;
                    $stateText = "Pending";
                    $trnStatus = 0;
                }

                $remark = "Income from project: $name (PrjID: $prjID), Customer: $customer, Account: $account, Dateline: $dateline";
                $stmt2 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt2->execute([$trnRef, $type, $trnStatus, $stateText, $usrID, $authUser, $entryDateTime]);

                $stmt3 = $conn->prepare("INSERT INTO projectPayments (prpProjectID, prpType, prpTrnRef) VALUES (?, ?, ?)");
                $stmt3->execute([$prjID, 'Income', $trnRef]);
                $payID = $conn->lastInsertId();

                $stmt4 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt4->execute([$trnRef, $ccy, $branch, $payableGL, "Dr", $amount, $remark, $entryDateTime]);
                $stmt4->execute([$trnRef, $ccy, $branch, $incomeGL, "Cr", $amount, $remark, $entryDateTime]);

            }
        }

        $value->generateUserActivityLog(
            $user, 
            "Project",
            "Updated - projectID: $prjID, Name: $name, Location: $location, Owner: $customer, Account: $account, Details: $details, Dateline: $dateline"
        );

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

function delete_project(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $prjID = $data['prjID'];

    try {
        $conn->beginTransaction();
        $stmt0 = $conn->prepare("SELECT * from projectDetails where pjdProject = ?");
        $stmt0->execute([$prjID]);
        $project = $stmt0->fetch(PDO::FETCH_ASSOC);
        if($project){
            echo json_encode(["msg" => "dependency"], JSON_PRETTY_PRINT);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM projects WHERE prjID = ?");
        $stmt->execute([$prjID]);

        $value->generateUserActivityLog(
            $user, 
            "Project",
            "Deleted - projectID: $prjID"
        );

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