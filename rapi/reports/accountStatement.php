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
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);
    $account = $data['account'];
    $from = $data['fromDate'];
    $to = $data['toDate'];

    try {

        $stmt = $conn->prepare("SELECT a.accNumber, a.accName, CONCAT(p.perName, ' ', p.perLastName) AS signatory, p.perPhone, p.perEmail,
                CONCAT(ad.addName, ', ', ad.addCity, ', ', ad.addProvince, ', ', ad.addCountry, ', ', ad.addZipCode) AS address,
                adt.actCurrency, c.ccySymbol, adt.actCreditLimit,
                COALESCE(b.curBalance, 0) AS curBalance, COALESCE(b.avilBalance, 0) AS avilBalance,
                adt.actStatus FROM accounts a
            JOIN accountDetails adt ON adt.actAccount = a.accNumber
            JOIN personal p ON p.perID = adt.actSignatory
            LEFT JOIN address ad ON ad.addID = p.perAddress
            JOIN currency c ON c.ccyCode = adt.actCurrency
            LEFT JOIN (
                SELECT 
                    td.trdAccount,
                    SUM(CASE 
                            WHEN td.trdDrCr='Cr' THEN td.trdAmount
                            WHEN td.trdDrCr='Dr' THEN -td.trdAmount
                        END) AS curBalance,
                    SUM(CASE 
                            WHEN t.trnStatus = 1 AND td.trdDrCr='Cr' THEN td.trdAmount
                            WHEN t.trnStatus = 1 AND td.trdDrCr='Dr' THEN -td.trdAmount
                            ELSE 0
                        END) AS avilBalance
                FROM trnDetails td
                JOIN transactions t ON t.trnReference = td.trdReference
                GROUP BY td.trdAccount
            ) b ON b.trdAccount = a.accNumber
            WHERE a.accNumber = ?
        ");
        $stmt->execute([$account]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        // echo json_encode($data, JSON_PRETTY_PRINT);



        $stmt1 = $conn->prepare("WITH
            opening AS (
                SELECT COALESCE(SUM(CASE WHEN td.trdDrCr='Cr' THEN td.trdAmount ELSE -td.trdAmount END), 0) AS opening_balance
                FROM trnDetails td
                JOIN transactions tr ON tr.trnReference = td.trdReference
                WHERE td.trdAccount = ? AND DATE(tr.trnEntryDate) <= ?
            ),
            period AS (
                SELECT tr.trnEntryDate, tr.trnReference, td.trdNarration,
                    CASE WHEN td.trdDrCr='Dr' THEN td.trdAmount ELSE 0 END AS debit,
                    CASE WHEN td.trdDrCr='Cr' THEN td.trdAmount ELSE 0 END AS credit,
                    (CASE WHEN td.trdDrCr='Cr' THEN td.trdAmount ELSE -td.trdAmount END) AS amount,
                    CASE WHEN tr.trnStatus=1 THEN '' ELSE '*' END AS status
                FROM trnDetails td
                JOIN transactions tr ON tr.trnReference = td.trdReference
                WHERE td.trdAccount = ? AND DATE(tr.trnEntryDate) BETWEEN ? AND ?
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
            SELECT 1 AS sort_order, ? AS trnEntryDate, '' AS trnReference, 'Opening Balance' AS trdNarration, 0 AS debit, 0 AS credit, opening_balance AS total, '' AS status
            FROM opening
            UNION ALL
            -- Period transactions with running total
            SELECT 2 AS sort_order, trnEntryDate, trnReference, trdNarration, debit, credit, total, status
            FROM running
            UNION ALL
            -- Closing balance
            SELECT 3 AS sort_order, ? AS trnEntryDate, '' AS trnReference, 'Closing Balance' AS trdNarration, 0 AS debit, 0 AS credit, closing_balance AS total, '' AS status
            FROM closing
            ORDER BY sort_order, trnEntryDate");
        $stmt1->execute([$account, $from, $account, $from, $to, $from, $to]);
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