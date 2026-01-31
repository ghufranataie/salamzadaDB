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
        get_balanceSheet();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_balanceSheet() {
    global $conn, $value;

    $localCcy = $value->getCompanyAttributes("comLocalCcy");

    try {
        // Get Account Category
        $stmt1 = $conn->prepare("SELECT * from accountCategory where acgCategory = ? and acgID !=8;");
        // Get Balances of Assets for Sheet balances
        $stmt2 = $conn->prepare("SELECT ac.accName, td.trdAccount, ag.acgName,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Dr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Dr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference
            WHERE ac.accCategory = :cat
            AND ac.accCategory != 8
            GROUP BY ac.accName, td.trdAccount, ag.acgName;");
        
        // Get Balances of Liabilities for Sheet balances
        $stmt3 = $conn->prepare("SELECT ac.accName, td.trdAccount, ag.acgName,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Cr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference
            WHERE ac.accCategory = :cat
            AND ac.accCategory != 8
            GROUP BY ac.accName, td.trdAccount, ag.acgName;");
        
        // Get Balances of Stakeholders for Sheet balances
        $stmt4 = $conn->prepare("SELECT 'Stakeholders' as accName, '29999999' as trdAccount, 'Stakeholders' as acgName,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Cr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference
            WHERE ac.accCategory = 8;");
        
        
        // Get Balances of Net Profit for Sheet balances
        $stmt5 = $conn->prepare("SELECT 'Net Profit' as accName, '30000000' as trdAccount, 'Profit & Loss' as acgName,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Cr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference
            WHERE ag.acgCategory between 3 and 4;");

        $stmt1->execute([1]);
        $asset = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        $allBalances = [];
        foreach($asset as $cat){
            $catID = $cat['acgID'];
            $stmt2->execute([
                ':lCcy' => $localCcy,
                ':cat' => $catID
            ]);
            $assetBalances[$cat['acgName']] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        $data['Assets'] = $assetBalances;

        $stmt1->execute([2]);
        $liability = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        foreach($liability as $cat){
            $catID = $cat['acgID'];
            $stmt3->execute([
                ':lCcy' => $localCcy,
                ':cat' => $catID
            ]);
            $liabilityBalances[$cat['acgName']] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        }



        $data['Liability'] = $liabilityBalances;

        $stmt4->execute([':lCcy' => $localCcy]);
        $stakeholders = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        $stmt5->execute([':lCcy' => $localCcy]);
        $netProfit = $stmt5->fetchAll(PDO::FETCH_ASSOC);

        $data['Liability']['Stakeholders'] = $stakeholders;
        $data['Liability']['Net Profit'] = $netProfit;

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