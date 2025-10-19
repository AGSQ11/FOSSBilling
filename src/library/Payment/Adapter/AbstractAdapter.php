<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Payment\Adapter;

abstract class AbstractAdapter
{
    protected array $config = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Get the adapter configuration.
     *
     * @return array
     */
    abstract public static function getConfig(): array;
    
    /**
     * Process a payment.
     *
     * @param \Model_Invoice $invoice
     * @param array          $data
     *
     * @return array
     */
    abstract public function processPayment(\Model_Invoice $invoice, array $data = []): array;
    
    /**
     * Verify a payment callback/webhook.
     *
     * @param array $data
     *
     * @return bool
     */
    abstract public function verifyCallback(array $data): bool;
    
    /**
     * Get the payment form HTML.
     *
     * @param \Model_Invoice $invoice
     *
     * @return string
     */
    abstract public function getPaymentForm(\Model_Invoice $invoice): string;
    
    /**
     * Get the adapter name.
     *
     * @return string
     */
    public function getName(): string
    {
        $classParts = explode('\\', static::class);
        return end($classParts);
    }
    
    /**
     * Check if the adapter supports recurring payments.
     *
     * @return bool
     */
    public function supportsRecurring(): bool
    {
        return false;
    }
    
    /**
     * Check if the adapter supports refunds.
     *
     * @return bool
     */
    public function supportsRefunds(): bool
    {
        return false;
    }
    
    /**
     * Process a refund.
     *
     * @param \Model_Transaction $transaction
     * @param float              $amount
     * @param string             $reason
     *
     * @return bool
     */
    public function processRefund(\Model_Transaction $transaction, float $amount, string $reason = ''): bool
    {
        throw new \FOSSBilling\Exception('Refunds are not supported by this payment adapter');
    }
    
    /**
     * Create a recurring payment profile.
     *
     * @param \Model_Invoice $invoice
     * @param array          $data
     *
     * @return array
     */
    public function createRecurringProfile(\Model_Invoice $invoice, array $data = []): array
    {
        throw new \FOSSBilling\Exception('Recurring payments are not supported by this payment adapter');
    }
    
    /**
     * Cancel a recurring payment profile.
     *
     * @param string $profileId
     *
     * @return bool
     */
    public function cancelRecurringProfile(string $profileId): bool
    {
        throw new \FOSSBilling\Exception('Recurring payments are not supported by this payment adapter');
    }
    
    /**
     * Update a recurring payment profile.
     *
     * @param string $profileId
     * @param array  $data
     *
     * @return bool
     */
    public function updateRecurringProfile(string $profileId, array $data = []): bool
    {
        throw new \FOSSBilling\Exception('Recurring payments are not supported by this payment adapter');
    }
    
    /**
     * Get recurring payment profile details.
     *
     * @param string $profileId
     *
     * @return array
     */
    public function getRecurringProfileDetails(string $profileId): array
    {
        throw new \FOSSBilling\Exception('Recurring payments are not supported by this payment adapter');
    }
    
    /**
     * Get the supported currencies.
     *
     * @return array
     */
    public function getSupportedCurrencies(): array
    {
        return ['USD'];
    }
    
    /**
     * Check if a currency is supported.
     *
     * @param string $currency
     *
     * @return bool
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array($currency, $this->getSupportedCurrencies());
    }
    
    /**
     * Get the payment gateway logo.
     *
     * @return string|null
     */
    public function getLogo(): ?string
    {
        $config = static::getConfig();
        return $config['logo']['logo'] ?? null;
    }
    
    /**
     * Get the payment gateway description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        $config = static::getConfig();
        return $config['description'] ?? null;
    }
    
    /**
     * Get the payment gateway form configuration.
     *
     * @return array
     */
    public function getFormConfig(): array
    {
        $config = static::getConfig();
        return $config['form'] ?? [];
    }
    
    /**
     * Validate the adapter configuration.
     *
     * @param array $config
     *
     * @return bool
     */
    public function validateConfig(array $config): bool
    {
        $requiredFields = $this->getRequiredConfigFields();
        foreach ($requiredFields as $field) {
            if (empty($config[$field])) {
                throw new \FOSSBilling\Exception("Required configuration field '{$field}' is missing");
            }
        }
        
        return true;
    }
    
    /**
     * Get the required configuration fields.
     *
     * @return array
     */
    protected function getRequiredConfigFields(): array
    {
        $formConfig = $this->getFormConfig();
        $requiredFields = [];
        
        foreach ($formConfig as $field => $config) {
            if (isset($config[1]['required']) && $config[1]['required']) {
                $requiredFields[] = $field;
            }
        }
        
        return $requiredFields;
    }
    
    /**
     * Format an amount for the payment gateway.
     *
     * @param float  $amount
     * @param string $currency
     *
     * @return string
     */
    protected function formatAmount(float $amount, string $currency): string
    {
        // Most payment gateways expect amounts in the smallest currency unit (cents for USD)
        $multiplier = 100; // Default to cents
        
        // Some currencies don't have subunits
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'XAF', 'XOF', 'XPF'];
        if (in_array($currency, $zeroDecimalCurrencies)) {
            $multiplier = 1;
        }
        
        return (string) ($amount * $multiplier);
    }
    
    /**
     * Log a payment gateway event.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logEvent(string $message, array $context = []): void
    {
        $logger = \Box::getDi()['logger'];
        $logger->info("[Payment Gateway: {$this->getName()}] {$message}", $context);
    }
    
    /**
     * Log a payment gateway error.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        $logger = \Box::getDi()['logger'];
        $logger->error("[Payment Gateway: {$this->getName()}] {$message}", $context);
    }
}