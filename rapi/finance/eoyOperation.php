
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
        get_profitLoss();
        break;
    case 'POST':
        process_EOY();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_profitLoss() {
    global $conn;

    try {
        $sql = "SELECT 
                td.trdBranch,
                ac.accNumber as account_number,
                ac.accName as account_name,
                td.trdCcy as currency,
                case when ag.acgCategory = 3 then 'Income' when ag.acgCategory = 4 then 'Expense' end as category,
                CASE WHEN SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, 'USD') ELSE -td.trdAmount * getRate(td.trdCcy, 'USD') END) < 0
                    THEN ABS(SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, 'USD') ELSE -td.trdAmount * getRate(td.trdCcy, 'USD') END )) ELSE 0 END AS debit,
                    
                CASE WHEN SUM(CASE  WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, 'USD') ELSE -td.trdAmount * getRate(td.trdCcy, 'USD') END ) > 0 
                    THEN SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, 'USD') ELSE -td.trdAmount * getRate(td.trdCcy, 'USD') END) ELSE 0 END AS credit
            from trnDetails td
            join transactions tr on tr.trnReference = td.trdReference
            join accounts ac on ac.accNumber = td.trdAccount
            join accountCategory ag on ag.acgID	= ac.accCategory
            where ag.acgCategory between 3 and 4
            group by td.trdBranch, ac.accNumber, ac.accName, td.trdCcy, ag.acgName";
        $stmt = $conn->prepare($sql);
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

function process_EOY() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $remark = $data['remark'];
    $pBranch = $data['parkingBranch'];


    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $entryDateTime = date("Y-m-d H:i:s");

    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $type = "PLCL";
    $trnRef = $value->generateTrnRef($branch, $type);
    $stateText = "Pending";
    $status = 0;
    $authUser = NULL;
    $remainAmount  = 0;
    $retainedAC = "20202026";



    try {
        $conn->beginTransaction();

        // Insert into transactions
        $stmt1 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt1->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

        // Prepare transaction details insert
        $stmt2 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt3 = $conn->prepare("SELECT td.trdBranch, td.trdAccount, td.trdCcy,
                SUM(CASE WHEN tr.trnStatus=1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount ELSE -td.trdAmount END ELSE 0 END) as available,
                SUM(CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount ELSE -td.trdAmount END) as current
            from trnDetails td
            join transactions tr on tr.trnReference = td.trdReference
            join accounts ac on ac.accNumber = td.trdAccount
            join accountCategory ag on ag.acgID	= ac.accCategory
            where ag.acgCategory between 3 and 4
            group by td.trdBranch, td.trdAccount, td.trdCcy");
        $stmt3->execute();
        $plBalance = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        foreach($plBalance as $pl){
            if($pl['available'] != $pl['current']){
                echo json_encode(["msg" => "pending", "account" => $pl['trdAccount']]);
                $conn->rollBack();
                exit;
            }
            $remainAmount += $pl['available'];
            $DrCr = ($pl['available'] > 0) ? 'Dr' : 'Cr';
            $stmt2->execute([$trnRef, $pl['trdCcy'], $pl['trdBranch'], $pl['trdAccount'], $DrCr, abs($pl['available']), $remark, $entryDateTime]);
            usleep(50000);
            $entryDateTime = date("Y-m-d H:i:s");
        }


        $stmt4 = $conn->prepare("SELECT trdCcy, trdBranch, sum(case when trdDrCr='Dr' then trdAmount else -trdAmount end) as total
            from trnDetails where trdReference = ?
            group by trdCcy, trdBranch");
        $stmt4->execute([$trnRef]);
        $totalPL = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        foreach($totalPL as $tPL){
            $totalBalance = $tPL['total'];
            $stmt2->execute([$trnRef, $tPL['trdCcy'], $tPL['trdBranch'], $retainedAC, ($totalBalance > 0)?'Cr':'Dr', abs($totalBalance), $remark, $entryDateTime]);
        }
        $value->generateUserActivityLog(
            $user, 
            "PLCL",
            "$trnRef"
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

function update_glAccounts(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $accNumber = $data['accNumber'];
    $accName = $data['accName'];

    try {
        $stmt = $conn->prepare("select count(*) from accounts where accName = ?");
        $stmt->execute([$accName]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE accounts SET accName=? WHERE accNumber=?");
        $stmt1->execute([$accName, $accNumber]);

        $value->generateUserActivityLog(
            $user, 
            "GL Update",
            "$accNumber, $accName"
        );

        $conn->commit();
        echo json_encode(["msg" => "success", "accountNo" => $accNumber]);

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

function delete_glAccounts(){
    global $conn;
    $gl = $_GET['gl'];

    $stmt = $conn->prepare("select count(*) as total from trnDetails where trdAccount = ?");
    $stmt->execute([$gl]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result['total'];
    if($total > 0){
        echo json_encode(["msg" => "dependent", "error" => "the account you are trying to delete is have transaction record and not possible to delete"]);
        exit();
    }
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("delete from accounts where accNumber=?");
        $stmt->execute([$gl]);

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

?>