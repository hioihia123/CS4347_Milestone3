<?php
// payFines.php - Fixes logic to ensure updates happen

header('Content-Type: application/json');
// Debugging: Uncomment to see errors in browser, but keep commented for JSON response
// ini_set('display_errors', 1); error_reporting(E_ALL);

$servername = "db5019002027.hosting-data.io";
$username   = "dbu1596977";
$password   = "UTDallas123$$";
$dbname     = "dbs14962566";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection failed"]));
}

$cardId = $_POST['card_id'] ?? '';
$loanIdsStr = $_POST['loan_ids'] ?? '';

if (empty($cardId) || empty($loanIdsStr)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters."]);
    $conn->close();
    exit;
}

// Convert "1,2,3" string to array [1, 2, 3]
$loanIds = explode(',', $loanIdsStr);
$loanIds = array_map('intval', $loanIds); // Ensure they are integers
$loanIds = array_filter($loanIds); // Remove 0s or empty

if (empty($loanIds)) {
    echo json_encode(["status" => "error", "message" => "Invalid Loan IDs."]);
    $conn->close();
    exit;
}

// 1. Verify all books are returned (Date_in IS NOT NULL)
$placeholders = implode(',', array_fill(0, count($loanIds), '?'));
$types = str_repeat('i', count($loanIds));

// Check for any active loans in this batch
$checkSql = "SELECT COUNT(*) as active_count FROM Book_Loans 
             WHERE Loan_id IN ($placeholders) AND Date_in IS NULL";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param($types, ...$loanIds);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['active_count'] > 0) {
    echo json_encode(["status" => "error", "message" => "Cannot pay. Some books are not returned yet."]);
    $conn->close();
    exit;
}

// 2. Perform the Payment Update
$updateSql = "UPDATE Fines SET Paid = 1 
              WHERE Loan_id IN ($placeholders) AND Paid = 0";

$stmt = $conn->prepare($updateSql);
$stmt->bind_param($types, ...$loanIds);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Payment successful!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "No unpaid fines found for these loans (already paid?)."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>