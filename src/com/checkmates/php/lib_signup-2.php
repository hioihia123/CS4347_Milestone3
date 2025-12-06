<?php
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

// Retrieve data from the request
$lib_name= $_POST['lib_name'] ?? '';
$email         = $_POST['email'] ?? '';
$passWord      = $_POST['passWord'] ?? '';

// Check if all fields are provided
if (empty($lib_name) || empty($email) || empty($passWord)) {
    echo json_encode(["status" => "error", "message" => "Librarian name, email, and password are required."]);
    $conn->close();
    exit;
}

// First, check if the email already exists in the database
$stmtCheck = $conn->prepare("SELECT email FROM librarians WHERE email = ?");
$stmtCheck->bind_param("s", $email);
$stmtCheck->execute();
$stmtCheck->store_result();
if ($stmtCheck->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already exists."]);
    $stmtCheck->close();
    $conn->close();
    exit;
}
$stmtCheck->close();

// Generate a custom librarian ID (2 letters from name + 5 random digits)
$cleanName = strtoupper(preg_replace('/\s+/', '', $lib_name));
$prefix = substr($cleanName, 0, 2);
$randomNumber = rand(10000, 99999);
$lib_id = $prefix . $randomNumber;

// Hash the password before storing it
$hashedPassword = password_hash($passWord, PASSWORD_DEFAULT);

// Prepare SQL statement including lib_id
$stmt = $conn->prepare("INSERT INTO librarians (lib_id, lib_name, email, passWord) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $lib_id, $lib_name, $email, $hashedPassword);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Prepare email details
$to      = $email;  // The user's email address
$subject = "Confirmation: Account Created";
$message = "Hi " . htmlspecialchars($lib_name) . ",\n\n" .
           "Thank you for signing up! Your account has been created successfully.\n\n" .
           "Here is your login info:\n\n" . 
           "Email: " . htmlspecialchars($email) . "\n" . 
           "Password: " . htmlspecialchars($passWord) . "\n" .
           "Librarian ID: " . htmlspecialchars($lib_id) . "\n\n" .
           "Please keep this info somewhere safe.\n\n" .
           "--CheckMates Support Team--";
$headers = "From: no-reply@cm8tes.com\r\n" .
           "Reply-To: questions@cm8tes.com\r\n" .
           "X-Mailer: PHP/" . phpversion();

// Attempt to send the email 
$mailSent = mail($to, $subject, $message, $headers);

// Return JSON response regardless of email outcome
echo json_encode([
    "status" => "Signed up successfully", 
    "lib_id" => $lib_id
]);

$conn->close();
?>
