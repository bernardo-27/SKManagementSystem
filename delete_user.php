<?php
// Include the database connection file
require_once 'db.php';

// Check if the 'id' parameter is set in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = $_GET['id'];

    try {
        // Prepare the SQL query to delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);

        // Check if the deletion was successful
        if ($stmt->rowCount() > 0) {
            // User deleted successfully
            header("Location: dashboard.php?alert=delete_success"); // Redirect back to the users page
            exit;
        } else {
            // No rows affected (user not found)
            header("Location: dashboard.php?alert=delete_error");
            exit;
        }
    } catch (PDOException $e) {
        // Handle database errors
        error_log("Error deleting user: " . $e->getMessage());
        header("Location: dashboard.php?alert=delete_error");
        exit;
    }
} else {
    // Invalid or missing 'id' parameter
    header("Location: dashboard.php?alert=invalid_request");
    exit;
}
?>