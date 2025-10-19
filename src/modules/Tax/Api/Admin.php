<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Tax management.
 */

namespace Box\Mod\Tax\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of tax rules.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        [$sql, $params] = $service->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('Tax', $item['id'], 'Tax rule not found');
            $pager['list'][$key] = $this->getService()->toApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get tax rule details.
     *
     * @param array $data
     *
     * @return array
     */
    public function get($data)
    {
        $required = [
            'id' => 'Tax rule ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Create new tax rule.
     *
     * @param array $data
     *
     * @return int - new tax rule ID
     */
    public function create($data)
    {
        $required = [
            'name' => 'Tax rule name is required',
            'taxrate' => 'Tax rate is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createTaxRule($data);
    }

    /**
     * Update tax rule.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update($data)
    {
        $required = [
            'id' => 'Tax rule ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        return $this->getService()->updateTaxRule($model, $data);
    }

    /**
     * Delete tax rule.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Tax rule ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        return $this->getService()->deleteTaxRule($model);
    }

    /**
     * Create advanced tax rule with complex calculations.
     *
     * @param array $data
     *
     * @return int - new tax rule ID
     */
    public function create_advanced($data)
    {
        $required = [
            'name' => 'Tax rule name is required',
            'taxrate' => 'Tax rate is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createAdvancedTaxRule($data);
    }

    /**
     * Update advanced tax rule.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_advanced($data)
    {
        $required = [
            'id' => 'Tax rule ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        return $this->getService()->updateAdvancedTaxRule($model, $data);
    }

    /**
     * Get tax rate for client.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_client_tax_rate($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $title = null;

        $result = $this->getService()->getAdvancedTaxRateForClient($client, $data, $title);

        return array_merge($result, ['title' => $title]);
    }

    /**
     * Calculate compound tax.
     *
     * @param array $data
     *
     * @return array
     */
    public function calculate_compound_tax($data)
    {
        $required = [
            'base_amount' => 'Base amount is required',
            'tax_rates' => 'Tax rates are required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $baseAmount = $data['base_amount'];
        $taxRates = $data['tax_rates'];

        return $this->getService()->calculateCompoundTax($baseAmount, $taxRates);
    }

    /**
     * Calculate reverse charge tax.
     *
     * @param array $data
     *
     * @return array
     */
    public function calculate_reverse_charge_tax($data)
    {
        $required = [
            'base_amount' => 'Base amount is required',
            'tax_rate' => 'Tax rate is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $baseAmount = $data['base_amount'];
        $taxRate = $data['tax_rate'];

        return $this->getService()->calculateReverseChargeTax($baseAmount, $taxRate);
    }

    /**
     * Get client tax exemptions.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_client_exemptions($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');

        return $this->getService()->getClientTaxExemptions($client);
    }

    /**
     * Create client tax exemption.
     *
     * @param array $data
     *
     * @return int - exemption ID
     */
    public function create_client_exemption($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');

        return $this->getService()->createClientTaxExemption($client, $data);
    }

    /**
     * Check if tax exemption is valid.
     *
     * @param array $data
     *
     * @return bool
     */
    public function is_exemption_valid($data)
    {
        $required = [
            'exemption_id' => 'Exemption ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $exemption = $this->di['db']->getExistingModelById('TaxExemption', $data['exemption_id'], 'Exemption not found');

        return $this->getService()->isTaxExemptionValid($exemption);
    }

    /**
     * Apply tax exemption to invoice.
     *
     * @param array $data
     *
     * @return bool
     */
    public function apply_exemption($data)
    {
        $required = [
            'invoice_id' => 'Invoice ID is required',
            'exemption_id' => 'Exemption ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $invoice = $this->di['db']->getExistingModelById('Invoice', $data['invoice_id'], 'Invoice not found');
        $exemption = $this->di['db']->getExistingModelById('TaxExemption', $data['exemption_id'], 'Exemption not found');

        return $this->getService()->applyTaxExemption($invoice, $exemption);
    }

    /**
     * Get tax reports.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_reports($data)
    {
        return $this->getService()->getTaxReports($data);
    }

    /**
     * Get tax liability report.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_liability_report($data)
    {
        return $this->getService()->getTaxLiabilityReport($data);
    }

    /**
     * Get tax audit trail.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_audit_trail($data)
    {
        $startDate = $data['start_date'] ?? date('Y-01-01');
        $endDate = $data['end_date'] ?? date('Y-12-31');
        $clientId = $data['client_id'] ?? null;

        return $this->getService()->getTaxAuditTrail([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'client_id' => $clientId,
        ]);
    }

    /**
     * Import tax rules from CSV.
     *
     * @param array $data
     *
     * @return array
     */
    public function import_csv($data)
    {
        $required = [
            'csv_file' => 'CSV file path is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $csvFilePath = $data['csv_file'];

        return $this->getService()->importTaxRulesFromCsv($csvFilePath);
    }

    /**
     * Export tax rules to CSV.
     *
     * @param array $data
     *
     * @return bool
     */
    public function export_csv($data)
    {
        $required = [
            'csv_file' => 'CSV file path is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $csvFilePath = $data['csv_file'];

        return $this->getService()->exportTaxRulesToCsv($csvFilePath);
    }

    /**
     * Get tax rule suggestions based on client location.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_suggestions($data)
    {
        $required = [
            'country' => 'Country is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $country = $data['country'];
        $state = $data['state'] ?? null;

        return $this->getService()->getTaxRuleSuggestions($country, $state);
    }

    /**
     * Validate tax registration number.
     *
     * @param array $data
     *
     * @return bool
     */
    public function validate_registration($data)
    {
        $required = [
            'registration_number' => 'Registration number is required',
            'country' => 'Country is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registrationNumber = $data['registration_number'];
        $country = $data['country'];

        return $this->getService()->validateTaxRegistration($registrationNumber, $country);
    }

    /**
     * Get tax calendar for filing deadlines.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_calendar($data)
    {
        $country = $data['country'] ?? null;
        $year = $data['year'] ?? null;

        return $this->getService()->getTaxCalendar($country, $year);
    }

    /**
     * Create tax registration for client.
     *
     * @param array $data
     *
     * @return int - registration ID
     */
    public function create_registration($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
            'country' => 'Country is required',
            'registration_number' => 'Registration number is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');

        $registration = $this->di['db']->dispense('TaxRegistration');
        $registration->client_id = $client->id;
        $registration->country = $data['country'];
        $registration->registration_number = $data['registration_number'];
        $registration->registration_type = $data['registration_type'] ?? 'vat';
        $registration->valid_from = !empty($data['valid_from']) ? date('Y-m-d H:i:s', strtotime($data['valid_from'])) : null;
        $registration->valid_to = !empty($data['valid_to']) ? date('Y-m-d H:i:s', strtotime($data['valid_to'])) : null;
        $registration->is_verified = $data['is_verified'] ?? 0;
        $registration->verification_date = !empty($data['verification_date']) ? date('Y-m-d H:i:s', strtotime($data['verification_date'])) : null;
        $registration->verified_by = $data['verified_by'] ?? null;
        $registration->created_at = date('Y-m-d H:i:s');
        $registration->updated_at = date('Y-m-d H:i:s');
        $registrationId = $this->di['db']->store($registration);

        $this->di['logger']->info('Created tax registration #%s for client #%s', $registrationId, $client->id);

        return $registrationId;
    }

    /**
     * Update tax registration for client.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_registration($data)
    {
        $required = [
            'id' => 'Registration ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registration = $this->di['db']->getExistingModelById('TaxRegistration', $data['id'], 'Registration not found');
        $registration->country = $data['country'] ?? $registration->country;
        $registration->registration_number = $data['registration_number'] ?? $registration->registration_number;
        $registration->registration_type = $data['registration_type'] ?? $registration->registration_type;
        $registration->valid_from = !empty($data['valid_from']) ? date('Y-m-d H:i:s', strtotime($data['valid_from'])) : $registration->valid_from;
        $registration->valid_to = !empty($data['valid_to']) ? date('Y-m-d H:i:s', strtotime($data['valid_to'])) : $registration->valid_to;
        $registration->is_verified = $data['is_verified'] ?? $registration->is_verified;
        $registration->verification_date = !empty($data['verification_date']) ? date('Y-m-d H:i:s', strtotime($data['verification_date'])) : $registration->verification_date;
        $registration->verified_by = $data['verified_by'] ?? $registration->verified_by;
        $registration->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($registration);

        $this->di['logger']->info('Updated tax registration #%s', $registration->id);

        return true;
    }

    /**
     * Delete tax registration.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_registration($data)
    {
        $required = [
            'id' => 'Registration ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registration = $this->di['db']->getExistingModelById('TaxRegistration', $data['id'], 'Registration not found');
        $registrationId = $registration->id;
        $this->di['db']->trash($registration);

        $this->di['logger']->info('Deleted tax registration #%s', $registrationId);

        return true;
    }

    /**
     * Get tax registrations for client.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_client_registrations($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');

        $registrations = $this->di['db']->find('TaxRegistration', 'client_id = :client_id', [':client_id' => $client->id]);

        $result = [];
        foreach ($registrations as $registration) {
            $result[] = $this->di['db']->toArray($registration);
        }

        return $result;
    }

    /**
     * Create tax rate history entry.
     *
     * @param array $data
     *
     * @return int - history ID
     */
    public function create_rate_history($data)
    {
        $required = [
            'tax_id' => 'Tax rule ID is required',
            'old_rate' => 'Old rate is required',
            'new_rate' => 'New rate is required',
            'effective_date' => 'Effective date is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $history = $this->di['db']->dispense('TaxRateHistory');
        $history->tax_id = $data['tax_id'];
        $history->old_rate = $data['old_rate'];
        $history->new_rate = $data['new_rate'];
        $history->effective_date = date('Y-m-d H:i:s', strtotime($data['effective_date']));
        $history->reason = $data['reason'] ?? null;
        $history->changed_by = $this->getIdentity()->id;
        $history->created_at = date('Y-m-d H:i:s');
        $historyId = $this->di['db']->store($history);

        $this->di['logger']->info('Created tax rate history #%s for tax rule #%s', $historyId, $data['tax_id']);

        return $historyId;
    }

    /**
     * Get tax rate history.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_rate_history($data)
    {
        $required = [
            'tax_id' => 'Tax rule ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $history = $this->di['db']->find('TaxRateHistory', 'tax_id = :tax_id ORDER BY effective_date DESC', [':tax_id' => $data['tax_id']]);

        $result = [];
        foreach ($history as $entry) {
            $result[] = $this->di['db']->toArray($entry);
        }

        return $result;
    }

    /**
     * Create tax jurisdiction.
     *
     * @param array $data
     *
     * @return int - jurisdiction ID
     */
    public function create_jurisdiction($data)
    {
        $required = [
            'tax_id' => 'Tax rule ID is required',
            'jurisdiction_type' => 'Jurisdiction type is required',
            'jurisdiction_code' => 'Jurisdiction code is required',
            'jurisdiction_name' => 'Jurisdiction name is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $jurisdiction = $this->di['db']->dispense('TaxJurisdiction');
        $jurisdiction->tax_id = $data['tax_id'];
        $jurisdiction->jurisdiction_type = $data['jurisdiction_type'];
        $jurisdiction->jurisdiction_code = $data['jurisdiction_code'];
        $jurisdiction->jurisdiction_name = $data['jurisdiction_name'];
        $jurisdiction->parent_jurisdiction_id = $data['parent_jurisdiction_id'] ?? null;
        $jurisdiction->created_at = date('Y-m-d H:i:s');
        $jurisdiction->updated_at = date('Y-m-d H:i:s');
        $jurisdictionId = $this->di['db']->store($jurisdiction);

        $this->di['logger']->info('Created tax jurisdiction #%s for tax rule #%s', $jurisdictionId, $data['tax_id']);

        return $jurisdictionId;
    }

    /**
     * Update tax jurisdiction.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_jurisdiction($data)
    {
        $required = [
            'id' => 'Jurisdiction ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $jurisdiction = $this->di['db']->getExistingModelById('TaxJurisdiction', $data['id'], 'Jurisdiction not found');
        $jurisdiction->jurisdiction_type = $data['jurisdiction_type'] ?? $jurisdiction->jurisdiction_type;
        $jurisdiction->jurisdiction_code = $data['jurisdiction_code'] ?? $jurisdiction->jurisdiction_code;
        $jurisdiction->jurisdiction_name = $data['jurisdiction_name'] ?? $jurisdiction->jurisdiction_name;
        $jurisdiction->parent_jurisdiction_id = $data['parent_jurisdiction_id'] ?? $jurisdiction->parent_jurisdiction_id;
        $jurisdiction->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($jurisdiction);

        $this->di['logger']->info('Updated tax jurisdiction #%s', $jurisdiction->id);

        return true;
    }

    /**
     * Delete tax jurisdiction.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_jurisdiction($data)
    {
        $required = [
            'id' => 'Jurisdiction ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $jurisdiction = $this->di['db']->getExistingModelById('TaxJurisdiction', $data['id'], 'Jurisdiction not found');
        $jurisdictionId = $jurisdiction->id;
        $this->di['db']->trash($jurisdiction);

        $this->di['logger']->info('Deleted tax jurisdiction #%s', $jurisdictionId);

        return true;
    }

    /**
     * Get tax jurisdictions.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_jurisdictions($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $jurisdictionType = $data['jurisdiction_type'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($jurisdictionType) {
            $where .= ' AND jurisdiction_type = :jurisdiction_type';
            $params[':jurisdiction_type'] = $jurisdictionType;
        }

        $jurisdictions = $this->di['db']->find('TaxJurisdiction', "$where ORDER BY jurisdiction_name", $params);

        $result = [];
        foreach ($jurisdictions as $jurisdiction) {
            $result[] = $this->di['db']->toArray($jurisdiction);
        }

        return $result;
    }

    /**
     * Create tax category mapping.
     *
     * @param array $data
     *
     * @return int - mapping ID
     */
    public function create_category_mapping($data)
    {
        $required = [
            'tax_id' => 'Tax rule ID is required',
            'category_id' => 'Category ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Check if mapping already exists
        $existing = $this->di['db']->findOne('TaxCategoryMapping', 'tax_id = :tax_id AND category_id = :category_id', [
            ':tax_id' => $data['tax_id'],
            ':category_id' => $data['category_id'],
        ]);

        if ($existing) {
            throw new \FOSSBilling\Exception('Tax category mapping already exists');
        }

        $mapping = $this->di['db']->dispense('TaxCategoryMapping');
        $mapping->tax_id = $data['tax_id'];
        $mapping->category_id = $data['category_id'];
        $mapping->created_at = date('Y-m-d H:i:s');
        $mappingId = $this->di['db']->store($mapping);

        $this->di['logger']->info('Created tax category mapping #%s', $mappingId);

        return $mappingId;
    }

    /**
     * Delete tax category mapping.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_category_mapping($data)
    {
        $required = [
            'id' => 'Mapping ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $mapping = $this->di['db']->getExistingModelById('TaxCategoryMapping', $data['id'], 'Mapping not found');
        $mappingId = $mapping->id;
        $this->di['db']->trash($mapping);

        $this->di['logger']->info('Deleted tax category mapping #%s', $mappingId);

        return true;
    }

    /**
     * Get tax category mappings.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_category_mappings($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $categoryId = $data['category_id'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($categoryId) {
            $where .= ' AND category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $mappings = $this->di['db']->find('TaxCategoryMapping', "$where ORDER BY tax_id", $params);

        $result = [];
        foreach ($mappings as $mapping) {
            $result[] = $this->di['db']->toArray($mapping);
        }

        return $result;
    }

    /**
     * Create tax client group mapping.
     *
     * @param array $data
     *
     * @return int - mapping ID
     */
    public function create_client_group_mapping($data)
    {
        $required = [
            'tax_id' => 'Tax rule ID is required',
            'client_group_id' => 'Client group ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Check if mapping already exists
        $existing = $this->di['db']->findOne('TaxClientGroupMapping', 'tax_id = :tax_id AND client_group_id = :client_group_id', [
            ':tax_id' => $data['tax_id'],
            ':client_group_id' => $data['client_group_id'],
        ]);

        if ($existing) {
            throw new \FOSSBilling\Exception('Tax client group mapping already exists');
        }

        $mapping = $this->di['db']->dispense('TaxClientGroupMapping');
        $mapping->tax_id = $data['tax_id'];
        $mapping->client_group_id = $data['client_group_id'];
        $mapping->created_at = date('Y-m-d H:i:s');
        $mappingId = $this->di['db']->store($mapping);

        $this->di['logger']->info('Created tax client group mapping #%s', $mappingId);

        return $mappingId;
    }

    /**
     * Delete tax client group mapping.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_client_group_mapping($data)
    {
        $required = [
            'id' => 'Mapping ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $mapping = $this->di['db']->getExistingModelById('TaxClientGroupMapping', $data['id'], 'Mapping not found');
        $mappingId = $mapping->id;
        $this->di['db']->trash($mapping);

        $this->di['logger']->info('Deleted tax client group mapping #%s', $mappingId);

        return true;
    }

    /**
     * Get tax client group mappings.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_client_group_mappings($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $clientGroupId = $data['client_group_id'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($clientGroupId) {
            $where .= ' AND client_group_id = :client_group_id';
            $params[':client_group_id'] = $clientGroupId;
        }

        $mappings = $this->di['db']->find('TaxClientGroupMapping', "$where ORDER BY tax_id", $params);

        $result = [];
        foreach ($mappings as $mapping) {
            $result[] = $this->di['db']->toArray($mapping);
        }

        return $result;
    }

    /**
     * Create tax calendar event.
     *
     * @param array $data
     *
     * @return int - calendar ID
     */
    public function create_calendar_event($data)
    {
        $required = [
            'country' => 'Country is required',
            'tax_type' => 'Tax type is required',
            'description' => 'Description is required',
            'due_date' => 'Due date is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $calendar = $this->di['db']->dispense('TaxCalendar');
        $calendar->country = $data['country'];
        $calendar->tax_type = $data['tax_type'];
        $calendar->description = $data['description'];
        $calendar->due_date = date('Y-m-d H:i:s', strtotime($data['due_date']));
        $calendar->deadline = $data['deadline'] ?? 1;
        $calendar->notification_days = $data['notification_days'] ?? 7;
        $calendar->is_recurring = $data['is_recurring'] ?? 1;
        $calendar->created_at = date('Y-m-d H:i:s');
        $calendar->updated_at = date('Y-m-d H:i:s');
        $calendarId = $this->di['db']->store($calendar);

        $this->di['logger']->info('Created tax calendar event #%s', $calendarId);

        return $calendarId;
    }

    /**
     * Update tax calendar event.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_calendar_event($data)
    {
        $required = [
            'id' => 'Calendar event ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $calendar = $this->di['db']->getExistingModelById('TaxCalendar', $data['id'], 'Calendar event not found');
        $calendar->country = $data['country'] ?? $calendar->country;
        $calendar->tax_type = $data['tax_type'] ?? $calendar->tax_type;
        $calendar->description = $data['description'] ?? $calendar->description;
        $calendar->due_date = !empty($data['due_date']) ? date('Y-m-d H:i:s', strtotime($data['due_date'])) : $calendar->due_date;
        $calendar->deadline = $data['deadline'] ?? $calendar->deadline;
        $calendar->notification_days = $data['notification_days'] ?? $calendar->notification_days;
        $calendar->is_recurring = $data['is_recurring'] ?? $calendar->is_recurring;
        $calendar->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($calendar);

        $this->di['logger']->info('Updated tax calendar event #%s', $calendar->id);

        return true;
    }

    /**
     * Delete tax calendar event.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_calendar_event($data)
    {
        $required = [
            'id' => 'Calendar event ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $calendar = $this->di['db']->getExistingModelById('TaxCalendar', $data['id'], 'Calendar event not found');
        $calendarId = $calendar->id;
        $this->di['db']->trash($calendar);

        $this->di['logger']->info('Deleted tax calendar event #%s', $calendarId);

        return true;
    }

    /**
     * Get tax calendar events.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_calendar_events($data)
    {
        $country = $data['country'] ?? null;
        $taxType = $data['tax_type'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        $where = '1=1';
        $params = [];

        if ($country) {
            $where .= ' AND country = :country';
            $params[':country'] = $country;
        }

        if ($taxType) {
            $where .= ' AND tax_type = :tax_type';
            $params[':tax_type'] = $taxType;
        }

        if ($startDate) {
            $where .= ' AND due_date >= :start_date';
            $params[':start_date'] = date('Y-m-d H:i:s', strtotime($startDate));
        }

        if ($endDate) {
            $where .= ' AND due_date <= :end_date';
            $params[':end_date'] = date('Y-m-d H:i:s', strtotime($endDate));
        }

        $events = $this->di['db']->find('TaxCalendar', "$where ORDER BY due_date", $params);

        $result = [];
        foreach ($events as $event) {
            $result[] = $this->di['db']->toArray($event);
        }

        return $result;
    }

    /**
     * Get tax settings.
     *
     * @return array
     */
    public function get_settings()
    {
        $settings = [
            'tax_enabled' => $this->di['mod_service']('system')->getParamValue('tax_enabled', 1),
            'tax_compound_enabled' => $this->di['mod_service']('system')->getParamValue('tax_compound_enabled', 0),
            'tax_threshold_enabled' => $this->di['mod_service']('system')->getParamValue('tax_threshold_enabled', 0),
            'tax_registration_required' => $this->di['mod_service']('system')->getParamValue('tax_registration_required', 0),
            'tax_default_country' => $this->di['mod_service']('system')->getParamValue('tax_default_country', ''),
            'tax_default_state' => $this->di['mod_service']('system')->getParamValue('tax_default_state', ''),
            'tax_default_rate' => $this->di['mod_service']('system')->getParamValue('tax_default_rate', '0.00'),
        ];

        return $settings;
    }

    /**
     * Update tax settings.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_settings($data)
    {
        $settings = [
            'tax_enabled' => $data['tax_enabled'] ?? 1,
            'tax_compound_enabled' => $data['tax_compound_enabled'] ?? 0,
            'tax_threshold_enabled' => $data['tax_threshold_enabled'] ?? 0,
            'tax_registration_required' => $data['tax_registration_required'] ?? 0,
            'tax_default_country' => $data['tax_default_country'] ?? '',
            'tax_default_state' => $data['tax_default_state'] ?? '',
            'tax_default_rate' => $data['tax_default_rate'] ?? '0.00',
        ];

        foreach ($settings as $key => $value) {
            $this->di['mod_service']('system')->setParamValue($key, $value);
        }

        $this->di['logger']->info('Updated tax settings');

        return true;
    }

    /**
     * Get tax audit log.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_audit_log($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $invoiceId = $data['invoice_id'] ?? null;
        $clientId = $data['client_id'] ?? null;
        $action = $data['action'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($invoiceId) {
            $where .= ' AND invoice_id = :invoice_id';
            $params[':invoice_id'] = $invoiceId;
        }

        if ($clientId) {
            $where .= ' AND client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        if ($action) {
            $where .= ' AND action = :action';
            $params[':action'] = $action;
        }

        if ($startDate) {
            $where .= ' AND performed_at >= :start_date';
            $params[':start_date'] = date('Y-m-d H:i:s', strtotime($startDate));
        }

        if ($endDate) {
            $where .= ' AND performed_at <= :end_date';
            $params[':end_date'] = date('Y-m-d H:i:s', strtotime($endDate));
        }

        $logs = $this->di['db']->find('TaxAuditLog', "$where ORDER BY performed_at DESC", $params);

        $result = [];
        foreach ($logs as $log) {
            $result[] = $this->di['db']->toArray($log);
        }

        return $result;
    }

    /**
     * Create tax audit log entry.
     *
     * @param array $data
     *
     * @return int - log ID
     */
    public function create_audit_log($data)
    {
        $required = [
            'action' => 'Action is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $log = $this->di['db']->dispense('TaxAuditLog');
        $log->tax_id = $data['tax_id'] ?? null;
        $log->invoice_id = $data['invoice_id'] ?? null;
        $log->client_id = $data['client_id'] ?? null;
        $log->action = $data['action'];
        $log->old_value = $data['old_value'] ?? null;
        $log->new_value = $data['new_value'] ?? null;
        $log->reason = $data['reason'] ?? null;
        $log->performed_by = $this->getIdentity()->id;
        $log->performed_at = date('Y-m-d H:i:s');
        $logId = $this->di['db']->store($log);

        $this->di['logger']->info('Created tax audit log entry #%s', $logId);

        return $logId;
    }

    /**
     * Get tax exemptions.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_exemptions($data)
    {
        $clientId = $data['client_id'] ?? null;
        $exemptionType = $data['exemption_type'] ?? null;

        $where = '1=1';
        $params = [];

        if ($clientId) {
            $where .= ' AND client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        if ($exemptionType) {
            $where .= ' AND exemption_type = :exemption_type';
            $params[':exemption_type'] = $exemptionType;
        }

        $exemptions = $this->di['db']->find('TaxExemption', "$where ORDER BY created_at DESC", $params);

        $result = [];
        foreach ($exemptions as $exemption) {
            $result[] = $this->di['db']->toArray($exemption);
        }

        return $result;
    }

    /**
     * Update tax exemption.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_exemption($data)
    {
        $required = [
            'id' => 'Exemption ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $exemption = $this->di['db']->getExistingModelById('TaxExemption', $data['id'], 'Exemption not found');
        $exemption->exemption_type = $data['exemption_type'] ?? $exemption->exemption_type;
        $exemption->exemption_reason = $data['exemption_reason'] ?? $exemption->exemption_reason;
        $exemption->exemption_rate = $data['exemption_rate'] ?? $exemption->exemption_rate;
        $exemption->product_categories = !empty($data['product_categories']) ? json_encode($data['product_categories']) : $exemption->product_categories;
        $exemption->valid_from = !empty($data['valid_from']) ? date('Y-m-d H:i:s', strtotime($data['valid_from'])) : $exemption->valid_from;
        $exemption->valid_to = !empty($data['valid_to']) ? date('Y-m-d H:i:s', strtotime($data['valid_to'])) : $exemption->valid_to;
        $exemption->certificate_number = $data['certificate_number'] ?? $exemption->certificate_number;
        $exemption->certificate_issuer = $data['certificate_issuer'] ?? $exemption->certificate_issuer;
        $exemption->certificate_file = $data['certificate_file'] ?? $exemption->certificate_file;
        $exemption->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($exemption);

        $this->di['logger']->info('Updated tax exemption #%s', $exemption->id);

        return true;
    }

    /**
     * Delete tax exemption.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_exemption($data)
    {
        $required = [
            'id' => 'Exemption ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $exemption = $this->di['db']->getExistingModelById('TaxExemption', $data['id'], 'Exemption not found');
        $exemptionId = $exemption->id;
        $this->di['db']->trash($exemption);

        $this->di['logger']->info('Deleted tax exemption #%s', $exemptionId);

        return true;
    }

    /**
     * Get tax rate histories.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_rate_histories($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($startDate) {
            $where .= ' AND effective_date >= :start_date';
            $params[':start_date'] = date('Y-m-d H:i:s', strtotime($startDate));
        }

        if ($endDate) {
            $where .= ' AND effective_date <= :end_date';
            $params[':end_date'] = date('Y-m-d H:i:s', strtotime($endDate));
        }

        $histories = $this->di['db']->find('TaxRateHistory', "$where ORDER BY effective_date DESC", $params);

        $result = [];
        foreach ($histories as $history) {
            $result[] = $this->di['db']->toArray($history);
        }

        return $result;
    }

    /**
     * Update tax rate history.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_rate_history($data)
    {
        $required = [
            'id' => 'History ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $history = $this->di['db']->getExistingModelById('TaxRateHistory', $data['id'], 'History not found');
        $history->old_rate = $data['old_rate'] ?? $history->old_rate;
        $history->new_rate = $data['new_rate'] ?? $history->new_rate;
        $history->effective_date = !empty($data['effective_date']) ? date('Y-m-d H:i:s', strtotime($data['effective_date'])) : $history->effective_date;
        $history->reason = $data['reason'] ?? $history->reason;
        $history->changed_by = $data['changed_by'] ?? $history->changed_by;
        $this->di['db']->store($history);

        $this->di['logger']->info('Updated tax rate history #%s', $history->id);

        return true;
    }

    /**
     * Delete tax rate history.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_rate_history($data)
    {
        $required = [
            'id' => 'History ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $history = $this->di['db']->getExistingModelById('TaxRateHistory', $data['id'], 'History not found');
        $historyId = $history->id;
        $this->di['db']->trash($history);

        $this->di['logger']->info('Deleted tax rate history #%s', $historyId);

        return true;
    }

    /**
     * Get tax registrations.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_registrations($data)
    {
        $clientId = $data['client_id'] ?? null;
        $country = $data['country'] ?? null;
        $isVerified = $data['is_verified'] ?? null;

        $where = '1=1';
        $params = [];

        if ($clientId) {
            $where .= ' AND client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        if ($country) {
            $where .= ' AND country = :country';
            $params[':country'] = $country;
        }

        if ($isVerified !== null) {
            $where .= ' AND is_verified = :is_verified';
            $params[':is_verified'] = $isVerified;
        }

        $registrations = $this->di['db']->find('TaxRegistration', "$where ORDER BY created_at DESC", $params);

        $result = [];
        foreach ($registrations as $registration) {
            $result[] = $this->di['db']->toArray($registration);
        }

        return $result;
    }

    /**
     * Update tax registration.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_registration($data)
    {
        $required = [
            'id' => 'Registration ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registration = $this->di['db']->getExistingModelById('TaxRegistration', $data['id'], 'Registration not found');
        $registration->country = $data['country'] ?? $registration->country;
        $registration->registration_number = $data['registration_number'] ?? $registration->registration_number;
        $registration->registration_type = $data['registration_type'] ?? $registration->registration_type;
        $registration->valid_from = !empty($data['valid_from']) ? date('Y-m-d H:i:s', strtotime($data['valid_from'])) : $registration->valid_from;
        $registration->valid_to = !empty($data['valid_to']) ? date('Y-m-d H:i:s', strtotime($data['valid_to'])) : $registration->valid_to;
        $registration->is_verified = $data['is_verified'] ?? $registration->is_verified;
        $registration->verification_date = !empty($data['verification_date']) ? date('Y-m-d H:i:s', strtotime($data['verification_date'])) : $registration->verification_date;
        $registration->verified_by = $data['verified_by'] ?? $registration->verified_by;
        $registration->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($registration);

        $this->di['logger']->info('Updated tax registration #%s', $registration->id);

        return true;
    }

    /**
     * Delete tax registration.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_registration($data)
    {
        $required = [
            'id' => 'Registration ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registration = $this->di['db']->getExistingModelById('TaxRegistration', $data['id'], 'Registration not found');
        $registrationId = $registration->id;
        $this->di['db']->trash($registration);

        $this->di['logger']->info('Deleted tax registration #%s', $registrationId);

        return true;
    }

    /**
     * Get tax jurisdictions.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_jurisdictions($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $jurisdictionType = $data['jurisdiction_type'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($jurisdictionType) {
            $where .= ' AND jurisdiction_type = :jurisdiction_type';
            $params[':jurisdiction_type'] = $jurisdictionType;
        }

        $jurisdictions = $this->di['db']->find('TaxJurisdiction', "$where ORDER BY jurisdiction_name", $params);

        $result = [];
        foreach ($jurisdictions as $jurisdiction) {
            $result[] = $this->di['db']->toArray($jurisdiction);
        }

        return $result;
    }

    /**
     * Update tax jurisdiction.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_jurisdiction($data)
    {
        $required = [
            'id' => 'Jurisdiction ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $jurisdiction = $this->di['db']->getExistingModelById('TaxJurisdiction', $data['id'], 'Jurisdiction not found');
        $jurisdiction->jurisdiction_type = $data['jurisdiction_type'] ?? $jurisdiction->jurisdiction_type;
        $jurisdiction->jurisdiction_code = $data['jurisdiction_code'] ?? $jurisdiction->jurisdiction_code;
        $jurisdiction->jurisdiction_name = $data['jurisdiction_name'] ?? $jurisdiction->jurisdiction_name;
        $jurisdiction->parent_jurisdiction_id = $data['parent_jurisdiction_id'] ?? $jurisdiction->parent_jurisdiction_id;
        $jurisdiction->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($jurisdiction);

        $this->di['logger']->info('Updated tax jurisdiction #%s', $jurisdiction->id);

        return true;
    }

    /**
     * Delete tax jurisdiction.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_jurisdiction($data)
    {
        $required = [
            'id' => 'Jurisdiction ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $jurisdiction = $this->di['db']->getExistingModelById('TaxJurisdiction', $data['id'], 'Jurisdiction not found');
        $jurisdictionId = $jurisdiction->id;
        $this->di['db']->trash($jurisdiction);

        $this->di['logger']->info('Deleted tax jurisdiction #%s', $jurisdictionId);

        return true;
    }

    /**
     * Get tax category mappings.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_category_mappings($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $categoryId = $data['category_id'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($categoryId) {
            $where .= ' AND category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $mappings = $this->di['db']->find('TaxCategoryMapping', "$where ORDER BY tax_id", $params);

        $result = [];
        foreach ($mappings as $mapping) {
            $result[] = $this->di['db']->toArray($mapping);
        }

        return $result;
    }

    /**
     * Update tax category mapping.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_category_mapping($data)
    {
        $required = [
            'id' => 'Mapping ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $mapping = $this->di['db']->getExistingModelById('TaxCategoryMapping', $data['id'], 'Mapping not found');
        $mapping->tax_id = $data['tax_id'] ?? $mapping->tax_id;
        $mapping->category_id = $data['category_id'] ?? $mapping->category_id;
        $this->di['db']->store($mapping);

        $this->di['logger']->info('Updated tax category mapping #%s', $mapping->id);

        return true;
    }

    /**
     * Delete tax category mapping.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_category_mapping($data)
    {
        $required = [
            'id' => 'Mapping ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $mapping = $this->di['db']->getExistingModelById('TaxCategoryMapping', $data['id'], 'Mapping not found');
        $mappingId = $mapping->id;
        $this->di['db']->trash($mapping);

        $this->di['logger']->info('Deleted tax category mapping #%s', $mappingId);

        return true;
    }

    /**
     * Get tax client group mappings.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_client_group_mappings($data)
    {
        $taxId = $data['tax_id'] ?? null;
        $clientGroupId = $data['client_group_id'] ?? null;

        $where = '1=1';
        $params = [];

        if ($taxId) {
            $where .= ' AND tax_id = :tax_id';
            $params[':tax_id'] = $taxId;
        }

        if ($clientGroupId) {
            $where .= ' AND client_group_id = :client_group_id';
            $params[':client_group_id'] = $clientGroupId;
        }

        $mappings = $this->di['db']->find('TaxClientGroupMapping', "$where ORDER BY tax_id", $params);

        $result = [];
        foreach ($mappings as $mapping) {
            $result[] = $this->di['db']->toArray($mapping);
        }

        return $result;
    }

    /**
     * Update tax client group mapping.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_client_group_mapping($data)
    {
        $required = [
            'id' => 'Mapping ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $mapping = $this->di['db']->getExistingModelById('TaxClientGroupMapping', $data['id'], 'Mapping not found');
        $mapping->tax_id = $data['tax_id'] ?? $mapping->tax_id;
        $mapping->client_group_id = $data['client_group_id'] ?? $mapping->client_group_id;
        $this->di['db']->store($mapping);

        $this->di['logger']->info('Updated tax client group mapping #%s', $mapping->id);

        return true;
    }

    /**
     * Delete tax client group mapping.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_client_group_mapping($data)
    {
        $required = [
            'id' => 'Mapping ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $mapping = $this->di['db']->getExistingModelById('TaxClientGroupMapping', $data['id'], 'Mapping not found');
        $mappingId = $mapping->id;
        $this->di['db']->trash($mapping);

        $this->di['logger']->info('Deleted tax client group mapping #%s', $mappingId);

        return true;
    }

    /**
     * Get tax calendar events.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_calendar_events($data)
    {
        $country = $data['country'] ?? null;
        $taxType = $data['tax_type'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        $where = '1=1';
        $params = [];

        if ($country) {
            $where .= ' AND country = :country';
            $params[':country'] = $country;
        }

        if ($taxType) {
            $where .= ' AND tax_type = :tax_type';
            $params[':tax_type'] = $taxType;
        }

        if ($startDate) {
            $where .= ' AND due_date >= :start_date';
            $params[':start_date'] = date('Y-m-d H:i:s', strtotime($startDate));
        }

        if ($endDate) {
            $where .= ' AND due_date <= :end_date';
            $params[':end_date'] = date('Y-m-d H:i:s', strtotime($endDate));
        }

        $events = $this->di['db']->find('TaxCalendar', "$where ORDER BY due_date", $params);

        $result = [];
        foreach ($events as $event) {
            $result[] = $this->di['db']->toArray($event);
        }

        return $result;
    }

    /**
     * Update tax calendar event.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_calendar_event($data)
    {
        $required = [
            'id' => 'Calendar event ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $calendar = $this->di['db']->getExistingModelById('TaxCalendar', $data['id'], 'Calendar event not found');
        $calendar->country = $data['country'] ?? $calendar->country;
        $calendar->tax_type = $data['tax_type'] ?? $calendar->tax_type;
        $calendar->description = $data['description'] ?? $calendar->description;
        $calendar->due_date = !empty($data['due_date']) ? date('Y-m-d H:i:s', strtotime($data['due_date'])) : $calendar->due_date;
        $calendar->deadline = $data['deadline'] ?? $calendar->deadline;
        $calendar->notification_days = $data['notification_days'] ?? $calendar->notification_days;
        $calendar->is_recurring = $data['is_recurring'] ?? $calendar->is_recurring;
        $calendar->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($calendar);

        $this->di['logger']->info('Updated tax calendar event #%s', $calendar->id);

        return true;
    }

    /**
     * Delete tax calendar event.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_calendar_event($data)
    {
        $required = [
            'id' => 'Calendar event ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $calendar = $this->di['db']->getExistingModelById('TaxCalendar', $data['id'], 'Calendar event not found');
        $calendarId = $calendar->id;
        $this->di['db']->trash($calendar);

        $this->di['logger']->info('Deleted tax calendar event #%s', $calendarId);

        return true;
    }
}