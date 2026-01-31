
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
    case 'GET':
        get_comProfile();
        break;
    case 'POST':
        upload_comProfile();
        break;
    case 'PUT':
        update_comProfile();
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method Not Allowed"));
        break;
}
function get_comProfile() {
    global $conn;
    try {
        $sql = "select comID, comName, comLicenseNo, comSlogan, comDetails, comPhone, comEmail, comWebsite, comLogo, concat(perName, ' ', perLastName) as comOwner, comAddress, comFB, comInsta, comWhatsapp,
                comVerify, comLocalCcy, comTimeZone, comEntryDate, addName, addCity, addProvince, addCountry, addZipCode  from companyProfile
                left join address on address.addID = companyProfile.comAddress
                join personal on personal.perID = companyProfile.comOwner";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as &$row) {
            if (isset($row['comLogo'])) {
                $row['comLogo'] = base64_encode($row['comLogo']);
            }
        }
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

function update_comProfile(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['comID'];
    $licenseNo = $data['comLicenseNo'];
    $slogan = $data['comSlogan'];
    $details = $data['comDetails'];
    $phone = $data['comPhone'];
    $email = $data['comEmail'];
    $website = $data['comWebsite'];
    $address = $data['comAddress'];
    $fb = $data['comFB'];
    $ig = $data['comInsta'];
    $ws = $data['comWhatsapp'];
    $addID = $data['comAddress'];
    $addName = $data['addName'];
    $city = $data['addCity'];
    $province = $data['addProvince'];
    $country = $data['addCountry'];
    $zipCode = $data['addZipCode'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE companyProfile SET
        comLicenseNo=?, comSlogan=?, comDetails=?, comPhone=?, comEmail=?, comWebsite=?, comFB=?, comInsta=?, comWhatsapp=? WHERE comID=?");
        $stmt->execute([$licenseNo, $slogan, $details, $phone, $email, $website, $fb, $ig, $ws, $id]);

        $stmt1 = $conn->prepare("UPDATE address SET
        addName=?, addCity=?, addProvince=?, addCountry=?, addZipCode=? WHERE addID=?");
        $stmt1->execute([$addName, $city, $province, $country, $zipCode, $address]);

        $conn->commit();
        echo json_encode(["msg" => "success", "company" => $id]);
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

function upload_comProfile(){
    global $conn;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $logo = file_get_contents($_FILES['image']['tmp_name']);

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE companyProfile SET comLogo=?");
        $stmt->execute([$logo]);

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