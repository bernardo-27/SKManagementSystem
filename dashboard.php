<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'db.php';

// Fetch additional user data (optional)
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

$total_users_stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
$total_users = $total_users_stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark px-4 py-3">
        <span class="navbar-text text-white">
            Welcome, <em><span class="name fw-bold"><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></span></em>!
        </span>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </nav>


</div>
    <div class="container mt-5">

        <!-- Alerts -->
        <div id="deleteSuccessAlert" class="alert alert-success alert-dismissible fade show" role="alert" style="display:none;">
            User deleted successfully!
        </div>
        <div id="deleteErrorAlert" class="alert alert-danger alert-dismissible fade show" role="alert" style="display:none;">
            Failed to delete user. Please try again.
        </div>

        <h2 class="text-center mb-4">List of Users</h2>

        <!-- Table to Display Users -->
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch all users from the database
                $stmt = $pdo->query("SELECT * FROM users");
                while ($row = $stmt->fetch()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    echo '<td>';
                    echo '<a href="delete_user.php?id=' . $row['id'] . '" 
                    class="btn btn-danger btn-sm delete-btn" 
                    onclick="return confirmDelete(' . $row['id'] . ');">
                    Delete
                    </a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4 m-5">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Total Users</h5>
            <p class="card-text"><?php echo htmlspecialchars($total_users, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
    </div>


    <script>
        $(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const alertType = urlParams.get('alert');

    if (alertType === 'delete_success') {
        $('#deleteSuccessAlert').text("User deleted successfully.").fadeIn(500).delay(2000).fadeOut(500);
    } else if (alertType === 'delete_error') {
        $('#deleteErrorAlert').text("Failed to delete user. Please try again.").fadeIn(500).delay(2000).fadeOut(500);
    }
});
        function confirmDelete(userId) {
    // Display a confirmation dialog
    const isConfirmed = confirm("Are you sure you want to delete this user? This action cannot be undone.");

    if (isConfirmed) {
        // If confirmed, redirect to the delete script
        window.location.href = `delete_user.php?id=${userId}`;
    }

    // Prevent the default action if the user cancels
    return false;
}
    </script>
</body>
</html>