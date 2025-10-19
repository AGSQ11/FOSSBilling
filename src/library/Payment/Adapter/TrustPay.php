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

class Payment_Adapter_TrustPay extends Payment_AdapterAbstract implements FOSSBilling\InjectionAwareInterface
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
        if (!isset($this->config['project_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'TrustPay', ':missing' => 'Project ID'], 4001);
        }
        if (!isset($this->config['secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'TrustPay', ':missing' => 'Secret'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => 'TrustPay is a European payment service provider offering advanced payment routing and multiple payment methods.',
            'logo' => [
                'logo' => 'trustpay.png',
                'height' => '25px',
                'width' => '85px',
            ],
            'form' => [
                'project_id' => [
                    'text',
                    [
                        'label' => 'Project ID:',
                        'required' => true,
                    ],
                ],
                'secret' => [
                    'text',
                    [
                        'label' => 'Secret:',
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
        $amount = $this->getAmountInCents($invoice) / 100; // TrustPay expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Prepare payment data
        $paymentData = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'description' => $description,
            'merchant_id' => $this->config['project_id'],
            'order_id' => $invoice->id,
            'return_url' => $this->getReturnUrl($invoice),
            'cancel_url' => $this->getCancelUrl($invoice),
            'notify_url' => $this->getCallbackUrl($invoice),
            'signature' => $this->generateSignature($amount, $currency, $invoice->id),
        ];

        // Create payment using TrustPay API
        $response = $this->makeApiCall('payment', $paymentData, 'POST');

        if (!isset($response['redirect_url'])) {
            throw new Payment_Exception('Failed to create TrustPay payment');
        }

        $paymentUrl = $response['redirect_url'];
        $paymentId = $response['payment_id'] ?? null;

        return [
            'type' => 'redirect',
            'redirect_url' => $paymentUrl,
            'payment_id' => $paymentId,
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
        $signature = $payload['signature'] ?? '';
        $expectedSignature = $this->generateWebhookSignature($payload);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logError('Invalid TrustPay webhook signature');
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
        $amount = $this->getAmountInCents($invoice) / 100; // TrustPay expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Prepare payment data
        $paymentData = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'description' => $description,
            'merchant_id' => $this->config['project_id'],
            'order_id' => $invoice->id,
            'return_url' => $this->getReturnUrl($invoice),
            'cancel_url' => $this->getCancelUrl($invoice),
            'notify_url' => $this->getCallbackUrl($invoice),
            'signature' => $this->generateSignature($amount, $currency, $invoice->id),
        ];

        // Create payment using TrustPay API
        $response = $this->makeApiCall('payment', $paymentData, 'POST');

        if (!isset($response['redirect_url'])) {
            throw new Payment_Exception('Failed to create TrustPay payment form');
        }

        $paymentUrl = $response['redirect_url'];

        $form = '
        <div class="alert alert-info">
            <p>You will be redirected to TrustPay to complete your payment.</p>
            <p>Click the button below to proceed.</p>
        </div>
        <a href="' . $paymentUrl . '" class="btn btn-primary">Pay with TrustPay</a>
        <script>
            setTimeout(function() {
                window.location.href = "' . $paymentUrl . '";
            }, 3000);
        </script>';

        return $form;
    }

    private function makeApiCall(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        $baseUrl = ($this->config['test_mode']) ? 'https://test.trustpay.eu' : 'https://trustpay.eu';
        $url = $baseUrl . '/api/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['secret'],
            'Content-Type: application/json',
            'User-Agent: FOSSBilling/TrustPay-Adapter',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->logError("TrustPay API call failed with HTTP code {$httpCode}: {$response}");
            throw new Payment_Exception('TrustPay API call failed');
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to decode TrustPay API response: {$response}");
            throw new Payment_Exception('Failed to decode TrustPay API response');
        }

        return $decodedResponse;
    }

    private function generateSignature(float $amount, string $currency, int $orderId): string
    {
        $data = $amount . $currency . $orderId . $this->config['project_id'];
        return hash_hmac('sha256', $data, $this->config['secret']);
    }

    private function generateWebhookSignature(array $data): string
    {
        ksort($data);
        $dataString = json_encode($data);
        return hash_hmac('sha256', $dataString, $this->config['secret']);
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
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "TrustPay"');
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
        return false; // TrustPay doesn't support recurring payments
    }

    public function supportsRefunds(): bool
    {
        return true; // TrustPay supports refunds through API
    }

    public function processRefund(Model_Transaction $transaction, float $amount, string $reason = ''): bool
    {
        // Process refund using TrustPay API
        $this->logEvent("Processing refund for transaction #{$transaction->id}", [
            'amount' => $amount,
            'reason' => $reason,
        ]);

        $refundData = [
            'transaction_id' => $transaction->txn_id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $transaction->currency,
            'reason' => $reason,
        ];

        $response = $this->makeApiCall('refund', $refundData, 'POST');

        if (!isset($response['status']) || $response['status'] !== 'success') {
            $this->logError("TrustPay refund failed: " . json_encode($response));
            throw new Payment_Exception('TrustPay refund failed');
        }

        return true;
    }

    public function getSupportedCurrencies(): array
    {
        // TrustPay supports many European currencies
        return [
            'EUR', 'USD', 'GBP', 'CZK', 'HUF', 'PLN', 'RON', 'BGN', 'HRK', 'DKK',
            'SEK', 'NOK', 'CHF', 'RUB', 'TRY', 'UAH', 'BYN', 'ILS', 'ZAR', 'BRL',
            'MXN', 'INR', 'MYR', 'PHP', 'THB', 'IDR', 'KRW', 'VND', 'SGD', 'HKD',
            'AUD', 'CAD', 'NZD', 'JPY', 'CNY'
        ];
    }
}