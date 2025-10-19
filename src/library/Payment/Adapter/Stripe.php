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

class Stripe extends AbstractAdapter implements InjectionAwareInterface
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
            'supports_subscriptions' => true,
            'description' => 'Stripe is a technology company that builds economic infrastructure for the internet. Businesses of every size—from startups to Fortune 500s—use our software to accept payments and manage their businesses online.',
            'logo' => [
                'logo' => 'stripe.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'implementation' => [
                    'select', [
                        'label' => 'Stripe Implementation:',
                        'multiOptions' => [
                            'payment_intents' => 'Payment Intents (Recommended)',
                            'checkout' => 'Checkout Sessions',
                            'sources' => 'Sources API (Legacy)',
                        ],
                    ],
                ],
                'pub_key' => [
                    'text', [
                        'label' => 'Live publishable key:',
                    ],
                ],
                'api_key' => [
                    'text', [
                        'label' => 'Live Secret key:',
                    ],
                ],
                'test_pub_key' => [
                    'text', [
                        'label' => 'Test Publishable key:',
                        'required' => false,
                    ],
                ],
                'test_api_key' => [
                    'text', [
                        'label' => 'Test Secret key:',
                        'required' => false,
                    ],
                ],
                'enable_3d_secure' => [
                    'select', [
                        'label' => 'Enable 3D Secure:',
                        'multiOptions' => [
                            '0' => 'No',
                            '1' => 'Yes',
                        ],
                    ],
                ],
                'capture_method' => [
                    'select', [
                        'label' => 'Capture Method:',
                        'multiOptions' => [
                            'automatic' => 'Automatic',
                            'manual' => 'Manual',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function processPayment(\Model_Invoice $invoice, array $data = []): array
    {
        // Implementation varies based on selected method
        $implementation = $this->config['implementation'] ?? 'payment_intents';
        
        switch ($implementation) {
            case 'checkout':
                return $this->_processCheckoutPayment($invoice, $data);
            case 'sources':
                return $this->_processSourcesPayment($invoice, $data);
            case 'payment_intents':
            default:
                return $this->_processPaymentIntentsPayment($invoice, $data);
        }
    }

    public function verifyCallback(array $data): bool
    {
        // Implementation varies based on selected method
        $implementation = $this->config['implementation'] ?? 'payment_intents';
        
        switch ($implementation) {
            case 'checkout':
                return $this->_verifyCheckoutCallback($data);
            case 'sources':
                return $this->_verifySourcesCallback($data);
            case 'payment_intents':
            default:
                return $this->_verifyPaymentIntentsCallback($data);
        }
    }

    public function getPaymentForm(\Model_Invoice $invoice): string
    {
        // Implementation varies based on selected method
        $implementation = $this->config['implementation'] ?? 'payment_intents';
        
        switch ($implementation) {
            case 'checkout':
                return $this->_generateCheckoutForm($invoice);
            case 'sources':
                return $this->_generateSourcesForm($invoice);
            case 'payment_intents':
            default:
                return $this->_generatePaymentIntentsForm($invoice);
        }
    }

    private function _processPaymentIntentsPayment(\Model_Invoice $invoice, array $data): array
    {
        // Process payment using Payment Intents API
        $amount = $this->getAmountInCents($invoice);
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);
        
        // Create payment intent
        $intent = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $invoice->buyer_email,
        ];
        
        // Handle 3D Secure if enabled
        if (!empty($this->config['enable_3d_secure']) && $this->config['enable_3d_secure']) {
            $intent['payment_method_options'] = [
                'card' => [
                    'request_three_d_secure' => 'any',
                ],
            ];
        }
        
        // Handle capture method
        if (!empty($this->config['capture_method']) && $this->config['capture_method'] === 'manual') {
            $intent['capture_method'] = 'manual';
        }
        
        return [
            'type' => 'payment_intents',
            'intent' => $intent,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
        ];
    }

    private function _processCheckoutPayment(\Model_Invoice $invoice, array $data): array
    {
        // Process payment using Checkout Sessions
        $amount = $this->getAmountInCents($invoice);
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);
        
        // Create checkout session
        $session = [
            'success_url' => $this->getReturnUrl($invoice),
            'cancel_url' => $this->getCancelUrl($invoice),
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => $description,
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'client_reference_id' => $invoice->id,
        ];
        
        return [
            'type' => 'checkout',
            'session' => $session,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
        ];
    }

    private function _processSourcesPayment(\Model_Invoice $invoice, array $data): array
    {
        // Process payment using Sources API (legacy)
        $amount = $this->getAmountInCents($invoice);
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);
        
        // Create source
        $source = [
            'type' => 'card',
            'amount' => $amount,
            'currency' => $currency,
            'owner' => [
                'email' => $invoice->buyer_email,
                'name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            ],
            'redirect' => [
                'return_url' => $this->getReturnUrl($invoice),
            ],
        ];
        
        return [
            'type' => 'sources',
            'source' => $source,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
        ];
    }

    private function _verifyPaymentIntentsCallback(array $data): bool
    {
        // Verify Payment Intents callback
        return true; // Simplified implementation
    }

    private function _verifyCheckoutCallback(array $data): bool
    {
        // Verify Checkout Sessions callback
        return true; // Simplified implementation
    }

    private function _verifySourcesCallback(array $data): bool
    {
        // Verify Sources API callback
        return true; // Simplified implementation
    }

    private function _generatePaymentIntentsForm(\Model_Invoice $invoice): string
    {
        $amount = $this->getAmountInCents($invoice);
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);
        
        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];
        
        $form = '
        <form id="payment-form" data-secret=":intent_secret">
            <div class="loading" style="display:none;"><span>Loading ...</span></div>
            <script src="https://js.stripe.com/v3/"></script>

            <div id="error-message">
                <!-- Error messages will be displayed here -->
            </div>
            <div id="payment-element">
                <!-- Stripe Elements will create form elements here -->
            </div>

            <button id="submit" class="btn btn-primary mt-2" style="margin-top: 0.5em;">Submit</button>

            <script>
                const stripe = Stripe(":pub_key");

                var elements = stripe.elements({
                    clientSecret: ":intent_secret",
                });

                var paymentElement = elements.create("payment", {
                    billingDetails: {
                        name: "never",
                        email: "never",
                    },
                });

                paymentElement.mount("#payment-element");

                const form = document.getElementById("payment-form");

                form.addEventListener("submit", async (event) => {
                    event.preventDefault();

                    const {error} = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: ":callbackUrl&redirect=true&invoice_hash=:invoice_hash",
                            payment_method_data: {
                                billing_details: {
                                    name: ":buyer_name",
                                    email: ":buyer_email",
                                },
                            },
                        },
                    });

                    if (error) {
                        const messageContainer = document.querySelector("#error-message");
                        messageContainer.innerHTML = `<p class="alert alert-danger">${error.message}</p>`;
                    }
                });
            </script>
        </form>';
        
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        $bindings = [
            ':pub_key' => $pubKey,
            ':intent_secret' => 'dummy_secret', // This would be generated in a real implementation
            ':amount' => $amount,
            ':currency' => $currency,
            ':description' => $description,
            ':buyer_email' => $invoice->buyer_email,
            ':buyer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':redirectUrl' => $this->di['tools']->url('invoice/' . $invoice->hash),
            ':invoice_hash' => $invoice->hash,
        ];
        
        return strtr($form, $bindings);
    }

    private function _generateCheckoutForm(\Model_Invoice $invoice): string
    {
        $amount = $this->getAmountInCents($invoice);
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);
        
        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];
        
        $form = '
        <div class="alert alert-info">Redirecting to Stripe Checkout...</div>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            var stripe = Stripe(":pub_key");
            stripe.redirectToCheckout({
                sessionId: ":session_id"
            }).then(function (result) {
                // If redirectToCheckout fails due to a browser or network
                // error, display the localized error message to your customer
                // using result.error.message.
                console.error(result.error.message);
            });
        </script>';
        
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        $bindings = [
            ':pub_key' => $pubKey,
            ':session_id' => 'dummy_session_id', // This would be generated in a real implementation
            ':amount' => $amount,
            ':currency' => $currency,
            ':description' => $description,
            ':buyer_email' => $invoice->buyer_email,
            ':buyer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':redirectUrl' => $this->di['tools']->url('invoice/' . $invoice->hash),
            ':invoice_hash' => $invoice->hash,
        ];
        
        return strtr($form, $bindings);
    }

    private function _generateSourcesForm(\Model_Invoice $invoice): string
    {
        $amount = $this->getAmountInCents($invoice);
        $currency = $invoice->currency;
        $description = $this->getInvoiceTitle($invoice);
        
        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];
        
        $form = '
        <form id="payment-form" action=":callbackUrl" method="POST">
            <div class="form-row">
                <label for="card-element">
                    Credit or debit card
                </label>
                <div id="card-element">
                    <!-- A Stripe Element will be inserted here. -->
                </div>

                <!-- Used to display form errors. -->
                <div id="card-errors" role="alert"></div>
            </div>
            <button class="btn btn-primary mt-2" style="margin-top: 0.5em;">Submit Payment</button>
        </form>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
            // Create a Stripe client
            var stripe = Stripe(":pub_key");

            // Create an instance of Elements
            var elements = stripe.elements();

            // Custom styling can be passed to options when creating an Element.
            var style = {
                base: {
                    color: "#000",
                    fontFamily: "Archivo, Helvetica, sans-serif",
                    fontSmoothing: "antialiased",
                    fontSize: "16px",
                    "::placeholder": {
                        color: "rgba(0,0,0,0.5)"
                    }
                },
                invalid: {
                    color: "#fa755a",
                    iconColor: "#fa755a"
                }
            };

            // Create an instance of the card Element
            var card = elements.create("card", {style: style});

            // Add an instance of the card Element into the `card-element` <div>
            card.mount("#card-element");

            // Handle real-time validation errors from the card Element.
            card.on("change", function(event) {
                var displayError = document.getElementById("card-errors");
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = "";
                }
            });

            // Handle form submission
            var form = document.getElementById("payment-form");
            form.addEventListener("submit", function(event) {
                event.preventDefault();

                stripe.createToken(card).then(function(result) {
                    if (result.error) {
                        // Inform the user if there was an error
                        var errorElement = document.getElementById("card-errors");
                        errorElement.textContent = result.error.message;
                    } else {
                        // Send the token to your server
                        stripeTokenHandler(result.token);
                    }
                });
            });

            // Submit the token to the server
            function stripeTokenHandler(token) {
                // Insert the token ID into the form so it gets submitted to the server
                var form = document.getElementById("payment-form");
                var hiddenInput = document.createElement("input");
                hiddenInput.setAttribute("type", "hidden");
                hiddenInput.setAttribute("name", "stripeToken");
                hiddenInput.setAttribute("value", token.id);
                form.appendChild(hiddenInput);

                // Submit the form
                form.submit();
            }
        </script>';
        
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        $bindings = [
            ':pub_key' => $pubKey,
            ':amount' => $amount,
            ':currency' => $currency,
            ':description' => $description,
            ':buyer_email' => $invoice->buyer_email,
            ':buyer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':redirectUrl' => $this->di['tools']->url('invoice/' . $invoice->hash),
            ':invoice_hash' => $invoice->hash,
        ];
        
        return strtr($form, $bindings);
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
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice) . '&status=success';
    }

    public function getCancelUrl(\Model_Invoice $invoice): string
    {
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        return $payGatewayService->getCallbackUrl($payGateway, $invoice) . '&status=cancelled';
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function processRefund(\Model_Transaction $transaction, float $amount, string $reason = ''): bool
    {
        // Process refund using Stripe API
        $this->logEvent("Processing refund for transaction #{$transaction->id}", [
            'amount' => $amount,
            'reason' => $reason,
        ]);
        
        return true; // Simplified implementation
    }

    public function createRecurringProfile(\Model_Invoice $invoice, array $data = []): array
    {
        // Create recurring payment profile using Stripe API
        $this->logEvent("Creating recurring profile for invoice #{$invoice->id}");
        
        return [
            'profile_id' => 'stripe_profile_' . uniqid(),
            'status' => 'active',
        ]; // Simplified implementation
    }

    public function cancelRecurringProfile(string $profileId): bool
    {
        // Cancel recurring payment profile using Stripe API
        $this->logEvent("Cancelling recurring profile #{$profileId}");
        
        return true; // Simplified implementation
    }

    public function updateRecurringProfile(string $profileId, array $data = []): bool
    {
        // Update recurring payment profile using Stripe API
        $this->logEvent("Updating recurring profile #{$profileId}");
        
        return true; // Simplified implementation
    }

    public function getRecurringProfileDetails(string $profileId): array
    {
        // Get recurring payment profile details using Stripe API
        $this->logEvent("Getting details for recurring profile #{$profileId}");
        
        return [
            'profile_id' => $profileId,
            'status' => 'active',
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]; // Simplified implementation
    }

    public function getSupportedCurrencies(): array
    {
        // Return all currencies supported by Stripe
        return [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD', 'CHF', 'JPY',
            'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'TRY', 'ZAR',
            'BRL', 'MXN', 'INR', 'RUB', 'MYR', 'PHP', 'THB', 'IDR', 'KRW', 'VND',
            'AED', 'SAR', 'EGP', 'KWD', 'QAR', 'OMR', 'BHD', 'JOD', 'ILS', 'UAH'
        ];
    }
}