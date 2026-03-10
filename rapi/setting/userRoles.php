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
        get_userRole();
        break;
    case 'POST':
        add_userRole();
        break;
    case 'PUT':
        update_userRole();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_userRole() {
    global $conn;
    try {
        $sql = "SELECT * from roles";
        if (isset($_GET['role']) && !empty($_GET['role'])) {
            $sql .= " WHERE rolID = :id and rolName != 'Super'";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['role'], PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmtRP = $conn->prepare("SELECT rpID, rsgName, rpStatus from rolePermission rp join roleSubGroup rsg on rsg.rsgID = rp.rpRoleNameID WHERE rpRoleID = :id");
            $stmtRP->bindParam(':id', $_GET['role'], PDO::PARAM_INT);
            $stmtRP->execute();
            $permissions = $stmtRP->fetchAll(PDO::FETCH_ASSOC);
            $data["permissions"] = $permissions;
            echo json_encode($data, JSON_PRETTY_PRINT);

        } else {
            $sql .= " WHERE rolStatus = 1 and rolName != 'Super'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtRP = $conn->prepare("SELECT rpID, rsgName, rpStatus from rolePermission rp join roleSubGroup rsg on rsg.rsgID = rp.rpRoleNameID WHERE rpRoleID = :id Order by rpID ASC");

            foreach($data as &$role){
                $stmtRP->bindParam(':id', $role['rolID'], PDO::PARAM_INT);
                $stmtRP->execute();
                $permissions = $stmtRP->fetchAll(PDO::FETCH_ASSOC);
                $role['permissions'] = $permissions;
            }
            echo json_encode($data, JSON_PRETTY_PRINT);
        }
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

function add_userRole() {
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

function update_userRole(){
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    $user = $data['usrName'];
    $permissions = $data['permissions'];
   

    try {
        $conn->beginTransaction();

        foreach($permissions as $perm){
            $permID = $perm['rpID'];
            $stmt2 = $conn->prepare("UPDATE rolePermission SET rpStatus = ? WHERE rpID = ?");
            $stmt2->execute([$perm['rpStatus'], $permID]);
        }

        $value->generateUserActivityLog(
            $user, 
            "Role Permissions",
            "Updated Role Permissions",
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
?>