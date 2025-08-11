<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Handle admin reply
if (isset($_POST['admin_reply']) && isset($_POST['ticket_id'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $reply_message = trim($_POST['reply_message'] ?? '');
    if ($reply_message !== '') {
        $orig = $db->query("SELECT user_id, ticket_type FROM support_tickets WHERE id=$ticket_id")->fetch_assoc();
        if ($orig) {
            $stmt = $db->prepare("INSERT INTO support_tickets (user_id, message, is_admin_reply, parent_id, ticket_type, seen_by_user) VALUES (?, ?, 1, ?, ?, 0)");
            $stmt->bind_param('isis', $orig['user_id'], $reply_message, $ticket_id, $orig['ticket_type']);
            $stmt->execute();
            $stmt->close();
        }
    }
}
// Mark all user messages as seen when admin visits this page
$db->query("UPDATE support_tickets SET seen_by_admin=1 WHERE is_admin_reply=0 AND seen_by_admin=0");
// Fetch all top-level tickets (user messages, not admin replies)
$support_tickets = $db->query("SELECT st.*, u.username FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE st.parent_id IS NULL ORDER BY st.created_at DESC LIMIT 100");

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
    <title><?php echo t('admin_support_tickets'); ?> - Sirtech SMM</title>
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
<body class="font-poppins bg-gray-100 text-gray-900 min-h-screen">
    <!-- Admin Header -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md py-3 px-6 z-50 border-b border-gray-200 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <i class="fas fa-headset text-primary text-2xl"></i>
            <h1 class="text-xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Admin Support</h1>
        </div>
        <div class="flex items-center space-x-4">
            <a href="admin.php" class="text-sm font-medium text-gray-600 hover:text-primary transition-colors">
                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
            </a>
            <a href="logout.php" class="text-sm font-medium text-gray-600 hover:text-danger transition-colors">
                <i class="fas fa-sign-out-alt mr-1"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-16 px-4 pb-6">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="mb-6">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <i class="fas fa-headset text-primary"></i> <?php echo t('admin_support_tickets'); ?>
                </h2>
                <p class="text-gray-600">Manage and respond to user support tickets</p>
            </div>

            <!-- Support Tickets Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">User</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Message</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Attachment</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Created</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Replies</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($ticket = $support_tickets->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $ticket['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($ticket['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $ticket['ticket_type'] === 'transaction' ? 'bg-info/10 text-info' : 'bg-primary/10 text-primary'; ?>">
                                            <?php echo htmlspecialchars($ticket['ticket_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($ticket['image_url']): ?>
                                            <a href="<?php echo htmlspecialchars($ticket['image_url']); ?>" target="_blank" class="inline-block">
                                                <img src="<?php echo htmlspecialchars($ticket['image_url']); ?>" alt="Attachment" class="h-12 w-12 rounded-md object-cover border border-gray-200">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php
                                        $replies = $db->query("SELECT * FROM support_tickets WHERE parent_id = {$ticket['id']} ORDER BY created_at ASC");
                                        while ($reply = $replies->fetch_assoc()): ?>
                                            <div class="mb-2 p-2 bg-gray-50 rounded-md border border-gray-100">
                                                <div class="text-xs font-medium text-primary mb-1">Admin Reply</div>
                                                <div class="text-sm mb-1"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></div>
                                                <div class="text-xs text-gray-400"><?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?></div>
                                            </div>
                                        <?php endwhile; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <form method="post" class="space-y-2">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                            <textarea name="reply_message" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary sm:text-sm" placeholder="Type your reply..."></textarea>
                                            <button type="submit" name="admin_reply" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                                <i class="fas fa-paper-plane mr-2"></i> Reply
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($support_tickets->num_rows === 0): ?>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No support tickets found</h3>
                    <p class="text-gray-500">When users submit support requests, they'll appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Make textareas auto-expand as user types
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
    </script>
</body>
</html>