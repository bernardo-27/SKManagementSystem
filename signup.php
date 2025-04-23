<?php
require_once 'db.php';

// Retrieve form data
$full_name = $_POST['full_name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// Validate unique email and full name
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email OR full_name = :full_name");
$stmt->execute(['email' => $email, 'full_name' => $full_name]);
$existing_user = $stmt->fetch();

if ($existing_user) {
    if ($existing_user['email'] === $email) {
        echo json_encode(["status" => "error", "message" => "Email already in use!"]);
        exit;
    } elseif ($existing_user['full_name'] === $full_name) {
        echo json_encode(["status" => "error", "message" => "This full name is already in use! Please choose a different name."]);
        exit;
    }
}

// Validate password match
if ($password !== $confirm_password) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match! Please try again."]);
    exit;
}

// Validate password complexity (at least 8 characters, containing letters and numbers)
if (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]{8,}$/', $password)) {
    echo json_encode(["status" => "error", "message" => "Password must contain at least one letter, one number, and be at least 8 characters long."]);
    exit;
}

// Hash the password and insert into the database
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (:full_name, :email, :phone, :password)");
try {
    $stmt->execute([
        'full_name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'password' => $hashed_password
    ]);
    echo json_encode(["status" => "success", "message" => "Signup successful!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Something went wrong. Please try again."]);
}
?>