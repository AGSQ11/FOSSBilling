<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_Alipay implements FOSSBilling\InjectionAwareInterface
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
        if (!isset($this->config['app_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Alipay', ':missing' => 'App ID'], 4001);
        }
        if (!isset($this->config['private_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Alipay', ':missing' => 'Private Key'], 4001);
        }
        if (!isset($this->config['alipay_public_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Alipay', ':missing' => 'Alipay Public Key'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false, // Alipay subscriptions would need separate implementation
            'description' => 'Alipay is a leading third-party payment platform in China, enabling users to make secure online payments.',
            'logo' => [
                'logo' => 'alipay.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'app_id' => [
                    'text', [
                        'label' => 'App ID:',
                    ],
                ],
                'private_key' => [
                    'textarea', [
                        'label' => 'Private Key:',
                        'rows' => 5,
                    ],
                ],
                'alipay_public_key' => [
                    'textarea', [
                        'label' => 'Alipay Public Key:',
                        'rows' => 5,
                    ],
                ],
                'environment' => [
                    'select', [
                        'label' => 'Environment:',
                        'multiOptions' => [
                            'sandbox' => 'Sandbox (Test)',
                            'production' => 'Production',
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

        // Prepare Alipay parameters
        $params = [
            'app_id' => $this->config['app_id'],
            'method' => 'alipay.trade.page.pay',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $this->getNotifyUrl($invoice),
            'return_url' => $this->getReturnUrl($invoice),
            'biz_content' => json_encode([
                'out_trade_no' => 'INV' . $invoice->id . '_' . time(), // Unique order number
                'total_amount' => number_format($totalAmount, 2, '.', ''), // Amount
                'subject' => $this->getInvoiceTitle($invoice), // Order title
                'product_code' => 'FAST_INSTANT_TRADE_PAY', // Product code
            ], JSON_UNESCAPED_SLASHES),
        ];

        // Sign the parameters
        $params['sign'] = $this->generateSign($params);

        // Build the request URL
        $baseUrl = ($this->config['environment'] === 'sandbox') 
            ? 'https://openapi-sandbox.dl.alipaydev.com/gateway.do'
            : 'https://openapi.alipay.com/gateway.do';

        $requestUrl = $baseUrl . '?' . http_build_query($params);

        $html = '<div class="alert alert-info">Redirecting to Alipay to complete payment...</div>';
        $html .= '<script type="text/javascript">window.location.href = "' . $requestUrl . '";</script>';

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

        // Check if this is a return from Alipay or a notification (webhook)
        $isNotification = isset($_POST['trade_status']) || (isset($_GET['trade_status']) && $_GET['trade_status']);
        
        if ($isNotification) {
            // Process notification from Alipay
            $postData = $_POST;
            
            // Verify the signature
            if (!$this->verifySign($postData)) {
                throw new FOSSBilling\Exception('Invalid Alipay signature');
            }

            // Update transaction with Alipay data
            $tx->txn_id = $postData['trade_no'] ?? '';
            $tx->txn_status = $postData['trade_status'] ?? '';
            $tx->amount = $postData['total_amount'] ?? 0;
            $tx->currency = $postData['currency'] ?? $postData['trans_currency'] ?? 'CNY'; // Default to CNY

            // Process payment based on status
            if ($tx->txn_status === 'TRADE_SUCCESS' || $tx->txn_status === 'TRADE_FINISHED') {
                $invoiceService = $this->di['mod_service']('Invoice');
                $clientService = $this->di['mod_service']('Client');

                // Extract invoice ID from out_trade_no (format: INV{id}_{timestamp})
                $outTradeNo = $postData['out_trade_no'] ?? '';
                $parts = explode('_', $outTradeNo);
                $invoiceId = str_replace('INV', '', $parts[0] ?? '');

                if (!$invoiceId) {
                    throw new FOSSBilling\Exception('Invoice ID not found in Alipay data');
                }

                $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

                // Add funds to client account
                $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
                $clientService->addFunds($client, $tx->amount, 'Alipay transaction ' . $tx->txn_id, [
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
            // This might be a return from the payment page
            $getData = $_GET;
            
            // Verify the signature
            if (isset($getData['sign'])) {
                unset($getData['CSRFToken']); // Remove CSRF token from verification
                if (!$this->verifySign($getData)) {
                    throw new FOSSBilling\Exception('Invalid Alipay signature on return');
                }

                // Update transaction with Alipay data
                $tx->txn_id = $getData['trade_no'] ?? '';
                $tx->txn_status = $getData['trade_status'] ?? '';
                $tx->amount = $getData['total_amount'] ?? 0;
                $tx->currency = $getData['currency'] ?? 'CNY';

                if ($tx->txn_status === 'TRADE_SUCCESS' || $tx->txn_status === 'TRADE_FINISHED') {
                    $invoiceService = $this->di['mod_service']('Invoice');
                    $clientService = $this->di['mod_service']('Client');

                    // Extract invoice ID from out_trade_no
                    $outTradeNo = $getData['out_trade_no'] ?? '';
                    $parts = explode('_', $outTradeNo);
                    $invoiceId = str_replace('INV', '', $parts[0] ?? '');

                    if (!$invoiceId) {
                        throw new FOSSBilling\Exception('Invoice ID not found in Alipay return data');
                    }

                    $invoice = $this->di['db']->getExistingModelById('Invoice', $invoiceId);

                    // Add funds to client account
                    $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
                    $clientService->addFunds($client, $tx->amount, 'Alipay transaction ' . $tx->txn_id, [
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

    private function generateSign(array $params): string
    {
        // Remove sign field if present
        unset($params['sign']);
        
        // Sort parameters by key
        ksort($params);
        
        // Build query string
        $queryString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $key !== 'sign') {
                $queryString .= $key . '=' . $value . '&';
            }
        }
        
        // Remove trailing &
        $queryString = rtrim($queryString, '&');
        
        // Sign the string with private key
        $privateKey = $this->config['private_key'];
        $privateKeyId = openssl_pkey_get_private($privateKey);
        
        $signature = '';
        openssl_sign($queryString, $signature, $privateKeyId, OPENSSL_ALGO_SHA256);
        
        // Free the key resource
        openssl_pkey_free($privateKeyId);
        
        return base64_encode($signature);
    }

    private function verifySign(array $params): bool
    {
        $sign = $params['sign'] ?? '';
        if (empty($sign)) {
            return false;
        }
        
        // Remove sign field
        unset($params['sign']);
        
        // Sort parameters by key
        ksort($params);
        
        // Build query string
        $queryString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $key !== 'sign') {
                $queryString .= $key . '=' . $value . '&';
            }
        }
        
        // Remove trailing &
        $queryString = rtrim($queryString, '&');
        
        // Verify the signature
        $publicKey = $this->config['alipay_public_key'];
        $publicKeyId = openssl_pkey_get_public($publicKey);
        
        $result = openssl_verify($queryString, base64_decode($sign), $publicKeyId, OPENSSL_ALGO_SHA256);
        
        // Free the key resource
        openssl_pkey_free($publicKeyId);
        
        return $result === 1;
    }

    private function getNotifyUrl(Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Alipay"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice);
    }

    private function getReturnUrl(Model_Invoice $invoice): string
    {
        return $this->di['tools']->url('/invoice/' . $invoice->hash);
    }

    public function recurrentPayment(Model_Invoice $invoice)
    {
        // Alipay recurring payments would require separate implementation
        throw new FOSSBilling\Exception('Recurring payments need to be implemented separately for Alipay');
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
                'logo' => 'alipay.png',
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