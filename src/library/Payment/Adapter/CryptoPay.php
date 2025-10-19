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

class Payment_Adapter_CryptoPay extends Payment_AdapterAbstract implements FOSSBilling\InjectionAwareInterface
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
        if (!isset($this->config['api_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'CryptoPay', ':missing' => 'API Key'], 4001);
        }
        if (!isset($this->config['api_secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'CryptoPay', ':missing' => 'API Secret'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => 'CryptoPay is a payment gateway for Bitcoin, Ethereum, Litecoin, and other cryptocurrencies with advanced features.',
            'logo' => [
                'logo' => 'cryptopay.png',
                'height' => '25px',
                'width' => '85px',
            ],
            'form' => [
                'api_key' => [
                    'text',
                    [
                        'label' => 'API Key:',
                        'required' => true,
                    ],
                ],
                'api_secret' => [
                    'text',
                    [
                        'label' => 'API Secret:',
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
        $amount = $this->getAmountInCents($invoice) / 100; // CryptoPay expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Prepare payment data
        $paymentData = [
            'price_amount' => number_format($amount, 2, '.', ''),
            'price_currency' => $currency,
            'pay_currency' => $this->getPreferredCryptoCurrency($currency),
            'order_id' => $invoice->id,
            'order_description' => $description,
            'ipn_callback_url' => $this->getCallbackUrl($invoice),
            'success_callback_url' => $this->getReturnUrl($invoice),
            'cancel_callback_url' => $this->getCancelUrl($invoice),
        ];

        // Create payment using CryptoPay API
        $response = $this->makeApiCall('createinvoice', $paymentData, 'POST');

        if (!isset($response['invoice_id'])) {
            throw new Payment_Exception('Failed to create CryptoPay payment');
        }

        $paymentUrl = $response['invoice_url'];
        $invoiceId = $response['invoice_id'];

        return [
            'type' => 'redirect',
            'redirect_url' => $paymentUrl,
            'invoice_id' => $invoiceId,
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
        $signature = $_SERVER['HTTP_X_CRYPTO_PAY_SIGNATURE'] ?? '';
        $expectedSignature = hash_hmac('sha256', $rawPayload, $this->config['api_secret']);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logError('Invalid CryptoPay webhook signature');
            return false;
        }

        // Verify payment status
        $paymentStatus = $payload['status'] ?? '';
        if (!in_array($paymentStatus, ['paid', 'confirmed'])) {
            $this->logEvent("Unsupported payment status: {$paymentStatus}");
            return false;
        }

        return true;
    }

    public function getPaymentForm(Model_Invoice $invoice): string
    {
        $amount = $this->getAmountInCents($invoice) / 100; // CryptoPay expects amount in standard units
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);

        // Prepare payment data
        $paymentData = [
            'price_amount' => number_format($amount, 2, '.', ''),
            'price_currency' => $currency,
            'pay_currency' => $this->getPreferredCryptoCurrency($currency),
            'order_id' => $invoice->id,
            'order_description' => $description,
            'ipn_callback_url' => $this->getCallbackUrl($invoice),
            'success_callback_url' => $this->getReturnUrl($invoice),
            'cancel_callback_url' => $this->getCancelUrl($invoice),
        ];

        // Create payment using CryptoPay API
        $response = $this->makeApiCall('createinvoice', $paymentData, 'POST');

        if (!isset($response['invoice_url'])) {
            throw new Payment_Exception('Failed to create CryptoPay payment form');
        }

        $paymentUrl = $response['invoice_url'];

        $form = '
        <div class="alert alert-info">
            <p>You will be redirected to CryptoPay to complete your payment.</p>
            <p>Click the button below to proceed.</p>
        </div>
        <a href="' . $paymentUrl . '" class="btn btn-primary">Pay with CryptoPay</a>
        <script>
            setTimeout(function() {
                window.location.href = "' . $paymentUrl . '";
            }, 3000);
        </script>';

        return $form;
    }

    private function makeApiCall(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        $baseUrl = ($this->config['test_mode']) ? 'https://cryptopay.me/api' : 'https://cryptopay.me/api';
        $url = $baseUrl . '/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: HMAC ' . $this->config['api_key'] . ':' . $this->generateSignature($endpoint, $data),
            'Content-Type: application/json',
            'User-Agent: FOSSBilling/CryptoPay-Adapter',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->logError("CryptoPay API call failed with HTTP code {$httpCode}: {$response}");
            throw new Payment_Exception('CryptoPay API call failed');
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to decode CryptoPay API response: {$response}");
            throw new Payment_Exception('Failed to decode CryptoPay API response');
        }

        return $decodedResponse;
    }

    private function generateSignature(string $endpoint, array $data): string
    {
        $dataString = json_encode($data);
        $signatureData = $endpoint . $dataString;
        return hash_hmac('sha256', $signatureData, $this->config['api_secret']);
    }

    private function getPreferredCryptoCurrency(string $fiatCurrency): string
    {
        $cryptoMap = [
            'USD' => 'BTC',
            'EUR' => 'BTC',
            'GBP' => 'BTC',
            'CAD' => 'BTC',
            'AUD' => 'BTC',
            'NZD' => 'BTC',
            'SGD' => 'BTC',
            'HKD' => 'BTC',
            'CHF' => 'BTC',
            'JPY' => 'BTC',
            'SEK' => 'BTC',
            'NOK' => 'BTC',
            'DKK' => 'BTC',
            'PLN' => 'BTC',
            'CZK' => 'BTC',
            'HUF' => 'BTC',
            'RON' => 'BTC',
            'BGN' => 'BTC',
            'TRY' => 'BTC',
            'ZAR' => 'BTC',
            'BRL' => 'BTC',
            'MXN' => 'BTC',
            'INR' => 'BTC',
            'RUB' => 'BTC',
            'MYR' => 'BTC',
            'PHP' => 'BTC',
            'THB' => 'BTC',
            'IDR' => 'BTC',
            'KRW' => 'BTC',
            'VND' => 'BTC',
        ];

        return $cryptoMap[$fiatCurrency] ?? 'BTC';
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
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "CryptoPay"');
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
        return false; // CryptoPay doesn't support recurring payments
    }

    public function supportsRefunds(): bool
    {
        return false; // CryptoPay doesn't support refunds through API
    }

    public function getSupportedCurrencies(): array
    {
        // CryptoPay supports many cryptocurrencies and fiat currencies
        return [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD', 'CHF', 'JPY',
            'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'TRY', 'ZAR',
            'BRL', 'MXN', 'INR', 'RUB', 'MYR', 'PHP', 'THB', 'IDR', 'KRW', 'VND',
            'BTC', 'ETH', 'LTC', 'BCH', 'XRP', 'DOGE', 'USDT', 'USDC', 'DAI'
        ];
    }
}