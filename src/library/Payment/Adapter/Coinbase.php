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

use FOSSBilling\InjectionAwareInterface;

class Coinbase extends AbstractAdapter implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => 'Coinbase Commerce is a merchant payment processor that enables merchants to accept multiple cryptocurrencies.',
            'logo' => [
                'logo' => 'coinbase.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'api_key' => [
                    'text', [
                        'label' => 'API Key:',
                        'required' => true,
                    ],
                ],
                'webhook_secret' => [
                    'text', [
                        'label' => 'Webhook Shared Secret:',
                        'required' => true,
                    ],
                ],
                'test_mode' => [
                    'select', [
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

    public function processPayment(\Model_Invoice $invoice, array $data = []): array
    {
        $amount = $this->getAmountInCents($invoice) / 100; // Coinbase expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Create charge data
        $chargeData = [
            'name' => $description,
            'description' => $description,
            'local_price' => [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
            ],
            'pricing_type' => 'fixed_price',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'customer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
                'customer_email' => $invoice->buyer_email,
            ],
        ];

        // Add redirect URLs
        $chargeData['redirect_url'] = $this->getReturnUrl($invoice);
        $chargeData['cancel_url'] = $this->getCancelUrl($invoice);

        // Create charge using Coinbase API
        $response = $this->makeApiCall('charges', $chargeData, 'POST');

        if (!isset($response['data']['id'])) {
            throw new \FOSSBilling\Exception('Failed to create Coinbase charge');
        }

        $chargeId = $response['data']['id'];
        $hostedUrl = $response['data']['hosted_url'];

        return [
            'type' => 'redirect',
            'charge_id' => $chargeId,
            'redirect_url' => $hostedUrl,
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
        $signature = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';
        $expectedSignature = hash_hmac('sha256', $rawPayload, $this->config['webhook_secret']);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logError('Invalid webhook signature');
            return false;
        }

        // Verify event type
        $eventType = $payload['event']['type'] ?? '';
        if (!in_array($eventType, ['charge:confirmed', 'charge:resolved'])) {
            $this->logEvent("Unsupported event type: {$eventType}");
            return false;
        }

        // Verify charge status
        $chargeStatus = $payload['data']['timeline'][count($payload['data']['timeline']) - 1]['status'] ?? '';
        if (!in_array($chargeStatus, ['COMPLETED', 'RESOLVED'])) {
            $this->logEvent("Invalid charge status: {$chargeStatus}");
            return false;
        }

        return true;
    }

    public function getPaymentForm(\Model_Invoice $invoice): string
    {
        $amount = $this->getAmountInCents($invoice) / 100; // Coinbase expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Create charge using Coinbase API
        $chargeData = [
            'name' => $description,
            'description' => $description,
            'local_price' => [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
            ],
            'pricing_type' => 'fixed_price',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'customer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
                'customer_email' => $invoice->buyer_email,
            ],
        ];

        // Add redirect URLs
        $chargeData['redirect_url'] = $this->getReturnUrl($invoice);
        $chargeData['cancel_url'] = $this->getCancelUrl($invoice);

        // Create charge using Coinbase API
        $response = $this->makeApiCall('charges', $chargeData, 'POST');

        if (!isset($response['data']['hosted_url'])) {
            throw new \FOSSBilling\Exception('Failed to create Coinbase payment form');
        }

        $hostedUrl = $response['data']['hosted_url'];

        $form = '
        <div class="alert alert-info">
            <p>You will be redirected to Coinbase Commerce to complete your payment.</p>
            <p>Click the button below to proceed.</p>
        </div>
        <a href="' . $hostedUrl . '" class="btn btn-primary">Pay with Coinbase</a>
        <script>
            setTimeout(function() {
                window.location.href = "' . $hostedUrl . '";
            }, 3000);
        </script>';

        return $form;
    }

    private function makeApiCall(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        $baseUrl = 'https://api.commerce.coinbase.com/';
        $url = $baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-CC-Api-Key: ' . $this->config['api_key'],
            'X-CC-Version: 2018-03-22',
            'Content-Type: application/json',
            'User-Agent: FOSSBilling/Coinbase-Commerce-Adapter',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->logError("Coinbase API call failed with HTTP code {$httpCode}: {$response}");
            throw new \FOSSBilling\Exception('Coinbase API call failed');
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to decode Coinbase API response: {$response}");
            throw new \FOSSBilling\Exception('Failed to decode Coinbase API response');
        }

        return $decodedResponse;
    }

    public function getAmountInCents(\Model_Invoice $invoice): int
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        return (int)($invoiceService->getTotalWithTax($invoice) * 100);
    }

    public function getInvoiceTitle(\Model_Invoice $invoice): string
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

    public function getReturnUrl(\Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Coinbase"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice) . '&status=success';
    }

    public function getCancelUrl(\Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Coinbase"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice) . '&status=cancelled';
    }

    public function supportsRecurring(): bool
    {
        return false; // Coinbase Commerce doesn't support recurring payments
    }

    public function supportsRefunds(): bool
    {
        return false; // Coinbase Commerce doesn't support refunds through API
    }

    public function getSupportedCurrencies(): array
    {
        // Coinbase Commerce supports many cryptocurrencies
        // Return major fiat currencies that can be converted to crypto
        return [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD', 'CHF', 'JPY',
            'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'TRY', 'ZAR',
            'BRL', 'MXN', 'INR', 'RUB', 'MYR', 'PHP', 'THB', 'IDR', 'KRW', 'VND'
        ];
    }
}