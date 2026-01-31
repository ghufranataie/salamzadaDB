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
        generate_glStatement();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function generate_glStatement() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);
    $account = $data['account'];
    $currency = $data['ccy'];
    $branch = $data['branch'];
    $from = $data['fromDate'];
    $to = $data['toDate'];

    try {

        $stmt = $conn->prepare("SELECT a.accNumber, a.accName, c.ccyCode, c.ccySymbol, c.ccyName, b.brcID, b.brcName,
            CASE WHEN a.accCategory = 1 THEN 'Asset'
                WHEN a.accCategory = 2 THEN 'Liability'
                WHEN a.accCategory = 3 THEN 'Income' ELSE 'Expense'
            END AS GL_Category,
            COALESCE(SUM(CASE 
                    WHEN td.trdDrCr='Cr' THEN td.trdAmount 
                    WHEN td.trdDrCr='Dr' THEN -td.trdAmount ELSE 0 
            END), 0) AS curBalance,
            COALESCE(SUM(CASE 
                    WHEN t.trnStatus = 1 AND td.trdDrCr='Cr' THEN td.trdAmount
                    WHEN t.trnStatus = 1 AND td.trdDrCr='Dr' THEN -td.trdAmount ELSE 0 
            END), 0) AS avilBalance
        FROM accounts a
        LEFT JOIN trnDetails td ON a.accNumber = td.trdAccount AND td.trdCcy = ? AND td.trdBranch = ?
        LEFT JOIN currency c ON c.ccyCode = td.trdCcy
        LEFT JOIN transactions t ON t.trnReference = td.trdReference
        LEFT JOIN branch b ON b.brcID = td.trdBranch
        WHERE a.accNumber = ?
        GROUP BY a.accNumber, td.trdCcy");
        $stmt->execute([$currency, $branch, $account]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        // echo json_encode($data, JSON_PRETTY_PRINT);



        $stmt1 = $conn->prepare("WITH
            opening AS (
                SELECT COALESCE(SUM(CASE WHEN td.trdDrCr='Cr' THEN td.trdAmount ELSE -td.trdAmount END), 0) AS opening_balance
                FROM trnDetails td
                JOIN transactions tr ON tr.trnReference = td.trdReference
                WHERE td.trdAccount = :acc AND trdCcy = :ccy AND trdBranch = :brc AND DATE(tr.trnEntryDate) < :fromDate
            ),
            period AS (
                SELECT tr.trnEntryDate, tr.trnReference, td.trdNarration,
                    CASE WHEN td.trdDrCr='Dr' THEN td.trdAmount ELSE 0 END AS debit,
                    CASE WHEN td.trdDrCr='Cr' THEN td.trdAmount ELSE 0 END AS credit,
                    (CASE WHEN td.trdDrCr='Cr' THEN td.trdAmount ELSE -td.trdAmount END) AS amount,
                    CASE WHEN tr.trnStatus=1 THEN '' ELSE '*' END AS status
                FROM trnDetails td
                JOIN transactions tr ON tr.trnReference = td.trdReference
                WHERE td.trdAccount = :acc AND trdCcy = :ccy AND trdBranch = :brc AND DATE(tr.trnEntryDate) BETWEEN :fromDate AND :toDate
            ),
            running AS (
                SELECT *,
                    (SELECT opening_balance FROM opening) + 
                    SUM(amount) OVER (ORDER BY trnEntryDate, trnReference ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total
                FROM period
            ),
            closing AS (
                SELECT (SELECT opening_balance FROM opening) + 
                    COALESCE(SUM(amount),0) AS closing_balance
                FROM period
            )
            -- Opening balance
            SELECT 1 AS sort_order, :fromDate AS trnEntryDate, '' AS trnReference, 'Opening Balance' AS trdNarration, 0 AS debit, 0 AS credit, opening_balance AS total, '' AS status
            FROM opening
            UNION ALL
            -- Period transactions with running total
            SELECT 2 AS sort_order, trnEntryDate, trnReference, trdNarration, debit, credit, total, status
            FROM running
            UNION ALL
            -- Closing balance
            SELECT 3 AS sort_order, :toDate AS trnEntryDate, '' AS trnReference, 'Closing Balance' AS trdNarration, 0 AS debit, 0 AS credit, closing_balance AS total, '' AS status
            FROM closing
            ORDER BY sort_order, trnEntryDate");
        $stmt1->bindParam(":acc", $account);
        $stmt1->bindParam(":ccy", $currency);
        $stmt1->bindParam(":brc", $branch);
        $stmt1->bindParam(":fromDate", $from);
        $stmt1->bindParam(":toDate", $to);
        $stmt1->execute();
        $data1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $data['records'] = $data1;
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