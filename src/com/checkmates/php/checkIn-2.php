<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

ini_set('display_errors', 0); 
error_reporting(E_ALL);

$servername = "db5019002027.hosting-data.io";
$username   = "dbu1596977";
$password   = "UTDallas123$$";
$dbname     = "dbs14962566";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'DB Connection failed']));
}

$loan_id = $_POST['Loan_id'] ?? '';
$lib_id_return = $_POST['lib_id_return'] ?? '';

if (empty($loan_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Loan ID.']);
    $conn->close();
    exit;
}

$stmt = $conn->prepare("
    UPDATE Book_Loans 
    SET Date_in = NOW(), lib_id_return = ?
    WHERE Loan_id = ?
");

$stmt->bind_param("si", $lib_id_return, $loan_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Book checked in successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Loan not found or already checked in.']);
    }
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
