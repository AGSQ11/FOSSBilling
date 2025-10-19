-- Migration to enhance payment gateway system with advanced features
-- Add columns for advanced payment gateway features

ALTER TABLE `pay_gateway` 
ADD COLUMN `gateway_type` VARCHAR(50) DEFAULT 'standard' COMMENT 'Gateway type (standard, crypto, regional, advanced)',
ADD COLUMN `supported_features` TEXT COMMENT 'JSON array of supported features',
ADD COLUMN `supported_currencies` TEXT COMMENT 'JSON array of supported currencies',
ADD COLUMN `supported_countries` TEXT COMMENT 'JSON array of supported countries',
ADD COLUMN `min_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Minimum transaction amount',
ADD COLUMN `max_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Maximum transaction amount',
ADD COLUMN `fee_fixed` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Fixed transaction fee',
ADD COLUMN `fee_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage transaction fee',
ADD COLUMN `sandbox_url` VARCHAR(255) DEFAULT NULL COMMENT 'Sandbox API URL',
ADD COLUMN `production_url` VARCHAR(255) DEFAULT NULL COMMENT 'Production API URL',
ADD COLUMN `webhook_url` VARCHAR(255) DEFAULT NULL COMMENT 'Webhook callback URL',
ADD COLUMN `redirect_url` VARCHAR(255) DEFAULT NULL COMMENT 'Redirect URL after payment',
ADD COLUMN `cancel_url` VARCHAR(255) DEFAULT NULL COMMENT 'Cancel URL for payment',
ADD COLUMN `supported_payment_methods` TEXT COMMENT 'JSON array of supported payment methods',
ADD COLUMN `recurring_supported` TINYINT(1) DEFAULT 0 COMMENT 'Whether gateway supports recurring payments',
ADD COLUMN `refund_supported` TINYINT(1) DEFAULT 0 COMMENT 'Whether gateway supports refunds',
ADD COLUMN `instant_payout` TINYINT(1) DEFAULT 0 COMMENT 'Whether gateway supports instant payouts',
ADD COLUMN `mobile_optimized` TINYINT(1) DEFAULT 0 COMMENT 'Whether gateway is mobile optimized',
ADD COLUMN `iframe_supported` TINYINT(1) DEFAULT 0 COMMENT 'Whether gateway supports iframe embedding',
ADD COLUMN `checkout_flow` VARCHAR(50) DEFAULT 'redirect' COMMENT 'Checkout flow type (redirect, iframe, popup)',
ADD COLUMN `authentication_type` VARCHAR(50) DEFAULT 'api_key' COMMENT 'Authentication type (api_key, oauth, certificate)',
ADD COLUMN `region` VARCHAR(100) DEFAULT NULL COMMENT 'Region where gateway is primarily used',
ADD COLUMN `priority` INT(11) DEFAULT 0 COMMENT 'Gateway priority for sorting',
ADD COLUMN `weight` INT(11) DEFAULT 0 COMMENT 'Gateway weight for load balancing',
ADD INDEX `idx_gateway_type` (`gateway_type`),
ADD INDEX `idx_region` (`region`),
ADD INDEX `idx_priority` (`priority`),
ADD INDEX `idx_recurring_supported` (`recurring_supported`),
ADD INDEX `idx_refund_supported` (`refund_supported`);

-- Create payment gateway feature table for detailed feature tracking
CREATE TABLE IF NOT EXISTS `pay_gateway_feature` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `feature_name` VARCHAR(100) NOT NULL COMMENT 'Feature name',
  `feature_value` TEXT COMMENT 'Feature value/configuration',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_feature` (`gateway_id`, `feature_name`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `feature_name_idx` (`feature_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway currency table for detailed currency support
CREATE TABLE IF NOT EXISTS `pay_gateway_currency` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `currency_code` VARCHAR(3) NOT NULL COMMENT 'Currency code (ISO 4217)',
  `min_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Minimum amount for this currency',
  `max_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Maximum amount for this currency',
  `fee_fixed` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Fixed fee for this currency',
  `fee_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage fee for this currency',
  `exchange_rate` DECIMAL(18,6) DEFAULT 1.000000 COMMENT 'Exchange rate to base currency',
  `active` TINYINT(1) DEFAULT 1 COMMENT 'Whether currency is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_currency` (`gateway_id`, `currency_code`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `currency_code_idx` (`currency_code`),
  KEY `active_idx` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway country table for detailed country support
CREATE TABLE IF NOT EXISTS `pay_gateway_country` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `country_code` VARCHAR(2) NOT NULL COMMENT 'Country code (ISO 3166-1 alpha-2)',
  `min_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Minimum amount for this country',
  `max_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Maximum amount for this country',
  `fee_fixed` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Fixed fee for this country',
  `fee_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage fee for this country',
  `active` TINYINT(1) DEFAULT 1 COMMENT 'Whether country is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_country` (`gateway_id`, `country_code`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `country_code_idx` (`country_code`),
  KEY `active_idx` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway method table for detailed payment method support
CREATE TABLE IF NOT EXISTS `pay_gateway_method` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `method_name` VARCHAR(100) NOT NULL COMMENT 'Payment method name',
  `method_type` VARCHAR(50) DEFAULT 'card' COMMENT 'Payment method type (card, bank, wallet, crypto)',
  `min_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Minimum amount for this method',
  `max_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Maximum amount for this method',
  `fee_fixed` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Fixed fee for this method',
  `fee_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage fee for this method',
  `active` TINYINT(1) DEFAULT 1 COMMENT 'Whether method is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_method` (`gateway_id`, `method_name`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `method_name_idx` (`method_name`),
  KEY `method_type_idx` (`method_type`),
  KEY `active_idx` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway log table for detailed logging
CREATE TABLE IF NOT EXISTS `pay_gateway_log` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) DEFAULT NULL COMMENT 'Payment gateway ID',
  `transaction_id` VARCHAR(255) DEFAULT NULL COMMENT 'Transaction ID',
  `log_level` VARCHAR(20) DEFAULT 'info' COMMENT 'Log level (info, warning, error)',
  `message` TEXT COMMENT 'Log message',
  `context` TEXT COMMENT 'Additional context/data',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `transaction_id_idx` (`transaction_id`),
  KEY `log_level_idx` (`log_level`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway webhook table for webhook tracking
CREATE TABLE IF NOT EXISTS `pay_gateway_webhook` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `event_type` VARCHAR(100) NOT NULL COMMENT 'Webhook event type',
  `payload` TEXT COMMENT 'Webhook payload',
  `signature` VARCHAR(255) DEFAULT NULL COMMENT 'Webhook signature',
  `processed` TINYINT(1) DEFAULT 0 COMMENT 'Whether webhook was processed',
  `processing_result` TEXT COMMENT 'Result of webhook processing',
  `created_at` DATETIME DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `event_type_idx` (`event_type`),
  KEY `processed_idx` (`processed`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway statistics table for performance tracking
CREATE TABLE IF NOT EXISTS `pay_gateway_stats` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `statistic_name` VARCHAR(100) NOT NULL COMMENT 'Statistic name',
  `statistic_value` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Statistic value',
  `statistic_count` BIGINT(20) DEFAULT 0 COMMENT 'Statistic count',
  `recorded_at` DATE DEFAULT NULL COMMENT 'Date when statistic was recorded',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_statistic_date` (`gateway_id`, `statistic_name`, `recorded_at`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `statistic_name_idx` (`statistic_name`),
  KEY `recorded_at_idx` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway configuration table for advanced configuration
CREATE TABLE IF NOT EXISTS `pay_gateway_config` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `config_key` VARCHAR(100) NOT NULL COMMENT 'Configuration key',
  `config_value` TEXT COMMENT 'Configuration value',
  `config_type` VARCHAR(50) DEFAULT 'string' COMMENT 'Configuration type (string, integer, boolean, json)',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_config` (`gateway_id`, `config_key`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `config_key_idx` (`config_key`),
  KEY `config_type_idx` (`config_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway routing table for advanced routing
CREATE TABLE IF NOT EXISTS `pay_gateway_routing` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `routing_rule` VARCHAR(255) NOT NULL COMMENT 'Routing rule (country, currency, amount, etc.)',
  `routing_value` VARCHAR(255) NOT NULL COMMENT 'Routing value',
  `priority` INT(11) DEFAULT 0 COMMENT 'Routing priority',
  `active` TINYINT(1) DEFAULT 1 COMMENT 'Whether routing rule is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `routing_rule_idx` (`routing_rule`),
  KEY `routing_value_idx` (`routing_value`),
  KEY `priority_idx` (`priority`),
  KEY `active_idx` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway performance table for performance monitoring
CREATE TABLE IF NOT EXISTS `pay_gateway_performance` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `response_time` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Average response time in milliseconds',
  `success_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Success rate percentage',
  `failure_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Failure rate percentage',
  `throughput` INT(11) DEFAULT 0 COMMENT 'Transactions per minute',
  `availability` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Gateway availability percentage',
  `recorded_at` DATE DEFAULT NULL COMMENT 'Date when performance was recorded',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_performance_date` (`gateway_id`, `recorded_at`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `recorded_at_idx` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway error table for error tracking
CREATE TABLE IF NOT EXISTS `pay_gateway_error` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `error_code` VARCHAR(50) NOT NULL COMMENT 'Error code',
  `error_message` TEXT COMMENT 'Error message',
  `error_count` INT(11) DEFAULT 1 COMMENT 'Number of times error occurred',
  `last_occurred_at` DATETIME DEFAULT NULL COMMENT 'When error last occurred',
  `first_occurred_at` DATETIME DEFAULT NULL COMMENT 'When error first occurred',
  `resolved` TINYINT(1) DEFAULT 0 COMMENT 'Whether error has been resolved',
  `resolved_at` DATETIME DEFAULT NULL COMMENT 'When error was resolved',
  `resolved_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who resolved error',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_error_code` (`gateway_id`, `error_code`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `error_code_idx` (`error_code`),
  KEY `resolved_idx` (`resolved`),
  KEY `last_occurred_at_idx` (`last_occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway notification table for gateway notifications
CREATE TABLE IF NOT EXISTS `pay_gateway_notification` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `notification_type` VARCHAR(50) NOT NULL COMMENT 'Notification type (maintenance, outage, update)',
  `notification_message` TEXT COMMENT 'Notification message',
  `notification_severity` VARCHAR(20) DEFAULT 'info' COMMENT 'Notification severity (info, warning, critical)',
  `starts_at` DATETIME DEFAULT NULL COMMENT 'When notification starts',
  `ends_at` DATETIME DEFAULT NULL COMMENT 'When notification ends',
  `active` TINYINT(1) DEFAULT 1 COMMENT 'Whether notification is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `notification_type_idx` (`notification_type`),
  KEY `notification_severity_idx` (`notification_severity`),
  KEY `active_idx` (`active`),
  KEY `starts_at_idx` (`starts_at`),
  KEY `ends_at_idx` (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway maintenance table for maintenance tracking
CREATE TABLE IF NOT EXISTS `pay_gateway_maintenance` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `maintenance_type` VARCHAR(50) NOT NULL COMMENT 'Maintenance type (scheduled, emergency)',
  `maintenance_message` TEXT COMMENT 'Maintenance message',
  `scheduled_start` DATETIME DEFAULT NULL COMMENT 'Scheduled start time',
  `scheduled_end` DATETIME DEFAULT NULL COMMENT 'Scheduled end time',
  `actual_start` DATETIME DEFAULT NULL COMMENT 'Actual start time',
  `actual_end` DATETIME DEFAULT NULL COMMENT 'Actual end time',
  `status` VARCHAR(20) DEFAULT 'scheduled' COMMENT 'Maintenance status (scheduled, in_progress, completed, cancelled)',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `maintenance_type_idx` (`maintenance_type`),
  KEY `status_idx` (`status`),
  KEY `scheduled_start_idx` (`scheduled_start`),
  KEY `scheduled_end_idx` (`scheduled_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create payment gateway audit table for audit logging
CREATE TABLE IF NOT EXISTS `pay_gateway_audit` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gateway_id` BIGINT(20) NOT NULL COMMENT 'Payment gateway ID',
  `action` VARCHAR(100) NOT NULL COMMENT 'Audit action (created, updated, deleted, enabled, disabled)',
  `performed_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who performed action',
  `performed_at` DATETIME DEFAULT NULL,
  `old_values` TEXT COMMENT 'Old values before change',
  `new_values` TEXT COMMENT 'New values after change',
  PRIMARY KEY (`id`),
  KEY `gateway_id_idx` (`gateway_id`),
  KEY `action_idx` (`action`),
  KEY `performed_by_idx` (`performed_by`),
  KEY `performed_at_idx` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;