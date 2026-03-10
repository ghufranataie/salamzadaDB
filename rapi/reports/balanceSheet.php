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
    $branch = $_GET['branch'] ?? null;

    try {
        // Get Account Category
        $stmt1 = $conn->prepare("SELECT * from accountCategory where acgCategory = ? and acgID !=8;");
        // Get Balances of Assets for Sheet balances
        $sql2 = "SELECT ac.accName, td.trdAccount, ag.acgName, :branch as trdBranch,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Dr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Dr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference";
        
        // Get Balances of Liabilities for Sheet balances
        $sql3 = "SELECT ac.accName, td.trdAccount, ag.acgName, :branch as trdBranch,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Cr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference";
        
        // Get Balances of Stakeholders for Sheet balances
        $sql4 = "SELECT 'Stakeholders' as accName, '29999999' as trdAccount, 'Stakeholders' as acgName, :branch as trdBranch,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Cr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference";

        // Get Balances of Net Profit for Sheet balances
        $sql5 = "SELECT 'Net Profit' as accName, '30000000' as trdAccount, 'Profit & Loss' as acgName, :branch as trdBranch,
                SUM(CASE WHEN tr.trnStatus = 1 then 
					CASE WHEN td.trdDrCr = 'Cr'THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END 
                ELSE 0 END) AS current_year,
                
				SUM(CASE WHEN td.trdEntryDate <= MAKEDATE(YEAR(CURDATE()) - 1, 365) AND tr.trnStatus = 1 THEN 
					CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lCcy) ELSE -td.trdAmount * getRate(td.trdCcy, :lCcy) END
				ELSE 0 END)	AS last_year
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            JOIN transactions tr on tr.trnReference = td.trdReference";


        if($branch == "" || $branch == null || empty($branch)){
            $sql2 .= " WHERE ac.accCategory = :cat AND ac.accCategory != 8
            GROUP BY ac.accName, td.trdAccount, ag.acgName";

            $sql3 .= " WHERE ac.accCategory = :cat AND ac.accCategory != 8
            GROUP BY ac.accName, td.trdAccount, ag.acgName";

            $sql4 .= " WHERE ac.accCategory = 8";

            $sql5 .= " WHERE ag.acgCategory between 3 and 4";

            $branch = "All";
        } else {
            $sql2 .= " WHERE ac.accCategory = :cat AND ac.accCategory != 8 AND td.trdBranch = :branch
            GROUP BY ac.accName, td.trdAccount, ag.acgName, td.trdBranch";

            $sql3 .= " WHERE ac.accCategory = :cat AND ac.accCategory != 8 AND td.trdBranch = :branch
            GROUP BY ac.accName, td.trdAccount, ag.acgName, td.trdBranch";

            $sql4 .= " WHERE ac.accCategory = 8 AND td.trdBranch = :branch group by td.trdBranch";

            $sql5 .= " WHERE ag.acgCategory between 3 and 4 and td.trdBranch = :branch group by td.trdBranch";
        }

        $stmt2 = $conn->prepare($sql2);
        $stmt3 = $conn->prepare($sql3);
        $stmt4 = $conn->prepare($sql4);
        $stmt5 = $conn->prepare($sql5);

        $stmt1->execute([1]);
        $asset = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        $allBalances = [];

        $assetBalances = [];
        foreach($asset as $cat){
            $catID = $cat['acgID'];
            $stmt2->execute([
                ':branch' => $branch,
                ':lCcy' => $localCcy,
                ':cat' => $catID
            ]);
            $assetBalances[$cat['acgName']] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        $data['Assets'] = $assetBalances;

        $stmt1->execute([2]);
        $liability = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $liabilityBalances = [];
        foreach($liability as $cat){
            $catID = $cat['acgID'];
            $stmt3->execute([
                ':branch' => $branch,
                ':lCcy' => $localCcy,
                ':cat' => $catID
            ]);
            $liabilityBalances[$cat['acgName']] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        }



        $data['Liability'] = $liabilityBalances;

        $stmt4->execute([':branch' => $branch, ':lCcy' => $localCcy]);
        $stakeholders = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        $stmt5->execute([':branch' => $branch, ':lCcy' => $localCcy]);
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