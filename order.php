<?php
require_once 'config.php';
require_once 'ApiClient.php';
require_once 'CurrencyManager.php';

$currencyManager = new CurrencyManager();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$user = $db->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
// Fetch only visible services from local DB
$services = $db->query("SELECT * FROM services WHERE visible = 1")->fetch_all(MYSQLI_ASSOC);
// Map api_service_id to local service for lookup
$service_map = [];
foreach ($services as $s) {
    $service_map[$s['api_service_id']] = $s;
}

// Currency toggle logic
$currency = $_GET['currency'] ?? $_SESSION['currency'] ?? 'usd';
if (isset($_GET['currency'])) {
    $_SESSION['currency'] = $currency;
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

// Build category list for filter
$categories = array_unique(array_filter(array_map(function($s) { return $s['category'] ?? null; }, $services)));
sort($categories);
$selected_category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
// Filter services by category and search
$filtered_services = array_filter($services, function($s) use ($selected_category, $search) {
    $cat = $s['category'] ?? '';
    $name = strtolower($s['name'] ?? '');
    $desc = strtolower($s['description'] ?? '');
    $search = strtolower($search);
    $cat_match = !$selected_category || $cat === $selected_category;
    $search_match = !$search || strpos($name, $search) !== false || strpos($desc, $search) !== false;
    return $cat_match && $search_match;
});

if (
    $_SERVER['REQUEST_METHOD'] == 'POST' &&
    isset($_POST['service'], $_POST['quantity'], $_POST['link'])
) {
    $local_service_id = (int)$_POST['service'];
    $quantity = (int)$_POST['quantity'];
    $link = $db->real_escape_string($_POST['link']);

    // Get service info from local DB
    $stmt = $db->prepare("SELECT s.*, p.api_url, p.api_key FROM services s LEFT JOIN api_providers p ON s.provider_id = p.id WHERE s.id = ?");
    $stmt->bind_param('i', $local_service_id);
    $stmt->execute();
    $service_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($service_info) {
        try {
            // Calculate total price
            $total_price = $currencyManager->calculateOrderCost($local_service_id, $quantity, $currency);

            // Check user balance
            if (!$currencyManager->hasSufficientBalance($user_id, $total_price, $currency)) {
                throw new Exception('Insufficient balance.');
            }

            $provider_order_id = null;
            // If service is from a provider, place order via API
            if ($service_info['provider_id'] && $service_info['api_url'] && $service_info['api_key']) {
                $apiClient = new ApiClient($service_info['api_url'], $service_info['api_key']);
                
                $order_params = [
                    'service' => $service_info['service_id'], // Use the provider's service ID
                    'link'    => $link,
                    'quantity'=> $quantity
                ];

                // Add other potential parameters if they exist in POST
                if (isset($_POST['comments'])) $order_params['comments'] = $_POST['comments'];
                if (isset($_POST['runs'])) $order_params['runs'] = $_POST['runs'];
                if (isset($_POST['interval'])) $order_params['interval'] = $_POST['interval'];

                $api_response = $apiClient->order($order_params);

                if (isset($api_response['order'])) {
                    $provider_order_id = $api_response['order'];
                } else {
                    throw new Exception('API Order Failed: ' . ($api_response['error'] ?? 'Unknown error from provider.'));
                }
            }

            // Insert order record into local DB
            $status = 'pending'; // Initial status for all orders
            $stmt = $db->prepare("INSERT INTO orders (user_id, service_id, quantity, link, provider_order_id, status, charge, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("DB Error: " . $db->error);
            $stmt->bind_param('iiisssds', $user_id, $local_service_id, $quantity, $link, $provider_order_id, $status, $total_price, $currency);
            $stmt->execute();
            $db_order_id = $stmt->insert_id;
            $stmt->close();

            // Deduct balance
            $deduction_result = $currencyManager->deductBalance($user_id, $total_price, $currency, $db_order_id);

            if ($deduction_result) {
                $success = "Order placed successfully! Your Order ID: $db_order_id";
            } else {
                $error = "Order placed, but failed to update balance. Please contact support.";
                // If deduction failed, we should attempt to cancel the external order if possible, or flag for admin review.
                // For now, just delete the local order to prevent user confusion.
                $db->query("DELETE FROM orders WHERE id = $db_order_id");
            }

        } catch (Exception $e) {
            $error = "Error processing order: " . $e->getMessage();
        }
    } else {
        $error = 'Service not found.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - Sirtech SMM</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-dark: #5649c0;
            --secondary: #00cec9;
            --dark: #2d3436;
            --light: #f5f6fa;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
            --info: #0984e3;
            --card-bg: rgba(255, 255, 255, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: var(--light);
            min-height: 100vh;
        }

        /* Header */
        .main-header {
            background: rgba(26, 26, 46, 0.9);
            backdrop-filter: blur(10px);
            padding: 0.8rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand-logo i {
            font-size: 1.4rem;
            color: var(--primary);
        }

        .brand-logo h1 {
            font-size: 1.2rem;
            font-weight: 700;
            background: linear-gradient(to right, #6c5ce7, #00cec9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0;
        }

        /* Main Container */
        .main-container {
            padding-top: 70px;
            min-height: 100vh;
        }

        /* Order Form */
        .order-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .order-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .order-title i {
            color: var(--primary);
        }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Order Form */
        .order-form {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Service Info Card */
        .service-info-card {
            background: rgba(9, 132, 227, 0.1);
            border-left: 4px solid var(--info);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .service-info-card h5 {
            color: white;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .service-info-card p {
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .service-info-card strong {
            color: white;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 92, 231, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .alert-danger {
            background: rgba(214, 48, 49, 0.2);
            border-left: 4px solid var(--danger);
            color: #ff6b6b;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.2);
            border-left: 4px solid var(--success);
            color: #55efc4;
        }

        /* Language Selector */
        .language-selector {
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Responsive Styles */
        @media (min-width: 576px) {
            .filter-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .order-title {
                font-size: 2rem;
            }
        }

        @media (min-width: 768px) {
            .order-container {
                padding: 2rem;
            }
            
            .filter-row {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .action-buttons {
                flex-wrap: nowrap;
            }
        }

        @media (min-width: 992px) {
            .order-container {
                padding: 2.5rem;
            }
            
            .filter-row {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="brand-logo">
            <i class="fas fa-shopping-cart"></i>
            <h1>Sirtech SMM</h1>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <div class="order-container">
            <h1 class="order-title fade-in">
                <i class="fas fa-cart-plus"></i> <?php echo t('place_order'); ?>
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section fade-in delay-1">
                <form method="get">
                    <div class="filter-row">
                        <div class="form-group">
                            <label for="category" class="form-label"><?php echo t('category'); ?></label>
                            <select name="category" id="category" class="form-select" onchange="this.form.submit()">
                                <option value=""><?php echo t('all'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($selected_category===$cat) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="search" class="form-label"><?php echo t('search'); ?></label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="<?php echo t('search_placeholder'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="currency" class="form-label"><?php echo t('currency'); ?></label>
                            <select name="currency" id="currency" class="form-select" onchange="this.form.submit()">
                                <option value="usd" <?php if($currency==='usd') echo 'selected'; ?>>USD</option>
                                <option value="tzs" <?php if($currency==='tzs') echo 'selected'; ?>>TZS</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="lang" class="form-label"><?php echo t('language'); ?></label>
                            <select name="lang" id="lang" class="form-select" onchange="this.form.submit()">
                                <option value="en" <?php if($lang==='en') echo 'selected'; ?>>English</option>
                                <option value="sw" <?php if($lang==='sw') echo 'selected'; ?>>Swahili</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="d-none">Apply Filters</button>
                </form>
            </div>
            
            <!-- Order Form -->
            <form class="order-form fade-in delay-2">
                <div class="form-group mb-4">
                    <label for="service" class="form-label"><?php echo t('service'); ?></label>
                    <select name="service_id" id="service" class="form-control" required onchange="showServiceInfo(this.value)">
                        <option value=""><?php echo t('select_service'); ?></option>
                        <?php foreach ($filtered_services as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-rate="<?php echo $rate; ?>" data-min="<?php echo $s['min']; ?>" data-max="<?php echo $s['max']; ?>" data-description="<?php echo htmlspecialchars($s['description'] ?? 'No description available.'); ?>">
                            >
                                <?php if($currency==='tzs'): ?>
                                    <?php echo htmlspecialchars(($s['name'] ?? 'Unknown') . ' - TZS ' . ($s['price_tzs'] ?? 'N/A') . '/1000'); ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars(($s['name'] ?? 'Unknown') . ' - $' . ($s['price_usd'] ?? 'N/A') . '/1000'); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Service Info Card -->
                <div id="service-info" class="service-info-card">
                    <h5><span id="info-name"></span></h5>
                    <p><strong><?php echo t('description'); ?>:</strong> <span id="info-description"></span></p>
                    <p><strong><?php echo t('rate'); ?>:</strong> <span id="info-rate"></span></p>
                    <p><strong><?php echo t('min'); ?>:</strong> <span id="info-min"></span> | <strong><?php echo t('max'); ?>:</strong> <span id="info-max"></span></p>
                    <p><strong><?php echo t('instructions'); ?>:</strong> <span id="info-instructions"></span></p>
                </div>
                
                <div class="form-group mb-4">
                    <label for="quantity" class="form-label"><?php echo t('quantity'); ?></label>
                    <input type="number" name="quantity" id="quantity" class="form-control" required>
                    <small class="text-muted"><?php echo t('quantity_hint'); ?></small>
                </div>
                
                <div class="form-group mb-4">
                    <label for="link" class="form-label"><?php echo t('link'); ?></label>
                    <input type="text" name="link" id="link" class="form-control" required>
                    <small class="text-muted"><?php echo t('link_hint'); ?></small>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> <?php echo t('place_order'); ?>
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo t('back'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        function showServiceInfo(val) {
            const select = document.getElementById('service');
            const option = select.options[select.selectedIndex];
            const serviceInfo = document.getElementById('service-info');
            
            if (!val) {
                serviceInfo.style.display = 'none';
                return;
            }
            
            // Update service info card
            document.getElementById('info-name').textContent = option.getAttribute('data-name');
            document.getElementById('info-description').textContent = option.getAttribute('data-description') || 'N/A';
            
            const rate = '<?php echo $currency; ?>' === 'usd' 
                ? '$' + option.getAttribute('data-price_usd') 
                : 'TZS ' + option.getAttribute('data-price_tzs');
            document.getElementById('info-rate').textContent = rate + ' per 1000';
            
            document.getElementById('info-min').textContent = option.getAttribute('data-min') || 'N/A';
            document.getElementById('info-max').textContent = option.getAttribute('data-max') || 'N/A';
            document.getElementById('info-instructions').textContent = option.getAttribute('data-instructions') || 'N/A';
            
            // Show the card with animation
            serviceInfo.style.display = 'block';
            serviceInfo.style.animation = 'fadeIn 0.3s ease forwards';
        }
        
        // Auto-submit search when user stops typing
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
        
        // Show service info if one is already selected on page load
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('service');
            if (select && select.value) {
                showServiceInfo(select.value);
            }
        });
    </script>
    <script src="js/user-ajax.js"></script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>