<?php
/**
 * Multi-Provider API Management System
 * Handles multiple SMM API providers with automatic failover and load balancing
 */

class MultiProviderApi {
    private $db;
    private $providers = [];
    private $currentProvider = null;
    
    public function __construct($database = null) {
        global $db;
        $this->db = $database ?: $db;
        $this->loadProviders();
    }
    
    /**
     * Load active providers from database ordered by priority
     */
    private function loadProviders() {
        $result = $this->db->query("
            SELECT * FROM api_providers 
            WHERE status = 'active' 
            ORDER BY priority ASC, success_rate DESC
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->providers[] = $row;
            }
        }
        
        if (!empty($this->providers)) {
            $this->currentProvider = $this->providers[0];
        }
    }
    
    /**
     * Add a new API provider
     */
    public function addProvider($name, $apiUrl, $apiKey, $priority = 1) {
        $stmt = $this->db->prepare("
            INSERT INTO api_providers (name, api_url, api_key, priority, status) 
            VALUES (?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE 
                api_url = VALUES(api_url),
                api_key = VALUES(api_key),
                priority = VALUES(priority),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->bind_param("sssi", $name, $apiUrl, $apiKey, $priority);
        return $stmt->execute();
    }
    
    /**
     * Update provider status
     */
    public function updateProviderStatus($providerId, $status) {
        $stmt = $this->db->prepare("
            UPDATE api_providers 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->bind_param("si", $status, $providerId);
        return $stmt->execute();
    }
    
    /**
     * Get all providers
     */
    public function getProviders() {
        return $this->providers;
    }
    
    /**
     * Get services from a specific provider
     */
    public function getServicesFromProvider($providerId) {
        $provider = $this->getProviderById($providerId);
        if (!$provider) {
            return false;
        }
        
        $startTime = microtime(true);
        $response = $this->makeApiCall($provider, ['action' => 'services']);
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        if ($response) {
            $this->updateProviderPerformance($providerId, true, $responseTime);
            return json_decode($response, true);
        } else {
            $this->updateProviderPerformance($providerId, false, $responseTime);
            return false;
        }
    }
    
    /**
     * Import services from a provider
     */
    public function importServicesFromProvider($providerId) {
        $services = $this->getServicesFromProvider($providerId);
        if (!$services) {
            return ['success' => false, 'message' => 'Failed to fetch services from provider'];
        }
        
        $imported = 0;
        $updated = 0;
        $errors = [];
        
        foreach ($services as $service) {
            try {
                // Check if service already exists for this provider
                $stmt = $this->db->prepare("
                    SELECT id FROM services 
                    WHERE provider_id = ? AND provider_service_id = ?
                ");
                $stmt->bind_param("is", $providerId, $service['service']);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                
                if ($existing) {
                    // Update existing service
                    $stmt = $this->db->prepare("
                        UPDATE services SET 
                            name = ?, 
                            description = ?, 
                            category = ?,
                            price_usd = ?, 
                            price_tzs = ?,
                            min_quantity = ?,
                            max_quantity = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    
                    $priceTzs = ($service['rate'] ?? 0) * 2500; // Convert USD to TZS
                    $minQty = $service['min'] ?? 1;
                    $maxQty = $service['max'] ?? 100000;
                    
                    $stmt->bind_param("sssddiis", 
                        $service['name'],
                        $service['description'] ?? '',
                        $service['category'] ?? 'General',
                        $service['rate'] ?? 0,
                        $priceTzs,
                        $minQty,
                        $maxQty,
                        $existing['id']
                    );
                    
                    if ($stmt->execute()) {
                        $updated++;
                    }
                } else {
                    // Insert new service
                    $stmt = $this->db->prepare("
                        INSERT INTO services (
                            provider_id, provider_service_id, api_service_id, name, description, 
                            category, price_usd, price_tzs, min_quantity, max_quantity, visible
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    
                    $priceTzs = ($service['rate'] ?? 0) * 2500;
                    $minQty = $service['min'] ?? 1;
                    $maxQty = $service['max'] ?? 100000;
                    
                    $stmt->bind_param("isisssdiii", 
                        $providerId,
                        $service['service'],
                        $service['service'], // Keep backward compatibility
                        $service['name'],
                        $service['description'] ?? '',
                        $service['category'] ?? 'General',
                        $service['rate'] ?? 0,
                        $priceTzs,
                        $minQty,
                        $maxQty
                    );
                    
                    if ($stmt->execute()) {
                        $imported++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Service {$service['name']}: " . $e->getMessage();
            }
        }
        
        // Log the sync
        $this->logServiceSync($providerId, 'manual', $imported, $updated, implode('; ', $errors));
        
        return [
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ];
    }
    
    /**
     * Place an order with automatic failover
     */
    public function placeOrder($serviceId, $link, $quantity, $userId) {
        // Get service details
        $stmt = $this->db->prepare("
            SELECT s.*, p.name as provider_name 
            FROM services s 
            JOIN api_providers p ON s.provider_id = p.id 
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();
        
        if (!$service) {
            return ['success' => false, 'message' => 'Service not found'];
        }
        
        $originalProviderId = $service['provider_id'];
        $providers = $this->getActiveProviders();
        
        // Try primary provider first
        foreach ($providers as $provider) {
            if ($provider['id'] == $originalProviderId) {
                $result = $this->attemptOrder($provider, $service, $link, $quantity, $userId);
                if ($result['success']) {
                    return $result;
                }
                
                // Log the failure and try backup providers
                $this->logProviderFailure($provider['id'], $result['message']);
                break;
            }
        }
        
        // Try backup providers
        $backupProviders = $this->getBackupProviders($serviceId);
        foreach ($backupProviders as $backupProvider) {
            $backupService = $this->findEquivalentService($backupProvider['backup_provider_id'], $service);
            if ($backupService) {
                $provider = $this->getProviderById($backupProvider['backup_provider_id']);
                $result = $this->attemptOrder($provider, $backupService, $link, $quantity, $userId);
                
                if ($result['success']) {
                    // Log successful failover
                    $this->logFailover($result['order_id'], $originalProviderId, $provider['id'], 'Primary provider failed');
                    return $result;
                }
            }
        }
        
        return ['success' => false, 'message' => 'All providers failed to process the order'];
    }
    
    /**
     * Attempt to place order with specific provider
     */
    private function attemptOrder($provider, $service, $link, $quantity, $userId) {
        $startTime = microtime(true);
        
        $postData = [
            'key' => $provider['api_key'],
            'action' => 'add',
            'service' => $service['provider_service_id'],
            'link' => $link,
            'quantity' => $quantity
        ];
        
        $response = $this->makeApiCall($provider, $postData);
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        if ($response) {
            $result = json_decode($response, true);
            
            if (isset($result['order'])) {
                // Calculate charge
                $currency = $_SESSION['currency'] ?? 'usd';
                $price = $currency === 'tzs' ? $service['price_tzs'] : $service['price_usd'];
                $totalCharge = $price * $quantity;
                
                // Save order to database
                $stmt = $this->db->prepare("
                    INSERT INTO orders (user_id, service, quantity, link, charge, status, api_order_id, provider_id) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmt->bind_param("isisssi", $userId, $service['name'], $quantity, $link, $totalCharge, $result['order'], $provider['id']);
                $stmt->execute();
                $orderId = $this->db->insert_id;
                
                $this->updateProviderPerformance($provider['id'], true, $responseTime);
                
                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'api_order_id' => $result['order'],
                    'provider' => $provider['name'],
                    'charge' => $totalCharge
                ];
            }
        }
        
        $this->updateProviderPerformance($provider['id'], false, $responseTime);
        return ['success' => false, 'message' => 'Provider API call failed'];
    }
    
    /**
     * Get order status with provider failover
     */
    public function getOrderStatus($orderId) {
        // Get order details
        $stmt = $this->db->prepare("
            SELECT o.*, p.* 
            FROM orders o 
            JOIN api_providers p ON o.provider_id = p.id 
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order || !$order['api_order_id']) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        $postData = [
            'key' => $order['api_key'],
            'action' => 'status',
            'order' => $order['api_order_id']
        ];
        
        $response = $this->makeApiCall($order, $postData);
        
        if ($response) {
            $result = json_decode($response, true);
            if (isset($result['status'])) {
                // Update order status in database
                $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $result['status'], $orderId);
                $stmt->execute();
                
                return [
                    'success' => true,
                    'status' => $result['status'],
                    'start_count' => $result['start_count'] ?? null,
                    'remains' => $result['remains'] ?? null
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to get order status'];
    }
    
    /**
     * Make API call to provider
     */
    private function makeApiCall($provider, $postData) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode == 200 && $response !== false) ? $response : false;
    }
    
    /**
     * Helper methods
     */
    private function getProviderById($id) {
        foreach ($this->providers as $provider) {
            if ($provider['id'] == $id) {
                return $provider;
            }
        }
        return null;
    }
    
    private function getActiveProviders() {
        return $this->providers;
    }
    
    private function getBackupProviders($serviceId) {
        $stmt = $this->db->prepare("
            SELECT * FROM provider_service_mapping 
            WHERE primary_service_id = ? 
            ORDER BY priority ASC
        ");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function findEquivalentService($providerId, $originalService) {
        $stmt = $this->db->prepare("
            SELECT * FROM services 
            WHERE provider_id = ? AND (
                name LIKE ? OR 
                category = ? OR 
                provider_service_id = ?
            )
            ORDER BY 
                CASE WHEN name = ? THEN 1 ELSE 2 END,
                CASE WHEN category = ? THEN 1 ELSE 2 END
            LIMIT 1
        ");
        
        $nameLike = '%' . $originalService['name'] . '%';
        $stmt->bind_param("isssss", 
            $providerId, 
            $nameLike, 
            $originalService['category'],
            $originalService['provider_service_id'],
            $originalService['name'],
            $originalService['category']
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function updateProviderPerformance($providerId, $success, $responseTime) {
        $date = date('Y-m-d');
        
        // Get or create today's performance record
        $stmt = $this->db->prepare("
            INSERT INTO provider_performance (provider_id, date_recorded, total_orders, successful_orders, failed_orders, avg_response_time)
            VALUES (?, ?, 1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_orders = total_orders + 1,
                successful_orders = successful_orders + ?,
                failed_orders = failed_orders + ?,
                avg_response_time = (avg_response_time * (total_orders - 1) + ?) / total_orders
        ");
        
        $successCount = $success ? 1 : 0;
        $failCount = $success ? 0 : 1;
        
        $stmt->bind_param("isiidiidd", 
            $providerId, $date, $successCount, $failCount, $responseTime,
            $successCount, $failCount, $responseTime
        );
        $stmt->execute();
        
        // Update provider success rate
        $stmt = $this->db->prepare("
            UPDATE api_providers SET 
                success_rate = (
                    SELECT (SUM(successful_orders) * 100.0 / SUM(total_orders)) 
                    FROM provider_performance 
                    WHERE provider_id = ? AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ),
                last_check = CURRENT_TIMESTAMP,
                " . ($success ? "last_success = CURRENT_TIMESTAMP" : "last_failure = CURRENT_TIMESTAMP") . "
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $providerId, $providerId);
        $stmt->execute();
    }
    
    private function logFailover($orderId, $originalProviderId, $fallbackProviderId, $reason) {
        $stmt = $this->db->prepare("
            INSERT INTO provider_failover_logs (order_id, original_provider_id, fallback_provider_id, reason)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiis", $orderId, $originalProviderId, $fallbackProviderId, $reason);
        $stmt->execute();
    }
    
    private function logProviderFailure($providerId, $reason) {
        // This could be expanded to implement more sophisticated failure tracking
        error_log("Provider {$providerId} failed: {$reason}");
    }
    
    private function logServiceSync($providerId, $syncType, $imported, $updated, $errors) {
        $stmt = $this->db->prepare("
            INSERT INTO service_sync_logs (provider_id, sync_type, services_imported, services_updated, errors)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiis", $providerId, $syncType, $imported, $updated, $errors);
        $stmt->execute();
    }
}
?>
