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
        get_userRolePermission();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_userRolePermission() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $username = $input['username'];
    $role = $input['role'];

    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY u.usrID) as No,
            up.uprID as permissionID,
            u.usrName as username, 
            u.usrID as userID,
            u.usrRole as role,
            rsg.rsgID as roleID,
            rsg.rsgName as roleName,
            rmg.rmgName as roleMainGroup,
            case when up.uprStatus = 1 then 'Granted' else 'Denied' end  as roleStatus
        from userPermissions up
        join roleSubGroup rsg on rsg.rsgID = up.uprRole
        join roleMainGroup rmg on rmg.rmgID = rsg.rsgMainGroup
        join users u on u.usrID = up.uprUserID
        where u.usrRole != 'Super'";

        $params = [];

        if (!empty($username)) {
            $sql .= " AND u.usrName = :uName";
            $params[':uName'] = $username;
        }

        if (!empty($role)) {
            $sql .= " AND u.usrRole = :rl";
            $params[':rl'] = $role;
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