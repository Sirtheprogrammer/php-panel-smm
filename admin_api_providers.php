<?php
session_start();
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_providers,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_providers,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_providers,
        AVG(success_rate) as avg_success_rate
    FROM api_providers
")->fetch_assoc();



// Get pending payment requests count for navigation
$pending_count = $db->query("SELECT COUNT(*) as count FROM payment_requests WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Provider Management - SIRTECH SMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-light: #a29bfe;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .navbar {
            background: var(--primary) !important;
            box-shadow: 0 2px 10px rgba(108, 92, 231, 0.3);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 15px;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 10px;
        }
        
        .table thead th {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-chart-line me-2"></i>SIRTECH SMM - Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                <a class="nav-link" href="admin_payments.php">
                    <i class="fas fa-credit-card me-1"></i>Payments
                    <?php if ($pending_count > 0): ?>
                        <span class="badge bg-warning ms-1"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="admin_currency.php"><i class="fas fa-coins me-1"></i>Currency</a>
                <a class="nav-link" href="admin_support.php">
                    <i class="fas fa-headset me-1"></i>Support
                    <?php if ($unseen_user_tickets > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unseen_user_tickets; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">API Provider Management</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-server me-2"></i>API Provider Management</h1>
                <p class="text-muted mb-0">Manage your SMM API providers and services</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProviderModal">
                <i class="fas fa-plus me-2"></i>Add New Provider
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-server fa-2x mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['total_providers'] ?? 0; ?></h3>
                        <p class="mb-0">Total Providers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['active_providers'] ?? 0; ?></h3>
                        <p class="mb-0">Active Providers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-pause-circle fa-2x mb-3"></i>
                        <h3 class="mb-1"><?php echo $stats['inactive_providers'] ?? 0; ?></h3>
                        <p class="mb-0">Inactive Providers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x mb-3"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['avg_success_rate'] ?? 0, 1); ?>%</h3>
                        <p class="mb-0">Avg Success Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Providers Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>API Providers List</h5>
                <div>
                    <button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#importServicesModal">
                        <i class="fas fa-download me-1"></i>Import Services
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="location.reload()">
                        <i class="fas fa-refresh me-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>API URL</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Success Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $providers_result = $db->query("SELECT * FROM api_providers ORDER BY priority ASC");
                            if ($providers_result && $providers_result->num_rows > 0) {
                                $counter = 1;
                                while ($provider = $providers_result->fetch_assoc()) {
                                    $statusClass = $provider['status'] === 'active' ? 'success' : 'danger';
                                    $statusText = $provider['status'] === 'active' ? 'ACTIVE' : 'INACTIVE';
                                    echo "<tr>";
                                    echo "<td>{$counter}</td>";
                                    echo "<td><strong>{$provider['name']}</strong></td>";
                                    echo "<td><small class='text-muted'>" . substr($provider['api_url'], 0, 40) . "...</small></td>";
                                    echo "<td><span class='badge bg-{$statusClass}'>{$statusText}</span></td>";
                                    echo "<td><span class='badge bg-secondary'>{$provider['priority']}</span></td>";
                                    echo "<td>" . number_format($provider['success_rate'] ?? 0, 1) . "%</td>";
                                    echo "<td>";
                                    echo "<div class='btn-group'>";
                                    echo "<button class='btn btn-outline-primary btn-sm edit-provider-btn' data-provider-id='{$provider['id']}' data-bs-toggle='modal' data-bs-target='#editProviderModal'><i class='fas fa-edit'></i></button>";
                                    echo "<button class='btn btn-outline-info btn-sm sync-services-btn' data-provider-id='{$provider['id']}'><i class='fas fa-sync'></i></button>";
                                    echo "<button class='btn btn-outline-secondary btn-sm toggle-provider-btn' data-provider-id='{$provider['id']}' data-current-status='{$provider['status']}'><i class='fas fa-power-off'></i></button>";
                                    echo "<button class='btn btn-outline-danger btn-sm delete-provider-btn' data-provider-id='{$provider['id']}'><i class='fas fa-trash'></i></button>";
                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                    $counter++;
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center text-muted py-4'>";
                                echo "<i class='fas fa-server fa-3x mb-3 d-block'></i>";
                                echo "<h5>No API providers found</h5>";
                                echo "<p>Add your first API provider to get started</p>";
                                echo "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Provider Modal -->
    <div class="modal fade" id="addProviderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New API Provider</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form class="add-provider-form">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>This tool supports all v2 email-based API providers like SMMGUO, SubscriptionBay, FollowersUp, SMMKart etc.
                            <br><small class="text-muted">For v2 APIs, use format: https://domain.com/api/v2</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Provider Name *</label>
                                <input type="text" name="provider_name" class="form-control" placeholder="e.g., SMMGUO, SubscriptionBay" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">API Version</label>
                                <select name="api_version" class="form-select" onchange="toggleApiFields(this.value)">
                                    <option value="v2">API v2 (Recommended)</option>
                                    <option value="v1">API v1 (Legacy)</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">API URL *</label>
                                <input type="url" name="api_url" class="form-control" placeholder="https://smmguo.com/api/v2" required>
                                <small class="text-muted">For v2 APIs, ensure URL ends with /api/v2</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">API Key *</label>
                                <input type="text" name="api_key" class="form-control" placeholder="Your API key from provider" required>
                            </div>
                            <div class="col-md-6" id="email_field">
                                <label class="form-label">Email (for v2 APIs)</label>
                                <input type="email" name="api_email" class="form-control" placeholder="your@email.com">
                                <small class="text-muted">Some v2 providers require email authentication</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control" value="1" min="1" max="100">
                                <small class="text-muted">Lower number = higher priority</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Provider Type</label>
                                <select name="provider_type" class="form-select">
                                    <option value="smmguo">SMMGUO (v2)</option>
                                    <option value="subscriptionbay">SubscriptionBay (v2)</option>
                                    <option value="followersup">FollowersUp (v2)</option>
                                    <option value="smmkart">SMMKart (v2)</option>
                                    <option value="justanotherpanel">JustAnotherPanel (v2)</option>
                                    <option value="smmpanel">SMMPanel (v2)</option>
                                    <option value="other_v2">Other v2 Provider</option>
                                    <option value="custom">Custom/Legacy</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Optional description or notes about this provider"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Provider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Provider Modal -->
    <div class="modal fade" id="editProviderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit API Provider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form class="edit-provider-form">
                    <input type="hidden" name="provider_id" id="edit_provider_id">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-edit me-2"></i>Update provider information. For v2 APIs, ensure URL format is correct.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Provider Name *</label>
                                <input type="text" name="provider_name" id="edit_provider_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">API Version</label>
                                <select name="api_version" id="edit_api_version" class="form-select" onchange="toggleEditApiFields(this.value)">
                                    <option value="v2">API v2 (Recommended)</option>
                                    <option value="v1">API v1 (Legacy)</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">API URL *</label>
                                <input type="url" name="api_url" id="edit_api_url" class="form-control" required>
                                <small class="text-muted">For v2 APIs, ensure URL ends with /api/v2</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">API Key *</label>
                                <input type="text" name="api_key" id="edit_api_key" class="form-control" required>
                            </div>
                            <div class="col-md-6" id="edit_email_field">
                                <label class="form-label">Email (for v2 APIs)</label>
                                <input type="email" name="api_email" id="edit_api_email" class="form-control" placeholder="your@email.com">
                                <small class="text-muted">Some v2 providers require email authentication</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" id="edit_priority" class="form-control" min="1" max="100">
                                <small class="text-muted">Lower number = higher priority</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_provider_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Provider Type</label>
                                <select name="provider_type" id="edit_provider_type" class="form-select">
                                    <option value="smmguo">SMMGUO (v2)</option>
                                    <option value="subscriptionbay">SubscriptionBay (v2)</option>
                                    <option value="followersup">FollowersUp (v2)</option>
                                    <option value="smmkart">SMMKart (v2)</option>
                                    <option value="justanotherpanel">JustAnotherPanel (v2)</option>
                                    <option value="smmpanel">SMMPanel (v2)</option>
                                    <option value="other_v2">Other v2 Provider</option>
                                    <option value="custom">Custom/Legacy</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="edit_provider_description" class="form-control" rows="2" placeholder="Optional description or notes about this provider"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Provider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Services Modal -->
    <div class="modal fade" id="importServicesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-download me-2"></i>Import Services from Provider</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Import services from your connected API providers. 
                        This will fetch the latest services and update your catalog.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Provider</label>
                        <select id="importProviderSelect" class="form-select">
                            <option value="">-- Select a provider --</option>
                            <?php
                            $providers = $db->query("SELECT id, name FROM api_providers WHERE status = 'active'");
                            while ($provider = $providers->fetch_assoc()) {
                                echo "<option value='" . $provider['id'] . "'>" . htmlspecialchars($provider['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div id="importProgress" class="d-none">
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="text-center">
                            <p class="mb-2"><span id="importStatus">Starting import...</span></p>
                            <p class="text-muted small mb-0">Please wait while we import services from the provider.</p>
                        </div>
                    </div>
                    
                    <div id="importResults" class="d-none">
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Import Complete</h5>
                            <p class="mb-1"><strong>New Services:</strong> <span id="importedCount">0</span></p>
                            <p class="mb-0"><strong>Updated Services:</strong> <span id="updatedCount">0</span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="startImportBtn" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Start Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle API fields based on version selection
        function toggleApiFields(version) {
            const emailField = document.getElementById('email_field');
            if (version === 'v2') {
                emailField.style.display = 'block';
            } else {
                emailField.style.display = 'none';
            }
        }
        
        function toggleEditApiFields(version) {
            const emailField = document.getElementById('edit_email_field');
            if (version === 'v2') {
                emailField.style.display = 'block';
            } else {
                emailField.style.display = 'none';
            }
        }
        
        // Auto-suggest API URL based on provider type
        function updateApiUrl(providerType, isEdit = false) {
            const urlField = isEdit ? document.getElementById('edit_api_url') : document.querySelector('input[name="api_url"]');
            const versionField = isEdit ? document.getElementById('edit_api_version') : document.querySelector('select[name="api_version"]');
            
            const apiUrls = {
                'smmguo': 'https://smmguo.com/api/v2',
                'subscriptionbay': 'https://subscriptionbay.com/api/v2',
                'followersup': 'https://followersup.com/api/v2',
                'smmkart': 'https://smmkart.com/api/v2',
                'justanotherpanel': 'https://justanotherpanel.com/api/v2',
                'smmpanel': 'https://smmpanel.net/api/v2'
            };
            
            if (apiUrls[providerType] && versionField.value === 'v2') {
                urlField.value = apiUrls[providerType];
            }
        }
        
        // JavaScript for handling forms and actions
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize field visibility
            toggleApiFields('v2');
            toggleEditApiFields('v2');
            
            // Add provider type change listeners
            document.querySelector('select[name="provider_type"]').addEventListener('change', function() {
                updateApiUrl(this.value, false);
            });
            
            document.getElementById('edit_provider_type').addEventListener('change', function() {
                updateApiUrl(this.value, true);
            });
            // Add provider form
            document.querySelector('.add-provider-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'add_provider');
                
                fetch('admin_api_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Provider added successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            });

            // Edit provider buttons
            document.querySelectorAll('.edit-provider-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const providerId = this.dataset.providerId;
                    
                    fetch(`admin_api_handler.php?action=get_provider&provider_id=${providerId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const provider = data.provider;
                            document.getElementById('edit_provider_id').value = provider.id;
                            document.getElementById('edit_provider_name').value = provider.name;
                            document.getElementById('edit_api_url').value = provider.api_url;
                            document.getElementById('edit_api_key').value = provider.api_key;
                            document.getElementById('edit_api_email').value = provider.api_email || '';
                            document.getElementById('edit_api_version').value = provider.api_version || 'v2';
                            document.getElementById('edit_provider_type').value = provider.provider_type || 'other_v2';
                            document.getElementById('edit_provider_status').value = provider.status;
                            document.getElementById('edit_priority').value = provider.priority || 1;
                            document.getElementById('edit_provider_description').value = provider.description || '';
                            
                            // Toggle email field visibility based on API version
                            toggleEditApiFields(provider.api_version || 'v2');
                        } else {
                            alert('Error loading provider data: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while loading provider data.');
                    });
                });
            });

            // Edit provider form
            document.querySelector('.edit-provider-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'edit_provider');
                
                fetch('admin_api_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Provider updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            });

            // Other action buttons
            document.querySelectorAll('.sync-services-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const providerId = this.dataset.providerId;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    
                    fetch('admin_api_handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=sync_services&provider_id=${providerId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.success ? 'Services synced successfully!' : 'Error: ' + data.message);
                        location.reload();
                    });
                });
            });

            document.querySelectorAll('.toggle-provider-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const providerId = this.dataset.providerId;
                    const currentStatus = this.dataset.currentStatus;
                    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                    
                    if (confirm(`${newStatus === 'active' ? 'Activate' : 'Deactivate'} this provider?`)) {
                        fetch('admin_api_handler.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `action=toggle_provider&provider_id=${providerId}&status=${newStatus}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else alert('Error: ' + data.message);
                        });
                    }
                });
            });

            document.querySelectorAll('.delete-provider-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Delete this provider? This action cannot be undone.')) {
                        const providerId = this.dataset.providerId;
                        
                        fetch('admin_api_handler.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `action=delete_provider&provider_id=${providerId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Provider deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        });
                    }
                });
            });
        });

        // Service Import Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const importModal = new bootstrap.Modal(document.getElementById('importServicesModal'));
            const startImportBtn = document.getElementById('startImportBtn');
            const importProviderSelect = document.getElementById('importProviderSelect');
            const importProgress = document.getElementById('importProgress');
            const importResults = document.getElementById('importResults');
            const importStatus = document.getElementById('importStatus');
            const importedCount = document.getElementById('importedCount');
            const updatedCount = document.getElementById('updatedCount');
            const progressBar = document.querySelector('#importProgress .progress-bar');
            
            // Reset modal when shown
            document.getElementById('importServicesModal').addEventListener('show.bs.modal', function() {
                importProgress.classList.add('d-none');
                importResults.classList.add('d-none');
                importProviderSelect.value = '';
                startImportBtn.disabled = false;
                startImportBtn.innerHTML = '<i class="fas fa-download me-2"></i>Start Import';
            });
            
            // Start import button click handler
            startImportBtn.addEventListener('click', function() {
                const providerId = importProviderSelect.value;
                
                if (!providerId) {
                    alert('Please select a provider to import services from.');
                    return;
                }
                
                // Update UI for import in progress
                startImportBtn.disabled = true;
                startImportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Importing...';
                importProgress.classList.remove('d-none');
                importResults.classList.add('d-none');
                progressBar.style.width = '0%';
                importStatus.textContent = 'Connecting to provider...';
                
                // Start the import process
                fetch('admin_api_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=sync_services&provider_id=${providerId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update progress bar
                        progressBar.style.width = '100%';
                        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                        
                        // Show success message
                        importStatus.textContent = 'Import completed successfully!';
                        
                        // Update results
                        importedCount.textContent = data.imported || 0;
                        updatedCount.textContent = data.updated || 0;
                        importResults.classList.remove('d-none');
                        
                        // Update button
                        startImportBtn.innerHTML = '<i class="fas fa-check me-2"></i>Done';
                        startImportBtn.disabled = false;
                        
                        // Reload the page after a short delay to show updated services
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        throw new Error(data.message || 'Failed to import services');
                    }
                })
                .catch(error => {
                    console.error('Import error:', error);
                    importStatus.innerHTML = `<span class="text-danger">Error: ${error.message || 'Failed to import services'}</span>`;
                    progressBar.classList.add('bg-danger');
                    startImportBtn.disabled = false;
                    startImportBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Try Again';
                });
            });
        });
    </script>
</body>
</html>
