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
$services = $db->query("SELECT * FROM services WHERE visible = 1")->fetch_all(MYSQLI_ASSOC);
$orders = $db->query("SELECT * FROM orders WHERE user_id = $user_id")->fetch_all(MYSQLI_ASSOC);

// Currency and language logic remains the same
$currency = $_GET['currency'] ?? $_SESSION['currency'] ?? 'usd';
if (isset($_GET['currency'])) {
    $_SESSION['currency'] = $currency;
}

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

// Order refresh logic remains the same
if (isset($_GET['refresh_order']) && is_numeric($_GET['refresh_order'])) {
    require_once 'Api.php';
    $api = new Api();
    $refresh_order_id = (int)$_GET['refresh_order'];
    $order_row = $db->query("SELECT api_order_id FROM orders WHERE id = $refresh_order_id AND user_id = $user_id")->fetch_assoc();
    if ($order_row && !empty($order_row['api_order_id'])) {
        $api_status = $api->status($order_row['api_order_id']);
        if (isset($api_status->status)) {
            $new_status = $db->real_escape_string($api_status->status);
            $db->query("UPDATE orders SET status = '$new_status' WHERE id = $refresh_order_id");
        }
    }
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sirtech SMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6c5ce7',
                        'primary-dark': '#5649c0',
                        secondary: '#00cec9',
                        dark: '#2d3436',
                        light: '#f5f6fa',
                        success: '#00b894',
                        danger: '#d63031',
                        warning: '#fdcb6e',
                        info: '#0984e3',
                    },
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease forwards',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body class="font-poppins bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100 min-h-screen flex flex-col">
    <!-- Mobile Header -->
    <header class="fixed top-0 left-0 right-0 bg-gray-900/90 backdrop-blur-md py-3 px-4 z-50 shadow-lg border-b border-gray-800 flex items-center justify-between lg:hidden">
        <button id="mobileMenuToggle" class="text-white text-xl">
            <i class="fas fa-bars"></i>
        </button>
        <div class="flex items-center space-x-2">
            <i class="fas fa-chart-line text-primary text-xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Sirtech SMM</h1>
        </div>
        <div id="mobileUserToggle" class="relative">
            <div class="flex items-center space-x-2 bg-primary/10 rounded-full p-1 cursor-pointer">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <i class="fas fa-chevron-down text-xs"></i>
            </div>
            <div id="mobileUserMenu" class="hidden absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-xl py-1 z-50">
                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-primary/20 hover:text-white">
                    <i class="fas fa-user mr-2 text-primary"></i> Profile
                </a>
                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-primary/20 hover:text-white">
                    <i class="fas fa-cog mr-2 text-primary"></i> Settings
                </a>
                <div class="border-t border-gray-700 my-1"></div>
                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-primary/20 hover:text-white">
                    <i class="fas fa-sign-out-alt mr-2 text-primary"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Desktop Header -->
    <header class="hidden lg:flex fixed top-0 left-0 right-0 bg-gray-900/90 backdrop-blur-md py-3 px-6 z-50 shadow-lg border-b border-gray-800 items-center justify-between">
        <div class="flex items-center space-x-3">
            <i class="fas fa-chart-line text-primary text-2xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Sirtech SMM</h1>
        </div>
        <div id="desktopUserToggle" class="relative">
            <div class="flex items-center space-x-2 bg-primary/10 hover:bg-primary/20 rounded-full px-4 py-1 cursor-pointer transition-all">
                <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <span class="text-sm font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                <i class="fas fa-chevron-down text-xs"></i>
            </div>
            <div id="desktopUserMenu" class="hidden absolute right-0 mt-2 w-56 bg-gray-800 rounded-lg shadow-xl py-1 z-50">
                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-primary/20 hover:text-white">
                    <i class="fas fa-user mr-2 text-primary"></i> Profile
                </a>
                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-primary/20 hover:text-white">
                    <i class="fas fa-cog mr-2 text-primary"></i> Settings
                </a>
                <div class="border-t border-gray-700 my-1"></div>
                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-primary/20 hover:text-white">
                    <i class="fas fa-sign-out-alt mr-2 text-primary"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 bottom-0 w-64 bg-gray-900/95 backdrop-blur-md border-r border-gray-800 z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 pt-16 lg:pt-20">
        <div class="h-full overflow-y-auto px-3 py-4">
            <ul class="space-y-1">
                <li class="px-3 pt-5 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Main</li>
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-base font-medium rounded-lg text-white bg-primary/20 group">
                        <i class="fas fa-home mr-3 text-primary"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
                        <i class="fas fa-user mr-3 text-primary"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="order.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
                        <i class="fas fa-shopping-cart mr-3 text-primary"></i>
                        <span>Place Order</span>
                        <span class="ml-auto bg-primary text-white text-xs font-bold px-2 py-1 rounded-full">New</span>
                    </a>
                </li>
                <li>
                    <a href="transactions.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
                        <i class="fas fa-wallet mr-3 text-primary"></i>
                        <span>Transactions</span>
                    </a>
                </li>
                
                <li class="px-3 pt-5 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Settings</li>
                <li>
                    <a href="settings.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
                        <i class="fas fa-cog mr-3 text-primary"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="support.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
                        <i class="fas fa-headset mr-3 text-primary"></i>
                        <span>Support</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 pt-16 lg:pt-20 lg:pl-64 transition-all duration-300">
        <div class="p-4 md:p-6 lg:p-8">
            <!-- Dashboard Header -->
            <div class="mb-6 md:mb-8 lg:mb-10 animate-fade-in">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-bold text-white flex items-center gap-2">
                            <i class="fas fa-home text-primary"></i> Dashboard
                        </h2>
                        <p class="text-gray-400">Welcome back, <?php echo htmlspecialchars($user['username']); ?>! Here's what's happening today.</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <a href="order.php" class="flex items-center justify-center gap-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                            <i class="fas fa-plus-circle"></i> Place Order
                        </a>
                        <a href="transactions.php" class="flex items-center justify-center gap-2 px-4 py-2 border border-gray-700 hover:border-gray-600 text-white font-medium rounded-lg transition-all hover:bg-gray-800/50">
                            <i class="fas fa-wallet"></i> Transactions
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
                    <!-- Total Orders -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-4 md:p-6 hover:border-primary/30 hover:shadow-lg transition-all animate-fade-in delay-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium">Total Orders</p>
                                <h3 class="text-2xl md:text-3xl font-bold text-white my-1"><?php echo count($orders); ?></h3>
                                <div class="flex items-center text-success text-xs font-medium">
                                    <i class="fas fa-arrow-up mr-1"></i> 12% from last month
                                </div>
                            </div>
                            <div class="p-3 bg-primary/10 rounded-lg text-primary">
                                <i class="fas fa-shopping-cart text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Completed Orders -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-4 md:p-6 hover:border-success/30 hover:shadow-lg transition-all animate-fade-in delay-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium">Completed Orders</p>
                                <h3 class="text-2xl md:text-3xl font-bold text-white my-1">
                                    <?php 
                                        $completed = array_filter($orders, function($order) {
                                            return strtolower($order['status']) === 'completed';
                                        });
                                        echo count($completed);
                                    ?>
                                </h3>
                                <div class="flex items-center text-success text-xs font-medium">
                                    <i class="fas fa-arrow-up mr-1"></i> 8% from last month
                                </div>
                            </div>
                            <div class="p-3 bg-success/10 rounded-lg text-success">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Orders -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-4 md:p-6 hover:border-warning/30 hover:shadow-lg transition-all animate-fade-in delay-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium">Pending Orders</p>
                                <h3 class="text-2xl md:text-3xl font-bold text-white my-1">
                                    <?php 
                                        $pending = array_filter($orders, function($order) {
                                            return strtolower($order['status']) === 'pending';
                                        });
                                        echo count($pending);
                                    ?>
                                </h3>
                                <div class="flex items-center text-danger text-xs font-medium">
                                    <i class="fas fa-arrow-down mr-1"></i> 3% from last month
                                </div>
                            </div>
                            <div class="p-3 bg-warning/10 rounded-lg text-warning">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="bg-gradient-to-r from-primary to-primary-dark rounded-2xl p-6 mb-6 md:mb-8 relative overflow-hidden animate-fade-in delay-400">
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 text-white/90 font-medium mb-4">
                            <i class="fas fa-wallet"></i>
                            <span>Account Balance</span>
                        </div>
                        <h3 class="text-3xl md:text-4xl font-bold text-white mb-6">
                            <?php
                            if ($currency === 'tzs') {
                                echo 'TZS ' . number_format($user['balance'] * 2700, 2);
                            } else {
                                echo '$' . number_format($user['balance'], 2);
                            }
                            ?>
                        </h3>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button class="flex items-center justify-center gap-2 px-4 py-2 bg-white hover:bg-gray-100 text-primary font-medium rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                                <i class="fas fa-plus-circle"></i> Add Funds
                            </button>
                            <button class="flex items-center justify-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white font-medium rounded-lg transition-all backdrop-blur-sm">
                                <i class="fas fa-exchange-alt"></i> Convert
                            </button>
                        </div>
                    </div>
                    <div class="absolute -top-20 -right-20 w-40 h-40 rounded-full bg-white/10"></div>
                    <div class="absolute -bottom-16 -right-10 w-32 h-32 rounded-full bg-white/5"></div>
                </div>

                <!-- Order History -->
                <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-4 md:p-6 backdrop-blur-sm">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <h2 class="text-xl md:text-2xl font-semibold text-white flex items-center gap-3">
                            <i class="fas fa-history text-primary"></i> Order History
                        </h2>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form method="get" class="w-full sm:w-auto">
                                <select name="currency" onchange="this.form.submit()" class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                                    <option value="usd" <?php if($currency==='usd') echo 'selected'; ?>>USD</option>
                                    <option value="tzs" <?php if($currency==='tzs') echo 'selected'; ?>>TZS</option>
                                </select>
                            </form>
                            <form method="get" class="w-full sm:w-auto">
                                <select name="lang" onchange="this.form.submit()" class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                                    <option value="en" <?php if($lang==='en') echo 'selected'; ?>>English</option>
                                    <option value="sw" <?php if($lang==='sw') echo 'selected'; ?>>Swahili</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (empty($orders)): ?>
                        <div class="py-10 text-center">
                            <i class="fas fa-info-circle text-4xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400 mb-6">No orders yet. Get started by placing your first order!</p>
                            <a href="order.php" class="inline-flex items-center gap-2 px-6 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                                <i class="fas fa-plus-circle"></i> Place Order
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto rounded-lg border border-gray-700">
                            <table class="min-w-full divide-y divide-gray-700">
                                <thead class="bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Service</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Link</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-gray-800/50 divide-y divide-gray-700">
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="hover:bg-gray-800/70 transition-colors" data-order-id="<?php echo $order['id']; ?>">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-white"><?php echo htmlspecialchars($order['service']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo $order['quantity']; ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($order['link']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo $currency === 'tzs' ? 'TZS ' . number_format($order['charge'] * 2500, 0) : '$' . number_format($order['charge'], 2); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <?php
                                                    $statusClass = '';
                                                    $statusIcon = 'fa-circle-notch fa-spin';
                                                    switch(strtolower($order['status'])) {
                                                        case 'completed':
                                                            $statusClass = 'bg-success/10 text-success';
                                                            $statusIcon = 'fa-check-circle';
                                                            break;
                                                        case 'pending':
                                                            $statusClass = 'bg-warning/10 text-warning';
                                                            $statusIcon = 'fa-clock';
                                                            break;
                                                        case 'processing':
                                                            $statusClass = 'bg-info/10 text-info';
                                                            $statusIcon = 'fa-sync-alt fa-spin';
                                                            break;
                                                        case 'failed':
                                                            $statusClass = 'bg-danger/10 text-danger';
                                                            $statusIcon = 'fa-times-circle';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-gray-700 text-gray-300';
                                                    }
                                                ?>
                                                <span class="order-status inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                    <i class="fas <?php echo $statusIcon; ?> mr-1.5"></i>
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <button class="refresh-order-btn inline-flex items-center px-3 py-1 rounded-md bg-primary/10 text-primary hover:bg-primary/20 transition-colors" data-order-id="<?php echo $order['id']; ?>">
                                                    <i class="fas fa-sync-alt mr-1.5 text-xs"></i> Refresh
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900/80 backdrop-blur-md border-t border-gray-800 mt-auto">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex flex-wrap justify-center gap-4 md:gap-6">
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">About Us</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Services</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Pricing</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">FAQ</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Contact</a>
                </div>
                <div class="flex gap-4">
                    <a href="#" class="w-9 h-9 rounded-full bg-gray-800 hover:bg-primary text-gray-400 hover:text-white flex items-center justify-center transition-colors">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-full bg-gray-800 hover:bg-primary text-gray-400 hover:text-white flex items-center justify-center transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-full bg-gray-800 hover:bg-primary text-gray-400 hover:text-white flex items-center justify-center transition-colors">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-full bg-gray-800 hover:bg-primary text-gray-400 hover:text-white flex items-center justify-center transition-colors">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>
            <div class="mt-6 text-center text-gray-500 text-xs">
                &copy; <?php echo date('Y'); ?> Sirtech SMM. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        });
        
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
        
        // Mobile user dropdown
        const mobileUserToggle = document.getElementById('mobileUserToggle');
        const mobileUserMenu = document.getElementById('mobileUserMenu');
        
        mobileUserToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            mobileUserMenu.classList.toggle('hidden');
        });
        
        // Desktop user dropdown
        const desktopUserToggle = document.getElementById('desktopUserToggle');
        const desktopUserMenu = document.getElementById('desktopUserMenu');
        
        desktopUserToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            desktopUserMenu.classList.toggle('hidden');
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            mobileUserMenu.classList.add('hidden');
            desktopUserMenu.classList.add('hidden');
        });
        
        // Close sidebar when clicking on a link (mobile)
        document.querySelectorAll('#sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                }
            });
        });
        
        // Handle window resize
        function handleResize() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        }
        
        window.addEventListener('resize', handleResize);
        handleResize(); // Run on initial load
    </script>
    <script src="js/user-ajax.js"></script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>