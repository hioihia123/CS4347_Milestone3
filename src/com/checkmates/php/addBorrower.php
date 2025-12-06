<?php
// Start output buffering to catch any stray output
ob_start();

// Suppress any warnings/errors that might output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header first
header("Content-Type: application/json");

try {
    // Database connection - IONOS Database Credentials
    $host = "db5019002027.hosting-data.io";
    $user = "dbu1596977";
    $pass = "UTDallas123$$";
    $db   = "dbs14962566";

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "DB Connection failed: " . $conn->connect_error]);
        exit();
    }

    // Read JSON input from Java
    $input = json_decode(file_get_contents("php://input"), true);

    // Validate input exists
    if (!$input) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        $conn->close();
        exit();
    }

$card_id = isset($input["card_id"]) ? $input["card_id"] : "";
$ssn     = isset($input["ssn"]) ? $input["ssn"] : "";
$name    = isset($input["name"]) ? $input["name"] : "";
$address = isset($input["address"]) ? $input["address"] : "";
$phone   = isset($input["phone"]) ? $input["phone"] : null; // Phone is optional

    // Validate required fields
    if (empty($ssn) || empty($name) || empty($address)) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "SSN, Name, and Address are required fields"]);
        $conn->close();
        exit();
    }

    // Detect table name - Try different variations
    $tableName = null;
    $tableNames = ["BORROWER", "borrower", "Borrowers", "Borrower"];
    
    foreach ($tableNames as $testTable) {
        // Test if table exists using INFORMATION_SCHEMA (more reliable)
        $checkTable = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "' AND TABLE_NAME = '" . $conn->real_escape_string($testTable) . "' LIMIT 1");
        if ($checkTable && $checkTable->num_rows > 0) {
            $tableName = $testTable;
            $checkTable->close();
            break;
        }
        if ($checkTable) $checkTable->close();
    }
    
    if (!$tableName) {
        // List available tables for debugging
        $tablesResult = $conn->query("SHOW TABLES");
        $availableTables = [];
        if ($tablesResult) {
            while ($row = $tablesResult->fetch_array()) {
                $availableTables[] = $row[0];
            }
            $tablesResult->close();
        }
        
        // Try to create the table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS BORROWER (
            Card_id VARCHAR(20) PRIMARY KEY,
            ssn VARCHAR(20) UNIQUE NOT NULL,
            Bname VARCHAR(100) NOT NULL,
            address VARCHAR(200) NOT NULL,
            phone VARCHAR(20)
        )";
        
        if ($conn->query($createTable)) {
            $tableName = "BORROWER";
        } else {
            // Try lowercase column names
            $createTable = "CREATE TABLE IF NOT EXISTS BORROWER (
                card_id VARCHAR(20) PRIMARY KEY,
                ssn VARCHAR(20) UNIQUE NOT NULL,
                bname VARCHAR(100) NOT NULL,
                address VARCHAR(200) NOT NULL,
                phone VARCHAR(20)
            )";
            if ($conn->query($createTable)) {
                $tableName = "BORROWER";
            }
        }
        
        if (!$tableName) {
            ob_end_clean();
            $tablesList = !empty($availableTables) ? implode(", ", $availableTables) : "none found";
            echo json_encode([
                "status" => "error", 
                "message" => "Borrower table not found and could not be created. Tried: BORROWER, borrower, Borrowers, Borrower. Available tables: " . $tablesList . ". Error: " . $conn->error
            ]);
            $conn->close();
            exit();
        }
    }
    
    // Check duplicate SSN - try lowercase ssn column first, then uppercase
    $check = $conn->prepare("SELECT ssn FROM " . $tableName . " WHERE ssn = ?");
    if (!$check) {
        $check = $conn->prepare("SELECT SSN FROM " . $tableName . " WHERE SSN = ?");
    }
    if (!$check) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "SQL prepare failed: " . $conn->error]);
        $conn->close();
        exit();
    }

    $check->bind_param("s", $ssn);
    if (!$check->execute()) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Error checking SSN: " . $check->error]);
        $check->close();
        $conn->close();
        exit();
    }

    $check->store_result();

    if ($check->num_rows > 0) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Borrower with this SSN already exists"]);
        $check->close();
        $conn->close();
        exit();
    }
    $check->close();

    // ALWAYS generate a new card_id from database (ignore what Java sends)
    // This ensures we never get duplicate primary key errors
    // Try different column name variations
    $maxQuery = $conn->query("SELECT MAX(CAST(Card_id AS UNSIGNED)) AS max_id FROM " . $tableName);
    if (!$maxQuery || $maxQuery->num_rows == 0) {
        $maxQuery = $conn->query("SELECT MAX(CAST(card_id AS UNSIGNED)) AS max_id FROM " . $tableName);
    }
    if ($maxQuery && $row = $maxQuery->fetch_assoc()) {
        $maxId = $row["max_id"] ? (int)$row["max_id"] : 0;
        $card_id = (string)($maxId + 1);
    } else {
        $card_id = "10001"; // Default starting ID if table is empty
    }
    if ($maxQuery) $maxQuery->close();
    
    // Double-check: if generated card_id already exists, increment until we find a free one
    $checkCardId = $conn->prepare("SELECT Card_id FROM " . $tableName . " WHERE Card_id = ?");
    if (!$checkCardId) {
        $checkCardId = $conn->prepare("SELECT card_id FROM " . $tableName . " WHERE card_id = ?");
    }
    if ($checkCardId) {
        $checkCardId->bind_param("s", $card_id);
        $checkCardId->execute();
        $checkCardId->store_result();
        while ($checkCardId->num_rows > 0) {
            // Card ID exists, increment and check again
            $card_id = (string)((int)$card_id + 1);
            $checkCardId->close();
            $checkCardId = $conn->prepare("SELECT Card_id FROM " . $tableName . " WHERE Card_id = ?");
            if (!$checkCardId) {
                $checkCardId = $conn->prepare("SELECT card_id FROM " . $tableName . " WHERE card_id = ?");
            }
            if ($checkCardId) {
                $checkCardId->bind_param("s", $card_id);
                $checkCardId->execute();
                $checkCardId->store_result();
            } else {
                break; // Can't check anymore, use the ID we have
            }
        }
        if ($checkCardId) $checkCardId->close();
    }

    // Insert new borrower - Use detected table name
    // Try capitalized column names first (Card_id, Bname), then lowercase
    $stmt = $conn->prepare("INSERT INTO " . $tableName . " (Card_id, ssn, Bname, address, phone) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        // Try lowercase column names
        $stmt = $conn->prepare("INSERT INTO " . $tableName . " (card_id, ssn, bname, address, phone) VALUES (?, ?, ?, ?, ?)");
    }
    if (!$stmt) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "SQL prepare failed: " . $conn->error]);
        $conn->close();
        exit();
    }

    $stmt->bind_param("sssss", $card_id, $ssn, $name, $address, $phone);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode([
            "status" => "success", 
            "message" => "Borrower added successfully",
            "card_id" => $card_id
        ]);
    } else {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Failed to add borrower: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "PHP Error: " . $e->getMessage()]);
}
?>
