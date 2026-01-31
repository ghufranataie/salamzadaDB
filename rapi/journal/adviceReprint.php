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
        get_printDetails();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_printDetails() {
    global $conn;

    $ref = $_GET['ref'];


    try {
        $sql = "select tr.trnReference, tr.trnType, td.trdAmount, td.trdCcy, td.trdBranch, td.trdAccount, td.trdNarration,
            td.trdEntryDate, mk.usrName as maker, ck.usrname as checker from transactions tr
            join trnDetails td on td.trdReference = tr.trnReference
            join users mk on mk.usrID = tr.trnMaker
            left join users ck on ck.usrID = tr.trnAuthorizer
            where 
                td.trdAccount != 10101010 and
                td.trdReference = :ref
            order by td.trdEntryDate desc limit 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":ref", $ref, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
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