<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';
require_once 'MultiProviderApi.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add_provider':
        addProvider();
        break;
    case 'edit_provider':
        editProvider();
        break;
    case 'get_provider':
        getProvider();
        break;
    case 'sync_services':
        syncServices();
        break;
    case 'toggle_provider':
        toggleProvider();
        break;
    case 'delete_provider':
        deleteProvider();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}

function syncServices() {
    global $db;
    try {
        $provider_id = (int)($_POST['provider_id'] ?? 0);
        if ($provider_id === 0) {
            throw new Exception('Invalid Provider ID.');
        }

        $provider_stmt = $db->prepare("SELECT * FROM api_providers WHERE id = ?");
        $provider_stmt->bind_param('i', $provider_id);
        $provider_stmt->execute();
        $provider_result = $provider_stmt->get_result();
        $provider = $provider_result->fetch_assoc();

        if (!$provider) {
            throw new Exception('Provider not found.');
        }

        // Use the correct ApiClient that uses the 'connect' method
        $apiClient = new MultiProviderApi($provider['api_url'], $provider['api_key']);
        $services = $apiClient->services();

        if (!is_array($services) || isset($services['error'])) {
            $error_message = isset($services['error']) ? $services['error'] : 'Invalid or empty response from provider.';
            throw new Exception('API Error: ' . $error_message);
        }

        $imported_count = 0;
        $updated_count = 0;
        $db->begin_transaction();

        foreach ($services as $service) {
            if (empty($service['service']) || empty($service['name']) || !isset($service['rate'])) {
                continue; 
            }

            $stmt = $db->prepare("SELECT id FROM services WHERE provider_id = ? AND provider_service_id = ?");
            $stmt->bind_param('is', $provider_id, $service['service']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $category = $service['category'] ?? 'Default';
            $type = $service['type'] ?? 'Default';
            $min = $service['min'] ?? 0;
            $max = $service['max'] ?? 10000;


            if ($result->num_rows > 0) {
                $update_stmt = $db->prepare("UPDATE services SET name = ?, category = ?, type = ?, rate = ?, min = ?, max = ?, updated_at = NOW() WHERE provider_id = ? AND provider_service_id = ?");
                $update_stmt->bind_param('sssdiisi', $service['name'], $category, $type, $service['rate'], $min, $max, $provider_id, $service['service']);
                $update_stmt->execute();
                $updated_count++;
            } else {
                $insert_stmt = $db->prepare("INSERT INTO services (provider_id, provider_service_id, name, category, type, rate, min, max) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param('issssdis', $provider_id, $service['service'], $service['name'], $category, $type, $service['rate'], $min, $max);
                $insert_stmt->execute();
                $imported_count++;
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => "Sync complete. Imported: $imported_count, Updated: $updated_count"]);

    } catch (Exception $e) {
        if ($db->in_transaction) {
            $db->rollback();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// --- Placeholder Functions ---
// These need to be re-implemented correctly based on existing logic.

function addProvider() {
    echo json_encode(['success' => false, 'message' => 'Add provider function not fully implemented.']);
}

function editProvider() {
    echo json_encode(['success' => false, 'message' => 'Edit provider function not fully implemented.']);
}

function getProvider() {
    global $db;
    $id = (int)($_GET['id'] ?? 0);
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Provider ID required']);
        return;
    }
    $stmt = $db->prepare("SELECT * FROM api_providers WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($provider = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'provider' => $provider]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
    }
}

function toggleProvider() {
    echo json_encode(['success' => false, 'message' => 'Toggle provider function not fully implemented.']);
}

function deleteProvider() {
    echo json_encode(['success' => false, 'message' => 'Delete provider function not fully implemented.']);
}
