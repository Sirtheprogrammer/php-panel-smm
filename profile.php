<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$user = $db->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Language selection logic
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $lang;
}
$langs = [
    'en' => require __DIR__ . '/lang_en.php',
    'sw' => require __DIR__ . '/lang_sw.php',
];
function t($key) {
    global $langs, $lang;
    return $langs[$lang][$key] ?? $key;
}

// Fetch real order statistics for the user
$orderStats = [
    'total_orders' => 0,
    'completed' => 0,
    'in_progress' => 0,
    'pending' => 0
];

$orderStatusMap = [
    'completed' => ['completed', 'success', 'done'],
    'in_progress' => ['in_progress', 'processing', 'partial'],
    'pending' => ['pending', 'waiting']
];

// Get total orders
$result = $db->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    $orderStats['total_orders'] = (int)$row['total'];
}
// Get completed orders
$result = $db->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id AND (status = 'completed' OR status = 'success' OR status = 'done')");
if ($row = $result->fetch_assoc()) {
    $orderStats['completed'] = (int)$row['total'];
}
// Get in progress orders
$result = $db->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id AND (status = 'in_progress' OR status = 'processing' OR status = 'partial')");
if ($row = $result->fetch_assoc()) {
    $orderStats['in_progress'] = (int)$row['total'];
}
// Get pending orders
$result = $db->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id AND (status = 'pending' OR status = 'waiting')");
if ($row = $result->fetch_assoc()) {
    $orderStats['pending'] = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('profile'); ?> - Sirtech SMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --info: #0984e3;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            min-height: 100vh;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(26, 26, 46, 0.9) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.7) !important;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white !important;
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        /* Profile Header */
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-right: 2rem;
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.5);
        }
        
        .profile-info h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .profile-info p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0;
        }
        
        /* Profile Cards */
        .profile-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .profile-card h3 {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .profile-card h3 i {
            margin-right: 0.75rem;
        }
        
        .info-item {
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: 600;
            width: 150px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .info-value {
            flex: 1;
            word-break: break-word;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(108, 92, 231, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 92, 231, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--glass-border);
            color: white;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Language Selector */
        .language-selector {
            margin-top: 2rem;
        }
        
        .form-select {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            color: white;
            border-radius: 8px;
            padding: 0.5rem 2.25rem 0.5rem 0.75rem;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
        }
        
        /* WhatsApp Floating Button */
        .whatsapp-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 30px;
            right: 30px;
            background-color: #25d366;
            color: #fff;
            border-radius: 50%;
            text-align: center;
            font-size: 2.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .whatsapp-float:hover {
            background: #128c7e;
            color: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            text-decoration: none;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 767.98px) {
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 0.25rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-line me-2"></i>Sirtech SMM
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user me-1"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="order.php">
                            <i class="fas fa-shopping-cart me-1"></i> Place Order
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-1"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Content -->
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <p><?php echo t('member_since'); ?>: <?php echo date('F Y', strtotime($user['created_at'] ?? 'now')); ?></p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['total_orders']; ?></div>
                <div class="stat-label"><?php echo t('total_orders'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['completed']; ?></div>
                <div class="stat-label"><?php echo t('completed'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['in_progress']; ?></div>
                <div class="stat-label"><?php echo t('in_progress'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['pending']; ?></div>
                <div class="stat-label"><?php echo t('pending'); ?></div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="profile-card">
            <h3><i class="fas fa-user-circle"></i> <?php echo t('personal_information'); ?></h3>
            
            <div class="info-item">
                <div class="info-label"><?php echo t('username'); ?>:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo t('user_email'); ?>:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? t('not_set')); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo t('phone_number'); ?>:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? t('not_set')); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo t('last_login'); ?>:</div>
                <div class="info-value">
                    <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : t('never_logged_in'); ?>
                </div>
            </div>
        </div>

        <!-- Account Actions -->
        <div class="profile-card">
            <h3><i class="fas fa-cog"></i> <?php echo t('account_actions'); ?></h3>
            
            <div class="action-buttons">
                <a href="settings.php" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i> <?php echo t('edit_profile'); ?>
                </a>
                <a href="settings.php" class="btn btn-outline">
                    <i class="fas fa-key me-2"></i> <?php echo t('change_password'); ?>
                </a>
                <a href="logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt me-2"></i> <?php echo t('logout'); ?>
                </a>
            </div>
        </div>

        <!-- Language Selector -->
        <div class="language-selector">
            <form method="get" class="d-inline">
                <label for="lang" class="form-label"><?php echo t('language'); ?>:</label>
                <select name="lang" id="lang" class="form-select" onchange="this.form.submit()">
                    <option value="en" <?php if($lang==='en') echo 'selected'; ?>><?php echo t('english'); ?></option>
                    <option value="sw" <?php if($lang==='sw') echo 'selected'; ?>><?php echo t('swahili'); ?></option>
                </select>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>