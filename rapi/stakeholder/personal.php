<?php

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
        get_personal();
        break;
    case 'POST':
        add_personal();
        break;
    case 'PUT':
        update_personal();
        break;
    case 'DELETE':
        delete_personal();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}


function get_personal() {
    global $conn;
    try {

        if (isset($_GET['perID']) && !empty($_GET['perID'])) {

            // Use SQL for single record
            $sql = "SELECT * FROM personal join address on address.addID = personal.perAddress WHERE perID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['perID'], PDO::PARAM_INT);
        
        } else {

            // Use default SQL for all records
            $sql = "SELECT * FROM personal join address on address.addID = personal.perAddress order by perID desc";
            $stmt = $conn->prepare($sql);
        }
        
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data, JSON_PRETTY_PRINT);
    } 
    catch (PDOException $e) {
        echo json_encode(["msg" => $e->getMessage()]);
    }
}

function add_personal() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    $firstName = $data['first_name'];
    $lastName = $data['last_name'];
    $dob = $data['per_DoB'];
    $gender = $data['per_gender'];
    $tazNo = $data['per_nidno'];
    $cellNo = $data['cell_number'];
    $email = $data['email'];
    $addName = $data['add_name'];
    $city = $data['add_city'];
    $province = $data['add_province'];
    $country = $data['add_country'];
    $zipCode = $data['zip_code'];
    $isMailing = $data['is_mailing'];
    $accCategory = 8;
    $newAcc = $value->getNewAccount($accCategory);
    $localCcy = $value->getLocalCurrency();
    $creditLimit = 0;
    $com = 1;

    try {
        $conn -> beginTransaction();

        $stmt1 = $conn->prepare("insert into address (addName, addCity, addProvince, addCountry, addZipCode, addMailing) values (?, ?, ?, ?, ?, ?)");
        $stmt1->execute([$addName, $city, $province, $country, $zipCode, $isMailing]);
        $addID = $conn->lastInsertId();

        $stmt2 = $conn->prepare("insert into personal (perName, perLastName, perGender, perDoB, perENIDNo, perAddress, perPhone, perEmail) values (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$firstName, $lastName, $gender, $dob, $tazNo, $addID, $cellNo, $email]);
        $personalID = $conn->lastInsertId();

        $stmt3 = $conn->prepare("insert into accounts (accNumber, accName, accCategory) values (?, ?, ?)");
        $stmt3->execute([$newAcc, "$firstName $lastName", $accCategory]);

        $stmt4 = $conn->prepare("insert into accountDetails (actAccount, actCurrency, actCreditlimit, actSignatory, actCompany) values (?, ?, ?, ?, ?)");
        $stmt4->execute([$newAcc, $localCcy, $creditLimit, $personalID, $com]);

        
        $conn->commit();

        echo json_encode(["msg" => "success", "personalID" => $personalID], JSON_PRETTY_PRINT);
    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode(["msg" => "failed", "error" => $th->getMessage()], JSON_PRETTY_PRINT);
    }
}

function update_personal() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);
    $perID = $data['per_ID'];
    $firstName = $data['first_name'];
    $lastName = $data['last_name'];
    $dob = $data['per_DoB'];
    $gender = $data['per_gender'];
    $tazNo = $data['per_nidno'];
    $cellNo = $data['cell_number'];
    $email = $data['email'];
    $addID = $data['add_ID'];
    $addName = $data['add_name'];
    $city = $data['add_city'];
    $province = $data['add_province'];
    $country = $data['add_country'];
    $zipCode = $data['zip_code'];
    $isMailing = $data['is_mailing'];


    try {
        $conn->beginTransaction();

        $stmt3 = $conn->prepare(
            "UPDATE personal SET perName=?, perLastName=?, perDoB=?, perENIDNo=?, perGender=?, perPhone=?, perEmail=? WHERE perID=?"
        );
        $stmt3->execute([$firstName, $lastName, $dob, $tazNo, $gender, $cellNo, $email, $perID]);

        $stmt4 = $conn->prepare(
            "UPDATE address SET addName=?, addCity=?, addProvince=?, addCountry=?, addZipCode=?, addMailing=? 
             WHERE addID = ?"
        );
        $stmt4->execute([$addName, $city, $province, $country, $zipCode, $isMailing, $addID]);

        $conn->commit();
        echo json_encode(["msg" => "success", "perID" => $perID]);
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

function delete_personal() {
    global $conn;
    $id = $_GET["id"];
    $sql = "DELETE FROM users WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(array("message" => "User deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Error: " . $sql . "<br>" . $conn->error));
    }
}

?>