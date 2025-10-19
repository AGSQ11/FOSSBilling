<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Environment;

class Payment_Adapter_Rapyd extends Payment_AdapterAbstract implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function __construct(private $config)
    {
        if (!isset($this->config['access_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Rapyd', ':missing' => 'Access Key'], 4001);
        }
        if (!isset($this->config['secret_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Rapyd', ':missing' => 'Secret Key'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => 'Rapyd is a global fintech platform that enables businesses to accept payments from anywhere in the world with advanced payment routing.',
            'logo' => [
                'logo' => 'rapyd.png',
                'height' => '25px',
                'width' => '85px',
            ],
            'form' => [
                'access_key' => [
                    'text',
                    [
                        'label' => 'Access Key:',
                        'required' => true,
                    ],
                ],
                'secret_key' => [
                    'text',
                    [
                        'label' => 'Secret Key:',
                        'required' => true,
                    ],
                ],
                'test_mode' => [
                    'select',
                    [
                        'label' => 'Test Mode:',
                        'multiOptions' => [
                            '0' => 'No',
                            '1' => 'Yes',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function processPayment(Model_Invoice $invoice, array $data = []): array
    {
        $amount = $this->getAmountInCents($invoice) / 100; // Rapyd expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Prepare checkout data
        $checkoutData = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'description' => $description,
            'merchant_reference_id' => $invoice->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'customer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
                'customer_email' => $invoice->buyer_email,
            ],
            'return_url' => $this->getReturnUrl($invoice),
            'cancel_url' => $this->getCancelUrl($invoice),
            'complete_checkout_url' => $this->getCallbackUrl($invoice),
            'country' => $invoice->buyer_country,
        ];

        // Create checkout using Rapyd API
        $response = $this->makeApiCall('checkout', $checkoutData, 'POST');

        if (!isset($response['data']['redirect_url'])) {
            throw new Payment_Exception('Failed to create Rapyd checkout');
        }

        $paymentUrl = $response['data']['redirect_url'];
        $checkoutId = $response['data']['id'];

        return [
            'type' => 'redirect',
            'redirect_url' => $paymentUrl,
            'checkout_id' => $checkoutId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
        ];
    }

    public function verifyCallback(array $data): bool
    {
        // Get raw payload
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);

        if (!$payload) {
            return false;
        }

        // Verify webhook signature
        $signature = $_SERVER['HTTP_X_RAPYD_SIGNATURE'] ?? '';
        $expectedSignature = $this->generateSignature($rawPayload);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logError('Invalid Rapyd webhook signature');
            return false;
        }

        // Verify payment status
        $paymentStatus = $payload['status'] ?? '';
        if (!in_array($paymentStatus, ['paid', 'completed'])) {
            $this->logEvent("Unsupported payment status: {$paymentStatus}");
            return false;
        }

        return true;
    }

    public function getPaymentForm(Model_Invoice $invoice): string
    {
        $amount = $this->getAmountInCents($invoice) / 100; // Rapyd expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Prepare checkout data
        $checkoutData = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'description' => $description,
            'merchant_reference_id' => $invoice->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'customer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
                'customer_email' => $invoice->buyer_email,
            ],
            'return_url' => $this->getReturnUrl($invoice),
            'cancel_url' => $this->getCancelUrl($invoice),
            'complete_checkout_url' => $this->getCallbackUrl($invoice),
            'country' => $invoice->buyer_country,
        ];

        // Create checkout using Rapyd API
        $response = $this->makeApiCall('checkout', $checkoutData, 'POST');

        if (!isset($response['data']['redirect_url'])) {
            throw new Payment_Exception('Failed to create Rapyd payment form');
        }

        $paymentUrl = $response['data']['redirect_url'];

        $form = '
        <div class="alert alert-info">
            <p>You will be redirected to Rapyd to complete your payment.</p>
            <p>Click the button below to proceed.</p>
        </div>
        <a href="' . $paymentUrl . '" class="btn btn-primary">Pay with Rapyd</a>
        <script>
            setTimeout(function() {
                window.location.href = "' . $paymentUrl . '";
            }, 3000);
        </script>';

        return $form;
    }

    private function makeApiCall(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        $baseUrl = ($this->config['test_mode']) ? 'https://sandboxapi.rapyd.net' : 'https://api.rapyd.net';
        $url = $baseUrl . '/v1/' . $endpoint;

        // Generate signature
        $salt = uniqid();
        $timestamp = time();
        $signature = $this->generateRapydSignature($method, $url, $salt, $timestamp, $data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Rapyd access_key=' . $this->config['access_key'] . ', salt=' . $salt . ', timestamp=' . $timestamp . ', signature=' . $signature,
            'Content-Type: application/json',
            'User-Agent: FOSSBilling/Rapyd-Adapter',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->logError("Rapyd API call failed with HTTP code {$httpCode}: {$response}");
            throw new Payment_Exception('Rapyd API call failed');
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to decode Rapyd API response: {$response}");
            throw new Payment_Exception('Failed to decode Rapyd API response');
        }

        return $decodedResponse;
    }

    private function generateRapydSignature(string $method, string $url, string $salt, int $timestamp, array $body = []): string
    {
        $bodyString = empty($body) ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signatureData = $method . $url . $salt . $timestamp . $this->config['access_key'] . $this->config['secret_key'] . $bodyString;
        $signature = hash_hmac('sha256', $signatureData, $this->config['secret_key']);
        return $signature;
    }

    private function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->config['secret_key']);
    }

    public function getAmountInCents(Model_Invoice $invoice): int
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        return (int)($invoiceService->getTotalWithTax($invoice) * 100);
    }

    public function getInvoiceTitle(Model_Invoice $invoice): string
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', [':invoice_id' => $invoice->id]);

        $params = [
            ':id' => sprintf('%05s', $invoice->nr),
            ':serie' => $invoice->serie,
            ':title' => $invoiceItems[0]['title'],
        ];
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        if ((is_countable($invoiceItems) ? count($invoiceItems) : 0) > 1) {
            $title = __trans('Payment for invoice :serie:id', $params);
        }

        return $title;
    }

    public function getCallbackUrl(Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Rapyd"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice);
    }

    public function getReturnUrl(Model_Invoice $invoice): string
    {
        return $this->di['tools']->url('/invoice/' . $invoice->hash);
    }

    public function getCancelUrl(Model_Invoice $invoice): string
    {
        return $this->di['tools']->url('/invoice/' . $invoice->hash);
    }

    public function supportsRecurring(): bool
    {
        return false; // Rapyd doesn't support recurring payments in this implementation
    }

    public function supportsRefunds(): bool
    {
        return true; // Rapyd supports refunds through API
    }

    public function processRefund(Model_Transaction $transaction, float $amount, string $reason = ''): bool
    {
        // Process refund using Rapyd API
        $this->logEvent("Processing refund for transaction #{$transaction->id}", [
            'amount' => $amount,
            'reason' => $reason,
        ]);

        $refundData = [
            'payment' => $transaction->txn_id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $transaction->currency,
            'reason' => $reason,
        ];

        $response = $this->makeApiCall('refund', $refundData, 'POST');

        if (!isset($response['data']['id'])) {
            $this->logError("Rapyd refund failed: " . json_encode($response));
            throw new Payment_Exception('Rapyd refund failed');
        }

        return true;
    }

    public function getSupportedCurrencies(): array
    {
        // Rapyd supports many international currencies
        return [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD', 'CHF', 'JPY',
            'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'TRY', 'ZAR',
            'BRL', 'MXN', 'INR', 'RUB', 'MYR', 'PHP', 'THB', 'IDR', 'KRW', 'VND',
            'ILS', 'AED', 'SAR', 'EGP', 'KWD', 'QAR', 'OMR', 'BHD', 'JOD', 'UAH',
            'BYN', 'MDL', 'AMD', 'GEL', 'AZN', 'KZT', 'UZS', 'TJS', 'TMT', 'KGS'
        ];
    }
}