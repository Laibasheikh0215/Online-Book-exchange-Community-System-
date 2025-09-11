<?php
// Enable error reporting - TEMPORARILY for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
    exit(0);
}

// Set CORS headers for actual request
header("Access-Control-Allow-Origin: http://localhost");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");

try {
    // Include files with correct path
    include_once __DIR__ . '/../../config/database.php';
    include_once __DIR__ . '/../../models/Book.php';

    $database = new Database();
    $db = $database->getConnection();

    // Check if connection successful
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $book = new Book($db);

    // Get search parameters
    $search = isset($_GET['q']) ? $_GET['q'] : '';
    $genre = isset($_GET['genre']) ? $_GET['genre'] : '';
    $condition = isset($_GET['condition']) ? $_GET['condition'] : '';
    $location = isset($_GET['location']) ? $_GET['location'] : '';

    // Build query based on search criteria
    $current_user_id = isset($_GET['current_user_id']) ? intval($_GET['current_user_id']) : 0;
    
    // Start with basic query
    $query = "SELECT b.*, u.name as owner_name, u.city as location 
              FROM books b 
              JOIN users u ON b.user_id = u.id 
              WHERE b.status = 'Available'";
    
    $params = array();

    // Exclude current user's books
    if ($current_user_id > 0) {
        $query .= " AND b.user_id != :current_user_id";
        $params[':current_user_id'] = $current_user_id;
    }

    if (!empty($search)) {
        $query .= " AND (b.title LIKE :search OR b.author LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($genre)) {
        $query .= " AND b.genre = :genre";
        $params[':genre'] = $genre;
    }

    if (!empty($condition)) {
        $query .= " AND b.condition = :condition";
        $params[':condition'] = $condition;
    }

    if (!empty($location)) {
        $query .= " AND (u.city LIKE :location OR u.address LIKE :location)";
        $params[':location'] = "%$location%";
    }

    $query .= " ORDER BY b.created_at DESC";

    // Debug: Show the query and parameters
    error_log("Search Query: " . $query);
    error_log("Search Parameters: " . print_r($params, true));

    $stmt = $db->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    $num = $stmt->rowCount();

    if ($num > 0) {
        $books_arr = array();
        $books_arr["books"] = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $book_item = array(
                "id" => $row['id'],
                "title" => $row['title'],
                "author" => $row['author'],
                "isbn" => $row['isbn'],
                "genre" => $row['genre'],
                "condition" => $row['condition'],
                "description" => $row['description'],
                "status" => $row['status'],
                "owner_name" => $row['owner_name'],
                "location" => $row['location'],
                "created_at" => $row['created_at']
            );
            
            array_push($books_arr["books"], $book_item);
        }
        
        http_response_code(200);
        echo json_encode($books_arr);
    } else {
        http_response_code(200);
        echo json_encode(array("books" => []));
    }

} catch (Exception $e) {
    // Show error details for debugging
    http_response_code(500);
    echo json_encode(array(
        "message" => "Internal server error occurred.",
        "error" => $e->getMessage(),
        "error_code" => "SEARCH_ERROR"
    ));
}
?>