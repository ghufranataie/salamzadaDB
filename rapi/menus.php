<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

// connect
$db = new Database();
$conn = $db->getConnection();

// run query
$sql = "SELECT rmgID, rmgName, rsgID, rsgName, rsgStatus
    FROM roleMainGroup
    JOIN roleSubGroup ON roleSubGroup.rsgMainGroup = roleMainGroup.rmgID";

$stmt = $conn->prepare($sql);
$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];

// group data by rmgID
foreach ($data as $row) {

    $id = $row['rmgID'];

    // Create main group if not exists
    if (!isset($grouped[$id])) {
        $grouped[$id] = [
            "rmgID" => $row["rmgID"],
            "rmgName" => $row["rmgName"],
            "subGroups" => []
        ];
    }
    // Add sub group
    $grouped[$id]["subGroups"][] = [
        "rsgID" => $row["rsgID"],
        "rsgName" => $row["rsgName"],
        "rsgStatus" => $row["rsgStatus"]
    ];
}

// reset array keys
$grouped = array_values($grouped);

// pretty JSON output
echo json_encode($grouped, JSON_PRETTY_PRINT);
?>