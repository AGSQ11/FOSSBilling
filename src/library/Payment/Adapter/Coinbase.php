<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_Coinbase implements FOSSBilling\InjectionAwareInterface
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
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Coinbase Commerce', ':missing' => 'API Key'], 4001);
        }
        if (!isset($this->config['webhook_secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Coinbase Commerce', ':missing' => 'Webhook Secret'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Accept cryptocurrency payments with Coinbase Commerce. You can manage your API keys from your Coinbase Commerce dashboard.',
            'logo' => [
                'logo' => 'coinbase.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'api_key' => [
                    'text', [
                        'label' => 'API Key:',
                    ],
                ],
                'webhook_secret' => [
                    'text', [
                        'label' => 'Webhook Shared Secret:',
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

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoice = $this->di['db']->getExistingModelById('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceArr = $invoiceService->toApiArray($invoice);

        $data = [
            'name' => 'Payment for invoice #' . $invoiceArr['serie_nr'],
            'description' => $this->getInvoiceTitle($invoice),
            'local_price' => [
                'amount' => $invoiceService->getTotalWithTax($invoice),
                'currency' => $invoice->currency,
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceArr['serie_nr'],
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.commerce.coinbase.com/charges');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-CC-Api-Key: ' . $this->config['api_key'],
            'X-CC-Version: 2018-03-22',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            throw new FOSSBilling\Exception('Error creating Coinbase charge: ' . $response);
        }

        $charge = json_decode($response, true);
        $chargeUrl = $charge['data']['hosted_url'];

        $html = '<div class="alert alert-info">Redirecting to Coinbase to complete payment...</div>';
        $html .= '<script type="text/javascript">window.location.href = "' . $chargeUrl . '";</script>';

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

        // Get the webhook data
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);

        if (!$payload) {
            throw new FOSSBilling\Exception('Invalid payload received from Coinbase');
        }

        // Verify webhook signature
        $expectedSignature = $this->calculateWebhookSignature($rawPayload, $this->config['webhook_secret']);
        $actualSignature = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';

        if ($expectedSignature !== $actualSignature) {
            throw new FOSSBilling\Exception('Invalid webhook signature');
        }

        // Update transaction with Coinbase data
        $tx->txn_id = $payload['data']['id'];
        $tx->txn_status = $payload['data']['timeline'][count($payload['data']['timeline']) - 1]['status'];
        $tx->amount = $payload['data']['pricing']['local']['amount'];
        $tx->currency = $payload['data']['pricing']['local']['currency'];

        // Process payment based on status
        if ($tx->txn_status === 'CONFIRMED' || $tx->txn_status === 'RESOLVED') {
            // Payment is complete, process invoice
            $invoiceService = $this->di['mod_service']('Invoice');
            $clientService = $this->di['mod_service']('Client');

            // Get invoice
            $invoiceId = $payload['data']['metadata']['invoice_id'] ?? $data['get']['invoice_id'] ?? null;
            if (!$invoiceId) {
                throw new FOSSBilling\Exception('Invoice ID not found in Coinbase metadata');
            }

            $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

            // Add funds to client account
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService->addFunds($client, $tx->amount, 'Coinbase transaction ' . $tx->txn_id, [
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

    private function calculateWebhookSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public function recurrentPayment(Model_Invoice $invoice)
    {
        // Coinbase Commerce doesn't directly support recurring payments
        // This would require creating a separate subscription system
        throw new FOSSBilling\Exception('Recurring payments not supported by Coinbase Commerce adapter');
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
                'logo' => 'coinbase.png',
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