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
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        get_singleTransaction();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_singleTransaction() {
    global $conn;

    $ref = $_GET['ref'];


    try {
        $sql="SELECT tr.trnReference, tr.trnType, tr.trnStatus, tr.trnStateText, mk.usrName AS maker, ck.usrName AS checker,
        CASE 
            WHEN trnType IN ('CHDP','GLCR','INCM') AND trdDrCr='Cr' THEN trdAccount
            WHEN trnType IN ('CHWL','XPNS','GLDR') AND trdDrCr='Dr' THEN trdAccount
        END AS account, accName,
        CASE 
            WHEN trnType IN ('CHDP','GLCR','INCM') AND trdDrCr='Cr' THEN trdAmount
            WHEN trnType IN ('CHWL','XPNS','GLDR') AND trdDrCr='Dr' THEN trdAmount
        END AS amount,
        CASE 
            WHEN trnType IN ('CHDP','GLCR','INCM') AND trdDrCr='Cr' THEN trdCcy
            WHEN trnType IN ('CHWL','XPNS','GLDR') AND trdDrCr='Dr' THEN trdCcy
        END AS currency,
        CASE 
            WHEN trnType IN ('CHDP','GLCR','INCM') AND trdDrCr='Cr' THEN trdNarration
            WHEN trnType IN ('CHWL','XPNS','GLDR') AND trdDrCr='Dr' THEN trdNarration
        END AS narration,
        CASE 
            WHEN trnType IN ('CHDP','GLCR','INCM') AND trdDrCr='Cr' THEN trdBranch
            WHEN trnType IN ('CHWL','XPNS','GLDR') AND trdDrCr='Dr' THEN trdBranch
        END AS branch,tr.trnEntryDate FROM transactions tr
        LEFT JOIN trnDetails d 
            ON d.trdReference = tr.trnReference
            AND (
                    (tr.trnType IN ('CHDP','GLCR','INCM') AND d.trdDrCr='Cr')
                OR (tr.trnType IN ('CHWL','XPNS','GLDR') AND d.trdDrCr='Dr')
                )
        JOIN users mk ON mk.usrID = tr.trnMaker
        LEFT JOIN users ck ON ck.usrID = tr.trnAuthorizer
        left join accounts on accounts.accNumber = d.trdAccount
        WHERE tr.trnReference = :ref";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':ref', $ref, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data, JSON_PRETTY_PRINT);
    } 
    catch (PDOException $e) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

?>