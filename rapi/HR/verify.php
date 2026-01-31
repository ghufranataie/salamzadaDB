
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

try {
    if (!isset($_GET['token'])) {
        die("Invalid request.");
    }

    $token = $_GET['token'];

    // Look for user with that token
    $sql = "SELECT * FROM users WHERE usrToken = :token LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $user = $row['usrName'];
        // Update user to verified
        $stmt1 = $conn->prepare("UPDATE users SET usrToken = 'verified' where usrName = ?");
        $stmt1->execute([$user]);

        echo "Your email has been verified! You can now login.";

    } else {
        echo "Invalid or expired token.";
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




?>