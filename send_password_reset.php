<?php

require_once 'db.php';

// For security, log errors but don't display them to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

// XAMPP mail configuration
ini_set("SMTP", "localhost");
ini_set("smtp_port", "25"); 
ini_set("sendmail_from", "noreply@yourwebsite.com");

// Function to generate a secure random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to send a password reset email
function sendPasswordResetEmail($email, $token) {
    // Build the reset URL
    $resetUrl = "http://localhost/SKManagementSystem/reset_password.php?token=" . $token;
    
    // Prepare email content
    $subject = "Password Reset Request";
    $headers = "From: noreply@yourwebsite.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <p>Hello,</p>
        <p>We received a request to reset your password. If you didn't make this request, you can ignore this email.</p>
        <p>To reset your password, please click the link below:</p>
        <p><a href='$resetUrl'>Reset Your Password</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>Thank you,</p>
        <p>Your Website Team</p>
    </body>
    </html>
    ";
    
    // Send the email
    $mailSent = mail($email, $subject, $message, $headers);
    
    // Log the result
    if ($mailSent) {
        error_log("Password reset email sent to: $email");
        return true;
    } else {
        error_log("Failed to send password reset email to: $email");
        return false;
    }
}

// Handle form submission (when user requests password reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $response = ['status' => 'error', 'message' => 'An error occurred.'];
    
    error_log("Password reset requested for email: $email");
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['status' => 'error', 'message' => 'Please provide a valid email address.'];
        error_log("Invalid email format: $email");
    } else {
        try {
            // Check if the email exists in the database
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user_id = $user['id'];
                error_log("User found with ID: $user_id");
                
                // Generate a secure token
                $token = generateToken();
                error_log("Generated token: $token");
                
                // Set expiration time (1 hour from now)
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                error_log("Token expires at: $expires_at");
                
                // Delete any existing reset tokens for this user
                $deleteStmt = $conn->prepare("DELETE FROM password_reset WHERE user_id = ?");
                $deleteStmt->bind_param("i", $user_id);
                $deleteStmt->execute();
                error_log("Deleted existing tokens for user ID: $user_id");
                
                // Store the new token
                $insertStmt = $conn->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
                $insertStmt->bind_param("iss", $user_id, $token, $expires_at);
                $success = $insertStmt->execute();
                
                if ($success) {
                    error_log("Token stored successfully in database");
                } else {
                    error_log("Failed to store token: " . $insertStmt->error);
                }
                
                // Send the email
                if (sendPasswordResetEmail($email, $token)) {
                    $response = [
                        'status' => 'success', 
                        'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.'
                    ];
                } else {
                    // If email fails, still show success message for security
                    $response = [
                        'status' => 'success', 
                        'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.'
                    ];
                    error_log("Failed to send password reset email, but showing success message for security");
                }
            } else {
                // For security reasons, don't reveal whether the email exists or not
                $response = [
                    'status' => 'success', 
                    'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.'
                ];
                error_log("Email not found in database: $email");
            }
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
            error_log('Password reset request error: ' . $e->getMessage());
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password</title>
    <link rel="icon" href="sk.jpg">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
    <link rel="stylesheet" href="style1.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</head>
<body>
    <div class="section">
        <div class="container">
            <div class="row full-height justify-content-center">
                <div class="col-12 col-md-8 col-lg-6 align-self-center">
                    <div class="card-3d-wrap mx-auto">
                        <div class="card-front">
                            <div class="center-wrap">
                                <div class="section">
                                    <h4 class="mb-4 pb-3 text-center">
                                        <img src="sk1.png" alt="Logo" class="logo"> Forgot Password
                                    </h4>
                                    
                                    <div id="resetRequestForm">
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <div class="input-group">
                                                <i class="input-icon uil uil-at"></i>
                                                <input type="email" id="email" name="email" class="form-style" placeholder="Your Email" required>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn mt-4">Send Reset Link</button>
                                    </div>
                                    
                                    <p class="mb-0 mt-4 text-center">
                                        <a href="index.html" class="link">Back to Login</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert-container">
        <div id="resetAlert" class="alert alert-danger alert-dismissible fade show" role="alert" style="display:none;"></div>
        <div id="resetSuccess" class="alert alert-success alert-dismissible fade show" role="alert" style="display:none;"></div>
    </div>

    <script>
        $(document).ready(function() {
            $('#resetRequestForm').submit(function(event) {
                event.preventDefault();
                
                var email = $('#email').val();
                
                $.ajax({
                    type: "POST",
                    url: window.location.href,
                    data: {email: email},
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "success") {
                            $('#resetSuccess').text(response.message).fadeIn(500);
                            $('#resetRequestForm').hide();
                        } else {
                            $('#resetAlert').text(response.message).fadeIn(500).delay(3000).fadeOut(500);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#resetAlert').text("An error occurred. Please try again later.").fadeIn(500).delay(3000).fadeOut(500);
                        console.error("AJAX Error: " + status + " - " + error);
                    }
                });
            });
        });
    </script>
</body>
</html>