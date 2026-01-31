<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../db.php";


$db = new Database();
$conn = $db->getConnection();


$data = json_decode(file_get_contents("php://input"), true);


$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        get_drivers();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}


function get_drivers() {
    global $conn;
    try {

        if (isset($_GET['empID']) && !empty($_GET['empID'])) {

            // Use SQL for single record
            $sql = "SELECT
                        empID, 
                        concat(perName, ' ', perLastName) as perfullName,
                        perPhone,
                        perPhoto,
                        concat(addName, ', ', addCity) as address,
                        empHireDate, 
                        empStatus 
                        -- concat(vclModel, '-', vclYear, '-', vclplateNo) as vehicle

                    FROM personal 
                    join address on address.addID = personal.perAddress
                    join employees on employees.empPersonal = personal.perID
                    -- left join vehicles on vehicles.vclDriver = employees.empID
                    where empPosition = 'Driver'
                    AND empID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $_GET['empID'], PDO::PARAM_INT);
        
        } else {

            // Use default SQL for all records
            $sql = "SELECT
                        empID, 
                        concat(perName, ' ', perLastName) as perfullName,
                        perPhone,
                        perPhoto,
                        concat(addName, ', ', addCity) as address,
                        empHireDate, 
                        empStatus
                        -- concat(vclModel, '-', vclYear, '-', vclplateNo) as vehicle

                    FROM personal 
                    join address on address.addID = personal.perAddress
                    join employees on employees.empPersonal = personal.perID
                    -- left join vehicles on vehicles.vclDriver = employees.empID
                    where empPosition = 'Driver' order by perID desc";
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

?>