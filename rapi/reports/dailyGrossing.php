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
        daily_grossing();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function daily_grossing() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;
    $from = $data['from'];
    $to = $data['to'];
    $sGroup = $data['startGroup'];
    $eGroup = $data['stopGroup'];
    try {

        $stmt = $conn->prepare("SELECT Date(trdEntryDate) as dates, 
                case when ag.acgCategory=1 then 'Asset' when ag.acgCategory=2 then 'Liability' when ag.acgCategory=3 then 'Proffit' when ag.acgCategory=4 then 'Loss' end as category,
                abs(sum(case when trdDrCr='Cr' then trdAmount else -trdAmount end)) as balance
            from trnDetails td
            join accounts ac on ac.accNumber = td.trdAccount
            join accountCategory ag on ag.acgID = ac.accCategory
            where ag.acgCategory between ? and ? and Date(trdEntryDate) between ? and ?
            group by Date(trdEntryDate), ac.accCategory
            order by Date(trdEntryDate), ac.accCategory");
        $stmt->execute([$sGroup, $eGroup, $from, $to]);
        $data = $stmt->fetchALL(PDO::FETCH_ASSOC);

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