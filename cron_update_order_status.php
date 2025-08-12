<?php
// This script should be run via a cron job (e.g., every 5 minutes)
require_once 'config.php';
require_once 'ApiClient.php';

// Function to log messages
function log_message($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}

log_message("Cron job started: Updating order statuses.");

// Fetch all non-final orders that have a provider order ID
$sql = "SELECT o.id as local_order_id, o.provider_order_id, p.id as provider_id, p.api_url, p.api_key
        FROM orders o
        JOIN services s ON o.service_id = s.id
        JOIN api_providers p ON s.provider_id = p.id
        WHERE o.provider_order_id IS NOT NULL
        AND o.status NOT IN ('completed', 'cancelled', 'refunded', 'error')";

$result = $db->query($sql);

if (!$result) {
    log_message("Error fetching pending orders: " . $db->error);
    exit;
}

$orders_by_provider = [];
while ($row = $result->fetch_assoc()) {
    $orders_by_provider[$row['provider_id']]['api_url'] = $row['api_url'];
    $orders_by_provider[$row['provider_id']]['api_key'] = $row['api_key'];
    $orders_by_provider[$row['provider_id']]['orders'][$row['local_order_id']] = $row['provider_order_id'];
}

if (empty($orders_by_provider)) {
    log_message("No pending orders from providers to update.");
    exit;
}

log_message("Found " . count($orders_by_provider) . " provider(s) with pending orders.");

// Process orders for each provider
foreach ($orders_by_provider as $provider_id => $provider_data) {
    $apiClient = new ApiClient($provider_data['api_url'], $provider_data['api_key']);
    $provider_order_ids = array_values($provider_data['orders']);

    try {
        log_message("Checking status for " . count($provider_order_ids) . " order(s) from provider ID: $provider_id");
        $statuses = $apiClient->multiStatus($provider_order_ids);

        if (is_array($statuses)) {
            foreach ($statuses as $provider_order_id => $status_data) {
                $local_order_id = array_search($provider_order_id, $provider_data['orders']);
                if ($local_order_id && isset($status_data['status'])) {
                    $new_status = $db->real_escape_string($status_data['status']);
                    $charge = $status_data['charge'] ?? null;
                    $remains = $status_data['remains'] ?? null;

                    $update_sql = "UPDATE orders SET status = '$new_status'";
                    if ($charge !== null) {
                        $update_sql .= ", charge = '" . (float)$charge . "'";
                    }
                    // You might want to add 'remains' to your orders table as well
                    // $update_sql .= ", remains = '$remains'";
                    $update_sql .= " WHERE id = $local_order_id";

                    if ($db->query($update_sql)) {
                        log_message("Updated order $local_order_id (Provider: $provider_order_id) to status: $new_status");
                    } else {
                        log_message("Failed to update order $local_order_id. DB Error: " . $db->error);
                    }
                }
            }
        }
    } catch (Exception $e) {
        log_message("Error checking statuses for provider $provider_id: " . $e->getMessage());
    }
}

log_message("Cron job finished.");
?>
