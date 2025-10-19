# Enhanced Client Management Features Documentation

## Overview
This document describes the enhanced client management features added to FOSSBilling, providing advanced client segmentation, communication tracking, and credit management capabilities.

## Features

### Client Tagging and Segmentation
Advanced client tagging system for better segmentation and targeted marketing:

#### Adding Client Tags
```php
$clientService = $di['mod_service']('client');
$clientService->addClientTags($clientId, ['premium', 'enterprise', 'usa']);
```

#### Removing Client Tags
```php
$clientService->removeClientTags($clientId, ['beta-tester']);
```

#### Getting Client Tags
```php
$tags = $clientService->getClientTags($clientId);
```

#### Client Segmentation by Tags
```php
$clients = $clientService->getClientsByTags(['premium', 'enterprise']);
```

### Client Communication History
Centralized view of all client communications:

#### Retrieving Communication History
```php
$history = $clientService->getClientCommunicationHistory($clientId);
```

This includes:
- Sent emails
- Support tickets
- Internal notes
- Chronologically sorted

### Client Credit Management
Advanced credit account system for client prepaid balances:

#### Creating Credit Account
```php
$clientService->createClientCreditAccount($clientId, 100.00, 'USD');
```

#### Adding Credit
```php
$clientService->addCreditToAccount($clientId, 50.00, 'Monthly credit addition');
```

#### Deducting Credit
```php
$clientService->deductCreditFromAccount($clientId, 25.00, 'Service payment');
```

#### Checking Credit Balance
```php
$balance = $clientService->getClientCreditBalance($clientId);
```

### Client Segmentation Analytics
Advanced analytics for client segmentation and targeting:

#### Getting Segmentation Data
```php
$analytics = $clientService->getClientSegmentationAnalytics();
```

This provides:
- Clients by country
- Clients by group
- Clients by registration period
- Clients by tags

## Database Changes

### New Column
A new `tags` column has been added to the `client` table:
```sql
ALTER TABLE `client` ADD COLUMN `tags` TEXT NULL DEFAULT NULL COMMENT 'JSON array of client tags for segmentation';
```

### Credit Account Management
Credit accounts are managed through the existing `client_balance` table with specific types:
- `credit_account` - Main credit account record
- `credit_addition` - Record of credit additions
- `credit_deduction` - Record of credit deductions

## API Usage Examples

### Adding Tags to a Client
```php
$api->admin->client_add_client_tags([
    'id' => 123,
    'tags' => ['premium', 'beta-tester']
]);
```

### Getting Client Communication History
```php
$history = $api->admin->client_get_client_communication_history([
    'id' => 123
]);
```

### Managing Client Credit
```php
// Create credit account
$api->admin->client_create_client_credit_account([
    'id' => 123,
    'initial_amount' => 100.00,
    'currency' => 'USD'
]);

// Add credit
$api->admin->client_add_credit_to_account([
    'id' => 123,
    'amount' => 50.00,
    'description' => 'Monthly credit'
]);

// Check balance
$balance = $api->admin->client_get_client_credit_balance([
    'id' => 123
]);
```

## Implementation Details

### Tag Storage
Client tags are stored as JSON arrays in the database:
```json
["premium", "enterprise", "usa"]
```

### Credit Account Implementation
Credit accounts use the existing `client_balance` table with:
- Type field to distinguish credit accounts from regular transactions
- Rel_id to link transactions to the main credit account
- Positive amounts for additions, negative for deductions

### Performance Considerations
- JSON queries are optimized for tag-based filtering
- Credit account lookups use indexed client_id fields
- Communication history queries are limited to recent items
- Segmentation analytics use aggregated queries

## Security Considerations
- All operations require appropriate permissions
- Input validation is performed on all tag values
- Credit operations include sufficient funds checking
- Communication history is filtered by client access rights

## Extending the Features
The enhanced client management features can be extended by:
- Adding new tag-based filtering options
- Implementing additional communication history sources
- Creating custom credit account types
- Adding new segmentation analytics dimensions

## Best Practices
- Use descriptive tags for meaningful segmentation
- Regularly review and clean up unused tags
- Monitor credit account balances for unusual activity
- Use communication history for customer service improvements
- Leverage segmentation analytics for targeted marketing campaigns