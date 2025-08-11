<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $db->real_escape_string(trim($_POST['username']));
    $email = $db->real_escape_string(trim($_POST['email']));
    $phone = $db->real_escape_string(trim($_POST['phone']));
    $password = $_POST['password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($username) || strlen($username) < 4) {
        $errors['username'] = "Username must be at least 4 characters";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address";
    }
    
    if (empty($phone) || !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors['phone'] = "Please enter a valid phone number";
    }
    
    if (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    }
    
    if (empty($errors)) {
        // Check if username exists
        $result = $db->query("SELECT * FROM users WHERE username = '$username' OR email = '$email'");
        if ($result->num_rows > 0) {
            $errors['general'] = "Username or email already exists";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $db->query("INSERT INTO users (username, email, phone, password) VALUES ('$username', '$email', '$phone', '$password_hash')");
            
            // Set session and redirect
            $_SESSION['registration_success'] = true;
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sirtech SMM</title>
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
        
        .register-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .register-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .register-header p {
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
        
        .btn-register {
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
        
        .btn-register:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 92, 231, 0.4);
        }
        
        .btn-login {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            color: white;
            text-decoration: underline;
        }
        
        .password-strength {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            background: var(--danger);
            transition: all 0.3s ease;
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
        
        .terms-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .terms-text a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .terms-text a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 575.98px) {
            .register-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container animate__animated animate__fadeIn">
            <div class="register-card">
                <div class="register-header">
                    <h2>Create Your Account</h2>
                    <p>Join our community and get started today</p>
                </div>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control <?php echo !empty($errors['username']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <?php if (!empty($errors['username'])): ?>
                            <span class="error-message"><?php echo $errors['username']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <?php if (!empty($errors['email'])): ?>
                            <span class="error-message"><?php echo $errors['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="phone" id="phone" class="form-control <?php echo !empty($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                        </div>
                        <?php if (!empty($errors['phone'])): ?>
                            <span class="error-message"><?php echo $errors['phone']; ?></span>
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
                        <div class="password-strength">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <span class="error-message"><?php echo $errors['password']; ?></span>
                        <?php else: ?>
                            <span class="success-message" id="passwordHint">Minimum 8 characters</span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i> Create Account
                    </button>
                    
                    <a href="login.php" class="btn-login">
                        Already have an account? <span class="text-primary">Sign In</span>
                    </a>
                    
                    <div class="terms-text">
                        By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
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
        
        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('strengthMeter');
            const hint = document.getElementById('passwordHint');
            
            // Reset
            meter.style.width = '0%';
            meter.style.backgroundColor = var('--danger');
            hint.textContent = 'Minimum 8 characters';
            
            if (password.length === 0) return;
            
            // Calculate strength
            let strength = 0;
            
            // Length
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Complexity
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update meter
            const width = (strength / 5) * 100;
            meter.style.width = `${width}%`;
            
            // Update color and hint
            if (strength <= 2) {
                meter.style.backgroundColor = 'var(--danger)';
                hint.textContent = 'Weak password';
            } else if (strength <= 3) {
                meter.style.backgroundColor = 'var(--warning)';
                hint.textContent = 'Moderate password';
            } else {
                meter.style.backgroundColor = 'var(--success)';
                hint.textContent = 'Strong password!';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password.length < 8) {
                e.preventDefault();
                document.getElementById('password').classList.add('is-invalid');
                document.getElementById('passwordHint').textContent = 'Password must be at least 8 characters';
                document.getElementById('passwordHint').classList.remove('success-message');
                document.getElementById('passwordHint').classList.add('error-message');
            }
        });
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>