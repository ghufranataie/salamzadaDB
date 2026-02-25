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
        load_projectServices();
        break;
    case 'POST':
        create_projectServices();
        break;
    case 'PUT':
        update_projectServices();
        break;
    case 'DELETE':
        delete_projectServices();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function load_projectServices() {
    global $conn;
    try {
        $sql = "SELECT * from projectServices";
        
        if (isset($_GET['srvID']) && !empty($_GET['srvID'])) {
            $srvID = $_GET['srvID'];
            $sql .= " WHERE srvID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $srvID, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

        } elseif (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = "%" . $_GET['search'] . "%";
            $sql .= " WHERE srvName LIKE :search";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':search', $search, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $sql .= " order by srvID DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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

function create_projectServices() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));

    $name = $data['srvName'];
    $status = 1;
    


    try {
        $stmt0 = $conn->prepare("SELECT * from projectServices where srvName = ?");
        $stmt0->execute([$name]);
        $row = $stmt0->fetch(PDO::FETCH_ASSOC);
        if($row){
            echo json_encode(["msg" => "exist"], JSON_PRETTY_PRINT);
            exit;
        }
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO projectServices (srvName, srvStatus) VALUES (?, ?)");
        $stmt->execute([$name, $status]);

        $conn->commit();
        echo json_encode(["msg" => "success"], JSON_PRETTY_PRINT);

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

function update_projectServices(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set($value->getCompanyAttributes('comTimeZone'));
    $data = json_decode(file_get_contents("php://input"), true);

    $srvID = $data['srvID'];
    $name = $data['srvName'];
    $status = $data['srvStatus'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE projectServices SET srvName = ?, srvStatus = ? WHERE srvID = ?");
        $stmt->execute([$name, $status, $srvID]);

        $conn->commit();
        echo json_encode(["msg" => "success"]);

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

function delete_projectServices(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    try {
        if(isset($_GET['srvID']) && !empty($_GET['srvID'])){
            $srvID = $_GET['srvID'];
            $stmt0 = $conn->prepare("SELECT * from projectDetails where pjdService = ?");
            $stmt0->execute([$srvID]);
            $project = $stmt0->fetch(PDO::FETCH_ASSOC);
            if($project){
                echo json_encode(["msg" => "dependency"], JSON_PRETTY_PRINT);
                exit;
            }
        }
        $conn->beginTransaction();
   
        $stmt = $conn->prepare("DELETE FROM projectServices WHERE srvID = ?");
        $stmt->execute([$srvID]);

        $conn->commit();
        echo json_encode(["msg" => "success"]);
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