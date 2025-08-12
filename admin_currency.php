<?php
require_once 'config.php';
require_once 'CurrencyManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$currencyManager = new CurrencyManager();
$message = '';
$error = '';

// Handle exchange rate update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rates'])) {
    $usd_to_tzs = (float)$_POST['usd_to_tzs'];
    
    if ($usd_to_tzs > 0) {
        try {
            $result = $currencyManager->updateExchangeRates($usd_to_tzs, $_SESSION['admin_id'] ?? null);
            if ($result) {
                $message = "Exchange rates updated successfully!";
            } else {
                $error = "Failed to update exchange rates.";
            }
        } catch (Exception $e) {
            $error = "Error updating rates: " . $e->getMessage();
        }
    } else {
        $error = "Invalid exchange rate value.";
    }
}

// Handle user balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_balance'])) {
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'];
    $action = $_POST['action']; // 'add' or 'deduct'
    
    if ($user_id > 0 && $amount > 0) {
        try {
            if ($action === 'add') {
                $result = $currencyManager->addBalance($user_id, $amount, $currency);
                $message = "Balance added successfully!";
            } else {
                $result = $currencyManager->deductBalance($user_id, $amount, $currency);
                $message = "Balance deducted successfully!";
            }
        } catch (Exception $e) {
            $error = "Error adjusting balance: " . $e->getMessage();
        }
    } else {
        $error = "Invalid user ID or amount.";
    }
}

// Get current exchange rates
$rates = $currencyManager->getExchangeRates();

// Get recent balance transactions
$recent_transactions = $db->query("
    SELECT bt.*, u.username 
    FROM balance_transactions bt 
    JOIN users u ON bt.user_id = u.id 
    ORDER BY bt.created_at DESC 
    LIMIT 50
");

// Get users for balance adjustment
$users = $db->query("SELECT id, username, balance, balance_currency FROM users ORDER BY username");

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
    <title>Currency Management - Sirtech SMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-dark: #5649c0;
            --secondary: #00cec9;
            --accent: #fd79a8;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #e84393;
        }
        .font-poppins { font-family: 'Poppins', sans-serif; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .border-primary { border-color: var(--primary); }
        .hover\:bg-primary:hover { background-color: var(--primary); }
        .bg-success { background-color: var(--success); }
        .bg-danger { background-color: var(--danger); }
        .bg-warning { background-color: var(--warning); }
    </style>
</head>
<body class="font-poppins bg-gray-100 text-gray-900 min-h-screen">
    <!-- Admin Header -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md py-3 px-6 z-50 border-b border-gray-200 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <i class="fas fa-coins text-primary text-2xl"></i>
            <h1 class="text-xl font-bold text-gray-800">Currency Management</h1>
        </div>
        <div class="flex items-center space-x-4">
            <a href="admin.php" class="text-gray-600 hover:text-primary transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Admin Dashboard
            </a>
            <a href="admin_payments.php" class="text-gray-600 hover:text-primary transition-colors">
                <i class="fas fa-credit-card mr-2"></i>Payment Management
            </a>
            <a href="admin_support.php" class="text-gray-600 hover:text-primary transition-colors">
                <i class="fas fa-headset mr-2"></i>Support Tickets
            </a>
        </div>
    </header>

    <main class="pt-16 px-4 pb-6">
        <div class="max-w-7xl mx-auto">
            
            <?php if ($message): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Exchange Rates Management -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                    <i class="fas fa-exchange-alt text-primary"></i>Exchange Rates
                </h2>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Current Rates</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                <span class="font-medium">1 USD =</span>
                                <span class="text-primary font-bold"><?php echo number_format($rates['usd_to_tzs'], 2); ?> TZS</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                <span class="font-medium">1 TZS =</span>
                                <span class="text-primary font-bold"><?php echo number_format($rates['tzs_to_usd'], 8); ?> USD</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Update Rates</h3>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    USD to TZS Rate
                                </label>
                                <input type="number" name="usd_to_tzs" step="0.01" 
                                       value="<?php echo $rates['usd_to_tzs']; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       required>
                            </div>
                            <button type="submit" name="update_rates" 
                                    class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-save mr-2"></i>Update Rates
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User Balance Management -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                    <i class="fas fa-wallet text-primary"></i>User Balance Management
                </h2>
                
                <form method="POST" class="grid md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                        <select name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" required>
                            <option value="">Select User</option>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> 
                                    (<?php echo $currencyManager->formatCurrency($user['balance'], $user['balance_currency']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" 
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                        <select name="currency" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" required>
                            <option value="usd">USD</option>
                            <option value="tzs">TZS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <select name="action" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" required>
                            <option value="add">Add Balance</option>
                            <option value="deduct">Deduct Balance</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="adjust_balance" 
                                class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-primary-dark transition-colors">
                            <i class="fas fa-edit mr-2"></i>Adjust
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                        <i class="fas fa-history text-primary"></i>Recent Balance Transactions
                    </h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $transaction['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($transaction['username']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="<?php echo $transaction['amount'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $currencyManager->formatCurrency($transaction['amount'], $transaction['currency']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch($transaction['transaction_type']) {
                                                case 'order_deduction': echo 'bg-red-100 text-red-800'; break;
                                                case 'balance_addition': echo 'bg-green-100 text-green-800'; break;
                                                case 'refund': echo 'bg-blue-100 text-blue-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y H:i', strtotime($transaction['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
