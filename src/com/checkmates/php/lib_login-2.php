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

// Retrieve email and password from POST request
$email = $_POST['email'] ?? '';
$passWord = $_POST['passWord'] ?? '';

if (empty($email) || empty($passWord)) {
    echo json_encode(["status" => "error", "message" => "Email and password are required."]);
    exit;
}

// Prepare and execute the query to fetch professor_id, professorName, email, and hashed password
$stmt = $conn->prepare("SELECT lib_id, lib_name, email, passWord FROM librarians WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($lib_id, $lib_name, $libEmail, $hashedPassword);
    $stmt->fetch();
    if (password_verify($passWord, $hashedPassword)) {
        echo json_encode([
            "status" => "success",
            "lib_id" => $lib_id,
            "lib_name" => $lib_name,
            "email" => $libEmail
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
}

$stmt->close();
$conn->close();
?>
