<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
$servername = "db5019002027.hosting-data.io";
$username   = "dbu1596977";
$password   = "UTDallas123$$";
$dbname     = "dbs14962566";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Retrieve data from POST
$isbn = $_POST['isbn'] ?? '';
$title = $_POST['title'] ?? '';

if (empty($isbn) || empty($title)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields (isbn or title)."]);
    $conn->close();
    exit;
}

// Update Book Title
// Note: We assume ISBN cannot be changed because it is the Primary Key and Foreign Key in other tables.
// Changing an ISBN would require a complex ON UPDATE CASCADE setup or multiple queries.
$stmt = $conn->prepare("UPDATE Book SET Title = ? WHERE Isbn = ?");
if (!$stmt) {
    die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
}

$stmt->bind_param("ss", $title, $isbn);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Book title updated successfully."
        ]);
    } else {
        // Query worked, but nothing changed (maybe the title was already the same, or ISBN not found)
        echo json_encode([
            "status" => "success", // Still success, just no change needed
            "message" => "No changes made (Title was identical or Book not found)."
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>