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
        get_users();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}

function get_users() {
    global $conn;

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) exit;
    $username = $input['username'];
    $status = $input['status'];
    $branch = $input['branch'];
    $role = $input['role'];

    try {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY u.usrID) as No,
                u.usrOwner as personal_ID,
                u.usrName as username,
                concat(p.perName, ' ', p.perLastName) as fullName,
                u.usrBranch as branch,
                u.usrEmail as email, 
                p.perPhone as phone,
                u.usrRole as role,
                case when u.usrToken = 'verified' then 'Yes' else 'No' end as verification,
                case when u.usrFCP = 1 then 'Yes' else 'No' end as fcp,
                u.usrALFCounter as alf,
                case when u.usrStatus = 1 then 'Active' else 'Blocked' end as status,
                date(u.usrEntryDate) as createDate
            from users u
            join personal p on p.perID = u.usrOwner
            Where usrID != 0";

        $params = [];

        if (!empty($username)) {
            $sql .= " AND u.usrName = :uName";
            $params[':uName'] = $username;
        }

        if (isset($status) && $status !== '') {
            $sql .= " AND u.usrStatus = :statu";
            $params[':statu'] = $status;
        }

        if (!empty($branch)) {
            $sql .= " AND u.usrBranch = :brc";
            $params[':brc'] = $branch;
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