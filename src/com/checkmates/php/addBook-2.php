<?php
date_default_timezone_set('America/Chicago');
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

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

// Retrieve data from POST (Matches Java's lowercase keys)
$isbn      = $_POST['isbn'] ?? '';
$title     = $_POST['title'] ?? '';
$author_id = $_POST['author_id'] ?? '';

if (empty($isbn) || empty($title) || empty($author_id)) {
    echo json_encode(["status" => "error", "message" => "Missing fields. Received: isbn=$isbn, title=$title, auth=$author_id"]);
    $conn->close();
    exit;
}

// Start Transaction to ensure both inserts happen, or neither happens
$conn->begin_transaction();

try {
    // 1. Insert into BOOK table
    $stmt1 = $conn->prepare("INSERT INTO Book (Isbn, Title) VALUES (?, ?)");
    if (!$stmt1) {
        throw new Exception("Prepare Book failed: " . $conn->error);
    }
    $stmt1->bind_param("ss", $isbn, $title);
    if (!$stmt1->execute()) {
        // Check for duplicate entry error (Error code 1062)
        if ($conn->errno == 1062) {
            throw new Exception("Book with ISBN $isbn already exists.");
        }
        throw new Exception("Execute Book failed: " . $stmt1->error);
    }
    $stmt1->close();

    // 2. Insert into BOOK_AUTHORS table
    $stmt2 = $conn->prepare("INSERT INTO Book_Authors (Isbn, Author_id) VALUES (?, ?)");
    if (!$stmt2) {
        throw new Exception("Prepare Book_Authors failed: " . $conn->error);
    }
    $stmt2->bind_param("si", $isbn, $author_id);
    if (!$stmt2->execute()) {
        // This usually fails if the Author_id does not exist in the AUTHORS table
        throw new Exception("Failed to link Author. Does Author ID $author_id exist?");
    }
    $stmt2->close();

    // If we got here, commit the transaction
    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Book and Author link added successfully."
    ]);

} catch (Exception $e) {
    // Something went wrong, rollback changes
    $conn->rollback();
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>