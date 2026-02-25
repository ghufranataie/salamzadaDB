
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
        get_permissions();
        break;
    case 'PUT':
        update_permissions();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_permissions() {
    global $conn;
    try {
        if (!empty($_GET['username'])) {

            $sql = "SELECT 
                        userPermissions.uprRole,
                        users.usrName,
                        userPermissions.uprStatus,
                        roleSubGroup.rsgName
                    FROM userPermissions
                    JOIN users ON users.usrID = userPermissions.uprUserID
                    JOIN roleSubGroup ON roleSubGroup.rsgID = userPermissions.uprRole
                    WHERE users.usrName = :username";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $_GET['username'], PDO::PARAM_STR); // FIXED
            $stmt->execute();

            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
        }
    } 
    catch (PDOException $e) {
        echo json_encode(["msg" => $e->getMessage()]);
    }
}

function update_permissions(){
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['LogedInUser'];
    $userID = $data['uprUserID'];
    $records = $data['records'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE userPermissions SET uprStatus=? WHERE uprRole=? and uprUserID=?");
        foreach($records['records'] as $rec){
            $roleStatus = $rec['uprStatus'];
            $roleID = $rec['uprRole'];
            $stmt->execute([$roleStatus, $roleID, $userID]);
        }

        $value->generateUserActivityLog(
            $user, 
            'User Permission',
            "Modify Permissions from UserID: $userID"
        );

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