<?php
session_start();
require_once 'db.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require 'vendor/autoload.php';

// Function to generate a random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Function to send password reset email
function sendPasswordResetEmail($email, $resetLink) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'catrizbernardo27@gmail.com'; // Your Gmail address
        $mail->Password   = 'zqvv ceqk qrnk depd'; // Replace with App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Email Settings
        $mail->setFrom('skmanagementsystem@gmail.com', 'SK Management System');
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = "<p>Click <a href='$resetLink'>here</a> to reset your password.</p>";
        $mail->AltBody = "Click the link to reset your password: $resetLink";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $response = ['status' => 'error', 'message' => 'Email not found.'];

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            try {
                // Insert/Update token
                $checkStmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = :email");
                $checkStmt->execute(['email' => $email]);

                if ($checkStmt->rowCount() > 0) {
                    $updateStmt = $pdo->prepare("UPDATE password_resets SET token = :token, expires_at = :expires WHERE email = :email");
                    $updateStmt->execute(['token' => $token, 'expires' => $expires, 'email' => $email]);
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)");
                    $insertStmt->execute(['email' => $email, 'token' => $token, 'expires' => $expires]);
                }

                $resetLink = "http://localhost/" . basename(dirname($_SERVER['PHP_SELF'])) . "/password_reset.php?token=$token";

                if (sendPasswordResetEmail($email, $resetLink)) {
                    $response = ['status' => 'success', 'message' => 'Password reset link sent. Check your email.'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to send email. Contact support.'];
                }

            } catch (PDOException $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $response = ['status' => 'error', 'message' => 'Database error. Try again later.'];
            }
        } else {
            $response = ['status' => 'success', 'message' => 'If your email exists, a reset link was sent.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Enter a valid email address.'];
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
                                    <form id="forgotPasswordForm">
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email:</label>
                                            <i class="input-icon uil uil-at"></i>
                                            <input type="email" name="email" class="form-style" placeholder="Enter your email" required>
                                        </div>
                                        <button type="submit" class="btn mt-4">Reset Password</button>
                                    </form>
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
        <div id="forgotPasswordAlert" class="alert alert-danger alert-dismissible fade show" role="alert" style="display:none;"></div>
        <div id="forgotPasswordSuccess" class="alert alert-success alert-dismissible fade show" role="alert" style="display:none;"></div>
    </div>

    <script>
        $(document).ready(function() {
            $('#forgotPasswordForm').submit(function(event) {
                event.preventDefault();
                
                $.ajax({
                    type: "POST",
                    url: "forgot_password.php",
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "success") {
                            $('#forgotPasswordSuccess').text(response.message).fadeIn(500).delay(3000).fadeOut(500);
                            
                            // Clear the form
                            $('#forgotPasswordForm')[0].reset();
                        } else {
                            $('#forgotPasswordAlert').text(response.message).fadeIn(500).delay(3000).fadeOut(500);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#forgotPasswordAlert').text("An error occurred. Please try again later.").fadeIn(500).delay(3000).fadeOut(500);
                        console.error("AJAX Error: " + status + " - " + error);
                    }
                });
            });
        });
    </script>
</body>
</html>