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
        load_accountDetails();
        break;
    case 'POST':
        get_accountDetails();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_accountDetails() {
    global $conn, $value;

    $perID = $_GET['perID'];
    
    $ccy = $value->getCompanyAttributes('comLocalCcy');
    $input = "";
    $include = "8";
    $excludAcc = "";
    $signatory = $perID;
    $inputPattern = "%$input%"; 

    try {
        $sql = "SELECT a.accNumber, a.accName,
                CASE WHEN a.accCategory BETWEEN 1 AND 12 AND a.accCategory != 8 THEN :ccy ELSE ad.actCurrency END AS actCurrency,
                
                SUM(CASE WHEN tr.trnStatus = 1 THEN
                        CASE WHEN a.accCategory BETWEEN 1 AND 12 AND a.accCategory !=8 AND td.trdCcy = :ccy THEN
                            CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END
                        WHEN a.accCategory=8 THEN 
                            CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END 
                        ELSE 0 END 
                ELSE 0 END) AS accAvailBalance,

                SUM(CASE WHEN a.accCategory BETWEEN 1 AND 12 AND td.trdCcy = :ccy THEN
                        CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END
                    WHEN  a.accCategory=8 THEN
                            CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END
                ELSE 0 END) AS accBalance,
                COALESCE(ad.actCreditLimit, 999999999999.0000) AS accCreditLimit,
                COALESCE(ad.actStatus, 1) AS accStatus, a.accCategory
            FROM accounts a
            LEFT JOIN accountDetails ad ON a.accNumber = ad.actAccount 
            LEFT JOIN trnDetails td ON a.accNumber = td.trdAccount
            LEFT JOIN transactions tr ON tr.trnReference = td.trdReference
            JOIN accountCategory ag ON ag.acgID = a.accCategory
            WHERE (a.accName LIKE :input OR a.accNumber LIKE :input)
            AND FIND_IN_SET(a.accCategory, :includ)
            AND FIND_IN_SET(CAST(a.accNumber AS CHAR), :excludAcc) = 0
            AND actSignatory = $signatory
            GROUP BY a.accNumber, accName, actCurrency, ad.actCreditLimit, ad.actStatus, a.accCategory
            ORDER BY a.accNumber;";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':ccy' => $ccy,
            ':input' => $inputPattern,
            ':includ' => $include,
            ':excludAcc' => $excludAcc
        ]);
        $data1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data1, JSON_PRETTY_PRINT);
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

function get_accountDetails() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    $ccy = $data['ccy'];
    $input = $data['input'];
    $include = $data['include'];
    $excludAcc = $data['account'];

    $inputPattern = "%$input%";

    try {
        $sql = "SELECT a.accNumber, a.accName,
                CASE WHEN a.accCategory BETWEEN 1 AND 12 AND a.accCategory != 8 THEN :ccy ELSE ad.actCurrency END AS actCurrency,
                
                SUM(CASE WHEN tr.trnStatus = 1 THEN
                        CASE WHEN a.accCategory BETWEEN 1 AND 12 AND a.accCategory !=8 AND td.trdCcy = :ccy THEN
                            CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END
                        WHEN a.accCategory=8 THEN 
                            CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END 
                        ELSE 0 END 
                ELSE 0 END) AS accAvailBalance,

                SUM(CASE WHEN a.accCategory BETWEEN 1 AND 12 AND td.trdCcy = :ccy THEN
                        CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END
                    WHEN  a.accCategory=8 THEN
                            CASE WHEN td.trdDrCr = 'CR' THEN td.trdAmount WHEN td.trdDrCr = 'DR' THEN -td.trdAmount ELSE 0 END
                ELSE 0 END) AS accBalance,
                COALESCE(ad.actCreditLimit, 999999999999.0000) AS accCreditLimit,
                COALESCE(ad.actStatus, 1) AS accStatus, a.accCategory
            FROM accounts a
            LEFT JOIN accountDetails ad ON a.accNumber = ad.actAccount 
            LEFT JOIN trnDetails td ON a.accNumber = td.trdAccount
            LEFT JOIN transactions tr ON tr.trnReference = td.trdReference
            JOIN accountCategory ag ON ag.acgID = a.accCategory
            WHERE (a.accName LIKE :input OR a.accNumber LIKE :input)
            AND FIND_IN_SET(a.accCategory, :includ)
            AND FIND_IN_SET(CAST(a.accNumber AS CHAR), :excludAcc) = 0
            GROUP BY a.accNumber, accName, actCurrency, ad.actCreditLimit, ad.actStatus, a.accCategory
            ORDER BY a.accNumber;";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':ccy' => $ccy,
            ':input' => $inputPattern,
            ':includ' => $include,
            ':excludAcc' => $excludAcc
        ]);
        $data1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data1, JSON_PRETTY_PRINT);
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