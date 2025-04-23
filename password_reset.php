<?php
session_start();
require_once 'db.php';

// For security, log errors but don't display them to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

$demo_mode = false;

$token = isset($_GET['token']) ? $_GET['token'] : '';
$tokenValid = false;
$email = '';
$user_id = 0;

// Add debug logging
error_log("Script started. Token from URL: " . $token);

// Validate token if present
if (!empty($token) || $demo_mode) {
    try {
        if ($demo_mode) {
            // In demo mode, assume token is valid
            $tokenValid = true;
            $email = "demo@example.com";
            $user_id = 1;
            error_log("DEMO MODE: Using demo credentials");
        } else {
            // Normal token validation for production use
            $current_time = date('Y-m-d H:i:s');
            error_log("Current time: $current_time");
            
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND expires_at > :current_time");
            $stmt->execute([
                'token' => $token,
                'current_time' => $current_time
            ]);
            
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reset) {
                $tokenValid = true;
                $email = $reset['email'];
                
                // Get user_id from users table
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $userStmt->execute(['email' => $email]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $user_id = $user['id'];
                    error_log("Token valid for user ID: $user_id, Email: $email");
                    error_log("Token expires at: " . $reset['expires_at'] . ", Current time: $current_time");
                } else {
                    $tokenValid = false;
                    error_log("User not found for email: $email");
                }
            } else {
                error_log("Invalid or expired token: $token");
                
                // For debugging, fetch the token regardless of expiration
                $debug_stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token");
                $debug_stmt->execute(['token' => $token]);
                $debug_data = $debug_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($debug_data) {
                    error_log("Token found but expired. Expires: " . $debug_data['expires_at']);
                    error_log("Time difference: " . (strtotime($debug_data['expires_at']) - strtotime($current_time)) . " seconds");
                } else {
                    error_log("Token not found in database.");
                }
            }
        }
    } catch (Exception $e) {
        error_log('Token validation error: ' . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $response = ['status' => 'error', 'message' => 'Invalid or expired token.'];
    
    error_log("Form submitted. Token from form: " . $token);
    
    try {
        if ($demo_mode) {
            // In demo mode, skip token verification
            $tokenValid = true;
            error_log(" Skipping token verification on form submit");
        } else {
            // Validate token again
            $current_time = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND expires_at > :current_time");
            $stmt->execute([
                'token' => $token,
                'current_time' => $current_time
            ]);
            
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reset) {
                $tokenValid = true;
                $email = $reset['email'];
                
                // Get user_id from users table
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $userStmt->execute(['email' => $email]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $user_id = $user['id'];
                    error_log("Token valid on form submit for user ID: " . $user_id);
                } else {
                    $tokenValid = false;
                    error_log("User not found for email: $email");
                }
            } else {
                $tokenValid = false;
                error_log("Token invalid on form submit");
            }
        }
        
        if ($tokenValid) {
            if ($password !== $confirmPassword) {
                $response = ['status' => 'error', 'message' => 'Passwords do not match.'];
                error_log("Passwords do not match");
            } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $response = ['status' => 'error', 'message' => 'Password must be at least 8 characters and contain letters and numbers.'];
                error_log("Password does not meet requirements");
            } else {
                if (!$demo_mode) {
                    // Update the user's password in production mode
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                    $updateStmt->execute([
                        'password' => $hashedPassword,
                        'id' => $user_id
                    ]);
                    
                    // Delete the used token
                    $deleteStmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
                    $deleteStmt->execute(['token' => $token]);
                    
                    error_log("Password updated successfully for user ID: " . $user_id);
                } else {
                    // Just log in demo mode
                    error_log(" Password would be updated to: " . password_hash($password, PASSWORD_DEFAULT));
                }
                
                $response = [
                    'status' => 'success', 
                    'message' => 'Password has been reset successfully. You will be redirected to login page.'
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
        error_log('Password reset error: ' . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password</title>
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
                                        <img src="sk1.png" alt="Logo" class="logo"> Reset Password
                                    </h4>
                                    
                                    <?php if (!$tokenValid): ?>
                                        <div class="alert alert-danger">
                                            Invalid or expired password reset token. Please request a new password reset link.
                                        </div>
                                        <p class="text-center">
                                            <a href="forgot_password.php" class="btn mt-4">Request New Link</a>
                                        </p>
                                    <?php else: ?>
                                        <form id="resetPasswordForm">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                            <div class="form-group mt-2">
                                                <label for="password">New Password:</label>
                                                <div class="input-group">
                                                    <i class="input-icon uil uil-lock"></i>
                                                    <input type="password" id="password" name="password" class="form-style" placeholder="New Password" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" onclick="togglePassword('password', 'eye-icon-password')">
                                                            <i id="eye-icon-password" class="uil uil-eye"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">Password must be at least 8 characters and contain letters and numbers.</small>
                                            </div>
                                            <div class="form-group mt-2">
                                                <label for="confirm_password">Confirm New Password:</label>
                                                <div class="input-group">
                                                    <i class="input-icon uil uil-lock"></i>
                                                    <input type="password" id="confirm_password" name="confirm_password" class="form-style" placeholder="Confirm New Password" required>
                                                    <div class="input-group-append">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn mt-4">Update Password</button>
                                        </form>
                                    <?php endif; ?>
                                    
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
        function togglePassword(inputId, iconId) {
            var passwordInput = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
        
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove("uil-eye");
                icon.classList.add("uil-eye-slash");
            } else {
                passwordInput.type = "password";
                icon.classList.remove("uil-eye-slash");
                icon.classList.add("uil-eye");
            }
        }
        
        $(document).ready(function() {
            // Check password strength as user types
            $('#password').on('input', function() {
                var password = $(this).val();
                var hasLetter = /[A-Za-z]/.test(password);
                var hasNumber = /[0-9]/.test(password);
                var isLongEnough = password.length >= 8;
                
                if (isLongEnough && hasLetter && hasNumber) {
                    $(this).css('border-color', '#2dce89');
                } else {
                    $(this).css('border-color', '');
                }
            });
            
            // Check if passwords match as user types
            $('#confirm_password').on('input', function() {
                if ($(this).val() === $('#password').val()) {
                    $(this).css('border-color', '#2dce89');
                } else {
                    $(this).css('border-color', '');
                }
            });
            
            $('#resetPasswordForm').submit(function(event) {
                event.preventDefault();
                
                $.ajax({
                    type: "POST",
                    url: window.location.href, 
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "success") {
                            $('#resetSuccess').text(response.message).fadeIn(500);
                            $('#resetPasswordForm').hide();
                            setTimeout(function() {
                                window.location.href = "index.html";
                            }, 3000);
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

        document.addEventListener('DOMContentLoaded', function() {
        // Find all password fields
        const passwordFields = document.querySelectorAll('input[type="password"]');
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'password-tooltip';
        tooltip.innerText = 'Not allowed for security reasons';
        tooltip.style.cssText = 'position: absolute; background: #f44336; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; z-index: 1000; opacity: 0; transition: opacity 0.3s;';
        document.body.appendChild(tooltip);
        
        // Apply protection to each password field
        passwordFields.forEach(field => {
          // Prevent copy, cut, paste
          ['copy', 'cut', 'paste'].forEach(function(event) {
            field.addEventListener(event, function(e) {
              e.preventDefault();
              
              // Show tooltip near the field
              const rect = field.getBoundingClientRect();
              tooltip.style.left = rect.left + 'px';
              tooltip.style.top = (rect.bottom + 5) + 'px';
              tooltip.style.opacity = '1';
              
              // Hide tooltip after 2 seconds
              setTimeout(function() {
                tooltip.style.opacity = '0';
              }, 2000);
            });
          });
          
          // Prevent right-click
          field.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
          });
        });
      });
    </script>
</body>
</html>