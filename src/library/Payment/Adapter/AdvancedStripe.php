<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Stripe\StripeClient;

class Payment_Adapter_AdvancedStripe implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    private StripeClient $stripe;

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
        if ($this->config['test_mode']) {
            if (!isset($this->config['test_api_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Advanced Stripe', ':missing' => 'Test API Key'], 4001);
            }
            if (!isset($this->config['test_pub_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Advanced Stripe', ':missing' => 'Test publishable key'], 4001);
            }

            $this->stripe = new StripeClient($this->config['test_api_key']);
        } else {
            if (!isset($this->config['api_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Advanced Stripe', ':missing' => 'API key'], 4001);
            }
            if (!isset($this->config['pub_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Advanced Stripe', ':missing' => 'Publishable key'], 4001);
            }

            $this->stripe = new StripeClient($this->config['api_key']);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Advanced Stripe implementation supporting multiple payment methods: Checkout, Payment Intents, and Sources. You authenticate to the Stripe API by providing one of your API keys in the request.',
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
                            'checkout' => 'Checkout Session',
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

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoice = $this->di['db']->load('Invoice', $invoice_id);

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

    public function logError($e, Model_Transaction $tx): void
    {
        $body = $e->getJsonBody();
        $err = $body['error'];
        $tx->txn_status = $err['type'];
        $tx->error = $err['message'];
        $tx->status = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);

        if (DEBUG) {
            error_log(json_encode($e->getJsonBody()));
        }

        throw new Exception($tx->error);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id): void
    {
        $tx = $this->di['db']->getExistingModelById('Transaction', $id);

        // Use the invoice ID associated with the transaction or else fallback to the ID passed via GET.
        if ($tx->invoice_id) {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $tx->invoice_id);
        } else {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $data['get']['invoice_id'] ?? null);
            if ($invoice) {
                $tx->invoice_id = $invoice->id;
            }
        }

        $implementation = $this->config['implementation'] ?? 'payment_intents';

        try {
            if ($implementation === 'checkout') {
                // Handle checkout session completion
                $sessionId = $data['get']['session_id'] ?? null;
                if ($sessionId) {
                    $session = $this->stripe->checkout->sessions->retrieve($sessionId);
                    $paymentIntentId = $session->payment_intent;
                } else {
                    // Get payment intent from GET parameters
                    $paymentIntentId = $data['get']['payment_intent'] ?? null;
                }

                if ($paymentIntentId) {
                    $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
                    $this->processPaymentIntent($paymentIntent, $tx, $invoice);
                }
            } elseif ($implementation === 'sources') {
                // Handle source-based payment
                $sourceId = $data['get']['source'] ?? null;
                if ($sourceId) {
                    $source = $this->stripe->sources->retrieve($sourceId);
                    $this->processSourcePayment($source, $tx, $invoice);
                }
            } else { // payment_intents (default)
                $paymentIntentId = $data['get']['payment_intent'] ?? null;
                if ($paymentIntentId) {
                    $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
                    $this->processPaymentIntent($paymentIntent, $tx, $invoice);
                }
            }
        } catch (Stripe\Exception\CardException|Stripe\Exception\InvalidRequestException|Stripe\Exception\AuthenticationException|Stripe\Exception\ApiConnectionException|Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $tx);

            throw new FOSSBilling\Exception('There was an error when processing the transaction');
        }
    }

    private function processPaymentIntent($paymentIntent, Model_Transaction $tx, ?Model_Invoice $invoice): void
    {
        $tx->txn_status = $paymentIntent->status;
        $tx->txn_id = $paymentIntent->id;
        $tx->amount = $paymentIntent->amount / 100;
        $tx->currency = $paymentIntent->currency;

        $bd = [
            'amount' => $tx->amount,
            'description' => 'Stripe transaction ' . $paymentIntent->id,
            'type' => 'transaction',
            'rel_id' => $tx->id,
        ];

        // Only pay the invoice if the transaction has 'succeeded' on Stripe's end & the associated FOSSBilling transaction hasn't been processed.
        if ($paymentIntent->status == 'succeeded' && $tx->status !== 'processed' && $invoice) {
            // Instance the services we need
            $clientService = $this->di['mod_service']('client');
            $invoiceService = $this->di['mod_service']('Invoice');

            // Update the account funds
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            // Now pay the invoice
            $invoiceService->payInvoiceWithCredits($invoice);
        }

        $paymentStatus = match ($paymentIntent->status) {
            'succeeded' => 'processed',
            'pending' => 'received',
            'failed' => 'error',
            default => 'received',
        };

        $tx->status = $paymentStatus;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function processSourcePayment($source, Model_Transaction $tx, ?Model_Invoice $invoice): void
    {
        $tx->txn_status = $source->status;
        $tx->txn_id = $source->id;
        $tx->amount = $source->amount / 100;
        $tx->currency = $source->currency;

        $bd = [
            'amount' => $tx->amount,
            'description' => 'Stripe source transaction ' . $source->id,
            'type' => 'transaction',
            'rel_id' => $tx->id,
        ];

        // Only process if source is chargeable
        if ($source->status == 'chargeable' && $invoice) {
            $clientService = $this->di['mod_service']('client');
            $invoiceService = $this->di['mod_service']('Invoice');

            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            $invoiceService->payInvoiceWithCredits($invoice);
            $tx->status = 'processed';
        } else {
            $tx->status = 'received';
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    protected function _generatePaymentIntentsForm(Model_Invoice $invoice): string
    {
        $captureMethod = ($this->config['capture_method'] ?? 'automatic') === 'manual' ? 'manual' : 'automatic';
        $setupFutureUsage = $this->config['setup_future_usage'] ?? null;

        $params = [
            'amount' => $this->getAmountInCents($invoice),
            'currency' => $invoice->currency,
            'description' => $this->getInvoiceTitle($invoice),
            'receipt_email' => $invoice->buyer_email,
            'capture_method' => $captureMethod,
        ];

        if ($setupFutureUsage) {
            $params['setup_future_usage'] = $setupFutureUsage;
        }

        $intent = $this->stripe->paymentIntents->create($params);

        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];

        $form = '<form id="payment-form" data-secret=":intent_secret">
                <div class="loading" style="display:none;"><span>{% trans \'Loading ...\' %}</span></div>
                <script src="https://js.stripe.com/v3/"></script>

                    <div id="error-message">
                        <!-- Error messages will be displayed here -->
                    </div>
                    <div id="payment-element">
                        <!-- Stripe Elements will create form elements here -->
                    </div>

                    <button id="submit" class="btn btn-primary mt-2" style="margin-top: 0.5em;">Submit Payment</button>

                <script>
                    const stripe = Stripe(\':pub_key\');

                    var elements = stripe.elements({
                        clientSecret: \':intent_secret\',
                        locale: \'auto\',
                      });

                    var paymentElement = elements.create(\'payment\', {
                        billingDetails: {
                            name: \'auto\',
                            email: \'auto\',
                        },
                    });

                    paymentElement.mount(\'#payment-element\');

                    const form = document.getElementById(\'payment-form\');

                    form.addEventListener(\'submit\', async (event) => {
                    event.preventDefault();

                    const {error} = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: \':callbackUrl&redirect=true&invoice_hash=:invoice_hash\',
                        },
                    });

                    if (error) {
                        const messageContainer = document.querySelector(\'#error-message\');
                        messageContainer.innerHTML = `<p class="alert alert-danger">${error.message}</p>`;
                    }
                    });
                  </script>
                </form>';

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "AdvancedStripe"');
        $bindings = [
            ':pub_key' => $pubKey,
            ':intent_secret' => $intent->client_secret,
            ':buyer_email' => $invoice->buyer_email,
            ':buyer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':invoice_hash' => $invoice->hash,
        ];

        return strtr($form, $bindings);
    }

    protected function _generateCheckoutForm(Model_Invoice $invoice): string
    {
        $params = [
            'success_url' => $this->di['tools']->url('/invoice/' . $invoice->hash . '?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => $this->di['tools']->url('/invoice/' . $invoice->hash),
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $invoice->currency,
                    'product_data' => [
                        'name' => $this->getInvoiceTitle($invoice),
                    ],
                    'unit_amount' => $this->getAmountInCents($invoice),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'client_reference_id' => $invoice->id, // Store invoice ID
        ];

        $session = $this->stripe->checkout->sessions->create($params);

        $form = '<div class="alert alert-info">Redirecting to Stripe Checkout...</div>';
        $form .= '<script src="https://js.stripe.com/v3/"></script>';
        $form .= '<script>
            var stripe = Stripe(\'' . (($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key']) . '\');
            stripe.redirectToCheckout({
                sessionId: \'' . $session->id . '\'
            }).then(function (result) {
                // If redirectToCheckout fails due to a browser or network
                // error, display the localized error message to your customer
                // using result.error.message.
                console.error(result.error.message);
            });
        </script>';

        return $form;
    }

    protected function _generateSourcesForm(Model_Invoice $invoice): string
    {
        $form = '<form id="payment-form" action=":callbackUrl" method="POST">
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
                    var stripe = Stripe(\':pub_key\');

                    // Create an instance of Elements
                    var elements = stripe.elements();

                    // Custom styling can be passed to options when creating an Element.
                    var style = {
                        base: {
                            color: "#000",
                            fontFamily: \'Archivo, Helvetica, sans-serif\',
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
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "AdvancedStripe"');
        $bindings = [
            ':pub_key' => ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'],
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
        ];

        return strtr($form, $bindings);
    }

    public function recurrentPayment(Model_Invoice $invoice)
    {
        // Handle recurring payments with Stripe
        throw new FOSSBilling\Exception('Recurring payments need to be implemented separately for Advanced Stripe');
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
                'logo' => 'stripe.png',
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