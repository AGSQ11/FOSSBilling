# FOSSBilling Payment Gateway System - Enhanced Features Documentation

## Overview
This document describes the enhanced payment gateway features added to FOSSBilling, expanding the system to support multiple payment methods including cryptocurrency, regional providers, and modern payment solutions.

## New Payment Gateways Added

### 1. Advanced Stripe Gateway
The Advanced Stripe gateway supports multiple implementation methods:

#### Implementation Options
- **Payment Intents** (Default, Recommended): Modern payment method with strong customer authentication
- **Checkout Sessions**: Pre-built payment UI for faster integration
- **Sources API** (Legacy): For backward compatibility

#### Configuration Settings
- **Implementation**: Choose between Payment Intents, Checkout, or Sources
- **Live/Test Keys**: API keys for production and testing environments
- **3D Secure**: Enable/disable 3D Secure authentication
- **Capture Method**: Automatic or manual payment capture

#### Features
- Support for multiple payment methods (cards, digital wallets)
- Strong Customer Authentication (SCA) compliance
- Automatic currency conversion
- Webhook support for real-time updates

### 2. Coinbase Commerce Gateway
Accept cryptocurrency payments with Coinbase Commerce.

#### Configuration Settings
- **API Key**: Your Coinbase Commerce API key
- **Webhook Secret**: Secret for verifying webhook requests
- **Test Mode**: Enable/disable test mode

#### Features
- Accept multiple cryptocurrencies (Bitcoin, Ethereum, Litecoin, etc.)
- Automatic conversion to fiat currency
- Real-time exchange rates
- Secure payment processing

### 3. PayU Gateway
Regional payment provider for Central and Eastern Europe, India, and Africa.

#### Configuration Settings
- **POS ID**: Your PayU Point of Sale ID
- **Second Key**: PayU security key
- **OAuth Credentials**: Client ID and Secret for API access
- **Environment**: Sandbox or Production

#### Features
- Multiple payment methods (cards, bank transfers, e-wallets)
- Local payment methods for regional markets
- Subscription support
- Fraud protection

### 4. Razorpay Gateway
Payment gateway for the Indian market.

#### Configuration Settings
- **Key ID**: Your Razorpay Key ID
- **Key Secret**: Your Razorpay Key Secret
- **Webhook Secret**: Secret for webhook verification

#### Features
- UPI payments
- Net banking
- Wallet support
- International cards
- Subscription management

### 5. PayPal Checkout Gateway
Modern PayPal implementation supporting multiple payment methods.

#### Configuration Settings
- **Client ID**: Your PayPal Client ID
- **Client Secret**: Your PayPal Client Secret
- **Environment**: Sandbox or Live
- **Intent**: Capture (immediate) or Authorize (deferred)

#### Features
- PayPal, Venmo, Apple Pay, Google Pay support
- Multiple payment methods in one integration
- Advanced fraud protection
- Subscription support

### 6. Alipay Gateway
Leading payment platform in China.

#### Configuration Settings
- **App ID**: Your Alipay App ID
- **Private Key**: RSA private key for signing
- **Alipay Public Key**: Alipay's public key for verification
- **Environment**: Sandbox or Production

#### Features
- Alipay wallet payments
- Bank card payments
- Cross-border commerce support
- Multiple Chinese payment methods

## Integration Points

### Admin Panel
- All new gateways appear in the payment gateway management section
- Individual configuration interfaces for each gateway
- Enable/disable controls for each gateway
- Test mode options where applicable

### Client Portal
- Payment method selection during checkout
- Gateway-specific UI elements loaded dynamically
- Secure payment processing

### Invoice System
- Gateway selection for each invoice
- Payment processing integration
- Transaction logging and tracking
- Automatic invoice status updates

## Security Considerations

### API Key Management
- All API keys are stored encrypted in the database
- Test and live keys are separated
- Keys are validated during configuration

### Webhook Security
- All webhook endpoints verify signatures
- Proper authentication mechanisms in place
- Rate limiting to prevent abuse

### PCI Compliance
- Payment data is not stored locally
- All sensitive data is processed through secure gateways
- Tokenization used where applicable

## Multi-Currency Support
All new gateways include multi-currency support:
- Automatic currency conversion
- Exchange rate handling
- Local currency display
- Accurate accounting entries

## Configuration Parameters
The new gateways use the following configuration parameters:
- API keys and secrets
- Webhook endpoints and secrets
- Test/live environment settings
- Gateway-specific options

## Customization
- Gateway-specific UI can be customized
- Webhook endpoints can be modified
- Currency options can be configured per gateway
- Custom fields can be added as needed

## Troubleshooting
- Check API key validity
- Verify webhook endpoints
- Review transaction logs
- Enable debug mode for detailed logging

## Upgrading
Existing installations will automatically detect and allow installation of the new gateways through the admin panel. No database changes are required as the new gateways use the existing payment gateway infrastructure.