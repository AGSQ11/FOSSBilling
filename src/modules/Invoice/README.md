# FOSSBilling Invoice Module - Enhanced Features Documentation

## Overview
This document describes the enhanced features added to the FOSSBilling Invoice module, focusing on automated dunning management, late fee systems, and improved multi-currency support.

## New Features

### 1. Automated Dunning Management

#### Configuration Settings
- **Enable Dunning Management**: Toggle to enable/disable the dunning system
- **Dunning Grace Period**: Number of days after due date before starting dunning process
- **Dunning Cycles**: Comma-separated days after due date to send dunning notices (e.g., 1,3,7,14,30)

#### Dunning Process
- The system automatically checks for overdue invoices during the daily due event processing
- Different email templates are used based on the dunning level:
  - Regular notices for initial dunning cycles
  - Final notice for the last dunning cycle
- Dunning level is calculated based on days overdue and configured dunning cycles

#### Email Templates
- `mod_invoice_dunning_notice.html.twig`: Standard dunning notice template
- `mod_invoice_final_dunning.html.twig`: Final notice template for serious delinquencies

### 2. Late Fee System

#### Configuration Settings
- **Enable Late Fees**: Toggle to enable/disable late fee calculation
- **Late Fee Type**: Options include:
  - No late fees
  - Fixed amount
  - Percentage of invoice total
  - Fixed amount plus percentage
- **Late Fee Amount**: Fixed amount to charge as a late fee
- **Late Fee Percentage**: Percentage to charge as a late fee
- **Late Fee Grace Period**: Number of days after due date before applying late fee

#### Calculation
- Late fees are calculated dynamically based on invoice status and due date
- Fees are added to the total invoice amount
- Fees are displayed separately in invoice templates

### 3. Enhanced Invoice Templates

#### PDF Template Improvements
- Added paid date field
- Added late fee information
- Added currency exchange rate information
- Enhanced styling and layout

#### Client and Admin Template Improvements
- Added late fee display in the totals section
- Improved layout and readability
- Added additional invoice information fields

### 4. Multi-Currency Improvements
- Updated income calculation to include late fees and taxes
- Enhanced currency conversion handling in invoice totals
- Better exchange rate management

## API Endpoints

### Admin API Endpoints

#### `invoice_batch_process_dunning`
Process dunning for all overdue invoices.

#### `invoice_batch_apply_late_fees`
Apply late fees to all overdue invoices (calculated dynamically).

#### `invoice_get_dunning_level`
Get the dunning level for a specific invoice.

## Database Changes
No database schema changes were required. New functionality uses existing invoice fields and system parameters.

## Configuration Parameters
The following system parameters are used for the new features:
- `invoice_dunning_enabled` (0/1): Enable/disable dunning management
- `invoice_dunning_grace_period` (integer): Grace period in days
- `invoice_dunning_cycles` (string): Comma-separated dunning cycle days
- `invoice_late_fee_enabled` (0/1): Enable/disable late fees
- `invoice_late_fee_type` (string): Type of late fee ('none', 'fixed', 'percent', 'fixed_plus_percent')
- `invoice_late_fee_amount` (float): Fixed late fee amount
- `invoice_late_fee_percent` (float): Late fee percentage
- `invoice_late_fee_grace_period` (integer): Late fee grace period in days

## Integration Points
- Integrated with the existing due event system for automatic processing
- Compatible with existing payment processing workflows
- Works with existing multi-currency functionality
- Maintains existing email notification system integration

## Security Considerations
- All new API endpoints follow existing security patterns
- Configuration parameters are validated and sanitized
- No direct database modifications outside of existing patterns

## Upgrading
Existing installations will automatically have access to the new features. Default configuration values are provided for all new settings.

## Customization
- Email templates can be customized by creating custom templates
- Dunning cycles and late fee settings can be configured per installation
- Invoice templates can be customized following existing patterns