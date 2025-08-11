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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Change username
    if (!empty($_POST['new_username'])) {
        $new_username = $db->real_escape_string(trim($_POST['new_username']));
        if ($new_username !== $user['username']) {
            $exists = $db->query("SELECT id FROM users WHERE username = '$new_username'")->num_rows;
            if ($exists) {
                $message = '<div class="bg-danger/20 border-l-4 border-danger text-danger p-4 mb-6 rounded-lg">Username already taken.</div>';
            } else {
                $db->query("UPDATE users SET username = '$new_username' WHERE id = $user_id");
                $_SESSION['user_id'] = $user_id;
                $message = '<div class="bg-success/20 border-l-4 border-success text-success p-4 mb-6 rounded-lg">Username updated successfully.</div>';
                $user['username'] = $new_username;
            }
        }
    }
    // Change password
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $user_db = $db->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
        if (password_verify($current_password, $user_db['password'])) {
            $db->query("UPDATE users SET password = '$new_password' WHERE id = $user_id");
            $message .= '<div class="bg-success/20 border-l-4 border-success text-success p-4 mb-6 rounded-lg">Password updated successfully.</div>';
        } else {
            $message .= '<div class="bg-danger/20 border-l-4 border-danger text-danger p-4 mb-6 rounded-lg">Current password is incorrect.</div>';
        }
    }
}

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
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Sirtech SMM</title>
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
                    }
                }
            }
        }
    </script>
</head>
<body class="font-poppins bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100 min-h-screen flex flex-col">
    <!-- Mobile Header -->
    <header class="fixed top-0 left-0 right-0 bg-gray-900/90 backdrop-blur-md py-3 px-4 z-50 shadow-lg border-b border-gray-800 flex items-center justify-between lg:hidden">
        <button id="mobileMenuToggle" class="text-white text-xl">
            <i class="fas fa-bars"></i>
        </button>
        <div class="flex items-center space-x-2">
            <i class="fas fa-cog text-primary text-xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Settings</h1>
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
            <i class="fas fa-cog text-primary text-2xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Settings</h1>
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
                    <a href="dashboard.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
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
                    <a href="settings.php" class="flex items-center p-3 text-base font-medium rounded-lg text-white bg-primary/20 group">
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
            <!-- Page Header -->
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-white flex items-center gap-3">
                    <i class="fas fa-cog text-primary"></i> Account Settings
                </h2>
                <p class="text-gray-400">Manage your account preferences and security settings</p>
            </div>

            <?php echo $message; ?>

            <!-- Settings Cards -->
            <div class="grid gap-6 md:gap-8">
                <!-- Change Username Card -->
                <div class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden">
                    <div class="bg-gray-900/50 px-6 py-4 border-b border-gray-700">
                        <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-user-edit text-primary"></i> Change Username
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="post">
                            <div class="mb-4">
                                <label for="new_username" class="block text-sm font-medium text-gray-300 mb-2">New Username</label>
                                <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                                    class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5" 
                                    placeholder="Enter new username" required>
                            </div>
                            <button type="submit" class="w-full md:w-auto flex items-center justify-center gap-2 px-6 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                                <i class="fas fa-save"></i> Update Username
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden">
                    <div class="bg-gray-900/50 px-6 py-4 border-b border-gray-700">
                        <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-lock text-primary"></i> Change Password
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="post">
                            <div class="mb-4">
                                <label for="current_password" class="block text-sm font-medium text-gray-300 mb-2">Current Password</label>
                                <input type="password" id="current_password" name="current_password" 
                                    class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5" 
                                    placeholder="Enter current password" required>
                            </div>
                            <div class="mb-6">
                                <label for="new_password" class="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                    class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5" 
                                    placeholder="Enter new password" required>
                            </div>
                            <button type="submit" class="w-full md:w-auto flex items-center justify-center gap-2 px-6 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Language Settings Card -->
                <div class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden">
                    <div class="bg-gray-900/50 px-6 py-4 border-b border-gray-700">
                        <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-language text-primary"></i> Language Settings
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="get">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                <label for="lang" class="block text-sm font-medium text-gray-300">Language Preference:</label>
                                <select name="lang" id="lang" onchange="this.form.submit()" 
                                    class="bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                                    <option value="en" <?php if($lang==='en') echo 'selected'; ?>>English</option>
                                    <option value="sw" <?php if($lang==='sw') echo 'selected'; ?>>Swahili</option>
                                </select>
                            </div>
                        </form>
                    </div>
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
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>