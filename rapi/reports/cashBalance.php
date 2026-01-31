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
        get_cashBalance();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_cashBalance() {
    global $conn, $value;

    $localCcy = $value->getCompanyAttributes("comLocalCcy");
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $todayDate = date("Y-m-d");

    try {
        if (isset($_GET['brcID']) && !empty($_GET['brcID'])){
            $stmt1 = $conn->prepare("SELECT b.*, concat(a.addName, ' ', a.addCity, ' ', a.addProvince, ' ', a.addCountry, ' ', a.addZipCode) as address
                FROM branch b left join address a on a.addID = b.brcAddress where brcID = ?;");
            $stmt1->execute([$_GET['brcID']]);            
        }else{
            // Get Branches
            $stmt1 = $conn->prepare("SELECT b.*, concat(a.addName, ' ', a.addCity, ' ', a.addProvince, ' ', a.addCountry, ' ', a.addZipCode) as address
                FROM branch b left join address a on a.addID = b.brcAddress;");
            $stmt1->execute();
        }
        
        
        // Get Balances of branches for Cash balances
        $stmt2 = $conn->prepare("SELECT ac.accName, td.trdAccount, cy.ccyName, td.trdCcy, cy.ccySymbol,
                
                sum(case when tr.trnStatus=1 AND date(td.trdEntryDate) <= DATE_SUB(:dt, INTERVAL 1 DAY) then 
                        case when td.trdDrCr='Dr' then trdAmount else -trdAmount end 
                    else 0 end) as opening_balance,
                
                sum(case when tr.trnStatus=1 AND date(td.trdEntryDate) <= DATE_SUB(:dt, INTERVAL 1 DAY) then
                        case when td.trdDrCr='Dr' then trdAmount*getRate(td.trdCcy, :lccy) else -trdAmount*getRate(td.trdCcy, :lccy) end
                    else 0 end) as opening_sys_equivalent,
                
                sum(case when tr.trnStatus=1  then 
                        case when td.trdDrCr='Cr' then -trdAmount else trdAmount end
                    else 0 end) as closing_balance,

                sum(case when tr.trnStatus=1 then 
                        case when td.trdDrCr='Dr' then trdAmount*getRate(td.trdCcy, :lccy) else -trdAmount*getRate(td.trdCcy, :lccy) end
                    else 0 end) as closing_sys_equivalent
            
            FROM trnDetails td
            JOIN transactions tr ON tr.trnReference = td.trdReference
            JOIN currency cy ON cy.ccyCode = td.trdCcy
            JOIN accounts ac ON ac.accNumber = td.trdAccount
            where td.trdAccount = 10101010 and td.trdBranch = :brcID
            group by ac.accName, td.trdAccount, cy.ccyName, td.trdCcy, cy.ccySymbol;");

        $branches = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        foreach ($branches as &$brc) {
            $stmt2->execute([
                ':dt' => $todayDate,
                ':lccy'  => $localCcy,
                ':brcID' => $brc['brcID']
            ]);

            $brc['records'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($branches, JSON_PRETTY_PRINT);
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