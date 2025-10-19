<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Reports;

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
     * Get comprehensive business intelligence dashboard data
     */
    public function getBusinessIntelligence(array $data = []): array
    {
        $pdo = $this->di['pdo'];

        // Get revenue data
        $revenueData = $this->getRevenueAnalytics($data);
        
        // Get client acquisition data
        $clientData = $this->getClientAnalytics($data);
        
        // Get order and product data
        $orderData = $this->getOrderAnalytics($data);
        
        // Get support metrics
        $supportData = $this->getSupportAnalytics($data);
        
        // Calculate key business metrics
        $businessMetrics = $this->calculateBusinessMetrics($data);

        return [
            'revenue' => $revenueData,
            'clients' => $clientData,
            'orders' => $orderData,
            'support' => $supportData,
            'metrics' => $businessMetrics,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get revenue analytics with trend analysis
     */
    public function getRevenueAnalytics(array $data = []): array
    {
        $pdo = $this->di['pdo'];
        
        $time_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $time_to = $data['date_to'] ?? date('Y-m-d');
        
        // Revenue by period
        $query = "
            SELECT 
                DATE_FORMAT(paid_at, '%Y-%m') as period,
                SUM(total) as revenue,
                COUNT(id) as transactions,
                AVG(total) as average_order_value
            FROM invoice 
            WHERE status = 'paid' 
                AND paid_at BETWEEN :date_from AND :date_to
            GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
            ORDER BY period
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $revenueByPeriod = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Revenue by currency
        $currencyQuery = "
            SELECT 
                currency,
                SUM(total) as total_revenue,
                COUNT(id) as transaction_count
            FROM invoice 
            WHERE status = 'paid' 
                AND paid_at BETWEEN :date_from AND :date_to
            GROUP BY currency
            ORDER BY total_revenue DESC
        ";
        
        $stmt = $pdo->prepare($currencyQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $revenueByCurrency = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Revenue by category (product type)
        $categoryQuery = "
            SELECT 
                p.type as product_type,
                SUM(ii.price * ii.quantity) as revenue,
                COUNT(DISTINCT ii.invoice_id) as invoices
            FROM invoice_item ii
            JOIN invoice i ON ii.invoice_id = i.id
            LEFT JOIN product p ON ii.rel_id = p.id AND ii.type = 'order'
            WHERE i.status = 'paid'
                AND i.paid_at BETWEEN :date_from AND :date_to
            GROUP BY p.type
            ORDER BY revenue DESC
        ";
        
        $stmt = $pdo->prepare($categoryQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $revenueByCategory = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'by_period' => $revenueByPeriod,
            'by_currency' => $revenueByCurrency,
            'by_category' => $revenueByCategory,
            'summary' => $this->getRevenueSummary($time_from, $time_to),
        ];
    }

    /**
     * Get revenue summary for the specified period
     */
    private function getRevenueSummary(string $time_from, string $time_to): array
    {
        $pdo = $this->di['pdo'];
        
        $summaryQuery = "
            SELECT 
                SUM(total) as total_revenue,
                AVG(total) as average_order_value,
                COUNT(id) as total_transactions,
                (SUM(total) - LAG(SUM(total)) OVER (ORDER BY 1)) / LAG(SUM(total)) OVER (ORDER BY 1) * 100 as growth_rate
            FROM invoice 
            WHERE status = 'paid' 
                AND paid_at BETWEEN :date_from AND :date_to
        ";
        
        $stmt = $pdo->prepare($summaryQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $summary = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Calculate MRR (Monthly Recurring Revenue)
        $mrrQuery = "
            SELECT 
                SUM(ii.price * ii.quantity) as mrr
            FROM invoice_item ii
            JOIN invoice i ON ii.invoice_id = i.id
            WHERE i.status = 'paid'
                AND ii.type = 'order'
                AND i.paid_at BETWEEN :date_from AND :date_to
                AND EXISTS (
                    SELECT 1 FROM client_order co 
                    WHERE co.id = ii.rel_id 
                    AND co.unit = 'period' 
                    AND co.period != 'onetime'
                )
        ";
        
        $stmt = $pdo->prepare($mrrQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $mrr = $stmt->fetchColumn() ?: 0;

        return [
            'total_revenue' => $summary['total_revenue'] ?? 0,
            'average_order_value' => $summary['average_order_value'] ?? 0,
            'total_transactions' => $summary['total_transactions'] ?? 0,
            'growth_rate' => $summary['growth_rate'] ?? 0,
            'mrr' => $mrr,
        ];
    }

    /**
     * Get client analytics with segmentation
     */
    public function getClientAnalytics(array $data = []): array
    {
        $pdo = $this->di['pdo'];
        
        $time_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $time_to = $data['date_to'] ?? date('Y-m-d');
        
        // Client acquisition by period
        $acquisitionQuery = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(id) as new_clients,
                AVG(CASE WHEN currency IS NOT NULL THEN 1 ELSE 0 END) as conversion_rate
            FROM client 
            WHERE created_at BETWEEN :date_from AND :date_to
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period
        ";
        
        $stmt = $pdo->prepare($acquisitionQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $acquisitionByPeriod = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Client segmentation by country
        $countryQuery = "
            SELECT 
                country,
                COUNT(id) as client_count,
                AVG(total) as avg_revenue_per_client
            FROM client c
            LEFT JOIN (
                SELECT client_id, SUM(total) as total
                FROM invoice 
                WHERE status = 'paid'
                GROUP BY client_id
            ) i ON c.id = i.client_id
            GROUP BY country
            ORDER BY client_count DESC
            LIMIT 10
        ";
        
        $stmt = $pdo->prepare($countryQuery);
        $stmt->execute();
        
        $clientsByCountry = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Client segmentation by group
        $groupQuery = "
            SELECT 
                cg.title as group_name,
                COUNT(c.id) as client_count,
                AVG(i.total) as avg_revenue_per_client
            FROM client c
            LEFT JOIN client_group cg ON c.client_group_id = cg.id
            LEFT JOIN (
                SELECT client_id, SUM(total) as total
                FROM invoice 
                WHERE status = 'paid'
                GROUP BY client_id
            ) i ON c.id = i.client_id
            GROUP BY c.client_group_id
            ORDER BY client_count DESC
        ";
        
        $stmt = $pdo->prepare($groupQuery);
        $stmt->execute();
        
        $clientsByGroup = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Churn rate calculation
        $churnQuery = "
            SELECT 
                DATE_FORMAT(dismissed_at, '%Y-%m') as period,
                COUNT(id) as churned_clients
            FROM client 
            WHERE dismissed_at BETWEEN :date_from AND :date_to
            GROUP BY DATE_FORMAT(dismissed_at, '%Y-%m')
            ORDER BY period
        ";
        
        $stmt = $pdo->prepare($churnQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $churnByPeriod = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'acquisition' => $acquisitionByPeriod,
            'by_country' => $clientsByCountry,
            'by_group' => $clientsByGroup,
            'churn' => $churnByPeriod,
            'summary' => $this->getClientSummary($time_from, $time_to),
        ];
    }

    /**
     * Get client summary for the specified period
     */
    private function getClientSummary(string $time_from, string $time_to): array
    {
        $pdo = $this->di['pdo'];
        
        // Total clients
        $totalClientsQuery = "SELECT COUNT(id) as total FROM client";
        $stmt = $pdo->prepare($totalClientsQuery);
        $stmt->execute();
        $totalClients = $stmt->fetchColumn();
        
        // New clients in period
        $newClientsQuery = "
            SELECT COUNT(id) as new 
            FROM client 
            WHERE created_at BETWEEN :date_from AND :date_to
        ";
        $stmt = $pdo->prepare($newClientsQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        $newClients = $stmt->fetchColumn();
        
        // Active clients in period
        $activeClientsQuery = "
            SELECT COUNT(DISTINCT client_id) as active
            FROM invoice 
            WHERE status = 'paid' 
                AND paid_at BETWEEN :date_from AND :date_to
        ";
        $stmt = $pdo->prepare($activeClientsQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        $activeClients = $stmt->fetchColumn();
        
        // Churned clients in period
        $churnedClientsQuery = "
            SELECT COUNT(id) as churned
            FROM client 
            WHERE dismissed_at BETWEEN :date_from AND :date_to
        ";
        $stmt = $pdo->prepare($churnedClientsQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        $churnedClients = $stmt->fetchColumn();
        
        // Client lifetime value (simplified calculation)
        $clvQuery = "
            SELECT 
                AVG(total_spent) * AVG(retention_months) as clv
            FROM (
                SELECT 
                    client_id,
                    SUM(total) as total_spent,
                    DATEDIFF(MAX(paid_at), MIN(paid_at)) / 30 as retention_months
                FROM invoice 
                WHERE status = 'paid'
                GROUP BY client_id
            ) t
        ";
        $stmt = $pdo->prepare($clvQuery);
        $stmt->execute();
        $clv = $stmt->fetchColumn() ?: 0;

        return [
            'total_clients' => $totalClients,
            'new_clients' => $newClients,
            'active_clients' => $activeClients,
            'churned_clients' => $churnedClients,
            'client_lifetime_value' => $clv,
        ];
    }

    /**
     * Get order analytics with product insights
     */
    public function getOrderAnalytics(array $data = []): array
    {
        $pdo = $this->di['pdo'];
        
        $time_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $time_to = $data['date_to'] ?? date('Y-m-d');
        
        // Orders by period
        $ordersByPeriodQuery = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(id) as total_orders,
                SUM(revenue) as total_revenue,
                AVG(revenue) as average_order_value
            FROM (
                SELECT 
                    co.id,
                    co.created_at,
                    SUM(ii.price * ii.quantity) as revenue
                FROM client_order co
                JOIN invoice_item ii ON co.id = ii.rel_id AND ii.type = 'order'
                JOIN invoice i ON ii.invoice_id = i.id
                WHERE i.status = 'paid'
                    AND i.paid_at BETWEEN :date_from AND :date_to
                GROUP BY co.id
            ) t
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period
        ";
        
        $stmt = $pdo->prepare($ordersByPeriodQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $ordersByPeriod = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Top selling products
        $topProductsQuery = "
            SELECT 
                p.title as product_name,
                p.type as product_type,
                COUNT(co.id) as orders_count,
                SUM(ii.price * ii.quantity) as total_revenue,
                AVG(ii.price * ii.quantity) as avg_revenue_per_order
            FROM product p
            JOIN client_order co ON p.id = co.product_id
            JOIN invoice_item ii ON co.id = ii.rel_id AND ii.type = 'order'
            JOIN invoice i ON ii.invoice_id = i.id
            WHERE i.status = 'paid'
                AND i.paid_at BETWEEN :date_from AND :date_to
            GROUP BY p.id
            ORDER BY total_revenue DESC
            LIMIT 10
        ";
        
        $stmt = $pdo->prepare($topProductsQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $topProducts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Order status breakdown
        $statusQuery = "
            SELECT 
                status,
                COUNT(id) as count,
                SUM(revenue) as total_revenue
            FROM (
                SELECT 
                    co.id,
                    co.status,
                    SUM(ii.price * ii.quantity) as revenue
                FROM client_order co
                LEFT JOIN invoice_item ii ON co.id = ii.rel_id AND ii.type = 'order'
                LEFT JOIN invoice i ON ii.invoice_id = i.id AND i.status = 'paid'
                GROUP BY co.id
            ) t
            GROUP BY status
        ";
        
        $stmt = $pdo->prepare($statusQuery);
        $stmt->execute();
        
        $orderStatuses = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'by_period' => $ordersByPeriod,
            'top_products' => $topProducts,
            'status_breakdown' => $orderStatuses,
            'summary' => $this->getOrderSummary($time_from, $time_to),
        ];
    }

    /**
     * Get order summary for the specified period
     */
    private function getOrderSummary(string $time_from, string $time_to): array
    {
        $pdo = $pdo = $this->di['pdo'];
        
        $summaryQuery = "
            SELECT 
                COUNT(co.id) as total_orders,
                SUM(ii.price * ii.quantity) as total_revenue,
                AVG(ii.price * ii.quantity) as average_order_value,
                COUNT(DISTINCT co.client_id) as unique_customers
            FROM client_order co
            JOIN invoice_item ii ON co.id = ii.rel_id AND ii.type = 'order'
            JOIN invoice i ON ii.invoice_id = i.id
            WHERE i.status = 'paid'
                AND i.paid_at BETWEEN :date_from AND :date_to
        ";
        
        $stmt = $pdo->prepare($summaryQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_orders' => $result['total_orders'] ?? 0,
            'total_revenue' => $result['total_revenue'] ?? 0,
            'average_order_value' => $result['average_order_value'] ?? 0,
            'unique_customers' => $result['unique_customers'] ?? 0,
        ];
    }

    /**
     * Get support analytics with ticket insights
     */
    public function getSupportAnalytics(array $data = []): array
    {
        $pdo = $this->di['pdo'];
        
        $time_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $time_to = $data['date_to'] ?? date('Y-m-d');
        
        // Tickets by period
        $ticketsByPeriodQuery = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(id) as total_tickets,
                AVG(DATEDIFF(closed_at, created_at)) as avg_resolution_time,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets
            FROM support_ticket 
            WHERE created_at BETWEEN :date_from AND :date_to
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period
        ";
        
        $stmt = $pdo->prepare($ticketsByPeriodQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $ticketsByPeriod = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Tickets by priority
        $priorityQuery = "
            SELECT 
                priority,
                COUNT(id) as count,
                AVG(DATEDIFF(closed_at, created_at)) as avg_resolution_time
            FROM support_ticket 
            WHERE created_at BETWEEN :date_from AND :date_to
            GROUP BY priority
        ";
        
        $stmt = $pdo->prepare($priorityQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $ticketsByPriority = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Tickets by department
        $departmentQuery = "
            SELECT 
                sd.name as department_name,
                COUNT(st.id) as ticket_count,
                AVG(DATEDIFF(st.closed_at, st.created_at)) as avg_resolution_time
            FROM support_ticket st
            LEFT JOIN support_helpdesk sd ON st.helpdesk_id = sd.id
            WHERE st.created_at BETWEEN :date_from AND :date_to
            GROUP BY st.helpdesk_id
        ";
        
        $stmt = $pdo->prepare($departmentQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $ticketsByDepartment = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'by_period' => $ticketsByPeriod,
            'by_priority' => $ticketsByPriority,
            'by_department' => $ticketsByDepartment,
            'summary' => $this->getSupportSummary($time_from, $time_to),
        ];
    }

    /**
     * Get support summary for the specified period
     */
    private function getSupportSummary(string $time_from, string $time_to): array
    {
        $pdo = $this->di['pdo'];
        
        $summaryQuery = "
            SELECT 
                COUNT(id) as total_tickets,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
                AVG(DATEDIFF(closed_at, created_at)) as avg_resolution_time,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets
            FROM support_ticket 
            WHERE created_at BETWEEN :date_from AND :date_to
        ";
        
        $stmt = $pdo->prepare($summaryQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_tickets' => $result['total_tickets'] ?? 0,
            'closed_tickets' => $result['closed_tickets'] ?? 0,
            'open_tickets' => $result['open_tickets'] ?? 0,
            'avg_resolution_time' => $result['avg_resolution_time'] ?? 0,
        ];
    }

    /**
     * Calculate key business metrics
     */
    private function calculateBusinessMetrics(array $data = []): array
    {
        $time_from = $data['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
        $time_to = $data['date_to'] ?? date('Y-m-d');
        
        // Calculate churn rate
        $churnRate = $this->calculateChurnRate($time_from, $time_to);
        
        // Calculate customer acquisition cost (simplified)
        $cac = $this->calculateCAC($time_from, $time_to);
        
        // Calculate net promoter score (if feedback is available)
        $nps = $this->calculateNPS($time_from, $time_to);
        
        // Calculate monthly recurring revenue
        $mrr = $this->calculateMRR($time_from, $time_to);
        
        // Calculate customer lifetime value
        $clv = $this->calculateCLV($time_from, $time_to);

        return [
            'churn_rate' => $churnRate,
            'customer_acquisition_cost' => $cac,
            'net_promoter_score' => $nps,
            'monthly_recurring_revenue' => $mrr,
            'customer_lifetime_value' => $clv,
            'calculated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate churn rate
     */
    private function calculateChurnRate(string $time_from, string $time_to): float
    {
        $pdo = $this->di['pdo'];
        
        // Count clients who were active at the start of the period
        $startActiveQuery = "
            SELECT COUNT(DISTINCT client_id) as active
            FROM invoice
            WHERE status = 'paid'
                AND paid_at < :date_from
        ";
        
        $stmt = $pdo->prepare($startActiveQuery);
        $stmt->execute(['date_from' => $time_from]);
        $startActive = $stmt->fetchColumn();
        
        if ($startActive == 0) {
            return 0.0;
        }
        
        // Count clients who churned during the period
        $churnedQuery = "
            SELECT COUNT(DISTINCT client_id) as churned
            FROM invoice
            WHERE status = 'paid'
                AND client_id IN (
                    SELECT DISTINCT client_id
                    FROM invoice
                    WHERE status = 'paid'
                        AND paid_at < :date_from
                )
                AND paid_at BETWEEN :date_from AND :date_to
        ";
        
        $stmt = $pdo->prepare($churnedQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        $churned = $stmt->fetchColumn();

        return $startActive > 0 ? ($churned / $startActive) * 100 : 0.0;
    }

    /**
     * Calculate customer acquisition cost (simplified)
     */
    private function calculateCAC(string $time_from, string $time_to): float
    {
        // This is a simplified calculation - in reality, this would factor in marketing costs
        $pdo = $this->di['pdo'];
        
        // Get number of new clients in the period
        $newClientsQuery = "
            SELECT COUNT(id) as new_clients
            FROM client
            WHERE created_at BETWEEN :date_from AND :date_to
        ";
        
        $stmt = $pdo->prepare($newClientsQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        $newClients = $stmt->fetchColumn();
        
        if ($newClients == 0) {
            return 0.0;
        }
        
        // For this simplified version, we'll calculate the average revenue needed to acquire a customer
        // This would typically be based on marketing spend
        $totalRevenueQuery = "
            SELECT SUM(total) as total_revenue
            FROM invoice
            WHERE status = 'paid'
                AND paid_at BETWEEN :date_from AND :date_to
        ";
        
        $stmt = $pdo->prepare($totalRevenueQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        $totalRevenue = $stmt->fetchColumn();

        return $newClients > 0 ? $totalRevenue / $newClients : 0.0;
    }

    /**
     * Calculate Net Promoter Score (if feedback data is available)
     */
    private function calculateNPS(string $time_from, string $time_to): float
    {
        // This is a placeholder - FOSSBilling doesn't currently have a feedback system
        // This would typically come from customer surveys
        return 0.0; // Placeholder
    }

    /**
     * Calculate Monthly Recurring Revenue
     */
    private function calculateMRR(string $time_from, string $time_to): float
    {
        $pdo = $this->di['pdo'];
        
        $mrrQuery = "
            SELECT 
                SUM(ii.price * ii.quantity) as mrr
            FROM invoice_item ii
            JOIN invoice i ON ii.invoice_id = i.id
            WHERE i.status = 'paid'
                AND ii.type = 'order'
                AND i.paid_at BETWEEN :date_from AND :date_to
                AND EXISTS (
                    SELECT 1 FROM client_order co 
                    WHERE co.id = ii.rel_id 
                    AND co.unit = 'period' 
                    AND co.period != 'onetime'
                )
        ";
        
        $stmt = $pdo->prepare($mrrQuery);
        $stmt->execute([
            'date_from' => $time_from,
            'date_to' => $time_to,
        ]);
        
        return $stmt->fetchColumn() ?: 0.0;
    }

    /**
     * Calculate Customer Lifetime Value
     */
    private function calculateCLV(string $time_from, string $time_to): float
    {
        $pdo = $this->di['pdo'];
        
        $clvQuery = "
            SELECT 
                AVG(total_spent) * AVG(retention_months) as clv
            FROM (
                SELECT 
                    client_id,
                    SUM(total) as total_spent,
                    DATEDIFF(MAX(paid_at), MIN(paid_at)) / 30 as retention_months
                FROM invoice 
                WHERE status = 'paid'
                GROUP BY client_id
            ) t
        ";
        
        $stmt = $pdo->prepare($clvQuery);
        $stmt->execute();
        
        return $stmt->fetchColumn() ?: 0.0;
    }
}