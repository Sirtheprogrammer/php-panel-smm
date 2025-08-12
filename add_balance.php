<?php
require_once 'config.php';
require_once 'CurrencyManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user = $db->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$currencyManager = new CurrencyManager();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'];
    $phone_number = trim($_POST['phone_number']);
    $transaction_code = trim($_POST['transaction_code']);
    
    // Validate inputs
    if ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (empty($phone_number)) {
        $error = "Please enter your M-Pesa phone number.";
    } elseif (empty($transaction_code)) {
        $error = "Please enter the M-Pesa transaction code.";
    } elseif (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a screenshot of your payment.";
    } else {
        // Handle file upload
        $upload_dir = 'uploads/payment_screenshots/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = "Please upload a valid image file (JPG, PNG, GIF).";
        } elseif ($_FILES['payment_screenshot']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $error = "File size must be less than 5MB.";
        } else {
            $filename = 'payment_' . $user_id . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $filepath)) {
                // Insert payment request into database
                $stmt = $db->prepare("
                    INSERT INTO payment_requests 
                    (user_id, amount, currency, phone_number, transaction_code, screenshot_path, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("idsssss", $user_id, $amount, $currency, $phone_number, $transaction_code, $filepath);
                
                if ($stmt->execute()) {
                    $message = "Payment request submitted successfully! Your request is being reviewed by our admin team. You will be notified once approved.";
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $error = "Failed to submit payment request. Please try again.";
                    // Delete uploaded file if database insert failed
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
                $stmt->close();
            } else {
                $error = "Failed to upload screenshot. Please try again.";
            }
        }
    }
}

// Get user's pending payment requests
$pending_requests = $db->query("
    SELECT * FROM payment_requests 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 10
");

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

// Currency selection
$currency = $_GET['currency'] ?? $_SESSION['currency'] ?? 'usd';
if (isset($_GET['currency'])) {
    $_SESSION['currency'] = $currency;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('add_balance'); ?> - Sirtech SMM</title>
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
        
        .payment-step {
            transition: all 0.3s ease;
        }
        .payment-step.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .mpesa-logo {
            background: linear-gradient(135deg, #00a651, #008f43);
            color: white;
        }
    </style>
</head>
<body class="font-poppins bg-gray-100 text-gray-900 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-md py-4 px-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <i class="fas fa-wallet text-primary text-2xl"></i>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo t('add_balance'); ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-600">
                    <?php echo t('current_balance'); ?>: 
                    <span class="font-bold text-primary">
                        <?php echo $currencyManager->formatCurrency($user['balance'], $user['balance_currency'] ?? 'usd'); ?>
                    </span>
                </div>
                <a href="dashboard.php" class="text-gray-600 hover:text-primary transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i><?php echo t('back_to_dashboard'); ?>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        
        <?php if ($message): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Payment Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                        <i class="fas fa-credit-card text-primary"></i>
                        Add Balance via M-Pesa
                    </h2>

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Amount and Currency -->
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-money-bill mr-2"></i>Amount
                                </label>
                                <input type="number" name="amount" step="0.01" min="1" 
                                       value="<?php echo $_POST['amount'] ?? ''; ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Enter amount" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-coins mr-2"></i>Currency
                                </label>
                                <select name="currency" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" required>
                                    <option value="tzs" <?php echo $currency === 'tzs' ? 'selected' : ''; ?>>TZS (Tanzanian Shilling)</option>
                                    <option value="usd" <?php echo $currency === 'usd' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone mr-2"></i>M-Pesa Phone Number
                            </label>
                            <input type="tel" name="phone_number" 
                                   value="<?php echo $_POST['phone_number'] ?? ''; ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="e.g., 0712345678" required>
                        </div>

                        <!-- Transaction Code -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-receipt mr-2"></i>M-Pesa Transaction Code
                            </label>
                            <input type="text" name="transaction_code" 
                                   value="<?php echo $_POST['transaction_code'] ?? ''; ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="e.g., QGH7X8Y9Z0" required>
                        </div>

                        <!-- Screenshot Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-image mr-2"></i>Payment Screenshot
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary transition-colors">
                                <input type="file" name="payment_screenshot" accept="image/*" 
                                       class="hidden" id="screenshot-upload" required>
                                <label for="screenshot-upload" class="cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                    <p class="text-gray-600">Click to upload payment screenshot</p>
                                    <p class="text-sm text-gray-500 mt-2">JPG, PNG, GIF (Max 5MB)</p>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="submit_payment" 
                                class="w-full bg-primary text-white py-4 px-6 rounded-lg hover:bg-primary-dark transition-colors font-semibold text-lg">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Payment Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- Payment Instructions -->
            <div class="space-y-6">
                <!-- M-Pesa Instructions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="mpesa-logo text-center py-4 rounded-lg mb-6">
                        <i class="fas fa-mobile-alt text-3xl mb-2"></i>
                        <h3 class="text-xl font-bold">M-Pesa Payment</h3>
                    </div>

                    <div class="space-y-4">
                        <div class="payment-step active rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-white text-primary w-8 h-8 rounded-full flex items-center justify-center font-bold">1</span>
                                <div>
                                    <h4 class="font-semibold">Go to M-Pesa Menu</h4>
                                    <p class="text-sm opacity-90">Dial *150*00# or use M-Pesa app</p>
                                </div>
                            </div>
                        </div>

                        <div class="payment-step bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">2</span>
                                <div>
                                    <h4 class="font-semibold">Select "Lipa na M-Pesa"</h4>
                                    <p class="text-sm text-gray-600">Choose "Pay Bill" option</p>
                                </div>
                            </div>
                        </div>

                        <div class="payment-step bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">3</span>
                                <div>
                                    <h4 class="font-semibold">Enter Business Number</h4>
                                    <p class="text-sm text-gray-600">
                                        <strong class="text-primary text-lg">2345214</strong>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="payment-step bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">4</span>
                                <div>
                                    <h4 class="font-semibold">Enter Account Number</h4>
                                    <p class="text-sm text-gray-600">Use your username: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="payment-step bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">5</span>
                                <div>
                                    <h4 class="font-semibold">Enter Amount & PIN</h4>
                                    <p class="text-sm text-gray-600">Enter the amount and confirm with your M-Pesa PIN</p>
                                </div>
                            </div>
                        </div>

                        <div class="payment-step bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">6</span>
                                <div>
                                    <h4 class="font-semibold">Take Screenshot</h4>
                                    <p class="text-sm text-gray-600">Screenshot the confirmation message</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Important Notes -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-yellow-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>Important Notes
                    </h3>
                    <ul class="space-y-2 text-sm text-yellow-700">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-yellow-600 mt-1"></i>
                            <span>Payment processing may take 1-24 hours</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-yellow-600 mt-1"></i>
                            <span>Keep your transaction code safe</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-yellow-600 mt-1"></i>
                            <span>Upload a clear screenshot of the confirmation</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-yellow-600 mt-1"></i>
                            <span>Contact support if payment is not processed within 24 hours</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <?php if ($pending_requests->num_rows > 0): ?>
        <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                    <i class="fas fa-history text-primary"></i>Your Payment Requests
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($request = $pending_requests->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $currencyManager->formatCurrency($request['amount'], $request['currency']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($request['transaction_code']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($request['status']) {
                                            case 'approved': echo 'bg-green-100 text-green-800'; break;
                                            case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-yellow-100 text-yellow-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y H:i', strtotime($request['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // File upload preview
        document.getElementById('screenshot-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = e.target.nextElementSibling;
            
            if (file) {
                label.innerHTML = `
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                    <p class="text-green-600 font-semibold">${file.name}</p>
                    <p class="text-sm text-gray-500 mt-2">File selected successfully</p>
                `;
                label.parentElement.classList.add('border-green-300', 'bg-green-50');
                label.parentElement.classList.remove('border-gray-300');
            }
        });
    </script>
</body>
</html>
