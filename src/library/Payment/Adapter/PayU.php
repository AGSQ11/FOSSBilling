<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_PayU implements FOSSBilling\InjectionAwareInterface
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
        if (!isset($this->config['pos_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayU', ':missing' => 'POS ID'], 4001);
        }
        if (!isset($this->config['second_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayU', ':missing' => 'Second Key'], 4001);
        }
        if (!isset($this->config['oauth_client_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayU', ':missing' => 'OAuth Client ID'], 4001);
        }
        if (!isset($this->config['oauth_client_secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayU', ':missing' => 'OAuth Client Secret'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'PayU is a leading payment service provider in Central and Eastern Europe, India and Africa. Accept payments from various methods including cards, bank transfers, and e-wallets.',
            'logo' => [
                'logo' => 'payu.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'pos_id' => [
                    'text', [
                        'label' => 'POS ID:',
                    ],
                ],
                'second_key' => [
                    'text', [
                        'label' => 'Second Key:',
                    ],
                ],
                'oauth_client_id' => [
                    'text', [
                        'label' => 'OAuth Client ID:',
                    ],
                ],
                'oauth_client_secret' => [
                    'text', [
                        'label' => 'OAuth Client Secret:',
                    ],
                ],
                'environment' => [
                    'select', [
                        'label' => 'Environment:',
                        'multiOptions' => [
                            'sandbox' => 'Sandbox (Test)',
                            'secure' => 'Secure (Production)',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoice = $this->di['db']->getExistingModelById('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceArr = $invoiceService->toApiArray($invoice);

        // Get OAuth token
        $accessToken = $this->getAccessToken();

        $baseUrl = ($this->config['environment'] === 'sandbox') ? 'https://secure.snd.payu.com' : 'https://secure.payu.com';

        // Prepare order data
        $orderData = [
            'notifyUrl' => $this->getNotifyUrl($invoice),
            'continueUrl' => $this->getReturnUrl($invoice),
            'customerIp' => $this->getClientIp(),
            'merchantPosId' => $this->config['pos_id'],
            'description' => $this->getInvoiceTitle($invoice),
            'currencyCode' => $invoice->currency,
            'totalAmount' => (int)($invoiceService->getTotalWithTax($invoice) * 100), // Amount in cents
            'extOrderId' => uniqid($invoice->id . '_', true),
            'products' => [
                [
                    'name' => $this->getInvoiceTitle($invoice),
                    'unitPrice' => (int)($invoiceService->getTotalWithTax($invoice) * 100), // Amount in cents
                    'quantity' => 1,
                ],
            ],
            'buyer' => [
                'email' => $invoice->buyer_email,
                'firstName' => $invoice->buyer_first_name,
                'lastName' => $invoice->buyer_last_name,
                'language' => $this->getLanguageCode($invoice->buyer_country),
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v2_1/orders');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new FOSSBilling\Exception('Error creating PayU order: ' . $response);
        }

        $order = json_decode($response, true);

        // Redirect to PayU payment page
        $redirectUrl = $order['redirectUri'] ?? null;
        if (!$redirectUrl) {
            throw new FOSSBilling\Exception('Could not get redirect URL from PayU');
        }

        $html = '<div class="alert alert-info">Redirecting to PayU to complete payment...</div>';
        $html .= '<script type="text/javascript">window.location.href = "' . $redirectUrl . '";</script>';

        return $html;
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

    public function processTransaction($api_admin, $id, $data, $gateway_id): void
    {
        $tx = $this->di['db']->getExistingModelById('Transaction', $id);

        // Get webhook data
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);

        if (!$payload) {
            throw new FOSSBilling\Exception('Invalid payload received from PayU');
        }

        // Update transaction with PayU data
        $order = $payload['order'] ?? [];
        $tx->txn_id = $order['orderId'] ?? '';
        $tx->txn_status = $order['status'] ?? '';
        $tx->amount = isset($order['totalAmount']) ? $order['totalAmount'] / 100 : 0; // Convert from cents
        $tx->currency = $order['currencyCode'] ?? '';

        // Process payment based on status
        if ($tx->txn_status === 'COMPLETED') {
            // Payment is complete, process invoice
            $invoiceService = $this->di['mod_service']('Invoice');
            $clientService = $this->di['mod_service']('Client');

            // Extract invoice ID from extOrderId (format: invoiceId_uniqueId)
            $extOrderId = $order['extOrderId'] ?? '';
            $parts = explode('_', $extOrderId);
            $invoiceId = $parts[0] ?? null;

            if (!$invoiceId) {
                throw new FOSSBilling\Exception('Invoice ID not found in PayU order data');
            }

            $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

            // Add funds to client account
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService->addFunds($client, $tx->amount, 'PayU transaction ' . $tx->txn_id, [
                'type' => 'transaction',
                'rel_id' => $tx->id,
            ]);

            // Pay the invoice
            $invoiceService->payInvoiceWithCredits($invoice);

            $tx->status = 'processed';
        } else {
            $tx->status = 'received';
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function getAccessToken(): string
    {
        $baseUrl = ($this->config['environment'] === 'sandbox') ? 'https://secure.snd.payu.com' : 'https://secure.payu.com';

        $authData = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['oauth_client_id'],
            'client_secret' => $this->config['oauth_client_secret'],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/pl/standard/user/oauth/authorize');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($authData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new FOSSBilling\Exception('Error getting PayU access token: ' . $response);
        }

        $tokenData = json_decode($response, true);
        return $tokenData['access_token'] ?? '';
    }

    private function getNotifyUrl(Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "PayU"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice);
    }

    private function getReturnUrl(Model_Invoice $invoice): string
    {
        return $this->di['tools']->url('/invoice/' . $invoice->hash);
    }

    private function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function getLanguageCode(string $countryCode): string
    {
        $langMap = [
            'PL' => 'pl',
            'DE' => 'de',
            'FR' => 'fr',
            'ES' => 'es',
            'IT' => 'it',
            'EN' => 'en',
            'RU' => 'ru',
        ];

        $countryCode = strtoupper($countryCode);
        return $langMap[$countryCode] ?? 'en';
    }

    public function recurrentPayment(Model_Invoice $invoice)
    {
        // Handle recurring payments with PayU subscriptions
        throw new FOSSBilling\Exception('Recurring payments need to be implemented separately for PayU');
    }

    public function singlePayment(Payment_Invoice $invoice)
    {
        // This is handled by getHtml method
        return $this;
    }

    public function getTransaction()
    {
        // Return transaction data for processing
        return null;
    }

    public function getLogos()
    {
        return [
            [
                'logo' => 'payu.png',
                'height' => '30px',
                'width' => '65px',
            ],
        ];
    }

    public function getStatus()
    {
        return 'active';
    }
}