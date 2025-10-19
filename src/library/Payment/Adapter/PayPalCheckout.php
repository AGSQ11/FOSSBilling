<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_PayPalCheckout implements FOSSBilling\InjectionAwareInterface
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
        if (!isset($this->config['client_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayPal Checkout', ':missing' => 'Client ID'], 4001);
        }
        if (!isset($this->config['client_secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayPal Checkout', ':missing' => 'Client Secret'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'PayPal Checkout allows you to accept payments via PayPal, Venmo, Apple Pay, Google Pay, and other payment methods.',
            'logo' => [
                'logo' => 'paypal.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'client_id' => [
                    'text', [
                        'label' => 'Client ID:',
                    ],
                ],
                'client_secret' => [
                    'text', [
                        'label' => 'Client Secret:',
                    ],
                ],
                'environment' => [
                    'select', [
                        'label' => 'Environment:',
                        'multiOptions' => [
                            'sandbox' => 'Sandbox (Test)',
                            'live' => 'Live (Production)',
                        ],
                    ],
                ],
                'intent' => [
                    'select', [
                        'label' => 'Intent:',
                        'multiOptions' => [
                            'capture' => 'Capture (Immediate payment)',
                            'authorize' => 'Authorize (Authorize only)',
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
        $totalAmount = $invoiceService->getTotalWithTax($invoice);

        // Get access token
        $accessToken = $this->getAccessToken();

        // Create order in PayPal
        $orderData = [
            'intent' => $this->config['intent'] ?? 'capture',
            'purchase_units' => [
                [
                    'reference_id' => 'invoice_' . $invoice->id,
                    'description' => $this->getInvoiceTitle($invoice),
                    'amount' => [
                        'value' => number_format($totalAmount, 2, '.', ''),
                        'currency_code' => $invoice->currency,
                    ],
                    'custom_id' => $invoice->id, // Store invoice ID
                ],
            ],
            'application_context' => [
                'brand_name' => $this->di['tools']->getCurrentURL(),
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => $this->getReturnUrl($invoice),
                'cancel_url' => $this->getCancelUrl($invoice),
            ],
        ];

        $ch = curl_init();
        $baseUrl = ($this->config['environment'] === 'sandbox') 
            ? 'https://api.sandbox.paypal.com' 
            : 'https://api.paypal.com';
        
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders');
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

        if ($httpCode !== 201) {
            throw new FOSSBilling\Exception('Error creating PayPal order: ' . $response);
        }

        $order = json_decode($response, true);
        $orderId = $order['id'];

        // Generate PayPal JavaScript SDK
        $html = '
        <div id="paypal-button-container"></div>
        <script src="https://www.paypal.com/sdk/js?client-id=' . $this->config['client_id'] . '&currency=' . $invoice->currency . '&intent=' . ($this->config['intent'] ?? 'capture') . '"></script>
        <script>
            paypal.Buttons({
                style: {
                    layout: "vertical",
                    color: "blue",
                    shape: "rect",
                    label: "paypal"
                },
                createOrder: function(data, actions) {
                    // Use the order ID from PayPal
                    return "' . $orderId . '";
                },
                onApprove: function(data, actions) {
                    return fetch("' . $this->getCaptureUrl($invoice, $orderId) . '", {
                        method: "post",
                        headers: {
                            "content-type": "application/json"
                        },
                        body: JSON.stringify({
                            orderID: data.orderID
                        })
                    }).then(function(res) {
                        return res.json();
                    }).then(function(details) {
                        // Redirect to success page
                        window.location.href = "' . $this->getReturnUrl($invoice) . '";
                    });
                },
                onError: function(err) {
                    console.error("PayPal error:", err);
                    alert("An error occurred during the PayPal payment process.");
                }
            }).render("#paypal-button-container");
        </script>';

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

        // Check if this is a return from PayPal or a webhook
        $orderId = $data['get']['token'] ?? $data['post']['orderID'] ?? null;

        if ($orderId) {
            // Capture payment
            $accessToken = $this->getAccessToken();
            $baseUrl = ($this->config['environment'] === 'sandbox') 
                ? 'https://api.sandbox.paypal.com' 
                : 'https://api.paypal.com';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 201 && $httpCode !== 200) {
                throw new FOSSBilling\Exception('Error capturing PayPal payment: ' . $response);
            }

            $capture = json_decode($response, true);

            // Update transaction
            $tx->txn_id = $capture['id'] ?? '';
            $tx->txn_status = $capture['status'] ?? '';
            
            // Get the capture amount
            $purchaseUnit = $capture['purchase_units'][0] ?? [];
            $payments = $purchaseUnit['payments']['captures'][0] ?? [];
            $tx->amount = $payments['amount']['value'] ?? 0;
            $tx->currency = $payments['amount']['currency_code'] ?? $purchaseUnit['amount']['currency_code'] ?? '';

            // Process payment if successful
            if ($tx->txn_status === 'COMPLETED' || $tx->txn_status === 'CAPTURED') {
                $invoiceService = $this->di['mod_service']('Invoice');
                $clientService = $this->di['mod_service']('Client');

                // Extract invoice ID from purchase unit reference
                $referenceId = $purchaseUnit['reference_id'] ?? '';
                $invoiceId = str_replace('invoice_', '', $referenceId);

                if (!$invoiceId) {
                    throw new FOSSBilling\Exception('Invoice ID not found in PayPal order');
                }

                $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

                // Add funds to client account
                $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
                $clientService->addFunds($client, $tx->amount, 'PayPal transaction ' . $tx->txn_id, [
                    'type' => 'transaction',
                    'rel_id' => $tx->id,
                ]);

                // Pay the invoice
                $invoiceService->payInvoiceWithCredits($invoice);

                $tx->status = 'processed';
            } else {
                $tx->status = 'received';
            }
        } else {
            // Handle webhook data
            $rawPayload = file_get_contents('php://input');
            $payload = json_decode($rawPayload, true);

            if (isset($payload['resource'])) {
                $resource = $payload['resource'];
                $tx->txn_id = $resource['id'] ?? '';
                $tx->txn_status = $resource['status'] ?? '';
                $tx->amount = $resource['amount']['value'] ?? 0;
                $tx->currency = $resource['amount']['currency_code'] ?? '';

                if ($tx->txn_status === 'COMPLETED' || $tx->txn_status === 'CAPTURED') {
                    $invoiceService = $this->di['mod_service']('Invoice');
                    $clientService = $this->di['mod_service']('Client');

                    // Extract invoice ID from custom ID
                    $invoiceId = $resource['custom_id'] ?? null;

                    if (!$invoiceId) {
                        throw new FOSSBilling\Exception('Invoice ID not found in PayPal webhook');
                    }

                    $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

                    // Add funds to client account
                    $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
                    $clientService->addFunds($client, $tx->amount, 'PayPal transaction ' . $tx->txn_id, [
                        'type' => 'transaction',
                        'rel_id' => $tx->id,
                    ]);

                    // Pay the invoice
                    $invoiceService->payInvoiceWithCredits($invoice);

                    $tx->status = 'processed';
                } else {
                    $tx->status = 'received';
                }
            }
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function getAccessToken(): string
    {
        $baseUrl = ($this->config['environment'] === 'sandbox') 
            ? 'https://api.sandbox.paypal.com' 
            : 'https://api.paypal.com';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['client_id'] . ':' . $this->config['client_secret']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new FOSSBilling\Exception('Error getting PayPal access token: ' . $response);
        }

        $tokenData = json_decode($response, true);
        return $tokenData['access_token'] ?? '';
    }

    private function getReturnUrl(Model_Invoice $invoice): string
    {
        return $this->di['tools']->url('/invoice/' . $invoice->hash);
    }

    private function getCancelUrl(Model_Invoice $invoice): string
    {
        return $this->di['tools']->url('/invoice/' . $invoice->hash);
    }

    private function getCaptureUrl(Model_Invoice $invoice, string $orderId): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "PayPalCheckout"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice) . '&paypal_order_id=' . $orderId;
    }

    public function recurrentPayment(Model_Invoice $invoice)
    {
        // Handle recurring payments with PayPal subscriptions
        throw new FOSSBilling\Exception('Recurring payments need to be implemented separately for PayPal Checkout');
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
                'logo' => 'paypal.png',
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