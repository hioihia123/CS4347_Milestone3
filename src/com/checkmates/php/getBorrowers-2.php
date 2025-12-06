<?php
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "db5019002027.hosting-data.io";
$username   = "dbu1596977";
$password   = "UTDallas123$$";
$dbname     = "dbs14962566";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

$sql = "
    SELECT 
        bor.Card_id,
        bor.Ssn,
        bor.Bname,
        bor.Address,
        bor.Phone
    FROM Borrowers AS bor
    ORDER BY bor.Card_id ASC
";

$result = $conn->query($sql);

if (!$result) {
    die(json_encode(["status" => "error", "message" => "Query failed: " . $conn->error]));
}

$loans = [];
while ($row = $result->fetch_assoc()) {
    $loans[] = $row;
}

echo json_encode([
    "status" => "success",
    "loans" => $loans
]);

$conn->close();
?>
