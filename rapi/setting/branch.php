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
        get_branchs();
        break;
    case 'POST':
        add_branchs();
        break;
    case 'PUT':
        update_branchs();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_branchs() {
    global $conn;
    try {
        if (isset($_GET['brcID']) && !empty($_GET['brcID'])) {
            // Use SQL for single record
            $sql = "select * from branch join address on address.addID = branch.brcAddress where brcID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['brcID'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "select * from branch join address on address.addID = branch.brcAddress";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
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

function add_branchs() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $brcName = $data['brcName'];
    $brcPhone = $data['brcPhone'];
    $addName = $data['addName'];
    $city = $data['addCity'];
    $province = $data['addProvince'];
    $country = $data['addCountry'];
    $zipCode = $data['addZipCode'];
    $newBrcID = $value->generateBranchID();
    $com = 1;

    try {
        $conn->beginTransaction();
        $conn->exec("SET time_zone = '+04:30'");

        $stmt1 = $conn->prepare("INSERT into address (addName, addCity, addProvince, addCountry, addZipCode) values (?, ?, ?, ?, ?)");
        $stmt1->execute([$addName, $city, $province, $country, $zipCode]);
        $addID = $conn->lastInsertId();

        $stmt2 = $conn->prepare("INSERT into branch (brcID, brcCompany, brcName, brcAddress, brcPhone, brcEntryDate) values (?, ?, ?, ?, ?, now())");
        $stmt2->execute([$newBrcID, $com, $brcName, $addID, $brcPhone]);       
        
        $conn->commit();

        echo json_encode(["msg" => "success", "branch ID" => $newBrcID], JSON_PRETTY_PRINT);
    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile()
        ]);
    }
}

function update_branchs(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $brcID = $data['brcID'];
    $brcName = $data['brcName'];
    $brcPhone = $data['brcPhone'];
    $brcStatus = $data['brcStatus'];
    $addID = $data['addID'];
    $addName = $data['addName'];
    $city = $data['addCity'];
    $province = $data['addProvince'];
    $country = $data['addCountry'];
    $zipCode = $data['addZipCode'];

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE branch SET brcName=?, brcPhone=?, brcStatus=? WHERE brcID=?");
        $stmt1->execute([$brcName, $brcPhone, $brcStatus, $brcID]);

        $stmt2 = $conn->prepare(
            "UPDATE address SET addName=?, addCity=?, addProvince=?, addCountry=?, addZipCode=? 
             WHERE addID=?"
        );
        $stmt2->execute([$addName, $city, $province, $country, $zipCode, $addID]);

        $conn->commit();
        echo json_encode(["msg" => "success", "branch ID" => $brcID]);

    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            "msg" => "failed",
            "error" => $th->getMessage(),
            "line" => $th->getLine(),
            "file" => $th->getFile(),
            "trace" => $th->getTrace()
        ]);
    }
}
?>