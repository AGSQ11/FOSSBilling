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
}