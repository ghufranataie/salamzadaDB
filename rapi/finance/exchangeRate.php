
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

// $value = new DataValues($conn);
// $crFrom = 'USD';
// $crTo = 'AFN';
// $currentRate = $value->getCcyRate($crFrom, $crTo);
// echo $currentRate;

$data = json_decode(file_get_contents("php://input"), true);
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        get_exchangeRate();
        break;
    case 'POST':
        add_exchangeRate();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_exchangeRate() {
    global $conn;
    try {
        if (isset($_GET['ccy']) && !empty($_GET['ccy'])) {
            // Use SQL for single record
            $sql = "SELECT crID, crFrom, left(crFrom, 2) as fromCode, crTo, left(crTo, 2) as toCode, c.ccyLocalName, crExchange, crDate
                    FROM (
                        SELECT *,
                            ROW_NUMBER() OVER (
                                PARTITION BY crFrom, crTo
                                ORDER BY crDate DESC
                            ) AS rn
                        FROM ccyRate
                    ) AS t
                    join currency c on c.ccyCode = t.crTo
                    WHERE rn = 1 and crFrom = :ccy AND crFrom != crTo ";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':ccy', $_GET['ccy'], PDO::PARAM_STR);
        
        } else {
            // Use default SQL for all records
            $sql = "SELECT crID, crFrom, left(crFrom, 2) as fromCode, crTo, left(crTo, 2) as toCode, c.ccyLocalName, crExchange, crDate
                    FROM (
                        SELECT *,
                            ROW_NUMBER() OVER (
                                PARTITION BY crFrom, crTo
                                ORDER BY crDate DESC
                            ) AS rn
                        FROM ccyRate
                    ) AS t
                    join currency c on c.ccyCode = t.crTo
                    WHERE rn = 1 AND crFrom != crTo ";
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
function add_exchangeRate() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    $from = $data['crFrom'];
    $to = $data['crTo'];
    $rate = $data['crExchange'];

    try {
        $conn -> beginTransaction();
        $conn->exec("SET time_zone = '+04:30'");

        $stmt = $conn->prepare("INSERT into ccyRate (crFrom, crTo, crExchange, crDate) values (?, ?, ?, now())");
        $stmt->execute([$from, $to, $rate]);
        $conn->commit();

        echo json_encode(["msg" => "success", "rate" => $rate], JSON_PRETTY_PRINT);
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

?>