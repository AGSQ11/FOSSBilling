<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Tax;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
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

    /**
     * Get tax rule by ID.
     *
     * @param int $id - tax rule ID
     *
     * @return \Model_Tax
     */
    public function getTaxRuleById($id)
    {
        return $this->di['db']->getExistingModelById('Tax', $id, 'Tax rule not found');
    }

    /**
     * Get paginated list of tax rules.
     *
     * @param array $data
     *
     * @return array
     */
    public function getSearchQuery($data)
    {
        $sql = '
            SELECT *
            FROM tax
            WHERE 1 ';

        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $country = $data['country'] ?? null;

        $params = [];
        if ($search) {
            $sql .= ' AND (name LIKE :search OR country LIKE :search OR state LIKE :search)';
            $params['search'] = "%$search%";
        }

        if ($id) {
            $sql .= ' AND id = :id ';
            $params['id'] = $id;
        }

        if ($country) {
            $sql .= ' AND country = :country ';
            $params['country'] = $country;
        }

        $sql .= ' ORDER BY id DESC ';

        return [$sql, $params];
    }

    /**
     * Create new tax rule.
     *
     * @param array $data
     *
     * @return int - new tax rule ID
     */
    public function createTaxRule($data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Tax', 2);

        $model = $this->di['db']->dispense('Tax');
        $model->level = $data['level'] ?? 1;
        $model->name = $data['name'];
        $model->country = $data['country'] ?? null;
        $model->state = $data['state'] ?? null;
        $model->taxrate = $data['taxrate'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new tax rule %s', $model->name);

        return $newId;
    }

    /**
     * Update tax rule.
     *
     * @param \Model_Tax $model
     * @param array      $data
     *
     * @return bool
     */
    public function updateTaxRule($model, $data)
    {
        $model->level = $data['level'] ?? $model->level;
        $model->name = $data['name'] ?? $model->name;
        $model->country = $data['country'] ?? $model->country;
        $model->state = $data['state'] ?? $model->state;
        $model->taxrate = $data['taxrate'] ?? $model->taxrate;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated tax rule %s', $model->name);

        return true;
    }

    /**
     * Delete tax rule.
     *
     * @param \Model_Tax $model
     *
     * @return bool
     */
    public function deleteTaxRule($model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted tax rule #%s', $id);

        return true;
    }

    /**
     * Get tax rate for client.
     *
     * @param \Model_Client $client
     * @param string        $title
     *
     * @return float
     */
    public function getTaxRateForClient($client, &$title = null)
    {
        $clientService = $this->di['mod_service']('client');
        if (!$clientService->isClientTaxable($client)) {
            return 0;
        }

        $tax = $this->di['db']->findOne('Tax', 'state = ? and country = ?', [$client->state, $client->country]);
        // find rate which matches clients country and state

        if ($tax instanceof \Model_Tax) {
            $title = $tax->name;

            return $tax->taxrate;
        }

        // find rate which matches clients country
        $tax = $this->di['db']->findOne('Tax', 'country = ?', [$client->country]);
        if ($tax instanceof \Model_Tax) {
            $title = $tax->name;

            return $tax->taxrate;
        }

        // find global rate
        $tax = $this->di['db']->findOne('Tax', '(state is NULL or state = "") and (country is null or country = "")');
        if ($tax instanceof \Model_Tax) {
            $title = $tax->name;

            return $tax->taxrate;
        }

        return 0;
    }

    /**
     * Get tax for invoice.
     *
     * @param \Model_Invoice $invoice
     *
     * @return float
     */
    public function getTax($invoice)
    {
        if ($invoice->taxrate <= 0) {
            return 0;
        }

        $tax = 0;
        $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', [$invoice->id]);
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach ($invoiceItems as $item) {
            $tax += $invoiceItemService->getTax($item) * $item->quantity;
        }

        return $tax;
    }

    /**
     * Create advanced tax rule with complex calculations.
     *
     * @param array $data
     *
     * @return int - new tax rule ID
     */
    public function createAdvancedTaxRule($data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Tax', 2);

        $model = $this->di['db']->dispense('Tax');
        $model->level = $data['level'] ?? 1;
        $model->name = $data['name'];
        $model->country = $data['country'] ?? null;
        $model->state = $data['state'] ?? null;
        $model->taxrate = $data['taxrate'];
        
        // Advanced tax rule fields
        $model->tax_type = $data['tax_type'] ?? 'standard'; // standard, reduced, exempt, reverse_charge
        $model->compound_tax = $data['compound_tax'] ?? 0; // Whether this is compound tax
        $model->threshold_amount = $data['threshold_amount'] ?? 0; // Threshold for tax application
        $model->threshold_type = $data['threshold_type'] ?? 'order'; // order, annual
        $model->exempt_reason = $data['exempt_reason'] ?? null; // Reason for tax exemption
        $model->reverse_charge = $data['reverse_charge'] ?? 0; // Whether reverse charge applies
        $model->effective_from = !empty($data['effective_from']) ? date('Y-m-d H:i:s', strtotime($data['effective_from'])) : null;
        $model->effective_to = !empty($data['effective_to']) ? date('Y-m-d H:i:s', strtotime($data['effective_to'])) : null;
        $model->product_categories = !empty($data['product_categories']) ? json_encode($data['product_categories']) : null;
        $model->client_groups = !empty($data['client_groups']) ? json_encode($data['client_groups']) : null;
        $model->registration_required = $data['registration_required'] ?? 0; // Whether tax registration is required
        $model->registration_format = $data['registration_format'] ?? null; // Required format for tax registration
        
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new advanced tax rule %s', $model->name);

        return $newId;
    }

    /**
     * Update advanced tax rule.
     *
     * @param \Model_Tax $model
     * @param array      $data
     *
     * @return bool
     */
    public function updateAdvancedTaxRule($model, $data)
    {
        $model->level = $data['level'] ?? $model->level;
        $model->name = $data['name'] ?? $model->name;
        $model->country = $data['country'] ?? $model->country;
        $model->state = $data['state'] ?? $model->state;
        $model->taxrate = $data['taxrate'] ?? $model->taxrate;
        
        // Advanced tax rule fields
        $model->tax_type = $data['tax_type'] ?? $model->tax_type;
        $model->compound_tax = $data['compound_tax'] ?? $model->compound_tax;
        $model->threshold_amount = $data['threshold_amount'] ?? $model->threshold_amount;
        $model->threshold_type = $data['threshold_type'] ?? $model->threshold_type;
        $model->exempt_reason = $data['exempt_reason'] ?? $model->exempt_reason;
        $model->reverse_charge = $data['reverse_charge'] ?? $model->reverse_charge;
        $model->effective_from = !empty($data['effective_from']) ? date('Y-m-d H:i:s', strtotime($data['effective_from'])) : $model->effective_from;
        $model->effective_to = !empty($data['effective_to']) ? date('Y-m-d H:i:s', strtotime($data['effective_to'])) : $model->effective_to;
        $model->product_categories = !empty($data['product_categories']) ? json_encode($data['product_categories']) : $model->product_categories;
        $model->client_groups = !empty($data['client_groups']) ? json_encode($data['client_groups']) : $model->client_groups;
        $model->registration_required = $data['registration_required'] ?? $model->registration_required;
        $model->registration_format = $data['registration_format'] ?? $model->registration_format;
        
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated advanced tax rule %s', $model->name);

        return true;
    }

    /**
     * Get advanced tax rate for client with complex rules.
     *
     * @param \Model_Client $client
     * @param array         $data
     * @param string        $title
     *
     * @return array
     */
    public function getAdvancedTaxRateForClient($client, $data = [], &$title = null)
    {
        $clientService = $this->di['mod_service']('client');
        if (!$clientService->isClientTaxable($client)) {
            return [
                'rate' => 0,
                'type' => 'exempt',
                'reason' => 'Client not taxable',
            ];
        }

        // Check if tax registration is required and client has it
        $taxRule = $this->di['db']->findOne('Tax', 'state = ? and country = ?', [$client->state, $client->country]);
        if ($taxRule && $taxRule->registration_required && empty($client->company_vat)) {
            return [
                'rate' => 0,
                'type' => 'exempt',
                'reason' => 'Tax registration required but not provided',
            ];
        }

        // Check if tax rule is within effective dates
        if ($taxRule) {
            $now = new \DateTime();
            if ($taxRule->effective_from && $now < new \DateTime($taxRule->effective_from)) {
                return [
                    'rate' => 0,
                    'type' => 'exempt',
                    'reason' => 'Tax rule not yet effective',
                ];
            }
            if ($taxRule->effective_to && $now > new \DateTime($taxRule->effective_to)) {
                return [
                    'rate' => 0,
                    'type' => 'exempt',
                    'reason' => 'Tax rule has expired',
                ];
            }
        }

        // Check threshold if applicable
        if ($taxRule && $taxRule->threshold_amount > 0) {
            $thresholdMet = $this->checkThreshold($client, $taxRule, $data);
            if (!$thresholdMet) {
                return [
                    'rate' => 0,
                    'type' => 'exempt',
                    'reason' => 'Threshold not met',
                ];
            }
        }

        // Check product categories if applicable
        if ($taxRule && !empty($taxRule->product_categories) && !empty($data['products'])) {
            $categoriesAllowed = $this->checkProductCategories($taxRule, $data['products']);
            if (!$categoriesAllowed) {
                return [
                    'rate' => 0,
                    'type' => 'exempt',
                    'reason' => 'Product categories not covered by tax rule',
                ];
            }
        }

        // Check client groups if applicable
        if ($taxRule && !empty($taxRule->client_groups) && $client->client_group_id) {
            $groupsAllowed = $this->checkClientGroups($taxRule, $client->client_group_id);
            if (!$groupsAllowed) {
                return [
                    'rate' => 0,
                    'type' => 'exempt',
                    'reason' => 'Client group not covered by tax rule',
                ];
            }
        }

        // Get tax rate
        $rate = 0;
        $type = 'standard';
        $reason = '';

        if ($taxRule) {
            $rate = $taxRule->taxrate;
            $type = $taxRule->tax_type ?? 'standard';
            $title = $taxRule->name;
            $reason = 'Standard tax calculation';
        } else {
            // Try to find country-level rule
            $taxRule = $this->di['db']->findOne('Tax', 'country = ?', [$client->country]);
            if ($taxRule) {
                $rate = $taxRule->taxrate;
                $type = $taxRule->tax_type ?? 'standard';
                $title = $taxRule->name;
                $reason = 'Country-level tax calculation';
            } else {
                // Find global rate
                $taxRule = $this->di['db']->findOne('Tax', '(state is NULL or state = "") and (country is null or country = "")');
                if ($taxRule) {
                    $rate = $taxRule->taxrate;
                    $type = $taxRule->tax_type ?? 'standard';
                    $title = $taxRule->name;
                    $reason = 'Global tax calculation';
                }
            }
        }

        return [
            'rate' => $rate,
            'type' => $type,
            'reason' => $reason,
        ];
    }

    /**
     * Check if threshold is met for tax application.
     *
     * @param \Model_Client $client
     * @param \Model_Tax    $taxRule
     * @param array         $data
     *
     * @return bool
     */
    private function checkThreshold($client, $taxRule, $data = [])
    {
        if ($taxRule->threshold_type == 'order') {
            // Check order threshold
            $orderTotal = $data['order_total'] ?? 0;
            return $orderTotal >= $taxRule->threshold_amount;
        } elseif ($taxRule->threshold_type == 'annual') {
            // Check annual spending threshold
            $annualSpending = $this->getClientAnnualSpending($client);
            return $annualSpending >= $taxRule->threshold_amount;
        }

        return true;
    }

    /**
     * Get client's annual spending.
     *
     * @param \Model_Client $client
     *
     * @return float
     */
    private function getClientAnnualSpending($client)
    {
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');

        $result = $this->di['db']->getRow("
            SELECT COALESCE(SUM(total), 0) as annual_total
            FROM invoice
            WHERE client_id = :client_id
            AND status = 'paid'
            AND paid_at BETWEEN :start_date AND :end_date
        ", [
            ':client_id' => $client->id,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $result['annual_total'] ?? 0;
    }

    /**
     * Check if product categories are covered by tax rule.
     *
     * @param \Model_Tax $taxRule
     * @param array      $products
     *
     * @return bool
     */
    private function checkProductCategories($taxRule, $products)
    {
        $allowedCategories = json_decode($taxRule->product_categories ?? '[]', true);
        if (empty($allowedCategories)) {
            return true; // No category restrictions
        }

        foreach ($products as $product) {
            $categoryId = $product['category_id'] ?? null;
            if ($categoryId && in_array($categoryId, $allowedCategories)) {
                return true; // At least one product in allowed category
            }
        }

        return false; // No products in allowed categories
    }

    /**
     * Check if client group is covered by tax rule.
     *
     * @param \Model_Tax $taxRule
     * @param int        $clientGroupId
     *
     * @return bool
     */
    private function checkClientGroups($taxRule, $clientGroupId)
    {
        $allowedGroups = json_decode($taxRule->client_groups ?? '[]', true);
        if (empty($allowedGroups)) {
            return true; // No group restrictions
        }

        return in_array($clientGroupId, $allowedGroups);
    }

    /**
     * Calculate compound tax.
     *
     * @param float $baseAmount
     * @param array $taxRates
     *
     * @return array
     */
    public function calculateCompoundTax($baseAmount, $taxRates)
    {
        $totalTax = 0;
        $runningTotal = $baseAmount;
        $breakdown = [];

        foreach ($taxRates as $taxRate) {
            $taxAmount = $runningTotal * ($taxRate['rate'] / 100);
            $totalTax += $taxAmount;
            $runningTotal += $taxAmount;

            $breakdown[] = [
                'name' => $taxRate['name'],
                'rate' => $taxRate['rate'],
                'amount' => $taxAmount,
            ];
        }

        return [
            'total_tax' => $totalTax,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate reverse charge tax.
     *
     * @param float $baseAmount
     * @param float $taxRate
     *
     * @return array
     */
    public function calculateReverseChargeTax($baseAmount, $taxRate)
    {
        $taxAmount = $baseAmount * ($taxRate / 100);

        return [
            'base_amount' => $baseAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $baseAmount, // Total remains base amount for reverse charge
        ];
    }

    /**
     * Get tax exemptions for client.
     *
     * @param \Model_Client $client
     *
     * @return array
     */
    public function getClientTaxExemptions($client)
    {
        // Check for client-specific exemptions
        $exemptions = $this->di['db']->find('TaxExemption', 'client_id = :client_id', [':client_id' => $client->id]);

        $result = [];
        foreach ($exemptions as $exemption) {
            $result[] = $this->di['db']->toArray($exemption);
        }

        return $result;
    }

    /**
     * Create tax exemption for client.
     *
     * @param \Model_Client $client
     * @param array         $data
     *
     * @return int - exemption ID
     */
    public function createClientTaxExemption($client, $data)
    {
        $exemption = $this->di['db']->dispense('TaxExemption');
        $exemption->client_id = $client->id;
        $exemption->exemption_type = $data['exemption_type'] ?? 'full'; // full, partial, product_specific
        $exemption->exemption_reason = $data['exemption_reason'] ?? null;
        $exemption->exemption_rate = $data['exemption_rate'] ?? 0; // For partial exemptions
        $exemption->product_categories = !empty($data['product_categories']) ? json_encode($data['product_categories']) : null;
        $exemption->valid_from = !empty($data['valid_from']) ? date('Y-m-d H:i:s', strtotime($data['valid_from'])) : null;
        $exemption->valid_to = !empty($data['valid_to']) ? date('Y-m-d H:i:s', strtotime($data['valid_to'])) : null;
        $exemption->certificate_number = $data['certificate_number'] ?? null;
        $exemption->certificate_issuer = $data['certificate_issuer'] ?? null;
        $exemption->certificate_file = $data['certificate_file'] ?? null;
        $exemption->created_at = date('Y-m-d H:i:s');
        $exemption->updated_at = date('Y-m-d H:i:s');
        $exemptionId = $this->di['db']->store($exemption);

        $this->di['logger']->info('Created tax exemption for client #%s', $client->id);

        return $exemptionId;
    }

    /**
     * Check if tax exemption is valid.
     *
     * @param \Model_TaxExemption $exemption
     *
     * @return bool
     */
    public function isTaxExemptionValid($exemption)
    {
        // Check if exemption is within valid dates
        $now = new \DateTime();
        if ($exemption->valid_from && $now < new \DateTime($exemption->valid_from)) {
            return false;
        }
        if ($exemption->valid_to && $now > new \DateTime($exemption->valid_to)) {
            return false;
        }

        return true;
    }

    /**
     * Apply tax exemption to invoice.
     *
     * @param \Model_Invoice      $invoice
     * @param \Model_TaxExemption $exemption
     *
     * @return bool
     */
    public function applyTaxExemption($invoice, $exemption)
    {
        if (!$this->isTaxExemptionValid($exemption)) {
            return false;
        }

        if ($exemption->exemption_type == 'full') {
            // Full exemption
            $invoice->taxrate = 0;
            $invoice->taxname = 'Exempt - ' . $exemption->exemption_reason;
        } elseif ($exemption->exemption_type == 'partial') {
            // Partial exemption
            $invoice->taxrate = $invoice->taxrate * (1 - ($exemption->exemption_rate / 100));
            $invoice->taxname = 'Reduced - ' . $exemption->exemption_reason;
        }

        $invoice->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($invoice);

        $this->di['logger']->info('Applied tax exemption to invoice #%s', $invoice->id);

        return true;
    }

    /**
     * Get tax reports.
     *
     * @param array $data
     *
     * @return array
     */
    public function getTaxReports($data)
    {
        $startDate = $data['start_date'] ?? date('Y-01-01');
        $endDate = $data['end_date'] ?? date('Y-12-31');
        $groupBy = $data['group_by'] ?? 'month'; // month, quarter, year

        $dateFormat = '';
        switch ($groupBy) {
            case 'month':
                $dateFormat = '%Y-%m';
                break;
            case 'quarter':
                $dateFormat = 'CONCAT(YEAR(paid_at), "-Q", QUARTER(paid_at))';
                break;
            case 'year':
                $dateFormat = '%Y';
                break;
            default:
                $dateFormat = '%Y-%m';
        }

        $sql = "
            SELECT 
                DATE_FORMAT(paid_at, '$dateFormat') as period,
                SUM(tax) as total_tax_collected,
                COUNT(id) as invoice_count,
                AVG(tax) as average_tax_per_invoice
            FROM invoice
            WHERE status = 'paid'
            AND paid_at BETWEEN :start_date AND :end_date
            AND tax > 0
            GROUP BY period
            ORDER BY period
        ";

        $result = $this->di['db']->getAll($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $result;
    }

    /**
     * Get tax liability report.
     *
     * @param array $data
     *
     * @return array
     */
    public function getTaxLiabilityReport($data)
    {
        $startDate = $data['start_date'] ?? date('Y-01-01');
        $endDate = $data['end_date'] ?? date('Y-12-31');

        $sql = "
            SELECT 
                t.name as tax_name,
                t.country,
                t.state,
                t.taxrate,
                SUM(i.tax) as tax_collected,
                COUNT(i.id) as invoice_count,
                SUM(i.total) as total_revenue
            FROM invoice i
            JOIN tax t ON i.taxrate = t.taxrate
            WHERE i.status = 'paid'
            AND i.paid_at BETWEEN :start_date AND :end_date
            AND i.tax > 0
            GROUP BY t.id
            ORDER BY tax_collected DESC
        ";

        $result = $this->di['db']->getAll($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $result;
    }

    /**
     * Get tax audit trail.
     *
     * @param array $data
     *
     * @return array
     */
    public function getTaxAuditTrail($data)
    {
        $startDate = $data['start_date'] ?? date('Y-01-01');
        $endDate = $data['end_date'] ?? date('Y-12-31');
        $clientId = $data['client_id'] ?? null;

        $sql = "
            SELECT 
                i.id as invoice_id,
                i.serie_nr as invoice_number,
                c.first_name,
                c.last_name,
                c.company,
                i.taxname,
                i.taxrate,
                i.tax,
                i.total,
                i.paid_at,
                t.country,
                t.state
            FROM invoice i
            JOIN client c ON i.client_id = c.id
            LEFT JOIN tax t ON i.taxrate = t.taxrate
            WHERE i.status = 'paid'
            AND i.paid_at BETWEEN :start_date AND :end_date
            AND i.tax > 0
        ";

        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];

        if ($clientId) {
            $sql .= ' AND i.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        $sql .= ' ORDER BY i.paid_at DESC';

        $result = $this->di['db']->getAll($sql, $params);

        return $result;
    }

    /**
     * Import tax rules from CSV.
     *
     * @param string $csvFilePath
     *
     * @return array
     */
    public function importTaxRulesFromCsv($csvFilePath)
    {
        if (!file_exists($csvFilePath)) {
            throw new \FOSSBilling\Exception('CSV file not found');
        }

        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            throw new \FOSSBilling\Exception('Unable to open CSV file');
        }

        $header = fgetcsv($handle);
        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            try {
                $this->createAdvancedTaxRule($data);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Export tax rules to CSV.
     *
     * @param string $csvFilePath
     *
     * @return bool
     */
    public function exportTaxRulesToCsv($csvFilePath)
    {
        $taxRules = $this->di['db']->find('Tax', 'ORDER BY id');

        $handle = fopen($csvFilePath, 'w');
        if (!$handle) {
            throw new \FOSSBilling\Exception('Unable to create CSV file');
        }

        // Write header
        fputcsv($handle, ['id', 'level', 'name', 'country', 'state', 'taxrate', 'tax_type', 'compound_tax', 'threshold_amount', 'threshold_type', 'exempt_reason', 'reverse_charge', 'effective_from', 'effective_to', 'product_categories', 'client_groups', 'registration_required', 'registration_format', 'created_at', 'updated_at']);

        // Write data
        foreach ($taxRules as $taxRule) {
            fputcsv($handle, [
                $taxRule->id,
                $taxRule->level,
                $taxRule->name,
                $taxRule->country,
                $taxRule->state,
                $taxRule->taxrate,
                $taxRule->tax_type,
                $taxRule->compound_tax,
                $taxRule->threshold_amount,
                $taxRule->threshold_type,
                $taxRule->exempt_reason,
                $taxRule->reverse_charge,
                $taxRule->effective_from,
                $taxRule->effective_to,
                $taxRule->product_categories,
                $taxRule->client_groups,
                $taxRule->registration_required,
                $taxRule->registration_format,
                $taxRule->created_at,
                $taxRule->updated_at,
            ]);
        }

        fclose($handle);

        return true;
    }

    /**
     * Get tax rule suggestions based on client location.
     *
     * @param string $country
     * @param string $state
     *
     * @return array
     */
    public function getTaxRuleSuggestions($country, $state = null)
    {
        $suggestions = [];

        // Get standard tax rates for country
        $countryRules = $this->di['db']->find('Tax', 'country = :country AND (state IS NULL OR state = "")', [':country' => $country]);
        foreach ($countryRules as $rule) {
            $suggestions[] = [
                'name' => $rule->name,
                'rate' => $rule->taxrate,
                'type' => 'country_standard',
                'description' => "Standard {$country} tax rate",
            ];
        }

        // Get state/province tax rates if applicable
        if ($state) {
            $stateRules = $this->di['db']->find('Tax', 'country = :country AND state = :state', [
                ':country' => $country,
                ':state' => $state,
            ]);
            foreach ($stateRules as $rule) {
                $suggestions[] = [
                    'name' => $rule->name,
                    'rate' => $rule->taxrate,
                    'type' => 'state_standard',
                    'description' => "Standard {$state}, {$country} tax rate",
                ];
            }
        }

        // Get reduced rates
        $reducedRules = $this->di['db']->find('Tax', 'country = :country AND tax_type = "reduced"', [':country' => $country]);
        foreach ($reducedRules as $rule) {
            $suggestions[] = [
                'name' => $rule->name,
                'rate' => $rule->taxrate,
                'type' => 'reduced',
                'description' => "Reduced {$country} tax rate",
            ];
        }

        return $suggestions;
    }

    /**
     * Validate tax registration number.
     *
     * @param string $registrationNumber
     * @param string $country
     *
     * @return bool
     */
    public function validateTaxRegistration($registrationNumber, $country)
    {
        // This would typically integrate with a VAT validation service
        // For now, we'll implement a simple format validation

        $patterns = [
            'DE' => '/^DE[0-9]{9}$/', // Germany
            'FR' => '/^FR[A-Z0-9]{2}[0-9]{9}$/', // France
            'GB' => '/^GB[0-9]{3}[0-9]{4}[0-9]{2}$|^GB[0-9]{3}[0-9]{4}[0-9]{2}[0-9]{3}$|^GBGD[0-9]{3}$|^GBHA[0-9]{3}$/', // UK
            'IT' => '/^IT[0-9]{11}$/', // Italy
            'ES' => '/^ES[A-Z][0-9]{7}[A-Z]$|^ES[0-9]{8}[A-Z]$|^ES[A-Z][0-9]{8}$/', // Spain
            // Add more patterns as needed
        ];

        if (isset($patterns[$country])) {
            return preg_match($patterns[$country], $registrationNumber) === 1;
        }

        // Basic validation for other countries
        return !empty($registrationNumber) && strlen($registrationNumber) >= 5;
    }

    /**
     * Get tax calendar for filing deadlines.
     *
     * @param string $country
     * @param int    $year
     *
     * @return array
     */
    public function getTaxCalendar($country, $year = null)
    {
        if (!$year) {
            $year = date('Y');
        }

        // This would typically come from a tax calendar database
        // For now, we'll return some sample dates
        $calendar = [
            [
                'date' => "$year-01-31",
                'description' => 'Monthly VAT Return Due',
                'type' => 'vat',
                'deadline' => true,
            ],
            [
                'date' => "$year-03-31",
                'description' => 'Quarterly VAT Return Due',
                'type' => 'vat',
                'deadline' => true,
            ],
            [
                'date' => "$year-04-30",
                'description' => 'Annual Income Tax Return Due',
                'type' => 'income',
                'deadline' => true,
            ],
            [
                'date' => "$year-06-30",
                'description' => 'Half-Year VAT Return Due',
                'type' => 'vat',
                'deadline' => true,
            ],
            [
                'date' => "$year-09-30",
                'description' => 'Quarterly VAT Return Due',
                'type' => 'vat',
                'deadline' => true,
            ],
            [
                'date' => "$year-12-31",
                'description' => 'Year-End Tax Filing Deadline',
                'type' => 'annual',
                'deadline' => true,
            ],
        ];

        return $calendar;
    }
}