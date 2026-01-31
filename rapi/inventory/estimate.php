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
        get_estimate();
        break;
    case 'POST':
        create_estimate();
        break;
    case 'PUT':
        update_estimate();
        break;
    case 'DELETE':
        delete_estimate();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_estimate() {
    global $conn;
    try {
        if (isset($_GET['ordID']) && !empty($_GET['ordID'])) {
            $shpID = $_GET['ordID'];
            // Use SQL for single record
            $sql = "SELECT ordID, ordName, ordPersonal, concat(perName, ' ', perLastName) as ordPersonalName, ordBranch, b.brcName, ordxRef, ordTrnRef,
                round(sum(ts.tstSalePrice*ts.tstQuantity),4) as total
                from orders o
                left join personal p on p.perID = o.ordPersonal
                left join branch b on b.brcID = o.ordBranch
                left join tempStock ts on ts.tstOrder = o.ordID
                WHERE o.ordName = 'Estimate' and o.ordID = :id
                group by ordID, ordName, ordPersonal, concat(perName, ' ', perLastName), ordBranch, b.brcName, ordxRef, ordTrnRef";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $shpID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt1 = $conn->prepare("SELECT ts.*, p.proName as tstProductName, s.stgName as tstStorageName
                from tempStock ts 
                join product p on p.proID = ts.tstProduct 
                join storages s on s.stgID = ts.tstStorage
                where tstOrder = ?");
            $stmt1->execute([$_GET['ordID']]);
            $records = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            $data['records'] = $records;

        } else {
            // Use default SQL for all records
            $sql = "SELECT ordID, ordName, ordPersonal, concat(perName, ' ', perLastName) as ordPersonalName, ordBranch, b.brcName, ordxRef, ordTrnRef,
                round(sum(ts.tstSalePrice*ts.tstQuantity),4) as total
                from orders o
                left join personal p on p.perID = o.ordPersonal
                left join branch b on b.brcID = o.ordBranch
                left join tempStock ts on ts.tstOrder = o.ordID
                WHERE o.ordName = 'Estimate' 
                group by ordID, ordName, ordPersonal, concat(perName, ' ', perLastName), ordBranch, b.brcName, ordxRef, ordTrnRef
                order by ordID DESC";
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

function create_estimate(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $totalBill = 0;
    $totalProAmount = 0;


    $user = $data['usrName'];
    $oName = $data['ordName'];
    $perID = $data['ordPersonal'];
    $xRef = $data['ordxRef'];
    $records = $data['records'];

    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];


    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("INSERT INTO orders (ordPersonal, ordName, ordxRef, ordBranch, ordEntryDate) VALUES (?,?,?,?,?)");
        $stmt2 = $conn->prepare("INSERT INTO tempStock (tstOrder, tstProduct, tstStorage, tstQuantity, tstPurPrice, tstSalePrice) VALUES (?,?,?,?,?,?)");

        // Insert Into Orders
        $stmt1->execute([$perID, $oName, $xRef, $branch, $entryDateTime]);
        $ordID= $conn->lastInsertId();

        // Insert Into TempStock Table Using Loop
        foreach($data['records'] as $record){
            $proID = $record['tstProduct'];
            $stgID = $record['tstStorage'];
            $qty = $record['tstQuantity'];
            $pPrice = $record['tstPurPrice'];
            $sPrice = $record['tstSalePrice'];

            $totalBill += ($qty*$sPrice);
            
            // $stmt2s = $conn->prepare("select available from vw_availableProducts where proID = ? and stkStorage = ? limit 1");
            // $stmt2s->execute([$proID, $stgID]);
            // $rec = $stmt2s->fetch(PDO::FETCH_ASSOC);
            // $av = $rec['available'];
            // if($qty > $av){
            //     $conn->rollBack();
            //     echo json_encode(["msg" => "not enough", "specific" => "product:$proID, storage:$stgID"], JSON_PRETTY_PRINT);
            //     exit;
            // }

            $stmt2->execute([$ordID, $proID, $stgID, round($qty, 3), round($pPrice, 4), round($sPrice, 4)]);
        }


        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Order ID: $ordID,\nFrom Stakeholder: $perID,\nTotal Amount: $totalBill,\nxRef: $xRef"
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

function update_estimate(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $totalBill = 0;
    $totalProAmount = 0;

    $user = $data['usrName'];
    $ordID = $data['ordID'];
    $oName = $data['ordName'];
    $perID = $data['ordPersonal'];
    $xRef = $data['ordxRef'];
    $records = $data['records'];

   
    $entryDateTime = date("Y-m-d H:i:s");
    
    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("UPDATE orders set ordPersonal=?, ordxRef=? where ordID = ?");
        $stmt2u = $conn->prepare("UPDATE tempStock set tstProduct=?, tstStorage=?, tstQuantity=?, tstPurPrice=?, tstSalePrice=? where tstID=? and tstOrder=?");
        $stmt2s = $conn->prepare("SELECT count(*) from tempStock where tstID=? and tstOrder=?");
        $stmt2i = $conn->prepare("INSERT into tempStock (tstOrder, tstProduct, tstStorage, tstQuantity, tstPurPrice, tstSalePrice) VALUES (?,?,?,?,?,?)");

        // Update Orders table
        $stmt1->execute([$perID, $xRef, $ordID]);

        // Delete records from tempStock table which does not exist in user send data
        $userStkIDs = array_column($data['records'], 'tstID');
        if (!empty($userStkIDs)) {
            $inPlaceholders = implode(',', array_fill(0, count($userStkIDs), '?'));
            $stmt2d = $conn->prepare("DELETE FROM tempStock WHERE tstOrder = ? AND tstID NOT IN ($inPlaceholders)");
            $stmt2d->execute(array_merge([$ordID], $userStkIDs));
        } else {
            // If user submitted no records, delete all for this order
            $stmt2d2 = $conn->prepare("DELETE FROM tempStock WHERE tstOrder = ?");
            $stmt2d2->execute([$ordID]);
        }

        // Select each record then it checks if exist update if not then insert
        foreach($data['records'] as $record){
            $stkID = $record['tstID'];
            $proID = $record['tstProduct'];
            $stgID = $record['tstStorage'];
            $qty = $record['tstQuantity'];
            $pPrice = $record['tstPurPrice'];
            $sPrice = $record['tstSalePrice'];

            $totalBill += ($qty*$sPrice);
            $totalProAmount += ($qty*$pPrice);

            // $stmt2sq = $conn->prepare("select available from vw_availableProducts where proID = ? and stkStorage = ? limit 1");
            // $stmt2sq->execute([$proID, $stgID]);
            // $rec = $stmt2sq->fetch(PDO::FETCH_ASSOC);
            // $av = $rec['available'];
            // if($qty > $av){
            //     $conn->rollBack();
            //     echo json_encode(["msg" => "not enough", "specific" => "product:$proID, storage:$stgID"], JSON_PRETTY_PRINT);
            //     exit;
            // }

            $stmt2s->execute([$stkID, $ordID]);
            $result = $stmt2s->fetchColumn();
            if($result > 0){
                $stmt2u->execute([$proID, $stgID, round($qty, 3), round($pPrice, 4), round($sPrice, 4), $stkID, $ordID]);
            }else{
                $stmt2i->execute([$ordID, $proID, $stgID, round($qty, 3), round($pPrice, 4), round($sPrice, 4)]);
            }
        }


        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Updated - Order ID: $ordID,\nFrom Stakeholder: $perID,\nTotal Amount: $totalBill,\nxRef: $xRef"
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

function delete_estimate(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $ordID = $data['ordID'];

    try {

        $conn->beginTransaction();

        $stmt2 = $conn->prepare("DELETE FROM tempStock WHERE tstOrder = ?");
        $stmt3 = $conn->prepare("DELETE FROM orders WHERE ordID = ?");

        $stmt2->execute([$ordID]);
        $stmt3->execute([$ordID]);

        
        $value->generateUserActivityLog(
            $user, 
            "Estimate", 
            "Deleted - Order ID: $ordID"
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

?>