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
 * Reports management API.
 */

namespace Box\Mod\Reports\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get comprehensive business intelligence dashboard data
     * 
     * @param string $date_from - Start date (Y-m-d format)
     * @param string $date_to - End date (Y-m-d format)
     * 
     * @return array
     */
    public function get_business_intelligence($data)
    {
        $date_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $date_to = $data['date_to'] ?? date('Y-m-d');
        
        $this->di['validator']->isDate($date_from, 'Invalid date_from parameter');
        $this->di['validator']->isDate($date_to, 'Invalid date_to parameter');
        
        return $this->getService()->getBusinessIntelligence([
            'date_from' => $date_from,
            'date_to' => $date_to,
        ]);
    }

    /**
     * Get revenue analytics
     * 
     * @param string $date_from - Start date (Y-m-d format)
     * @param string $date_to - End date (Y-m-d format)
     * 
     * @return array
     */
    public function get_revenue_analytics($data)
    {
        $date_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $date_to = $data['date_to'] ?? date('Y-m-d');
        
        $this->di['validator']->isDate($date_from, 'Invalid date_from parameter');
        $this->di['validator']->isDate($date_to, 'Invalid date_to parameter');
        
        return $this->getService()->getRevenueAnalytics([
            'date_from' => $date_from,
            'date_to' => $date_to,
        ]);
    }

    /**
     * Get client analytics
     * 
     * @param string $date_from - Start date (Y-m-d format)
     * @param string $date_to - End date (Y-m-d format)
     * 
     * @return array
     */
    public function get_client_analytics($data)
    {
        $date_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $date_to = $data['date_to'] ?? date('Y-m-d');
        
        $this->di['validator']->isDate($date_from, 'Invalid date_from parameter');
        $this->di['validator']->isDate($date_to, 'Invalid date_to parameter');
        
        return $this->getService()->getClientAnalytics([
            'date_from' => $date_from,
            'date_to' => $date_to,
        ]);
    }

    /**
     * Get order analytics
     * 
     * @param string $date_from - Start date (Y-m-d format)
     * @param string $date_to - End date (Y-m-d format)
     * 
     * @return array
     */
    public function get_order_analytics($data)
    {
        $date_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $date_to = $data['date_to'] ?? date('Y-m-d');
        
        $this->di['validator']->isDate($date_from, 'Invalid date_from parameter');
        $this->di['validator']->isDate($date_to, 'Invalid date_to parameter');
        
        return $this->getService()->getOrderAnalytics([
            'date_from' => $date_from,
            'date_to' => $date_to,
        ]);
    }

    /**
     * Get support analytics
     * 
     * @param string $date_from - Start date (Y-m-d format)
     * @param string $date_to - End date (Y-m-d format)
     * 
     * @return array
     */
    public function get_support_analytics($data)
    {
        $date_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $date_to = $data['date_to'] ?? date('Y-m-d');
        
        $this->di['validator']->isDate($date_from, 'Invalid date_from parameter');
        $this->di['validator']->isDate($date_to, 'Invalid date_to parameter');
        
        return $this->getService()->getSupportAnalytics([
            'date_from' => $date_from,
            'date_to' => $date_to,
        ]);
    }

    /**
     * Get custom report based on specified parameters
     * 
     * @param string $report_type - Type of report to generate
     * @param string $date_from - Start date (Y-m-d format)
     * @param string $date_to - End date (Y-m-d format)
     * @param array $filters - Additional filters for the report
     * 
     * @return array
     */
    public function get_custom_report($data)
    {
        $required = [
            'report_type' => 'Report type is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $date_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $date_to = $data['date_to'] ?? date('Y-m-d');
        
        $this->di['validator']->isDate($date_from, 'Invalid date_from parameter');
        $this->di['validator']->isDate($date_to, 'Invalid date_to parameter');
        
        $reportType = $data['report_type'];
        $filters = $data['filters'] ?? [];
        
        // Call the appropriate method based on report type
        switch ($reportType) {
            case 'business_intelligence':
                return $this->get_business_intelligence($data);
            case 'revenue':
                return $this->get_revenue_analytics($data);
            case 'clients':
                return $this->get_client_analytics($data);
            case 'orders':
                return $this->get_order_analytics($data);
            case 'support':
                return $this->get_support_analytics($data);
            default:
                throw new \FOSSBilling\InformationException('Invalid report type specified');
        }
    }
}