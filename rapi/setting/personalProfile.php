
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../db.php";
$db = new Database();
$conn = $db->getConnection();
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        get_personalProfile();
        break;
    case 'PUT':
        update_personalProfile();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}
function get_personalProfile() {
    global $conn;
    $user = $_GET['user'];
    try {
        $sql1 = "SELECT p.* FROM personal p join users u on u.usrOwner = p.perID where usrName = ?";
        $sql2 = "SELECT * From address where addID = ?";
        $sql3 = "SELECT usrID, usrName, usrEmail, b.brcName, b.brcID, u.usrFCP, u.usrToken as usrVerify, u.usrALFCounter, u.usrEntryDate,
            case when usrStatus = 1 then 'Enabled' else 'Disabled' end as usrStatus
            FROM users u
            join roles r on r.rolID = u.usrRole
            join branch b on b.brcID = u.usrBranch
            where usrName = ?";
        $sql4 = "SELECT a.accNumber, a.accName, c.ccyName, ad.actCurrency,
            case when actCreditLimit >= 999999999999.9999 then 'Unlimited' else actCreditLimit end as accLimit,
            COALESCE(sum(case when td.trdDrCr = 'Cr' then td.trdAmount else -td.trdAmount end),0) as balance,
            case when actStatus = 1 then 'Active' else 'Blocked' end as accStatus
            from accountDetails ad
            join currency c on c.ccyCode = ad.actCurrency
            join accounts a on a.accNumber = ad.actAccount
            left join trnDetails td on td.trdAccount = a.accNumber
            where ad.actSignatory = ?
            group by a.accNumber, a.accName, c.ccyName, ad.actCurrency, ad.actCreditLimit, ad.actStatus";
        $sql5 = "SELECT empID, empHireDate, empDepartment, empPosition, empSalCalcBase, empPmntMethod, empSalary, empTaxInfo,
            case when empStatus = 1 then 'Hired' else 'Fired' end as empStatus, empEndDate as empFiredDate
            from employees 
            where empPersonal = ?";
        
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([$user]);
        $profile = $stmt1->fetch(PDO::FETCH_ASSOC);
        $addID = $profile['perAddress'];
        $perID = $profile['perID'];

        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([$addID]);
        $add = $stmt2->fetch(PDO::FETCH_ASSOC);

        $stmt3 = $conn->prepare($sql3);
        $stmt3->execute([$user]);
        $users = $stmt3->fetch(PDO::FETCH_ASSOC);

        $stmt4 = $conn->prepare($sql4);
        $stmt4->execute([$perID]);
        $accounts = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        $stmt5 = $conn->prepare($sql5);
        $stmt5->execute([$perID]);
        $employment = $stmt5->fetch(PDO::FETCH_ASSOC);


        $profile['address'] = $add;
        $profile['user'] = $users;
        $profile['accounts'] = $accounts;
        $profile['employment'] = $employment;
       
        echo json_encode($profile, JSON_PRETTY_PRINT);
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

function update_personalProfile(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode([
            "msg" => "failed",
            "error" => "Invalid or empty JSON input"
        ]);
        exit;
    }

    $id = $data['perID'];
    $dob = $data['perDoB'];
    $enid = $data['perENIDNo'];
    $phone = $data['perPhone'];
    $email = $data['perEmail'];
    $address = $data['address'] ?? [];
    $accounts = $data['accounts'] ?? [];
    $employee = $data['employment'] ?? [];

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE personal SET perDoB=?, perENIDNo=?, perPhone=?, perEmail=? WHERE perID=?");
        $stmt2 = $conn->prepare("UPDATE address SET addName=?, addCity=?, addProvince=?, addCountry=?, addZipCode=?, addMailing=? WHERE addID=?");
        $stmt3 = $conn->prepare("UPDATE accounts SET accName = ? WHERE accNumber = ?");
        $stmt4 = $conn->prepare("UPDATE employees SET empEmail=?, empTaxInfo=? WHERE empID=?");

        $stmt1->execute([$dob, $enid, $phone, $email, $id]);
        foreach($address as $addRec){
            $stmt2->execute([
                $addRec['addName'],
                $addRec['addCity'],
                $addRec['addProvince'],
                $addRec['addCountry'],
                $addRec['addZipCode'],
                $addRec['addMailing'],
                $addRec['addID']
            ]);
        }
        foreach($accounts as $accRec){
            $stmt3->execute([$accRec['accName'], $accRec['accNumber']]);
        }

        foreach($employee as $empRec){
            $stmt4->execute([$empRec['empEmail'], $empRec['empTaxInfo'], $empRec['empID']]);
        }

        $conn->commit();
        echo json_encode(["msg" => "success", "company" => $id]);
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

function upload_comProfile(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $logo = file_get_contents($_FILES['image']['tmp_name']);

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE companyProfile SET comLogo=?");
        $stmt->execute([$logo]);

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