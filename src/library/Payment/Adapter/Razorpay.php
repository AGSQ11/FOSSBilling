<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_Razorpay implements FOSSBilling\InjectionAwareInterface
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
        if (!isset($this->config['key_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Razorpay', ':missing' => 'Key ID'], 4001);
        }
        if (!isset($this->config['key_secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Razorpay', ':missing' => 'Key Secret'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Razorpay is India\'s only integrated payment processor, enabling businesses to accept, process and disburse payments with its product suite.',
            'logo' => [
                'logo' => 'razorpay.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'key_id' => [
                    'text', [
                        'label' => 'Key ID:',
                    ],
                ],
                'key_secret' => [
                    'text', [
                        'label' => 'Key Secret:',
                    ],
                ],
                'webhook_secret' => [
                    'text', [
                        'label' => 'Webhook Secret:',
                        'required' => false,
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

        $amount = (int)($invoiceService->getTotalWithTax($invoice) * 100); // Amount in paise
        $orderId = $this->createRazorpayOrder($invoice, $amount);

        $html = '
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            var options = {
                "key": "' . $this->config['key_id'] . '",
                "amount": "' . $amount . '",
                "currency": "' . $invoice->currency . '",
                "name": "Your Company Name",
                "description": "' . $this->getInvoiceTitle($invoice) . '",
                "order_id": "' . $orderId . '",
                "handler": function (response){
                    // Send the payment details to the server
                    var form = document.createElement("form");
                    form.method = "POST";
                    form.action = "' . $this->getNotifyUrl($invoice) . '";
                    
                    var params = {
                        "razorpay_payment_id": response.razorpay_payment_id,
                        "razorpay_order_id": response.razorpay_order_id,
                        "razorpay_signature": response.razorpay_signature
                    };
                    
                    for(var key in params) {
                        var hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.name = key;
                        hiddenField.value = params[key];
                        form.appendChild(hiddenField);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                },
                "prefill": {
                    "name": "' . trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name) . '",
                    "email": "' . $invoice->buyer_email . '",
                    "contact": "' . preg_replace('/[^0-9]/', '', $invoice->buyer_phone) . '"
                },
                "theme": {
                    "color": "#F37254"
                }
            };
            var rzp1 = new Razorpay(options);
            rzp1.open();
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

        // Check if this is a webhook call or a return from checkout
        $paymentId = $data['post']['razorpay_payment_id'] ?? null;
        $orderId = $data['post']['razorpay_order_id'] ?? null;
        $signature = $data['post']['razorpay_signature'] ?? null;

        if ($paymentId && $orderId && $signature) {
            // Validate the signature
            $this->validateSignature($orderId, $paymentId, $signature);

            // Get order details from Razorpay
            $orderDetails = $this->getOrderDetails($orderId);

            // Update transaction
            $tx->txn_id = $paymentId;
            $tx->txn_status = 'captured';
            $tx->amount = $orderDetails['amount'] / 100; // Convert from paise
            $tx->currency = $orderDetails['currency'];

            // Process payment
            $invoiceService = $this->di['mod_service']('Invoice');
            $clientService = $this->di['mod_service']('Client');

            // Extract invoice ID from order notes
            $invoiceId = $orderDetails['notes']['invoice_id'] ?? null;
            if (!$invoiceId) {
                throw new FOSSBilling\Exception('Invoice ID not found in Razorpay order');
            }

            $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

            // Add funds to client account
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService->addFunds($client, $tx->amount, 'Razorpay transaction ' . $tx->txn_id, [
                'type' => 'transaction',
                'rel_id' => $tx->id,
            ]);

            // Pay the invoice
            $invoiceService->payInvoiceWithCredits($invoice);

            $tx->status = 'processed';
        } else {
            // This might be a webhook call - process accordingly
            $rawPayload = file_get_contents('php://input');
            $payload = json_decode($rawPayload, true);

            if (isset($payload['payload']['payment']['entity']['id'])) {
                $paymentId = $payload['payload']['payment']['entity']['id'];
                $orderId = $payload['payload']['payment']['entity']['order_id'];
                $tx->txn_id = $paymentId;
                $tx->txn_status = $payload['payload']['payment']['entity']['status'];
                $tx->amount = $payload['payload']['payment']['entity']['amount'] / 100; // Convert from paise
                $tx->currency = $payload['payload']['payment']['entity']['currency'];

                // Process payment based on status
                if ($tx->txn_status === 'captured') {
                    $invoiceService = $this->di['mod_service']('Invoice');
                    $clientService = $this->di['mod_service']('Client');

                    // Extract invoice ID from order notes
                    $invoiceId = $payload['payload']['order']['entity']['notes']['invoice_id'] ?? null;
                    if (!$invoiceId) {
                        throw new FOSSBilling\Exception('Invoice ID not found in Razorpay order');
                    }

                    $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

                    // Add funds to client account
                    $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
                    $clientService->addFunds($client, $tx->amount, 'Razorpay transaction ' . $tx->txn_id, [
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

    private function createRazorpayOrder(Model_Invoice $invoice, int $amount): string
    {
        $data = [
            'amount' => $amount,
            'currency' => $invoice->currency,
            'receipt' => 'inv_' . $invoice->id,
            'notes' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['key_id'] . ':' . $this->config['key_secret']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new FOSSBilling\Exception('Error creating Razorpay order: ' . $response);
        }

        $order = json_decode($response, true);
        return $order['id'];
    }

    private function validateSignature(string $orderId, string $paymentId, string $signature): void
    {
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->config['key_secret']);

        if ($expectedSignature !== $signature) {
            throw new FOSSBilling\Exception('Invalid Razorpay signature');
        }
    }

    private function getOrderDetails(string $orderId): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders/' . $orderId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['key_id'] . ':' . $this->config['key_secret']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new FOSSBilling\Exception('Error getting Razorpay order details: ' . $response);
        }

        return json_decode($response, true);
    }

    private function getNotifyUrl(Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Razorpay"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice);
    }

    public function recurrentPayment(Model_Invoice $invoice)
    {
        // Handle recurring payments with Razorpay subscriptions
        throw new FOSSBilling\Exception('Recurring payments need to be implemented separately for Razorpay');
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
                'logo' => 'razorpay.png',
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