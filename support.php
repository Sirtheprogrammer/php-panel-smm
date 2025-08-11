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

// imgbb API key
$imgbb_key = '7554bd39b7ed8a9f415cdbf44f4cb9d4';

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $ticket_type = $_POST['ticket_type'] ?? 'support';
    $image_url = null;
    $image_delete_url = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img_data = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $imgbb_key);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'image' => $img_data
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        if (!empty($result['success'])) {
            $image_url = $result['data']['url'] ?? null;
            $image_delete_url = $result['data']['delete_url'] ?? null;
        } else {
            $error = 'Image upload failed.';
        }
    }

    if ($message !== '' || $image_url) {
        $stmt = $db->prepare("INSERT INTO support_tickets (user_id, message, image_url, image_delete_url, ticket_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $user_id, $message, $image_url, $image_delete_url, $ticket_type);
        $stmt->execute();
        $stmt->close();
        header('Location: support.php?success=1');
        exit;
    } else {
        $error = 'Please enter a message or attach an image.';
    }
}

// Mark all admin replies as seen when user visits support page
$db->query("UPDATE support_tickets SET seen_by_user=1 WHERE user_id=$user_id AND is_admin_reply=1 AND seen_by_user=0");
// Count unseen admin replies for notification badge
$unseen_admin_replies = $db->query("SELECT COUNT(*) as c FROM support_tickets WHERE user_id=$user_id AND is_admin_reply=1 AND seen_by_user=0")->fetch_assoc()['c'] ?? 0;

// Fetch previous tickets/messages
$tickets = $db->query("SELECT * FROM support_tickets WHERE user_id = $user_id ORDER BY created_at DESC");

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
    <title><?php echo t('support'); ?> - Sirtech SMM</title>
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
            <i class="fas fa-headset text-primary text-xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Support</h1>
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
            <i class="fas fa-headset text-primary text-2xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Support</h1>
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
                    <a href="settings.php" class="flex items-center p-3 text-base font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white group">
                        <i class="fas fa-cog mr-3 text-primary"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="support.php" class="flex items-center p-3 text-base font-medium rounded-lg text-white bg-primary/20 group">
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
                    <i class="fas fa-headset text-primary"></i> <?php echo t('support'); ?>
                    <?php if ($unseen_admin_replies > 0): ?>
                        <span class="bg-danger text-white text-sm font-bold px-2 py-1 rounded-full">
                            <?php echo $unseen_admin_replies; ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <p class="text-gray-400">Contact our support team for assistance with your account or services</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($error): ?>
                <div class="bg-danger/20 border-l-4 border-danger text-danger p-4 mb-6 rounded-lg">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="bg-success/20 border-l-4 border-success text-success p-4 mb-6 rounded-lg">
                    Message sent successfully!
                </div>
            <?php endif; ?>

            <!-- Support Form -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden mb-6 md:mb-8">
                <div class="bg-gray-900/50 px-6 py-4 border-b border-gray-700">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-paper-plane text-primary"></i> New Support Ticket
                    </h3>
                </div>
                <div class="p-4 md:p-6">
                    <form method="post" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="ticket_type" class="block text-sm font-medium text-gray-300 mb-2">Ticket Type</label>
                            <select name="ticket_type" id="ticket_type" class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                                <option value="support">Support</option>
                                <option value="transaction">Transaction Confirmation</option>
                            </select>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-300 mb-2">Message</label>
                            <textarea name="message" id="message" rows="4" class="w-full bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5" placeholder="Describe your issue or transaction..."></textarea>
                        </div>
                        <div>
                            <label for="image" class="block text-sm font-medium text-gray-300 mb-2">Attachment (optional)</label>
                            <div class="flex items-center gap-4">
                                <input type="file" name="image" id="image" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20" accept="image/*">
                            </div>
                        </div>
                        <button type="submit" class="w-full md:w-auto flex items-center justify-center gap-2 px-6 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- Ticket History -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden">
                <div class="bg-gray-900/50 px-6 py-4 border-b border-gray-700">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-history text-primary"></i> Your Support History
                    </h3>
                </div>
                <div class="p-4 md:p-6">
                    <?php if ($tickets->num_rows === 0): ?>
                        <div class="py-8 text-center text-gray-400">
                            <i class="fas fa-comment-slash text-4xl mb-4 opacity-30"></i>
                            <p class="text-lg">No support tickets yet</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php while ($row = $tickets->fetch_assoc()): ?>
                                <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-medium <?php echo $row['ticket_type'] === 'transaction' ? 'text-info' : 'text-primary'; ?>">
                                            <?php echo htmlspecialchars($row['ticket_type'] === 'transaction' ? 'Transaction Confirmation' : 'Support Ticket'); ?>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <?php echo date('M j, Y g:i A', strtotime($row['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if ($row['message']): ?>
                                        <div class="text-gray-300 mb-3 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['image_url']): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($row['image_url']); ?>" target="_blank" class="inline-block">
                                                <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="Attachment" class="max-w-full md:max-w-xs rounded-lg border border-gray-700">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
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
</body>
</html>