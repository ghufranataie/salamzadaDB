
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
        get_currency();
        break;
    case 'POST':
        add_currency();
        break;
    case 'PUT':
        update_currency();
        break;
    case 'DELETE':
        delete_currency();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_currency() {
    global $conn;
    try {
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            // Use SQL for single record
            $sql = "select * from currency
                    where ccyStatus = :id order by ccyStatus ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['status'], PDO::PARAM_INT);
        
        } else {
            // Use default SQL for all records
            $sql = "select * from currency";
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
function add_currency() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $code = $data['ccyCode'];
    $name = $data['ccyName'];
    $symbol = $data['ccySymbol'];
    $country = $data['ccyCountry'];
    $status = $data['ccyStatus'];

    try {
        $conn -> beginTransaction();

        $stmt = $conn->prepare("INSERT into currency (ccyCode, ccyName, ccySymbol, ccyCountry, ccyStatus) values (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $symbol, $country, $status]);
        $conn->commit();

        echo json_encode(["msg" => "success", "currency" => $name], JSON_PRETTY_PRINT);
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
function update_currency(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $code = $data['ccyCode'];
    $status = $data['ccyStatus'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE currency SET ccyStatus=? WHERE ccyCode=?");
        $stmt->execute([$status, $code]);

        $conn->commit();
        echo json_encode(["msg" => "success", "currency updated" => $code]);
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
function delete_currency(){
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);
    $code = $data['ccyCode'];
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE from currency WHERE ccyCode=?");
        $stmt->execute([$code]);
        $conn->commit();
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