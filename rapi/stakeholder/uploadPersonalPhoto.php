
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../db.php";
$db = new Database();
$conn = $db->getConnection();
$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'POST':
        upload_personalPhoto();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}


function upload_personalPhoto(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    try {
        if (isset($_POST['perID']) && isset($_FILES['image'])) {
            $perID = $_POST['perID'];
            $fileName = $_FILES['image']['name'];
            $tmpName  = $_FILES['image']['tmp_name'];

            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newName = time().'.'.$ext;
            $uploadDir = realpath(__DIR__ . '/../../images/personal');
            $uploadPath = $uploadDir . '/' . $newName;

            if (move_uploaded_file($tmpName, $uploadPath)) {

                $stmt1 = $conn->prepare("select perPhoto from personal where perID=?");
                $stmt1->execute([$perID]);
                $result = $stmt1->fetch(PDO::FETCH_ASSOC);
                $oldPhotoName = $result['perPhoto'];
                if(!empty($oldPhotoName)){
                    unlink($uploadDir .'/'. $oldPhotoName);
                }
                $stmt2 = $conn->prepare("UPDATE personal SET perPhoto = ? WHERE perID = ?");
                if ($stmt2->execute([$newName, $perID])) {
                    echo json_encode(["msg" => "success"]);
                }
            } else {
                echo json_encode(["msg" => "upload failed"]);
            }
        } else {
            echo json_encode(["msg" => "ID or file missing"]);
        }

    } catch (\Throwable $th) {
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