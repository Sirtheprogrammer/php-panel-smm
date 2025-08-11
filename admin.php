<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Simple admin authentication (for MVP, use a hardcoded admin user)
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['admin_pass'] === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: #6c5ce7;
                --dark: #1a1a2e;
                --light: #f5f6fa;
            }
            body {
                background: linear-gradient(135deg, var(--dark) 0%, #16213e 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                font-family: 'Poppins', sans-serif;
            }
            .login-card {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
                border-radius: 15px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                transition: all 0.3s ease;
            }
            .login-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            }
            .form-control {
                background: rgba(255, 255, 255, 0.1);
                border: none;
                color: white;
                padding: 12px 15px;
            }
            .form-control:focus {
                background: rgba(255, 255, 255, 0.15);
                color: white;
                box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
            }
            .btn-login {
                background: var(--primary);
                border: none;
                padding: 10px 25px;
                font-weight: 600;
                letter-spacing: 1px;
                transition: all 0.3s ease;
            }
            .btn-login:hover {
                background: #5a4bd4;
                transform: translateY(-2px);
            }
            .brand-logo {
                font-size: 1.8rem;
                font-weight: 700;
            }
            .brand-logo i {
                color: var(--primary);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="login-card p-4">
                        <div class="text-center mb-4">
                            <div class="brand-logo mb-3">
                                <i class="fas fa-chart-line"></i> SIRTECH <span style="color: #6c5ce7;">SMM</span>
                            </div>
                            <h3>Admin Login</h3>
                        </div>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="admin_pass" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login">Login</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// --- Main Queries: Always assign before HTML output ---
$total_users = (int)($db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?? 0);
$total_orders = (int)($db->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'] ?? 0);
$revenue = (float)($db->query("SELECT SUM(quantity * 0.01) as total FROM orders")->fetch_assoc()['total'] ?? 0.0);
$active_users = (int)($db->query("SELECT COUNT(DISTINCT user_id) as c FROM orders WHERE status='pending' OR status='processing' ")->fetch_assoc()['c'] ?? 0);

$page = isset($_GET['user_page']) ? max(1, (int)$_GET['user_page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$users = $db->query("SELECT * FROM users LIMIT $offset, $per_page");
$users = $users ? $users->fetch_all(MYSQLI_ASSOC) : [];
$user_count = (int)($db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?? 0);
$user_pages = $user_count > 0 ? ceil($user_count / $per_page) : 1;

$order_page = isset($_GET['order_page']) ? max(1, (int)$_GET['order_page']) : 1;
$order_offset = ($order_page - 1) * $per_page;
$orders = $db->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT $order_offset, $per_page");
$orders = $orders ? $orders->fetch_all(MYSQLI_ASSOC) : [];
$order_count = (int)($db->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'] ?? 0);
$order_pages = $order_count > 0 ? ceil($order_count / $per_page) : 1;

$activity = $db->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 10");
$activity = $activity ? $activity->fetch_all(MYSQLI_ASSOC) : [];

$local_services = $db->query("SELECT * FROM services");
$local_services = $local_services ? $local_services->fetch_all(MYSQLI_ASSOC) : [];

// Fetch mother panel balance from API
$mother_panel_balance = null;
$ch = curl_init(SMMGUO_API_URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['key' => SMMGUO_API_KEY, 'action' => 'balance']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);
if ($response) {
    $balance_data = json_decode($response, true);
    $mother_panel_balance = isset($balance_data['balance']) ? (float)$balance_data['balance'] : 0.0;
}

// Ensure all dashboard variables are always defined
$total_users = isset($total_users) ? (int)$total_users : 0;
$total_orders = isset($total_orders) ? (int)$total_orders : 0;
$revenue = isset($revenue) && $revenue !== null ? (float)$revenue : 0.0;
$active_users = isset($active_users) ? (int)$active_users : 0;
$mother_panel_balance = isset($mother_panel_balance) && $mother_panel_balance !== null ? (float)$mother_panel_balance : 0.0;
$currency_admin_dashboard = isset($currency_admin_dashboard) ? $currency_admin_dashboard : 'usd';
$order_count = isset($order_count) ? (int)$order_count : 0;
$users = isset($users) && is_array($users) ? $users : [];
$orders = isset($orders) && is_array($orders) ? $orders : [];
$user_pages = isset($user_pages) ? (int)$user_pages : 1;
$order_pages = isset($order_pages) ? (int)$order_pages : 1;
$local_services = isset($local_services) && is_array($local_services) ? $local_services : [];
$activity = isset($activity) && is_array($activity) ? $activity : [];

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

// Ensure main queries are always run and assigned
$users = $db->query("SELECT * FROM users LIMIT 10");
$users = $users ? $users->fetch_all(MYSQLI_ASSOC) : [];
$services = $db->query("SELECT * FROM services WHERE visible = 1");
$services = $services ? $services->fetch_all(MYSQLI_ASSOC) : [];
$orders = $db->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 10");
$orders = $orders ? $orders->fetch_all(MYSQLI_ASSOC) : [];

// Fetch available services from API for import
$api_services = [];
$ch = curl_init(SMMGUO_API_URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['key' => SMMGUO_API_KEY, 'action' => 'services']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$api_response = curl_exec($ch);
curl_close($ch);
if ($api_response) {
    $api_services = json_decode($api_response, true);
    if (!is_array($api_services)) $api_services = [];
}

// Handle service importation from API
if (isset($_POST['import_service']) && isset($_POST['api_service_id'])) {
    $api_service_id = $_POST['api_service_id'];
    // Find the selected service in $api_services
    $imported = null;
    foreach ($api_services as $s) {
        if ($s['service'] == $api_service_id) {
            $imported = $s;
            break;
        }
    }
    if ($imported) {
        // Insert into local DB, set visible=1 by default
        $price_usd = $imported['rate'];
        $price_tzs = $imported['rate'] * 2700;
        $stmt = $db->prepare("INSERT INTO services (api_service_id, name, description, price_usd, price_tzs, visible) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('issdd', $imported['service'], $imported['name'], $imported['description'], $price_usd, $price_tzs);
        $stmt->execute();
        $stmt->close();
    }
}

if (isset($_POST['update_service'])) {
    $service_id = (int)$_POST['service_id'];
    $name = $db->real_escape_string($_POST['name']);
    $description = $db->real_escape_string($_POST['description']);
    $visible = isset($_POST['visible']) ? 1 : 0;
    // Support both currencies
    $price_usd = isset($_POST['price_usd']) ? floatval($_POST['price_usd']) : null;
    $price_tzs = isset($_POST['price_tzs']) ? floatval($_POST['price_tzs']) : null;
    $set = "name='$name', description='$description', visible=$visible";
    if ($price_usd !== null) $set .= ", price_usd=$price_usd";
    if ($price_tzs !== null) $set .= ", price_tzs=$price_tzs";
    $db->query("UPDATE services SET $set WHERE id=$service_id");
}

// --- Support Tickets Admin Logic ---
// Handle admin reply
if (isset($_POST['admin_reply']) && isset($_POST['ticket_id'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $reply_message = trim($_POST['reply_message'] ?? '');
    if ($reply_message !== '') {
        // Get user_id and ticket_type from original ticket
        $orig = $db->query("SELECT user_id, ticket_type FROM support_tickets WHERE id=$ticket_id")->fetch_assoc();
        if ($orig) {
            $stmt = $db->prepare("INSERT INTO support_tickets (user_id, message, is_admin_reply, parent_id, ticket_type) VALUES (?, ?, 1, ?, ?)");
            $stmt->bind_param('isis', $orig['user_id'], $reply_message, $ticket_id, $orig['ticket_type']);
            $stmt->execute();
            $stmt->close();
        }
    }
}
// Fetch all top-level tickets (user messages, not admin replies)
$support_tickets = $db->query("SELECT st.*, u.username FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE st.parent_id IS NULL ORDER BY st.created_at DESC LIMIT 50");

// Mark all user messages as seen when admin visits support section
if (isset($_GET['section']) && $_GET['section'] === 'support') {
    $db->query("UPDATE support_tickets SET seen_by_admin=1 WHERE is_admin_reply=0 AND seen_by_admin=0");
}
// Count unseen user tickets for admin notification badge
$unseen_user_tickets = $db->query("SELECT COUNT(*) as c FROM support_tickets WHERE is_admin_reply=0 AND seen_by_admin=0")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo t('admin_dashboard'); ?> - SIRTECH SMM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-light: #a29bfe;
            --dark: #1a1a2e;
            --darker: #121221;
            --light: #f5f6fa;
            --success: #00b894;
            --info: #0984e3;
            --warning: #fdcb6e;
            --danger: #d63031;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--darker);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand i {
            color: var(--primary);
            margin-right: 10px;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
       
            background: var(--primary);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--dark);
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Stats Cards */
        .stat-card {
            border-left: 4px solid var(--primary);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .stat-card .stat-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Tables */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        
        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .table tbody tr:hover {
            background: rgba(108, 92, 231, 0.05);
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        /* Section visibility for tab navigation */
        .main-content .row[id] {
            display: none;
        }
        
        .main-content .row[id].active,
        .main-content .row[id]:target {
            display: block;
        }
        
        .btn-primary:hover {
            background: #5a4bd4;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1001;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggler {
                display: block !important;
            }
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        /* Tabs */
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 0.75rem 1.25rem;
            margin-right: 0.5rem;
            border-radius: 8px;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 2rem 0 1.5rem;
            color: var(--dark);
            position: relative;
            padding-left: 1rem;
        }
        
        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* Pagination */
        .pagination .page-item .page-link {
            border-radius: 8px !important;
            margin: 0 3px;
            border: none;
            color: var(--dark);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary);
            color: white;
        }
        
        /* Currency Toggle */
        .currency-toggle {
            background: rgba(108, 92, 231, 0.1);
            border-radius: 8px;
            padding: 0.25rem;
            display: inline-flex;
        }
        
        .currency-toggle .btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .currency-toggle .btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Mobile Menu Toggler */
        .sidebar-toggler {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.3);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-chart-line"></i>
            <span>SIRTECH <span style="color: #6c5ce7;">SMM</span></span>
        </div>
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a class="nav-link active" href="#dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <?php echo t('dashboard'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#users">
                    <i class="fas fa-users"></i>
                    <?php echo t('users'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#orders">
                    <i class="fas fa-list"></i>
                    <?php echo t('orders'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#activity">
                    <i class="fas fa-history"></i>
                    <?php echo t('activity'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#revenue">
                    <i class="fas fa-coins"></i>
                    <?php echo t('revenue'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#services">
                    <i class="fas fa-cogs"></i>
                    <?php echo t('services'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#providers">
                    <i class="fas fa-server"></i>
                    API Providers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_support.php">
                    <i class="fas fa-headset"></i>
                    <?php echo t('support'); ?>
                    <?php if ($unseen_user_tickets > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unseen_user_tickets; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo t('logout'); ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="d-flex justify-content-between align-items-center">
                <div class="page-title">
                    <h1><?php echo t('admin_dashboard'); ?></h1>
                </div>
                <div>
                    <form method="get" class="d-inline">
                        <label for="lang" class="form-label me-2"><?php echo t('language'); ?>:</label>
                        <select name="lang" id="lang" class="form-select d-inline w-auto" onchange="this.form.submit()">
                            <option value="en" <?php if($lang==='en') echo 'selected'; ?>><?php echo t('english'); ?></option>
                            <option value="sw" <?php if($lang==='sw') echo 'selected'; ?>><?php echo t('swahili'); ?></option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="container-fluid p-4 animate-fade-in">
            <!-- Dashboard Stats -->
            <div id="dashboard" class="row">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-title"><?php echo t('total_users'); ?></div>
                            <div class="stat-value"><?php echo $total_users; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="stat-title"><?php echo t('total_orders'); ?></div>
                            <div class="stat-value"><?php echo $total_orders; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-title"><?php echo t('revenue'); ?></div>
                            <div class="stat-value">$<?php echo number_format($revenue, 2); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-title"><?php echo t('active_users'); ?></div>
                            <div class="stat-value"><?php echo $active_users; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Stats -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo t('balance_overview'); ?></h5>
                            <form method="get" class="d-inline">
                                <div class="currency-toggle">
                                    <button type="button" class="btn <?php echo $currency_admin_dashboard==='usd' ? 'active' : ''; ?>" onclick="this.form.currency_admin_dashboard.value='usd'; this.form.submit()">USD</button>
                                    <button type="button" class="btn <?php echo $currency_admin_dashboard==='tzs' ? 'active' : ''; ?>" onclick="this.form.currency_admin_dashboard.value='tzs'; this.form.submit()">TZS</button>
                                    <input type="hidden" name="currency_admin_dashboard" value="<?php echo $currency_admin_dashboard; ?>">
                                </div>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card bg-light-primary border-0">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="text-primary"><?php echo t('mother_panel_balance'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $mother_panel_balance !== null ? ($currency_admin_dashboard==='tzs' ? 'TZS ' . number_format($mother_panel_balance*2700,2) : '$' . number_format($mother_panel_balance,2)) : 'N/A'; ?></h3>
                                                </div>
                                                <div class="bg-primary-light rounded p-3">
                                                    <i class="fas fa-wallet text-primary" style="font-size: 1.5rem;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light-success border-0">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="text-success"><?php echo t('income'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $currency_admin_dashboard==='tzs' ? 'TZS ' . number_format($revenue*2700,2) : '$' . number_format($revenue,2); ?></h3>
                                                </div>
                                                <div class="bg-success-light rounded p-3">
                                                    <i class="fas fa-arrow-down text-success" style="font-size: 1.5rem;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light-danger border-0">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="text-danger"><?php echo t('expenditure'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $currency_admin_dashboard==='tzs' ? 'TZS ' . number_format($order_count*0.01*2700,2) : '$' . number_format($order_count*0.01,2); ?></h3>
                                                </div>
                                                <div class="bg-danger-light rounded p-3">
                                                    <i class="fas fa-arrow-up text-danger" style="font-size: 1.5rem;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- User Management -->
            <div id="users" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title"><?php echo t('user_management'); ?></h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo t('username'); ?></th>
                                            <th><?php echo t('balance'); ?></th>
                                            <th><?php echo t('currency'); ?></th>
                                            <th><?php echo t('action'); ?></th>
                                            <th><?php echo t('add_to_balance'); ?></th>
                                            <th><?php echo t('delete'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <form method="POST" class="d-inline">
                                                <td>
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="number" step="0.01" name="new_balance" value="<?php echo $user['balance']; ?>" class="form-control d-inline w-75">
                                                </td>
                                                <td>
                                                    <select name="balance_currency" class="form-select d-inline w-auto">
                                                        <option value="usd">USD</option>
                                                        <option value="tzs">TZS</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="submit" name="update_balance" class="btn btn-sm btn-success"><?php echo t('set'); ?></button>
                                                </td>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <td>
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="number" step="0.01" name="add_balance" class="form-control d-inline w-50" placeholder="<?php echo t('amount'); ?>">
                                                    <select name="add_balance_currency" class="form-select d-inline w-auto">
                                                        <option value="usd">USD</option>
                                                        <option value="tzs">TZS</option>
                                                    </select>
                                                    <button type="submit" name="add_to_balance" class="btn btn-sm btn-primary mt-1"><?php echo t('add'); ?></button>
                                                </td>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo t('delete_user_confirm'); ?>');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <td><button type="submit" name="delete_user" class="btn btn-sm btn-danger"><?php echo t('delete'); ?></button></td>
                                            </form>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $user_pages; $i++): ?>
                                    <li class="page-item<?php if ($i == $page) echo ' active'; ?>">
                                        <a class="page-link" href="?user_page=<?php echo $i; ?>#users"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Management -->
            <div id="orders" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title"><?php echo t('order_management'); ?> & <?php echo t('view'); ?></h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo t('user'); ?></th>
                                            <th><?php echo t('service'); ?></th>
                                            <th><?php echo t('quantity'); ?></th>
                                            <th><?php echo t('link'); ?></th>
                                            <th><?php echo t('status'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                                            <td><?php echo htmlspecialchars($order['service']); ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($order['link']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] === 'completed' ? 'success' : 
                                                    ($order['status'] === 'processing' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $order_pages; $i++): ?>
                                    <li class="page-item<?php if ($i == $order_page) echo ' active'; ?>">
                                        <a class="page-link" href="?order_page=<?php echo $i; ?>#orders"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Activity -->
            <div id="activity" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title"><?php echo t('user_activity'); ?> (<?php echo t('last_10_orders'); ?>)</h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($activity as $act): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($act['username']); ?></strong>
                                            <small class="text-muted">Order #<?php echo $act['id']; ?></small>
                                        </div>
                                        <p><?php echo $act['service']; ?> - <?php echo $act['quantity']; ?> items</p>
                                        <span class="badge bg-<?php 
                                            echo $act['status'] === 'completed' ? 'success' : 
                                            ($act['status'] === 'processing' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo $act['status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue -->
            <div id="revenue" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title"><?php echo t('revenue'); ?></h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4><?php echo t('total_revenue'); ?></h4>
                                    <h1 class="text-primary">$<?php echo number_format($revenue, 2); ?></h1>
                                </div>
                                <div class="col-md-6">
                                    <div class="chart-container" style="height: 200px;">
                                        <!-- Placeholder for chart -->
                                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                            <i class="fas fa-chart-pie me-2"></i> Revenue chart will be displayed here
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Management -->
            <div id="services" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title"><?php echo t('service_management'); ?></h2>
                    <div class="card">
                        <div class="card-body">
                            <?php
                            if (isset($_POST['update_service'])) {
                                echo '<div class="alert alert-success">'.t('service_updated').'</div>';
                            }
                            if (isset($_POST['import_service'])) {
                                echo '<div class="alert alert-success">'.t('service_imported').'</div>';
                            }
                            if (isset($_POST['apply_bulk']) && $_POST['bulk_visibility']) {
                                echo '<div class="alert alert-info">'.t('bulk_visibility_updated').'</div>';
                            }
                            ?>
                            <div class="mb-4">
                                <h4><?php echo t('import_new_service_from_api'); ?></h4>
                                <form class="row g-3 align-items-end import-service-form">
                                    <div class="col-md-6">
                                        <select name="api_service_id" class="form-select">
                                            <?php foreach ($api_services as $s):
                                                $exists = false;
                                                foreach ($local_services as $ls) if ($ls['api_service_id'] == $s['service']) $exists = true;
                                                if ($exists) continue;
                                            ?>
                                                <option value="<?php echo $s['service']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary"><?php echo t('import'); ?></button>
                                    </div>
                                </form>
                            </div>
                            <div class="mb-4">
                                <h4><?php echo t('bulk_service_visibility'); ?></h4>
                                <form class="row g-3 align-items-end bulk-visibility-form">
                                    <div class="col-md-6">
                                        <select name="bulk_visibility" class="form-select">
                                            <option value=""><?php echo t('select_action'); ?></option>
                                            <option value="show_all"><?php echo t('show_all'); ?></option>
                                            <option value="hide_all"><?php echo t('hide_all'); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-warning"><?php echo t('apply'); ?></button>
                                    </div>
                                </form>
                            </div>
                            <form method="post">
                                <div class="mb-3">
                                    <label for="currency_admin" class="form-label"><?php echo t('view_prices_in'); ?></label>
                                    <div class="currency-toggle">
                                        <button type="button" class="btn <?php echo $currency_admin==='usd' ? 'active' : ''; ?>" onclick="this.form.currency_admin.value='usd'; this.form.submit()">USD</button>
                                        <button type="button" class="btn <?php echo $currency_admin==='tzs' ? 'active' : ''; ?>" onclick="this.form.currency_admin.value='tzs'; this.form.submit()">TZS</button>
                                        <input type="hidden" name="currency_admin" value="<?php echo $currency_admin; ?>">
                                    </div>
                                </div>
                            </form>
                            <?php $currency_admin = $_POST['currency_admin'] ?? $_SESSION['currency_admin'] ?? 'usd'; $_SESSION['currency_admin'] = $currency_admin; ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th><?php echo t('name'); ?></th>
                                            <th><?php echo t('description'); ?></th>
                                            <th><?php echo t('price'); ?></th>
                                            <th><?php echo t('visible'); ?></th>
                                            <th><?php echo t('actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($local_services as $service): ?>
                                        <tr>
                                            <form class="service-update-form">
                                                <td><input type="text" name="name" value="<?php echo htmlspecialchars($service['name']); ?>" class="form-control"></td>
                                                <td><textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($service['description']); ?></textarea></td>
                                                <td>
                                                    <?php if($currency_admin==='tzs'): ?>
                                                        <input type="number" step="0.01" name="price_tzs" value="<?php echo $service['price_tzs']; ?>" class="form-control" placeholder="TZS">
                                                    <?php else: ?>
                                                        <input type="number" step="0.01" name="price_usd" value="<?php echo $service['price_usd']; ?>" class="form-control" placeholder="USD">
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input service-visibility-toggle" type="checkbox" name="visible" data-service-id="<?php echo $service['id']; ?>" <?php if ($service['visible']) echo 'checked'; ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm"><?php echo t('save'); ?></button>
                                                </td>
                                            </form>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Tickets Management -->
            <div id="support" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title"><i class="fas fa-headset me-2"></i>Support Tickets</h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Message</th>
                                            <th>Image</th>
                                            <th>Created</th>
                                            <th>Replies</th>
                                            <th>Reply</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($ticket = $support_tickets->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $ticket['id']; ?></td>
                                            <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                                            <td><?php echo htmlspecialchars($ticket['ticket_type']); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></td>
                                            <td><?php if ($ticket['image_url']): ?><a href="<?php echo htmlspecialchars($ticket['image_url']); ?>" target="_blank"><img src="<?php echo htmlspecialchars($ticket['image_url']); ?>" alt="img" style="max-width:60px;"></a><?php endif; ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $replies = $db->query("SELECT * FROM support_tickets WHERE parent_id = {$ticket['id']} ORDER BY created_at ASC");
                                                while ($reply = $replies->fetch_assoc()): ?>
                                                    <div class="mb-2 p-2 bg-light text-dark rounded">
                                                        <strong>Admin:</strong> <?php echo nl2br(htmlspecialchars($reply['message'])); ?><br>
                                                        <small><?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?></small>
                                                    </div>
                                                <?php endwhile; ?>
                                            </td>
                                            <td>
                                                <form method="post">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                    <textarea name="reply_message" class="form-control mb-2" placeholder="Type reply..."></textarea>
                                                    <button type="submit" name="admin_reply" class="btn btn-sm btn-primary">Reply</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Providers Management -->
            <div id="providers" class="row mt-5">
                <div class="col-md-12">
                    <h2 class="section-title">API Providers Management</h2>
                    <div class="card">
                        <div class="card-body">
                            <!-- Add New Provider Form -->
                            <div class="mb-4">
                                <h4>Add New API Provider</h4>
                                <form class="add-provider-form row g-3">
                                    <div class="col-md-3">
                                        <input type="text" name="provider_name" class="form-control" placeholder="Provider Name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="url" name="api_url" class="form-control" placeholder="API URL" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="api_key" class="form-control" placeholder="API Key" required>
                                    </div>
                                    <div class="col-md-1">
                                        <input type="number" name="priority" class="form-control" placeholder="Priority" value="1" min="1">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary">Add</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Providers List -->
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>API URL</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Success Rate</th>
                                            <th>Last Check</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="providers-table-body">
                                        <?php
                                        // Load providers for display
                                        $providers_result = $db->query("SELECT * FROM api_providers ORDER BY priority ASC");
                                        if ($providers_result) {
                                            while ($provider = $providers_result->fetch_assoc()) {
                                                $statusClass = $provider['status'] === 'active' ? 'success' : ($provider['status'] === 'inactive' ? 'danger' : 'warning');
                                                echo "<tr data-provider-id='{$provider['id']}'>";
                                                echo "<td>{$provider['name']}</td>";
                                                echo "<td><small>" . substr($provider['api_url'], 0, 50) . "...</small></td>";
                                                echo "<td><span class='badge bg-{$statusClass}'>{$provider['status']}</span></td>";
                                                echo "<td>{$provider['priority']}</td>";
                                                echo "<td>" . number_format($provider['success_rate'], 1) . "%</td>";
                                                echo "<td><small>" . ($provider['last_check'] ? date('M j, H:i', strtotime($provider['last_check'])) : 'Never') . "</small></td>";
                                                echo "<td>";
                                                echo "<button class='btn btn-sm btn-info sync-services-btn' data-provider-id='{$provider['id']}'>Sync Services</button> ";
                                                echo "<button class='btn btn-sm btn-warning toggle-provider-btn' data-provider-id='{$provider['id']}' data-current-status='{$provider['status']}'>" . ($provider['status'] === 'active' ? 'Deactivate' : 'Activate') . "</button> ";
                                                echo "<button class='btn btn-sm btn-danger delete-provider-btn' data-provider-id='{$provider['id']}'>Delete</button>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Provider Performance Stats -->
                            <div class="mt-4">
                                <h4>Provider Performance (Last 7 Days)</h4>
                                <div class="row">
                                    <?php
                                    $perf_result = $db->query("
                                        SELECT p.name, p.status,
                                               SUM(pf.total_orders) as total_orders,
                                               SUM(pf.successful_orders) as successful_orders,
                                               AVG(pf.avg_response_time) as avg_response_time
                                        FROM api_providers p
                                        LEFT JOIN provider_performance pf ON p.id = pf.provider_id 
                                            AND pf.date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                        GROUP BY p.id, p.name, p.status
                                        ORDER BY p.priority ASC
                                    ");
                                    
                                    if ($perf_result) {
                                        while ($perf = $perf_result->fetch_assoc()) {
                                            $successRate = $perf['total_orders'] > 0 ? ($perf['successful_orders'] / $perf['total_orders']) * 100 : 0;
                                            $statusColor = $perf['status'] === 'active' ? 'success' : 'secondary';
                                            echo "<div class='col-md-4 mb-3'>";
                                            echo "<div class='card border-{$statusColor}'>";
                                            echo "<div class='card-body'>";
                                            echo "<h6 class='card-title'>{$perf['name']}</h6>";
                                            echo "<p class='card-text'>";
                                            echo "Orders: " . ($perf['total_orders'] ?: 0) . "<br>";
                                            echo "Success Rate: " . number_format($successRate, 1) . "%<br>";
                                            echo "Avg Response: " . number_format($perf['avg_response_time'] ?: 0, 0) . "ms";
                                            echo "</p>";
                                            echo "</div>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logout Button -->
            <div class="row mt-5">
                <div class="col-md-12 text-end">
                    <a href="logout.php" class="btn btn-secondary"><?php echo t('logout'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Toggler -->
    <button class="sidebar-toggler animate__animated animate__fadeInRight">
        <i class="fas fa-bars"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="js/admin-ajax.js"></script>
    <script>
        AOS.init();
        
        // Sidebar toggle functionality
        document.querySelector('.sidebar-toggler').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Tab-based navigation for admin sections
        function showSection(sectionId) {
            // Hide all sections by removing active class
            document.querySelectorAll('.main-content .row[id]').forEach(section => {
                section.classList.remove('active');
                section.style.display = 'none';
            });
            
            // Show target section
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.add('active');
                targetSection.style.display = 'block';
            }
            
            // Update active nav link
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            const activeLink = document.querySelector(`a[href="#${sectionId}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
        }
        
        // Handle sidebar navigation clicks
        document.querySelectorAll('.sidebar-nav a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const sectionId = this.getAttribute('href').substring(1);
                showSection(sectionId);
                
                // Update URL hash without scrolling
                history.pushState(null, null, '#' + sectionId);
            });
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const hash = window.location.hash.substring(1);
            if (hash) {
                showSection(hash);
            } else {
                showSection('dashboard'); // Default section
            }
        });
        
        // Show initial section based on URL hash or default to dashboard
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1);
            const initialSection = hash || 'dashboard';
            showSection(initialSection);
        });
    </script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>