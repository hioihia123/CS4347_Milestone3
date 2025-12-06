<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// Enable error reporting for debugging (Remove in production)
ini_set('display_errors', 1); 
error_reporting(E_ALL);

$servername = "db5019002027.hosting-data.io";
$username   = "dbu1596977";
$password   = "UTDallas123$$";
$dbname     = "dbs14962566";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'DB Connection failed']));
}

// Validate input
$isbn    = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';
$card_id = isset($_POST['Card_id']) ? trim($_POST['Card_id']) : '';
$lib_id  = $_POST['lib_id_checkout'] ?? '';

if (empty($isbn) || empty($card_id) || empty($lib_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ISBN, Card ID, or Librarian ID.']);
    $conn->close();
    exit;
}

// CHECK 1: Verify Librarian Exists
$check = $conn->prepare("SELECT lib_id FROM librarians WHERE lib_id = ?");
if (!$check) {
    echo json_encode(['status'=>'error','message'=>'Prepare Lib Check failed: ' . $conn->error]);
    exit;
}
$check->bind_param("s", $lib_id);
$check->execute();
$check->store_result();

if ($check->num_rows == 0) {
    echo json_encode(['status'=>'error','message'=>'Invalid librarian ID']);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

$conn->begin_transaction();

try {
    // RULE 1: Unpaid fines
    $sqlFines = "SELECT COUNT(*) as count 
                 FROM Fines AS f
                 JOIN Book_Loans AS bl ON f.Loan_id = bl.Loan_id
                 WHERE bl.Card_id = ? AND f.Paid = 0";

    $stmt = $conn->prepare($sqlFines);
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close(); // <--- FIX: Close statement

    if ($result['count'] > 0) {
        throw new Exception("Checkout Failed: Borrower has unpaid fines.");
    }

    // RULE 2: Max 3 active loans
    $sqlLimit = "SELECT COUNT(*) as count
                 FROM Book_Loans
                 WHERE Card_id = ? AND Date_in IS NULL";

    $stmt = $conn->prepare($sqlLimit);
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  
    if ($result['count'] >= 3) {
        throw new Exception("Checkout Failed: Borrower has reached the maximum of 3 active loans.");
    }

    // RULE 3: Book availability
    $sqlAvail = "SELECT COUNT(*) as count
                 FROM Book_Loans
                 WHERE Isbn = ? AND Date_in IS NULL";

    $stmt = $conn->prepare($sqlAvail);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close(); 

    if ($result['count'] > 0) {
        throw new Exception("Checkout Failed: Book is currently checked out by another user.");
    }

    // INSERT loan record
    $sqlInsert = "INSERT INTO Book_Loans 
                  (Isbn, Card_id, Date_out, Due_date, Date_in, lib_id_checkout)
                  VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), NULL, ?)";

    $stmt = $conn->prepare($sqlInsert);
    $stmt->bind_param("sis", $isbn, $card_id, $lib_id);

    if (!$stmt->execute()) {
        throw new Exception("Database Error: " . $stmt->error);
    }
    $stmt->close(); 

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Book checked out successfully. Due in 14 days.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>