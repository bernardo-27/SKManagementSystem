<?php
session_start();
require_once 'db.php';

// Initialize session variables if not set
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = [];
}
if (!isset($_SESSION['blocked_until'])) {
    $_SESSION['blocked_until'] = [];
}

$email = $_POST['email'];
$password = $_POST['password'];

// Check if the user is blocked
if (isset($_SESSION['blocked_until'][$email]) && $_SESSION['blocked_until'][$email] > time()) {
    $remaining_time = ceil(($_SESSION['blocked_until'][$email] - time()));
    echo json_encode([
        "status" => "error",
        "message" => "Too many failed attempts. Please try again in " . ceil($remaining_time / 60) . " minute(s).",
        "remaining_time" => $remaining_time // Send remaining time in seconds
    ]);
    exit;
}

// Check if the user exists in the database
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    // Reset failed attempts on successful login
    unset($_SESSION['failed_attempts'][$email]);
    unset($_SESSION['blocked_until'][$email]);

    // Start session and store user data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];

    echo json_encode([
        "status" => "success",
        "message" => "Login successful! Redirecting to dashboard..."
    ]);
    exit;
} else {
    // Increment failed attempts
    if (!isset($_SESSION['failed_attempts'][$email])) {
        $_SESSION['failed_attempts'][$email] = 0;
    }
    $_SESSION['failed_attempts'][$email]++;
    
    // Calculate attempts remaining
    $attempts_remaining = 3 - $_SESSION['failed_attempts'][$email];
    
    // Block the user after 3 failed attempts
    if ($_SESSION['failed_attempts'][$email] >= 3) {
        $lockout_time = pow(2, $_SESSION['failed_attempts'][$email] - 3) * 60; // Exponential backoff
        $_SESSION['blocked_until'][$email] = time() + $lockout_time;

        $remaining_time = ceil($lockout_time);
        echo json_encode([
            "status" => "error",
            "message" => "Too many failed attempts. Please try again in " . ceil($remaining_time / 60) . " minute(s).",
            "remaining_time" => $remaining_time // Send remaining time in seconds
        ]);
        exit;
    }

    // Return error for invalid credentials with attempts warning
    echo json_encode([
        "status" => "error",
        "message" => "Incorrect email or password! You have $attempts_remaining attempt(s) remaining before your account is temporarily locked."
    ]);
    exit;
}
?>