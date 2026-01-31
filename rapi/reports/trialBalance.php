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
    case 'POST':
        generate_accountStatement();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function generate_accountStatement() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    $date = $data['date'];
    // $ccy = $data['ccy'];
    
    $localCcy = $value->getCompanyAttributes("comLocalCcy");
    

    try {
        $stmt = $conn->prepare("SELECT '29999999' AS account_number, 'Stakeholders' AS account_name, :lccy AS currency, 'Liability' AS category,
                SUM(CASE WHEN tr.trnStatus = 1 THEN 
						CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END
				ELSE 0 END) AS actual_balance,
                
                CASE WHEN SUM(case WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END) < 0
                    THEN ABS(SUM(case WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END )) 
				ELSE 0 END AS debit,
                    
                CASE WHEN SUM(CASE WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END) > 0
                    THEN ABS(SUM(case WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END ))
				ELSE 0 END AS credit
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            LEFT JOIN currency c ON c.ccyCode = td.trdCcy
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            join transactions tr on tr.trnReference = td.trdReference
            WHERE ac.accCategory = 8 AND date(td.trdEntryDate) <= :dt
            GROUP BY '29999999'
            UNION ALL
            SELECT ac.accNumber AS account_number, ac.accName AS account_name, :lccy AS currency,
                CASE WHEN ag.acgCategory = 1 THEN 'Asset'
                    WHEN ag.acgCategory = 2 THEN 'Liability'
                    WHEN ag.acgCategory = 3 THEN 'Income'
                    WHEN ag.acgCategory = 4 THEN 'Expense'
                    ELSE 'Stakeholder'
                END AS category,
                SUM(CASE WHEN tr.trnStatus = 1 THEN 
						CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END
				ELSE 0 END) AS actual_balance,
                
                CASE WHEN SUM(case WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END) < 0
                    THEN ABS(SUM(case WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END )) 
				ELSE 0 END AS debit,
                    
                CASE WHEN SUM(CASE WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END) > 0
                    THEN ABS(SUM(case WHEN tr.trnStatus = 1 THEN CASE WHEN td.trdDrCr = 'Cr' THEN td.trdAmount * getRate(td.trdCcy, :lccy) ELSE -td.trdAmount * getRate(td.trdCcy, :lccy) END ELSE 0 END ))
				ELSE 0 END AS credit
            FROM trnDetails td
            LEFT JOIN accounts ac ON ac.accNumber = td.trdAccount
            LEFT JOIN currency c ON c.ccyCode = td.trdCcy
            JOIN accountCategory ag ON ag.acgID = ac.accCategory
            join transactions tr on tr.trnReference = td.trdReference
            WHERE ac.accCategory BETWEEN 1 AND 12
            AND ac.accCategory != 8 AND date(td.trdEntryDate) <= :dt
            GROUP BY ac.accNumber, ac.accName, ac.accCategory;");
        $stmt->bindParam(":lccy", $localCcy);
        $stmt->bindParam(":dt", $date);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        usort($data, function($a, $b) {
            // If account_number is numeric
            return $a['account_number'] <=> $b['account_number'];
            
            // If account_number might be a string and you want string comparison, use:
            // return strcmp($a['account_number'], $b['account_number']);
        });
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