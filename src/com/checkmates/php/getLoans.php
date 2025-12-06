<?php
// getLoans.php - Fetches ALL loan history

header("Content-Type: application/json");
// Enable error reporting to catch the "500" cause
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

// The Query
// select all columns needed.
$sql = "
    SELECT 
        bl.Loan_id,
        bl.Isbn, 
        bl.Card_id, 
        b.Bname,
        bl.Date_out, 
        bl.Due_date, 
        bl.Date_in, 
        bl.lib_id_checkout, 
        bl.lib_id_return
    FROM Book_Loans AS bl
    LEFT JOIN Borrowers AS b ON bl.Card_id = b.Card_id
    ORDER BY bl.Date_out DESC
";

$result = $conn->query($sql);

if (!$result) {
    // This will print the specific SQL error if the query fails
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