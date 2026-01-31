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

    $input = json_decode(file_get_contents("php://input"), true);
    $fromDate = $input['fromDate'] ?? null;
    $toDate   = $input['toDate'] ?? null;

    
    $localCcy = $value->getCompanyAttributes("comLocalCcy");
    

    try {
        $sql = "SELECT tp.trntName, 
                sum(case when td.trdDrCr='Cr' then td.trdAmount else td.trdAmount end)/2 as total,
                count(*) as total_trn
            FROM trnDetails td
            join transactions tr on tr.trnReference = td.trdReference
            join trnTypes tp on tp.trntCode = tr.trnType
            where date(td.trdEntryDate) BETWEEN :fDate AND :tDate 
            group by tp.trntName";
        
        $params = [
            ':fDate' => $fromDate,
            ':tDate' => $toDate
        ];


        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result, JSON_PRETTY_PRINT);
    } catch (PDOException $th) {
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

?>