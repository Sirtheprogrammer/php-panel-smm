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

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_payment'])) {
        $request_id = (int)$_POST['request_id'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        // Get payment request details
        $stmt = $db->prepare("SELECT * FROM payment_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($request) {
            try {
                // Start transaction
                $db->begin_transaction();
                
                // Add balance to user account
                $result = $currencyManager->addBalance($request['user_id'], $request['amount'], $request['currency']);
                
                if ($result['success']) {
                    // Update payment request status
                    $stmt = $db->prepare("
                        UPDATE payment_requests 
                        SET status = 'approved', admin_notes = ?, processed_by = ?, processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("sii", $admin_notes, $_SESSION['admin_id'], $request_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Commit transaction
                    $db->commit();
                    
                    $message = "Payment approved successfully! Balance added to user account.";
                } else {
                    throw new Exception("Failed to add balance to user account");
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error approving payment: " . $e->getMessage();
            }
        } else {
            $error = "Payment request not found or already processed.";
        }
    } elseif (isset($_POST['reject_payment'])) {
        $request_id = (int)$_POST['request_id'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        // Update payment request status
        $stmt = $db->prepare("
            UPDATE payment_requests 
            SET status = 'rejected', admin_notes = ?, processed_by = ?, processed_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("sii", $admin_notes, $_SESSION['admin_id'], $request_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Payment request rejected.";
        } else {
            $error = "Payment request not found or already processed.";
        }
        $stmt->close();
    }
}

// Get payment requests
$filter = $_GET['filter'] ?? 'pending';
$valid_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'pending';
}

$where_clause = $filter === 'all' ? '' : "WHERE pr.status = '$filter'";

$payment_requests = $db->query("
    SELECT pr.*, u.username, admin.username as processed_by_name
    FROM payment_requests pr 
    JOIN users u ON pr.user_id = u.id 
    LEFT JOIN users admin ON pr.processed_by = admin.id 
    $where_clause
    ORDER BY pr.created_at DESC 
    LIMIT 100
");

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved_amount
    FROM payment_requests
")->fetch_assoc();

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
    <title>Payment Management - Sirtech SMM Admin</title>
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
        
        .modal {
            display: none;
        }
        .modal.active {
            display: flex;
        }
    </style>
</head>
<body class="font-poppins bg-gray-100 text-gray-900 min-h-screen">
    <!-- Admin Header -->
    <header class="fixed top-0 left-0 right-0 bg-white shadow-md py-3 px-6 z-50 border-b border-gray-200 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <i class="fas fa-credit-card text-primary text-2xl"></i>
            <h1 class="text-xl font-bold text-gray-800">Payment Management</h1>
        </div>
        <div class="flex items-center space-x-4">
            <a href="admin.php" class="text-gray-600 hover:text-primary transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Admin
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

            <!-- Statistics Cards -->
            <div class="grid md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending Requests</p>
                            <p class="text-2xl font-bold text-warning"><?php echo $stats['pending_count']; ?></p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-clock text-warning text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-2xl font-bold text-success"><?php echo $stats['approved_count']; ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-check text-success text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Rejected</p>
                            <p class="text-2xl font-bold text-danger"><?php echo $stats['rejected_count']; ?></p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-times text-danger text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Approved</p>
                            <p class="text-2xl font-bold text-primary">TZS <?php echo number_format($stats['total_approved_amount'] ?? 0, 2); ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-money-bill text-primary text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <a href="?filter=pending" class="<?php echo $filter === 'pending' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Pending (<?php echo $stats['pending_count']; ?>)
                        </a>
                        <a href="?filter=approved" class="<?php echo $filter === 'approved' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Approved (<?php echo $stats['approved_count']; ?>)
                        </a>
                        <a href="?filter=rejected" class="<?php echo $filter === 'rejected' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Rejected (<?php echo $stats['rejected_count']; ?>)
                        </a>
                        <a href="?filter=all" class="<?php echo $filter === 'all' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            All
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Payment Requests Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Transaction Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($request = $payment_requests->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?php echo $request['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($request['username']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $currencyManager->formatCurrency($request['amount'], $request['currency']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($request['phone_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewPayment(<?php echo htmlspecialchars(json_encode($request)); ?>)" 
                                                class="text-primary hover:text-primary-dark mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button onclick="approvePayment(<?php echo $request['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button onclick="rejectPayment(<?php echo $request['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- View Payment Modal -->
    <div id="viewModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Payment Request Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="paymentDetails"></div>
        </div>
    </div>

    <!-- Approve Payment Modal -->
    <div id="approveModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Approve Payment</h3>
                <button onclick="closeModal('approveModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="request_id" id="approveRequestId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Optional)</label>
                    <textarea name="admin_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Add any notes about this approval..."></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('approveModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button type="submit" name="approve_payment" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <i class="fas fa-check mr-2"></i>Approve Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Payment Modal -->
    <div id="rejectModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Reject Payment</h3>
                <button onclick="closeModal('rejectModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="request_id" id="rejectRequestId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection</label>
                    <textarea name="admin_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Explain why this payment is being rejected..." required></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('rejectModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                    <button type="submit" name="reject_payment" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i>Reject Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewPayment(payment) {
            const modal = document.getElementById('viewModal');
            const details = document.getElementById('paymentDetails');
            
            details.innerHTML = `
                <div class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">User</label>
                            <p class="text-sm text-gray-900">${payment.username}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount</label>
                            <p class="text-sm text-gray-900 font-semibold">${payment.currency.toUpperCase()} ${parseFloat(payment.amount).toLocaleString()}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <p class="text-sm text-gray-900">${payment.phone_number}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Transaction Code</label>
                            <p class="text-sm text-gray-900 font-mono">${payment.transaction_code}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${payment.status === 'approved' ? 'bg-green-100 text-green-800' : payment.status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date</label>
                            <p class="text-sm text-gray-900">${new Date(payment.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    ${payment.admin_notes ? `
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Notes</label>
                            <p class="text-sm text-gray-900">${payment.admin_notes}</p>
                        </div>
                    ` : ''}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Screenshot</label>
                        <img src="${payment.screenshot_path}" alt="Payment Screenshot" class="max-w-full h-auto rounded border">
                    </div>
                </div>
            `;
            
            modal.classList.add('active');
        }

        function approvePayment(requestId) {
            document.getElementById('approveRequestId').value = requestId;
            document.getElementById('approveModal').classList.add('active');
        }

        function rejectPayment(requestId) {
            document.getElementById('rejectRequestId').value = requestId;
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
    </script>
</body>
</html>
