<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for successful registration redirect
$registrationSuccess = isset($_SESSION['registration_success']);
unset($_SESSION['registration_success']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $db->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) {
        $errors['username'] = "Username is required";
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
    }
    
    if (empty($errors)) {
        $result = $db->query("SELECT * FROM users WHERE username = '$username'");
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                
                // Set last login time
                $db->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");
                
                header('Location: dashboard.php');
                exit;
            }
        }
        
        // Generic error message to prevent username enumeration
        $errors['general'] = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sirtech SMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-dark: #5649c0;
            --secondary: #a29bfe;
            --dark: #2d3436;
            --light: #f5f6fa;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
        }
        
        .login-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .form-control {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(0, 0, 0, 0.3);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
            color: white;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .input-group-text {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            color: rgba(255, 255, 255, 0.7);
        }
        
        .btn-login {
            background: var(--primary);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            width: 100%;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 92, 231, 0.4);
        }
        
        .btn-register {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            color: white;
            text-decoration: underline;
        }
        
        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
        }
        
        .forgot-password:hover {
            color: var(--primary);
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .success-message {
            color: var(--success);
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .social-login {
            margin-top: 2rem;
            text-align: center;
        }
        
        .social-login p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
            position: relative;
        }
        
        .social-login p::before,
        .social-login p::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .social-login p::before {
            left: 0;
        }
        
        .social-login p::after {
            right: 0;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            transform: translateY(-3px);
            background: var(--primary);
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.3);
        }
        
        @media (max-width: 575.98px) {
            .login-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container animate__animated animate__fadeIn">
            <div class="login-card">
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to access your dashboard</p>
                </div>
                
                <?php if ($registrationSuccess): ?>
                    <div class="alert alert-success animate__animated animate__bounceIn">
                        Registration successful! Please log in.
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" id="username" class="form-control <?php echo !empty($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                        <?php if (!empty($errors['username'])): ?>
                            <span class="error-message"><?php echo $errors['username']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <span class="error-message"><?php echo $errors['password']; ?></span>
                        <?php endif; ?>
                        <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                    
                    <a href="register.php" class="btn-register">
                        Don't have an account? <span class="text-primary">Register</span>
                    </a>
                    
                    <div class="social-login">
                        <p>Or sign in with</p>
                        <div class="social-icons">
                            <a href="#" class="social-icon" aria-label="Google">
                                <i class="fab fa-google"></i>
                            </a>
                            <a href="#" class="social-icon" aria-label="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-icon" aria-label="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const passwordInput = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            let valid = true;
            
            if (username.trim() === '') {
                document.getElementById('username').classList.add('is-invalid');
                valid = false;
            }
            
            if (password.trim() === '') {
                document.getElementById('password').classList.add('is-invalid');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
        
        // Clear validation errors when typing
        document.getElementById('username').addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
        
        document.getElementById('password').addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    </script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>