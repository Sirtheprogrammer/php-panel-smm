<?php
require_once 'config.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON response header
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Language support
$lang = $_SESSION['lang'] ?? 'en';
$langs = [
    'en' => require __DIR__ . '/lang_en.php',
    'sw' => require __DIR__ . '/lang_sw.php',
];
function t($key) {
    global $langs, $lang;
    return $langs[$lang][$key] ?? $key;
}

// Handle different AJAX actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_service':
        updateService();
        break;
    
    case 'toggle_service_visibility':
        toggleServiceVisibility();
        break;
    
    case 'import_service':
        importService();
        break;
    
    case 'bulk_visibility':
        bulkVisibilityUpdate();
        break;
    
    case 'update_user':
        updateUser();
        break;
    
    case 'delete_user':
        deleteUser();
        break;
    
    case 'update_profile':
        updateProfile();
        break;
    
    case 'place_order':
        placeOrder();
        break;
    
    case 'refresh_order_status':
        refreshOrderStatus();
        break;
    
    case 'add_funds':
        addFunds();
        break;
    
    case 'submit_support_ticket':
        submitSupportTicket();
        break;
    
    case 'reply_support_ticket':
        replySupportTicket();
        break;
    
    case 'add_provider':
        addProvider();
        break;
    
    case 'toggle_provider_status':
        toggleProviderStatus();
        break;
    
    case 'delete_provider':
        deleteProvider();
        break;
    
    case 'sync_provider_services':
        syncProviderServices();
        break;
    
    case 'test_provider_connection':
        testProviderConnection();
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function updateService() {
    global $db;
    
    $service_id = (int)$_POST['service_id'];
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price_usd = (float)($_POST['price_usd'] ?? 0);
    $price_tzs = (float)($_POST['price_tzs'] ?? 0);
    $visible = isset($_POST['visible']) ? 1 : 0;
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => t('service_name_required')]);
        return;
    }
    
    $stmt = $db->prepare("UPDATE services SET name = ?, description = ?, price_usd = ?, price_tzs = ?, visible = ? WHERE id = ?");
    $stmt->bind_param("ssddii", $name, $description, $price_usd, $price_tzs, $visible, $service_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => t('service_updated'),
            'data' => [
                'id' => $service_id,
                'name' => $name,
                'description' => $description,
                'price_usd' => $price_usd,
                'price_tzs' => $price_tzs,
                'visible' => $visible
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('update_failed')]);
    }
}

function toggleServiceVisibility() {
    global $db;
    
    $service_id = (int)$_POST['service_id'];
    $visible = (int)$_POST['visible'];
    
    $stmt = $db->prepare("UPDATE services SET visible = ? WHERE id = ?");
    $stmt->bind_param("ii", $visible, $service_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => $visible ? t('service_shown') : t('service_hidden'),
            'visible' => $visible
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('update_failed')]);
    }
}

function importService() {
    global $db;
    
    $api_service_id = (int)$_POST['api_service_id'];
    
    // Fetch service details from API
    $api_url = SMMGUO_API_URL;
    $api_key = SMMGUO_API_KEY;
    
    $post_data = [
        'key' => $api_key,
        'action' => 'services'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $api_services = json_decode($response, true);
    
    if (!$api_services) {
        echo json_encode(['success' => false, 'message' => t('api_error')]);
        return;
    }
    
    // Find the specific service
    $service_to_import = null;
    foreach ($api_services as $service) {
        if ($service['service'] == $api_service_id) {
            $service_to_import = $service;
            break;
        }
    }
    
    if (!$service_to_import) {
        echo json_encode(['success' => false, 'message' => t('service_not_found')]);
        return;
    }
    
    // Insert into local database
    $stmt = $db->prepare("INSERT INTO services (api_service_id, name, description, category, price_usd, price_tzs, visible) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $price_tzs = $service_to_import['rate'] * 2500; // Convert USD to TZS (approximate)
    $stmt->bind_param("isssdd", 
        $service_to_import['service'],
        $service_to_import['name'],
        $service_to_import['description'] ?? '',
        $service_to_import['category'],
        $service_to_import['rate'],
        $price_tzs
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => t('service_imported'),
            'service' => [
                'id' => $db->insert_id,
                'name' => $service_to_import['name'],
                'description' => $service_to_import['description'] ?? '',
                'price_usd' => $service_to_import['rate'],
                'price_tzs' => $price_tzs
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('import_failed')]);
    }
}

function bulkVisibilityUpdate() {
    global $db;
    
    $bulk_action = $_POST['bulk_visibility'];
    
    if ($bulk_action === 'show_all') {
        $visible = 1;
    } elseif ($bulk_action === 'hide_all') {
        $visible = 0;
    } else {
        echo json_encode(['success' => false, 'message' => t('invalid_action')]);
        return;
    }
    
    $stmt = $db->prepare("UPDATE services SET visible = ?");
    $stmt->bind_param("i", $visible);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        echo json_encode([
            'success' => true, 
            'message' => $bulk_action === 'show_all' ? t('all_services_shown') : t('all_services_hidden'),
            'affected_rows' => $affected_rows,
            'visible' => $visible
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('bulk_update_failed')]);
    }
}

function updateUser() {
    global $db;
    
    $user_id = (int)$_POST['user_id'];
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $balance = (float)($_POST['balance'] ?? 0);
    
    if (empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => t('username_email_required')]);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, balance = ? WHERE id = ?");
    $stmt->bind_param("ssdi", $username, $email, $balance, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => t('user_updated'),
            'data' => [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'balance' => $balance
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('update_failed')]);
    }
}

function deleteUser() {
    global $db;
    
    $user_id = (int)$_POST['user_id'];
    
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => t('user_deleted'),
            'user_id' => $user_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('delete_failed')]);
    }
}

function updateProfile() {
    global $db;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => t('username_email_required')]);
        return;
    }
    
    // Check if username/email already exists for other users
    $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => t('username_email_exists')]);
        return;
    }
    
    // If password change is requested
    if (!empty($new_password)) {
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!password_verify($current_password, $result['password'])) {
            echo json_encode(['success' => false, 'message' => t('current_password_incorrect')]);
            return;
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssi", $username, $email, $hashed_password, $user_id);
    } else {
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $email, $user_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => t('profile_updated'),
            'data' => [
                'username' => $username,
                'email' => $email
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('update_failed')]);
    }
}

function placeOrder() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $service_id = (int)$_POST['service_id'];
    $quantity = (int)$_POST['quantity'];
    $link = $_POST['link'] ?? '';
    
    if (empty($service_id) || empty($quantity) || empty($link)) {
        echo json_encode(['success' => false, 'message' => t('all_fields_required')]);
        return;
    }
    
    // Get user balance
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get service details
    $stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND visible = 1");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => t('service_not_found')]);
        return;
    }
    
    $currency = $_SESSION['currency'] ?? 'usd';
    $price = $currency === 'tzs' ? $service['price_tzs'] : $service['price_usd'];
    $total_cost = $price * $quantity;
    
    if ($user['balance'] < $total_cost) {
        echo json_encode(['success' => false, 'message' => t('insufficient_balance')]);
        return;
    }
    
    // Use multi-provider API with automatic failover
    require_once 'MultiProviderApi.php';
    $multiApi = new MultiProviderApi();
    
    $result = $multiApi->placeOrder($service_id, $link, $quantity, $user_id);
    
    if ($result['success']) {
        // Deduct balance
        $new_balance = $user['balance'] - $total_cost;
        $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->bind_param("di", $new_balance, $user_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => t('order_placed_successfully') . " (Provider: {$result['provider']})",
            'data' => [
                'order_id' => $result['order_id'],
                'api_order_id' => $result['api_order_id'],
                'provider' => $result['provider'],
                'new_balance' => $new_balance,
                'total_cost' => $total_cost
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
}

function refreshOrderStatus() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $order_id = (int)$_POST['order_id'];
    
    // Get order details
    $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => t('order_not_found')]);
        return;
    }
    
    // Use multi-provider API to get status
    require_once 'MultiProviderApi.php';
    $multiApi = new MultiProviderApi();
    
    $result = $multiApi->getOrderStatus($order_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => t('status_updated'),
            'data' => [
                'order_id' => $order_id,
                'old_status' => $order['status'],
                'new_status' => $result['status']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
}

function addFunds() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'] ?? '';
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => t('invalid_amount')]);
        return;
    }
    
    // For demo purposes, we'll just add the funds directly
    // In a real application, you'd integrate with a payment processor
    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $amount, $user_id);
    
    if ($stmt->execute()) {
        // Get new balance
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $new_balance = $stmt->get_result()->fetch_assoc()['balance'];
        
        echo json_encode([
            'success' => true,
            'message' => t('funds_added_successfully'),
            'data' => [
                'amount_added' => $amount,
                'new_balance' => $new_balance
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('funds_add_failed')]);
    }
}

function submitSupportTicket() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => t('subject_message_required')]);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, message, status, created_at) VALUES (?, ?, ?, 'open', NOW())");
    $stmt->bind_param("iss", $user_id, $subject, $message);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => t('ticket_submitted_successfully'),
            'data' => [
                'ticket_id' => $db->insert_id
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('ticket_submit_failed')]);
    }
}

function replySupportTicket() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $ticket_id = (int)$_POST['ticket_id'];
    $message = $_POST['message'] ?? '';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => t('message_required')]);
        return;
    }
    
    // Verify ticket belongs to user
    $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ticket_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => t('ticket_not_found')]);
        return;
    }
    
    // Add reply
    $stmt = $db->prepare("INSERT INTO support_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $ticket_id, $user_id, $message);
    
    if ($stmt->execute()) {
        // Update ticket status
        $stmt = $db->prepare("UPDATE support_tickets SET status = 'awaiting_response', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => t('reply_added_successfully')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('reply_failed')]);
    }
}

function addProvider() {
    global $db;
    
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $name = $_POST['provider_name'] ?? '';
    $apiUrl = $_POST['api_url'] ?? '';
    $apiKey = $_POST['api_key'] ?? '';
    $priority = (int)($_POST['priority'] ?? 1);
    
    if (empty($name) || empty($apiUrl) || empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    require_once 'MultiProviderApi.php';
    $multiApi = new MultiProviderApi();
    
    if ($multiApi->addProvider($name, $apiUrl, $apiKey, $priority)) {
        echo json_encode([
            'success' => true,
            'message' => 'Provider added successfully',
            'data' => [
                'name' => $name,
                'api_url' => $apiUrl,
                'priority' => $priority
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add provider']);
    }
}

function toggleProviderStatus() {
    global $db;
    
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $providerId = (int)$_POST['provider_id'];
    $currentStatus = $_POST['current_status'] ?? '';
    
    $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
    
    require_once 'MultiProviderApi.php';
    $multiApi = new MultiProviderApi();
    
    if ($multiApi->updateProviderStatus($providerId, $newStatus)) {
        echo json_encode([
            'success' => true,
            'message' => "Provider {$newStatus}",
            'data' => [
                'provider_id' => $providerId,
                'new_status' => $newStatus
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update provider status']);
    }
}

function deleteProvider() {
    global $db;
    
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $providerId = (int)$_POST['provider_id'];
    
    // Check if provider has active services
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE provider_id = ?");
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $serviceCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($serviceCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete provider with {$serviceCount} associated services. Please remove services first."
        ]);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM api_providers WHERE id = ?");
    $stmt->bind_param("i", $providerId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Provider deleted successfully',
            'provider_id' => $providerId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete provider']);
    }
}

function syncProviderServices() {
    global $db;
    
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $providerId = (int)$_POST['provider_id'];
    
    require_once 'MultiProviderApi.php';
    $multiApi = new MultiProviderApi();
    
    $result = $multiApi->importServicesFromProvider($providerId);
    
    if ($result['success']) {
        $message = "Sync completed: {$result['imported']} imported, {$result['updated']} updated";
        if (!empty($result['errors'])) {
            $message .= ". Errors: " . count($result['errors']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $result
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
}

function testProviderConnection() {
    global $db;
    
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $providerId = (int)$_POST['provider_id'];
    
    // Get provider details
    $stmt = $db->prepare("SELECT * FROM api_providers WHERE id = ?");
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        return;
    }
    
    // Test connection by trying to get balance
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'key' => $provider['api_key'],
        'action' => 'balance'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $responseTime = (microtime(true) - $startTime) * 1000;
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response !== false) {
        $data = json_decode($response, true);
        if (isset($data['balance']) || isset($data['error'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'response_time' => round($responseTime, 2) . 'ms',
                    'balance' => $data['balance'] ?? 'N/A'
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid API response']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Connection failed']);
    }
}
?>
