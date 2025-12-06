<?php
// getBooks.php - Fetches all books with Authors and Availability status
// This script handles the Milestone 2 Requirement: "Book Search and Availability"

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // Enable CORS for development

// 1. Database Configuration
$servername = "db5019002027.hosting-data.io";
$username   = "dbu1596977";
$password   = "UTDallas123$$";
$dbname     = "dbs14962566";

// 2. Create Connection
$conn = new mysqli($servername, $username, $password, $dbname);

// 3. Check Connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// 4. The Main Query
// This query does three things:
// a) Selects Book details.
// b) Joins with AUTHORS to get a comma-separated list of author names.
// c) Joins with BOOK_LOANS to determine if the book is currently checked out.

$sql = "
    SELECT 
        b.Isbn,
        b.Title,
        GROUP_CONCAT(DISTINCT a.Name ORDER BY a.Name SEPARATOR ', ') AS Authors,
        CASE
            WHEN bl.Isbn IS NOT NULL THEN 'OUT'
            ELSE 'IN'
        END AS Availability
    FROM 
        Book AS b
    LEFT JOIN 
        Book_Authors AS ba ON b.Isbn = ba.Isbn
    LEFT JOIN 
        Authors AS a ON ba.Author_id = a.Author_id
    LEFT JOIN 
        Book_Loans AS bl ON b.Isbn = bl.Isbn AND bl.Date_in IS NULL
    GROUP BY 
        b.Isbn, b.Title
    ORDER BY 
        b.Title ASC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => "Query failed: " . $conn->error]);
    $conn->close();
    exit;
}

$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

// 5. Return JSON Response
echo json_encode([
    "status" => "success",
    "books" => $books
]);

$conn->close();
?>