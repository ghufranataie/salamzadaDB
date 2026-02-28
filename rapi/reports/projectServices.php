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
        get_projectServices();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_projectServices() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $from = $input['fromDate'];
    $to = $input['toDate'];
    $service = $input['services'];
    $project = $input['project'];

    try {
        $sql = "SELECT
                ps.srvName as ServiceName,
                p.prjName as ProjectName,
                Date(prjEntryDate) as EntryDate,
                pd.pjdQuantity,
                pd.pjdPricePerQty,
                sum(pd.pjdPricePerQty * pd.pjdQuantity) as totalAmount
            from projectDetails pd
            join projectServices ps on ps.srvID = pd.pjdServices
            join projects p on p.prjID = pd.pjdProject
            WHERE DATE(p.prjEntryDate) BETWEEN :fDate AND :tDate";

        $params = [
            ':fDate' => $from,
            ':tDate' => $to
        ];

        if (!empty($service)) {
            $sql .= " AND ps.srvID = :srv ";
            $params[':srv'] = $service;
        }

        if (!empty($project)) {
            $sql .= " AND p.prjID = :proj";
            $params[':proj'] = $project;
        }

        $sql .= " GROUP BY ps.srvID, p.prjID";

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