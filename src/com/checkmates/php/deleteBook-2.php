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

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Get and validate input
$isbn = $_POST['isbn'] ?? '';

if (empty($isbn)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing ISBN.'
    ]);
    $conn->close();
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Delete related records from Book_Authors
    $stmt1 = $conn->prepare("DELETE FROM Book_Authors WHERE Isbn = ?");
    if (!$stmt1) throw new Exception("Prepare Book_Authors failed: " . $conn->error);
    $stmt1->bind_param("s", $isbn);
    $stmt1->execute();
    $stmt1->close();

    // 2. Delete related records from Book_Loans (optional, usually good to keep history but for this project likely delete)
    // NOTE: If want to KEEP loan history, do not run this, but  Foreign Key on Book likely prevents deleting the book then.
    // Assuming ON DELETE CASCADE is NOT set,  must delete these manually.
    $stmt2 = $conn->prepare("DELETE FROM Book_Loans WHERE Isbn = ?");
    if (!$stmt2) throw new Exception("Prepare Book_Loans failed: " . $conn->error);
    $stmt2->bind_param("s", $isbn);
    $stmt2->execute();
    $stmt2->close();

    // 3. Delete the book from BOOK table
    $stmt3 = $conn->prepare("DELETE FROM Book WHERE Isbn = ?");
    if (!$stmt3) throw new Exception("Prepare BOOK failed: " . $conn->error);
    $stmt3->bind_param("s", $isbn);
    $stmt3->execute();

    if ($stmt3->affected_rows > 0) {
        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Book and all associated records deleted successfully.'
        ]);
    } else {
        throw new Exception("Book not found or already deleted.");
    }
    $stmt3->close();

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>