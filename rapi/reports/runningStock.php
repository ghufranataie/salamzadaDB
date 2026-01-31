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
        get_runningStock();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_runningStock() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $from = $input['fromDate'];
    $to = $input['toDate'];
    $product = (INT)$input['proID'];
    $storage = (INT)$input['stgID'];
    $personal = (INT)$input['perID'];


    $zero = 0;


    try {
        $sql = "WITH opening AS (
                SELECT productID, storageID,
                    SUM(CASE WHEN entryType = 'IN' THEN quantity ELSE -quantity END) AS openingQty
                FROM vw_productInOut
                WHERE entryDate < :fDate
                GROUP BY productID, storageID
            )
            SELECT
                ROW_NUMBER() OVER (ORDER BY vio.entryDate, vio.orderID) AS No,
                vio.*,
                -- running quantity including previous dates
                COALESCE(op.openingQty, 0)
                + SUM(CASE WHEN vio.entryType = 'IN' THEN vio.quantity ELSE -vio.quantity END) 
                OVER (
                    PARTITION BY vio.productID, vio.storageID
                    ORDER BY vio.entryDate, vio.orderID
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) AS runningQuantity
            FROM vw_productInOut vio
            LEFT JOIN opening op
                ON op.productID = vio.productID
            AND op.storageID = vio.storageID
            WHERE vio.entryDate BETWEEN :fDate AND :tDate";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($product)) {
            $sql .= " AND vio.productID = :pro";
            $params[':pro'] = $product;
        }

        if (!empty($storage)) {
            $sql .= " AND vio.storageID = :stg";
            $params[':stg'] = $storage;
        }

        if (!empty($personal)) {
            $sql .= " AND vio.perID = :perID";
            $params[':perID'] = $personal;
        }

        $sql .= " ORDER BY vio.entryDate, vio.orderID";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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