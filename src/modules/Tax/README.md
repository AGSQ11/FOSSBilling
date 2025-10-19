# FOSSBilling Tax Module - Enhanced Features Documentation

## Overview
The enhanced Tax module provides an advanced tax management system with complex rules, multi-jurisdiction support, and comprehensive reporting.

## Features

### Advanced Tax Rules
- **Multi-Jurisdiction Support**: Define tax rules for different countries, states, counties, and cities.
- **Compound Taxes**: Apply multiple taxes that are calculated on top of each other.
- **Thresholds**: Apply taxes only when a certain order or annual spending threshold is met.
- **Tax Exemptions**: Create client-specific tax exemptions with expiration dates.
- **Reverse Charge**: Handle reverse charge VAT for B2B transactions.
- **Product-Specific Rates**: Apply different tax rates to different product categories.
- **Client Group Restrictions**: Restrict tax rules to specific client groups.

### Tax Reporting and Auditing
- **Tax Reports**: Generate detailed tax reports for different periods.
- **Tax Liability Report**: View a summary of tax liability by jurisdiction.
- **Audit Trail**: Track all changes to tax rules and their application to invoices.

### Tax Automation
- **Automatic Tax Calculation**: Automatically calculate taxes based on client location and product type.
- **Tax Calendar**: Keep track of tax filing deadlines.
- **Tax Registration Validation**: Validate client tax registration numbers.

## Database Changes

### New Columns in `tax` Table
- `tax_type`
- `compound_tax`
- `threshold_amount`
- `threshold_type`
- `exempt_reason`
- `reverse_charge`
- `effective_from`
- `effective_to`
- `product_categories`
- `client_groups`
- `registration_required`
- `registration_format`

### New Tables
- `tax_exemption`
- `tax_category_mapping`
- `tax_client_group_mapping`
- `tax_jurisdiction`
- `tax_audit_log`
- `tax_calendar`
- `tax_registration`
- `tax_rate_history`

## API Endpoints

### Admin API
- `tax_create_advanced`
- `tax_update_advanced`
- `tax_get_client_tax_rate`
- `tax_calculate_compound_tax`
- `tax_calculate_reverse_charge_tax`
- `tax_get_client_exemptions`
- `tax_create_client_exemption`
- `tax_is_exemption_valid`
- `tax_apply_exemption`
- `tax_get_reports`
- `tax_get_liability_report`
- `tax_get_audit_trail`
- `tax_import_csv`
- `tax_export_csv`
- `tax_get_suggestions`
- `tax_validate_registration`
- `tax_get_calendar`

### Client API
- `tax_get_client_tax_rate`
- `tax_get_client_exemptions`

## Implementation Details
- **Complex Rules**: Tax rules are applied based on a hierarchy of conditions.
- **Jurisdiction Mapping**: Tax rules can be mapped to specific geographic jurisdictions.
- **Audit Trail**: All tax-related actions are logged for auditing purposes.

## Security Considerations
- All API endpoints have proper access control.
- Input is validated to prevent security vulnerabilities.
- Tax registration numbers are handled securely.

## Extending the Module
- Add new tax rule conditions and actions.
- Integrate with third-party tax calculation services.
- Create custom tax reports and dashboards.