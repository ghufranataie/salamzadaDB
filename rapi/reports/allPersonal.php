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
        get_personal();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_personal() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $name = $input['flNameSearch'];
    $dob = $input['dob'];
    $phone = $input['phone'];
    $gender = $input['gender'];

    try {
        $sql = "SELECT perID, perName, perLastName, perGender, perDoB, perENIDNo, perPhone, perEmail, 
                concat(a.addName, ' ', a.addCity,' ', a.addProvince, ' ', a.addCountry, ' ', a.addZipCode) as address
            FROM personal p
            JOIN address a on a.addID = p.perAddress
            WHERE p.perID > 0";

        $params = [];

        if (!empty($name)) {
            $sql .= " AND p.perName like :pName or p.perLastName like :pName";
            $params[':pName'] = "%$name%";
        }

        if (!empty($dob)) {
            $sql .= " AND p.perDoB >= :dob";
            $params[':dob'] = $dob;
        }
        if (!empty($phone)) {
            $sql .= " AND p.perPhone = :phone";
            $params[':phone'] = $phone;
        }
        if (!empty($gender)) {
            $sql .= " AND p.perGender = :gender";
            $params[':gender'] = $gender;
        }
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