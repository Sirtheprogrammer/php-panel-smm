<?php
/**
 * Currency Management System
 * Handles currency conversions, balance operations, and price calculations
 */

class CurrencyManager {
    private $db;
    private $exchangeRates;
    private $supportedCurrencies = ['usd', 'tzs'];
    
    public function __construct($database = null) {
        global $db;
        $this->db = $database ?: $db;
        $this->loadExchangeRates();
    }
    
    /**
     * Load exchange rates from database or use default rates
     */
    private function loadExchangeRates() {
        // Try to load from database first
        $result = $this->db->query("SELECT * FROM exchange_rates WHERE status = 'active' ORDER BY updated_at DESC LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $rates = $result->fetch_assoc();
            $this->exchangeRates = [
                'usd_to_tzs' => (float)$rates['usd_to_tzs'],
                'tzs_to_usd' => (float)$rates['tzs_to_usd']
            ];
        } else {
            // Default rates (should be configurable)
            $this->exchangeRates = [
                'usd_to_tzs' => 2700.00,
                'tzs_to_usd' => 1/2700.00
            ];
        }
    }
    
    /**
     * Convert amount from one currency to another
     */
    public function convertCurrency($amount, $fromCurrency, $toCurrency) {
        $fromCurrency = strtolower($fromCurrency);
        $toCurrency = strtolower($toCurrency);
        
        // Validate currencies
        if (!in_array($fromCurrency, $this->supportedCurrencies) || 
            !in_array($toCurrency, $this->supportedCurrencies)) {
            throw new InvalidArgumentException("Unsupported currency");
        }
        
        // Same currency, no conversion needed
        if ($fromCurrency === $toCurrency) {
            return (float)$amount;
        }
        
        // Convert between USD and TZS
        if ($fromCurrency === 'usd' && $toCurrency === 'tzs') {
            return (float)$amount * $this->exchangeRates['usd_to_tzs'];
        } elseif ($fromCurrency === 'tzs' && $toCurrency === 'usd') {
            return (float)$amount * $this->exchangeRates['tzs_to_usd'];
        }
        
        throw new InvalidArgumentException("Invalid currency conversion");
    }
    
    /**
     * Get service price in specified currency
     */
    public function getServicePrice($serviceId, $currency) {
        $currency = strtolower($currency);
        
        if (!in_array($currency, $this->supportedCurrencies)) {
            throw new InvalidArgumentException("Unsupported currency: $currency");
        }
        
        $stmt = $this->db->prepare("SELECT price_usd, price_tzs FROM services WHERE id = ?");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Service not found");
        }
        
        $service = $result->fetch_assoc();
        $stmt->close();
        
        // Return price in requested currency
        if ($currency === 'usd') {
            if ($service['price_usd'] !== null && $service['price_usd'] > 0) {
                return (float)$service['price_usd'];
            } elseif ($service['price_tzs'] !== null && $service['price_tzs'] > 0) {
                // Convert from TZS to USD
                return $this->convertCurrency($service['price_tzs'], 'tzs', 'usd');
            }
        } elseif ($currency === 'tzs') {
            if ($service['price_tzs'] !== null && $service['price_tzs'] > 0) {
                return (float)$service['price_tzs'];
            } elseif ($service['price_usd'] !== null && $service['price_usd'] > 0) {
                // Convert from USD to TZS
                return $this->convertCurrency($service['price_usd'], 'usd', 'tzs');
            }
        }
        
        throw new Exception("No valid price found for service in $currency");
    }
    
    /**
     * Calculate total cost for an order
     */
    public function calculateOrderCost($serviceId, $quantity, $currency) {
        $pricePerUnit = $this->getServicePrice($serviceId, $currency);
        return $pricePerUnit * (float)$quantity;
    }
    
    /**
     * Get user balance in specified currency
     */
    public function getUserBalance($userId, $currency) {
        $currency = strtolower($currency);
        
        if (!in_array($currency, $this->supportedCurrencies)) {
            throw new InvalidArgumentException("Unsupported currency: $currency");
        }
        
        $stmt = $this->db->prepare("SELECT balance, balance_currency FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("User not found");
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        $userBalance = (float)$user['balance'];
        $userCurrency = strtolower($user['balance_currency'] ?? 'usd');
        
        // Convert if necessary
        if ($userCurrency !== $currency) {
            return $this->convertCurrency($userBalance, $userCurrency, $currency);
        }
        
        return $userBalance;
    }
    
    /**
     * Deduct balance from user account with transaction safety
     */
    public function deductBalance($userId, $amount, $currency, $orderId = null) {
        $currency = strtolower($currency);
        
        if (!in_array($currency, $this->supportedCurrencies)) {
            throw new InvalidArgumentException("Unsupported currency: $currency");
        }
        
        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be positive");
        }
        
        // Start transaction
        $this->db->begin_transaction();
        
        try {
            // Lock user row for update
            $stmt = $this->db->prepare("SELECT balance, balance_currency FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                throw new Exception("User not found");
            }
            
            $user = $result->fetch_assoc();
            $stmt->close();
            
            $currentBalance = (float)$user['balance'];
            $userCurrency = strtolower($user['balance_currency'] ?? 'usd');
            
            // Convert deduction amount to user's currency
            $deductionAmount = $amount;
            if ($currency !== $userCurrency) {
                $deductionAmount = $this->convertCurrency($amount, $currency, $userCurrency);
            }
            
            // Check if user has sufficient balance
            if ($currentBalance < $deductionAmount) {
                throw new Exception("Insufficient balance");
            }
            
            // Calculate new balance
            $newBalance = $currentBalance - $deductionAmount;
            
            // Update user balance
            $stmt = $this->db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->bind_param("di", $newBalance, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update balance");
            }
            $stmt->close();
            
            // Log the transaction
            $this->logBalanceTransaction($userId, -$deductionAmount, $userCurrency, 'order_deduction', $orderId);
            
            // Commit transaction
            $this->db->commit();
            
            return [
                'success' => true,
                'new_balance' => $newBalance,
                'deducted_amount' => $deductionAmount,
                'currency' => $userCurrency
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Add balance to user account
     */
    public function addBalance($userId, $amount, $currency) {
        $currency = strtolower($currency);
        
        if (!in_array($currency, $this->supportedCurrencies)) {
            throw new InvalidArgumentException("Unsupported currency: $currency");
        }
        
        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be positive");
        }
        
        // Start transaction
        $this->db->begin_transaction();
        
        try {
            // Lock user row for update
            $stmt = $this->db->prepare("SELECT balance, balance_currency FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                throw new Exception("User not found");
            }
            
            $user = $result->fetch_assoc();
            $stmt->close();
            
            $currentBalance = (float)$user['balance'];
            $userCurrency = strtolower($user['balance_currency'] ?? 'usd');
            
            // Convert addition amount to user's currency
            $additionAmount = $amount;
            if ($currency !== $userCurrency) {
                $additionAmount = $this->convertCurrency($amount, $currency, $userCurrency);
            }
            
            // Calculate new balance
            $newBalance = $currentBalance + $additionAmount;
            
            // Update user balance
            $stmt = $this->db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->bind_param("di", $newBalance, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update balance");
            }
            $stmt->close();
            
            // Log the transaction
            $this->logBalanceTransaction($userId, $additionAmount, $userCurrency, 'balance_addition');
            
            // Commit transaction
            $this->db->commit();
            
            return [
                'success' => true,
                'new_balance' => $newBalance,
                'added_amount' => $additionAmount,
                'currency' => $userCurrency
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Log balance transaction
     */
    private function logBalanceTransaction($userId, $amount, $currency, $type, $orderId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO balance_transactions (user_id, amount, currency, transaction_type, order_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("idssi", $userId, $amount, $currency, $type, $orderId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Format currency display
     */
    public function formatCurrency($amount, $currency) {
        $currency = strtolower($currency);
        
        switch ($currency) {
            case 'usd':
                return '$' . number_format($amount, 2);
            case 'tzs':
                return 'TZS ' . number_format($amount, 2);
            default:
                return number_format($amount, 2) . ' ' . strtoupper($currency);
        }
    }
    
    /**
     * Update exchange rates
     */
    public function updateExchangeRates($usdToTzs, $adminId = null) {
        $tzsToUsd = 1 / $usdToTzs;
        
        // Deactivate old rates
        $this->db->query("UPDATE exchange_rates SET status = 'inactive'");
        
        // Insert new rates
        $stmt = $this->db->prepare("
            INSERT INTO exchange_rates (usd_to_tzs, tzs_to_usd, updated_by, status) 
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->bind_param("ddi", $usdToTzs, $tzsToUsd, $adminId);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->loadExchangeRates(); // Reload rates
        }
        
        return $result;
    }
    
    /**
     * Get current exchange rates
     */
    public function getExchangeRates() {
        return $this->exchangeRates;
    }
}
