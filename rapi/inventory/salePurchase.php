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
        get_order();
        break;
    case 'POST':
        create_order();
        break;
    case 'PUT':
        update_order();
        break;
    case 'DELETE':
        delete_order();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_order() {
    global $conn;
    try {
        if (isset($_GET['ordID']) && !empty($_GET['ordID'])) {
            $shpID = $_GET['ordID'];
            // Use SQL for single record
            $sql = "SELECT ordID, ordName, ordPersonal, concat(perName, ' ', perLastName) as ordPersonalName, ordxRef, ordTrnRef,
                max(case when td.trdAccount < 10000000 then td.trdAccount else NULL end) as account,
                max(case when td.trdAccount < 10000000 then td.trdAmount else 0 end) as amount,
                trnStateText, ordEntryDate 
                from orders o
                join stock s on s.stkOrder = o.ordID
                left join personal p on p.perID = o.ordPersonal
                left join transactions t on t.trnReference = o.ordTrnRef
                join trnDetails td on td.trdReference = t.trnReference
                join branch b on b.brcID = o.ordBranch
                where ordID = :id
                group by ordID, ordName, ordPersonal, ordPersonalName, ordxRef, ordTrnRef, trnStateText, ordEntryDate";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $shpID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$data){
                echo json_encode(["msg" => "not found"], JSON_PRETTY_PRINT);
                exit;
            }

            $stmt1 = $conn->prepare("SELECT * from stock where stkOrder = ?");
            $stmt1->execute([$_GET['ordID']]);
            $records = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            $data['records'] = $records;

        } else {
            // Use default SQL for all records
            $sql = "SELECT ordID, ordName, ordPersonal, concat(perName, ' ', perLastName) as ordPersonalName, ordxRef, ordTrnRef,
                max(case when td.trdAccount < 10000000 then td.trdAccount else 0 end) as account,
                max(case when td.trdAccount < 10000000 then td.trdAmount else 0 end) as amount,
                trnStateText, ordEntryDate 
                from orders o
                join stock s on s.stkOrder = o.ordID
                left join personal p on p.perID = o.ordPersonal
                left join transactions t on t.trnReference = o.ordTrnRef
                join trnDetails td on td.trdReference = t.trnReference
                join branch b on b.brcID = o.ordBranch
                group by ordID, ordName, ordPersonal, ordPersonalName, ordxRef, ordTrnRef, trnStateText, ordEntryDate 
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

function create_order(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $account = 0;
    $amount = 0;
    $totalBill = 0;
    $totalProAmount = 0;
    $av = 0;

    $user = $data['usrName'];
    $oName = $data['ordName'];
    $perID = $data['ordPersonal'];
    $xRef = $data['ordxRef'];
    $account = $data['account'];
    $amount = $data['amount'];
    $records = $data['records'];

    $type = ($oName == "Purchase") ? "PRCH" : "SALE";
    $status = 0;
    $stateText = "Pending";
    $authUser = NULL;
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $defaultCcy  = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);

    if($account < 10000000 && $account > 499999){
        $accStatus = $value->getAccountDetails($account, 'actStatus');
        $accCcy = $value->getAccountDetails($account, 'actCurrency');
        if($accStatus == 0){
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            exit;
        }
        if($accCcy !=  $defaultCcy){
            echo json_encode(["msg" => "invalid ccy"], JSON_PRETTY_PRINT);
            exit;
        }
    }

    

    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("INSERT INTO orders (ordPersonal, ordName, ordxRef, ordBranch, ordEntryDate) VALUES (?,?,?,?,?)");
        $stmt2 = $conn->prepare("INSERT INTO stock (stkOrder, stkProduct, stkEntryType, stkStorage, stkQuantity, stkPurPrice, stkSalePrice) VALUES (?,?,?,?,?,?,?)");
        $stmt3 = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) VALUES (?,?,?,?,?,?,?)");
        $stmt4 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?,?,?,?,?,?,?,?)");
        $stmt5 = $conn->prepare("UPDATE orders SET ordTrnRef=? WHERE ordID = ?");

        // Insert Into Orders
        $stmt1->execute([$perID, $oName, $xRef, $branch, $entryDateTime]);
        $ordID= $conn->lastInsertId();

        // Insert Into Stock
        foreach($data['records'] as $record){
            // $stkID = $record['stkID'];
            // $stkOrder = $record['stkOrder'];
            $oName == "Purchase" ? $eType = "IN" : $eType = "OUT";
            $proID = $record['stkProduct'];
            $stgID = $record['stkStorage'];
            $qty = $record['stkQuantity'];
            $pPrice = $record['stkPurPrice'];
            $sPrice = $record['stkSalePrice'];
            $oName == "Purchase" ? $totalBill += ($qty*$pPrice) : $totalBill += ($qty*$sPrice);
            $totalProAmount += ($qty*$pPrice);
            if($oName == 'Sale'){
                $stmt2s = $conn->prepare("select available from vw_availableProducts where proID = ? and stkStorage = ? limit 1");
                $stmt2s->execute([$proID, $stgID]);
                $rec = $stmt2s->fetch(PDO::FETCH_ASSOC);
                $av = $rec['available'];
                if($qty > $av){
                    $conn->rollBack();
                    echo json_encode(["msg" => "not enough", "specific" => "product:$proID, storage:$stgID"], JSON_PRETTY_PRINT);
                    exit;
                }
            }
            $stmt2->execute([$ordID, $proID, $eType, $stgID, round($qty, 3), round($pPrice, 4), round($sPrice, 4)]);
        }


        $plAccount = ($totalBill < $totalProAmount) ? 40404050 : 30303031;

        if($totalBill <= $limit){
            $authUser = $usrID;
            $status = 1;
            $stateText = "Authorized";
        }
        if($amount > $totalBill){
            $conn->rollBack();
            echo json_encode(["msg" => "large"], JSON_PRETTY_PRINT);
            exit;
        }
        // Insert Into Transactions
        $stmt3->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

        // Prepare transaction details insert
        $remark = "$oName Order ID: $ordID, From: $perID, Total Bill: $totalBill";
        
        // If It is Purchase Transaction
        if($oName == "Purchase"){
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101011, "Dr", $totalProAmount, $remark, $entryDateTime]);
                if($amount < $totalBill && $amount != 0){
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Cr", $amount, $remark, $entryDateTime]);
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Cr", ($totalBill-$amount), $remark, $entryDateTime]);
                }elseif($amount == $totalBill){
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Cr", $totalBill, $remark, $entryDateTime]);
                }else{
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Cr", $totalBill, $remark, $entryDateTime]);
                }
        // If It is sale Transaction
        }else{
            if(!empty($account) && $account < 600000){
                $accStatus = $value->getAccountDetails($account, 'actStatus');
                $acclimit = $value->checkForAccountLimit($account, $amount);

                if ($acclimit != 1) {
                    echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
                    exit;
                }elseif($accStatus != 1){
                    echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
                    exit;
                }
            }
            
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101011, "Cr", $totalProAmount, $remark, $entryDateTime]); //Product
            $stmt4->execute([$trnRef, $defaultCcy, $branch, $plAccount, ($totalBill > $totalProAmount ? "Cr" : "Dr"), abs($totalBill-$totalProAmount), $remark, $entryDateTime]); //Income
            if($amount < $totalBill && $amount != 0){
                $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]); //Account
                $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Dr", ($totalBill-$amount), $remark, $entryDateTime]); //Cash
            }elseif($amount == $totalBill){
                $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]); //Account
            }else{
                $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Dr", $totalBill, $remark, $entryDateTime]); //Cash
            }
        }
        
        // Update transaction reference to created order
        $stmt5->execute([$trnRef, $ordID]);

        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Order ID: $ordID,\nTrnRef: $trnRef\nFrom Stakeholder: $perID,\nTotal Amount: $totalBill,\nxRef: $xRef"
        );

        $conn->commit();
        echo json_encode(["msg" => "success", "ref" => $trnRef, "ordID" => $ordID], JSON_PRETTY_PRINT);
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

function update_order(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $account = 0;
    $amount = 0;
    $totalBill = 0;
    $totalProAmount = 0;

    $user = $data['usrName'];
    $ordID = $data['ordID'];
    $oName = $data['ordName'];
    $perID = $data['ordPersonal'];
    $xRef = $data['ordxRef'];
    $trnRef = $data['ordTrnRef'];
    $trnStatus = $data['trnStateText'];
    $account = $data['account'];
    $amount = $data['amount'];
    $records = $data['records'];
    $entryType = ($oName == "Purchase" ? "IN" : "OUT");

    // $type = "PRCH";
    $status = 0;
    $stateText = "Pending";
    $authUser = NULL;
    $entryDateTime = date("Y-m-d H:i:s");
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID = $userResult['usrID'];
    $defaultCcy  = $value->getCompanyAttributes('comLocalCcy');
    $limit = $value->getBranchAuthLimit($branch, $defaultCcy);
    
    if($account < 10000000 && $account > 499999){
        $accStatus = $value->getAccountDetails($account, 'actStatus');
        $accCcy = $value->getAccountDetails($account, 'actCurrency');
        if($accStatus == 0){
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            exit;
        }
        if($accCcy !=  $defaultCcy){
            echo json_encode(["msg" => "invalid ccy"], JSON_PRETTY_PRINT);
            exit;
        }
    }

    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("UPDATE orders set ordPersonal=?, ordxRef=? where ordID = ?");
        $stmt2u = $conn->prepare("UPDATE stock set stkProduct=?, stkStorage=?, stkQuantity=?, stkPurPrice=?, stkSalePrice=? where stkID=? and stkOrder=?");
        $stmt2s = $conn->prepare("SELECT count(*) from stock where stkID=? and stkOrder=?");
        $stmt2i = $conn->prepare("INSERT into stock (stkOrder, stkProduct, stkEntryType, stkStorage, stkQuantity, stkPurPrice, stkSalePrice) VALUES (?,?,?,?,?,?,?)");
        $stmt3 = $conn->prepare("UPDATE transactions set trnStatus=?, trnStateText=?, trnMaker=?, trnAuthorizer=? where trnReference=?");
        $stmt4d = $conn->prepare("DELETE FROM trnDetails WHERE trdReference = ?");
        $stmt4 = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) VALUES (?,?,?,?,?,?,?,?)");

        // Update Orders table
        $stmt1->execute([$perID, $xRef, $ordID]);

        // Delete records from stock table which does not exist in user send data
        $userStkIDs = array_column($data['records'], 'stkID');
        if (!empty($userStkIDs)) {
            $inPlaceholders = implode(',', array_fill(0, count($userStkIDs), '?'));
            $stmt2d = $conn->prepare("DELETE FROM stock WHERE stkOrder = ? AND stkID NOT IN ($inPlaceholders)");
            $stmt2d->execute(array_merge([$ordID], $userStkIDs));
        } else {
            // If user submitted no records, delete all for this order
            $stmt2d2 = $conn->prepare("DELETE FROM stock WHERE stkOrder = ?");
            $stmt2d2->execute([$ordID]);
        }

        // Select each record then it checks if exist update if not then insert
        foreach($data['records'] as $record){
            $stkID = $record['stkID'];
            $proID = $record['stkProduct'];
            $stgID = $record['stkStorage'];
            $qty = $record['stkQuantity'];
            $pPrice = $record['stkPurPrice'];
            $sPrice = $record['stkSalePrice'];

            $oName == "Purchase" ? $totalBill += ($qty*$pPrice) : $totalBill += ($qty*$sPrice);
            $totalProAmount += ($qty*$pPrice);

            if($oName == 'Sale'){
                $stmt2sq = $conn->prepare("select available from vw_availableProducts where proID = ? and stkStorage = ? limit 1");
                $stmt2sq->execute([$proID, $stgID]);
                $rec = $stmt2sq->fetch(PDO::FETCH_ASSOC);
                $av = $rec['available'];
                if($qty > $av){
                    echo json_encode(["msg" => "not anough", "specific" => "product:$proID, storage:$stgID"], JSON_PRETTY_PRINT);
                    exit;
                }
            }

            $stmt2s->execute([$stkID, $ordID]);
            $result = $stmt2s->fetchColumn();
            if($result > 0){
                $stmt2u->execute([$proID, $stgID, round($qty, 3), round($pPrice, 4), round($sPrice, 4), $stkID, $ordID]);
            }else{
                $stmt2i->execute([$ordID, $proID, $entryType, $stgID, round($qty, 3), round($pPrice, 4), round($sPrice, 4)]);
            }
        }
        $plAccount = ($totalBill < $totalProAmount) ? 40404050 : 30303031;

        if($totalBill <= $limit){
            $authUser = $usrID;
            $status = 1;
            $stateText = "Authorized";
        }

        if($amount > $totalBill){
            $conn->rollBack();
            echo json_encode(["msg" => "large"], JSON_PRETTY_PRINT);
            exit;
        }
        
        // Update Transaction Table
        $stmt3->execute([$status, $stateText, $usrID, $authUser, $trnRef]);

        // Delete the Transaction Details first
        $stmt4d->execute([$trnRef]);

        // Prepare transaction details insert
        $remark = "$oName Order ID: $ordID, From: $perID, Total Bill: $totalBill";
        
        // If It is Purchase Transaction
        if($oName == "Purchase"){
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101011, "Dr", $totalProAmount, $remark, $entryDateTime]);
                if($amount < $totalBill && $amount != 0){
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Cr", $amount, $remark, $entryDateTime]);
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Cr", ($totalBill-$amount), $remark, $entryDateTime]);
                }elseif($amount == $totalBill){
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Cr", $totalBill, $remark, $entryDateTime]);
                }else{
                    $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Cr", $totalBill, $remark, $entryDateTime]);
                }
        // If It is sale Transaction
        }else{
            $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101011, "Cr", $totalProAmount, $remark, $entryDateTime]); //Product
            $stmt4->execute([$trnRef, $defaultCcy, $branch, $plAccount, ($totalBill > $totalProAmount ? "Cr" : "Dr"), abs($totalBill-$totalProAmount), $remark, $entryDateTime]); //Income
            if($amount < $totalBill && $amount != 0){
                $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]); //Account
                $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Dr", ($totalBill-$amount), $remark, $entryDateTime]); //Cash
            }elseif($amount == $totalBill){
                $stmt4->execute([$trnRef, $defaultCcy, $branch, $account, "Dr", $amount, $remark, $entryDateTime]); //Account
            }else{
                $stmt4->execute([$trnRef, $defaultCcy, $branch, 10101010, "Dr", $totalBill, $remark, $entryDateTime]); //Cash
            }
        }

        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Updated - Order ID: $ordID,\nFrom Stakeholder: $perID,\nTotal Amount: $totalBill,\nxRef: $xRef"
        );

        $conn->commit();
        echo json_encode(["msg" => "success", "ref" => $trnRef, "ordID" => $ordID], JSON_PRETTY_PRINT);
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

function delete_order(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);


    $user = $data['usrName'];
    $ordID = $data['ordID'];
    $trnRef = $data['ordTrnRef'];
    $oName = $data['ordName'];

    try {

        $conn->beginTransaction();

        $stmt0 = $conn->prepare("SELECT count(*) from transactions where trnReference=? and trnStatus=1");


        $stmt1 = $conn->prepare("DELETE FROM trnDetails WHERE trdReference = ?");
        $stmt2 = $conn->prepare("DELETE FROM stock WHERE stkOrder = ?");
        $stmt3 = $conn->prepare("DELETE FROM orders WHERE ordID = ?");
        $stmt4 = $conn->prepare("UPDATE transactions SET trnStateText='Deleted', trnStatus=1 WHERE trnReference = ?");
        
        


        $stmt0->execute([$trnRef]);
        $result = $stmt0->fetchColumn();
        if($result > 0){
            echo json_encode(["msg" => "authorized"], JSON_PRETTY_PRINT);
            exit();
        }

        $stmt1->execute([$trnRef]);
        $stmt2->execute([$ordID]);
        $stmt3->execute([$ordID]);
        $stmt4->execute([$trnRef]);
        
        $value->generateUserActivityLog(
            $user, 
            $oName, 
            "Deleted - Order ID: $ordID,\nTrn Reference: $trnRef"
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