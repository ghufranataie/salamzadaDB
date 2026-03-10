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
        get_role();
        break;
    case 'POST':
        add_role();
        break;
    case 'PUT':
        update_role();
        break;
    case 'DELETE':
        delete_role();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_role() {
    global $conn;
    try {
        $sql = "SELECT * from roles";
        if (isset($_GET['role']) && !empty($_GET['role'])) {
            $sql .= " WHERE rolID = :id and rolName != 'Super'";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['role'], PDO::PARAM_STR);
            $stmt->execute();

        } else {
            $sql .= " WHERE rolName != 'Super'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }
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

function add_role() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $role= $data['rolName'];
   
    try {
        $stmt = $conn->prepare("SELECT count(*) from roles where rolName = ?");
        $stmt->execute([$role]);
        $count = $stmt->fetchColumn();
        if($count > 0){
             echo json_encode(["msg" => "exist", "count" => $count]);
             exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("INSERT into roles (rolName) values (?)");
        $stmt1->execute([$role]);
        $roleID = $conn->lastInsertId();

        $stmt3 = $conn->prepare("INSERT into rolePermission (rpRoleID, rpRoleNameID, rpStatus) SELECT ?, rsgID, 0 from roleSubGroup");
        $stmt3->execute([$roleID]);

        $value->generateUserActivityLog(
            $user, 
            "User Role",
            "Create New Role: $role",
        );

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

function update_role(){
   global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $roleID = $data['rolID'];
    $roleName = $data['rolName'];
    $roleStatus = $data['rolStatus'];
   

    try {
        $conn->beginTransaction();

        $stmt1 = $conn->prepare("UPDATE roles SET rolName = ?, rolStatus = ? WHERE rolID = ?");
        $stmt1->execute([$roleName, $roleStatus, $roleID]);

        $value->generateUserActivityLog(
            $user, 
            "User Role",
            "Updated Role: $roleName Role ID: $roleID",
        );

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

function delete_role(){
    global $conn, $value;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $roleID = $data['rolID'];

    try {
        $stmt = $conn->prepare("SELECT * from users where usrRole = ?");
        $stmt->execute([$roleID]);
        $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($row){
            echo json_encode(["msg" => "exist"]);
            exit;
        }

        $conn->beginTransaction();

        $stmt1 = $conn->prepare("DELETE from rolePermission WHERE rpRoleID = ?");
        $stmt1->execute([$roleID]);

        $stmt2 = $conn->prepare("DELETE from roles where rolID = ?");
        $stmt2->execute([$roleID]);

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