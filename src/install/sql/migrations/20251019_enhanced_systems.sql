-- Migration to enhance all three systems: Coupon, Support, and Tax
-- This migration adds advanced features to all three modules

-- Add advanced coupon features to the existing promo table
ALTER TABLE `promo` 
ADD COLUMN `min_order_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Minimum order amount required to use coupon',
ADD COLUMN `max_discount_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Maximum discount amount that can be applied',
ADD COLUMN `bundle_required` TINYINT(1) DEFAULT 0 COMMENT 'Require bundle purchase to apply coupon',
ADD COLUMN `gift_card_value` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Value of gift card if coupon is a gift card',
ADD COLUMN `loyalty_points_required` INT(11) DEFAULT 0 COMMENT 'Required loyalty points to use coupon',
ADD COLUMN `valid_on_weekends` TINYINT(1) DEFAULT 1 COMMENT 'Whether coupon is valid on weekends',
ADD COLUMN `valid_on_holidays` TINYINT(1) DEFAULT 1 COMMENT 'Whether coupon is valid on holidays',
ADD COLUMN `apply_to_addons` TINYINT(1) DEFAULT 1 COMMENT 'Whether coupon applies to addons',
ADD COLUMN `stackable` TINYINT(1) DEFAULT 0 COMMENT 'Whether coupon can be stacked with other coupons';

-- Create gift card table for gift card functionality
CREATE TABLE IF NOT EXISTS `gift_card` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL COMMENT 'Unique gift card code',
  `value` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Original value of gift card',
  `balance` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Current balance of gift card',
  `promo_id` BIGINT(20) DEFAULT NULL COMMENT 'Associated promo ID',
  `client_id` BIGINT(20) DEFAULT NULL COMMENT 'Assigned client ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `promo_id_idx` (`promo_id`),
  KEY `client_id_idx` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create gift card redemption table for tracking redemptions
CREATE TABLE IF NOT EXISTS `gift_card_redemption` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `gift_card_id` BIGINT(20) DEFAULT NULL COMMENT 'Gift card that was redeemed',
  `amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Amount that was redeemed',
  `invoice_id` BIGINT(20) DEFAULT NULL COMMENT 'Invoice associated with redemption',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gift_card_id_idx` (`gift_card_id`),
  KEY `invoice_id_idx` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create holiday table for holiday tracking
CREATE TABLE IF NOT EXISTS `holiday` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Name of holiday',
  `date` DATE NOT NULL COMMENT 'Date of holiday',
  `country` VARCHAR(100) DEFAULT NULL COMMENT 'Country-specific holiday',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_holiday` (`name`, `date`, `country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create loyalty points table for customer loyalty program
CREATE TABLE IF NOT EXISTS `loyalty_points` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT(20) NOT NULL COMMENT 'Client ID',
  `points` INT(11) DEFAULT 0 COMMENT 'Current loyalty points balance',
  `total_earned` INT(11) DEFAULT 0 COMMENT 'Total points earned',
  `total_spent` INT(11) DEFAULT 0 COMMENT 'Total points spent',
  `last_earned_at` DATETIME DEFAULT NULL COMMENT 'When last points were earned',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  KEY `client_id_idx` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create loyalty points transaction table for tracking point transactions
CREATE TABLE IF NOT EXISTS `loyalty_points_transaction` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT(20) NOT NULL COMMENT 'Client ID',
  `points` INT(11) NOT NULL COMMENT 'Points added/removed (positive/negative)',
  `type` VARCHAR(50) NOT NULL COMMENT 'Type of transaction (earn, spend, expire, bonus)',
  `description` TEXT COMMENT 'Description of transaction',
  `reference_id` BIGINT(20) DEFAULT NULL COMMENT 'Reference to related entity',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id_idx` (`client_id`),
  KEY `type_idx` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add advanced support ticket features to the existing support_ticket table
ALTER TABLE `support_ticket` 
ADD COLUMN `sla_level` VARCHAR(50) DEFAULT 'standard' COMMENT 'SLA level (critical, premium, standard, basic)',
ADD COLUMN `workflow_state` VARCHAR(50) DEFAULT 'new' COMMENT 'Current workflow state',
ADD COLUMN `tags` TEXT COMMENT 'JSON array of ticket tags',
ADD COLUMN `custom_fields` TEXT COMMENT 'JSON object of custom fields',
ADD COLUMN `assigned_to` BIGINT(20) DEFAULT NULL COMMENT 'Assigned staff member ID',
ADD COLUMN `department` VARCHAR(100) DEFAULT NULL COMMENT 'Department assignment',
ADD COLUMN `channel` VARCHAR(50) DEFAULT 'web' COMMENT 'Ticket channel (web, email, phone, chat)',
ADD COLUMN `source` VARCHAR(50) DEFAULT 'customer' COMMENT 'Ticket source (customer, staff, system)',
ADD COLUMN `escalation_level` INT(11) DEFAULT 0 COMMENT 'Escalation level',
ADD COLUMN `due_at` DATETIME DEFAULT NULL COMMENT 'SLA due date',
ADD COLUMN `first_response_at` DATETIME DEFAULT NULL COMMENT 'Timestamp of first response',
ADD COLUMN `resolved_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when ticket was resolved',
ADD COLUMN `satisfaction_rating` INT(11) DEFAULT NULL COMMENT 'Customer satisfaction rating (1-5)',
ADD COLUMN `survey_sent` TINYINT(1) DEFAULT 0 COMMENT 'Whether satisfaction survey was sent',
ADD INDEX `idx_assigned_to` (`assigned_to`),
ADD INDEX `idx_department` (`department`),
ADD INDEX `idx_channel` (`channel`),
ADD INDEX `idx_due_at` (`due_at`),
ADD INDEX `idx_workflow_state` (`workflow_state`);

-- Create support ticket status log table for tracking status changes
CREATE TABLE IF NOT EXISTS `support_ticket_status_log` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT(20) NOT NULL COMMENT 'Ticket ID',
  `old_status` VARCHAR(30) NOT NULL COMMENT 'Previous status',
  `new_status` VARCHAR(30) NOT NULL COMMENT 'New status',
  `changed_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who changed status',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id_idx` (`ticket_id`),
  KEY `changed_by_idx` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket survey table for customer satisfaction
CREATE TABLE IF NOT EXISTS `support_ticket_survey` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT(20) NOT NULL COMMENT 'Ticket ID',
  `rating` INT(11) DEFAULT NULL COMMENT 'Satisfaction rating (1-5)',
  `comments` TEXT COMMENT 'Customer comments',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_id` (`ticket_id`),
  KEY `ticket_id_idx` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket collaboration table for team collaboration
CREATE TABLE IF NOT EXISTS `support_ticket_collaborator` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT(20) NOT NULL COMMENT 'Ticket ID',
  `admin_id` BIGINT(20) NOT NULL COMMENT 'Collaborating admin ID',
  `role` VARCHAR(50) DEFAULT 'collaborator' COMMENT 'Role in collaboration (collaborator, observer)',
  `added_at` DATETIME DEFAULT NULL,
  `added_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who added collaborator',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_admin` (`ticket_id`, `admin_id`),
  KEY `ticket_id_idx` (`ticket_id`),
  KEY `admin_id_idx` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket attachment table for file attachments
CREATE TABLE IF NOT EXISTS `support_ticket_attachment` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT(20) NOT NULL COMMENT 'Ticket ID',
  `message_id` BIGINT(20) DEFAULT NULL COMMENT 'Message ID (null for ticket-level attachments)',
  `filename` VARCHAR(255) NOT NULL COMMENT 'Original filename',
  `filepath` VARCHAR(500) NOT NULL COMMENT 'File path on server',
  `filesize` BIGINT(20) DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` VARCHAR(100) DEFAULT NULL COMMENT 'MIME type',
  `uploaded_by` BIGINT(20) DEFAULT NULL COMMENT 'Uploader ID (client or admin)',
  `uploaded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id_idx` (`ticket_id`),
  KEY `message_id_idx` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support helpdesk SLA table for SLA definitions
CREATE TABLE IF NOT EXISTS `support_helpdesk_sla` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `helpdesk_id` BIGINT(20) NOT NULL COMMENT 'Helpdesk ID',
  `name` VARCHAR(100) NOT NULL COMMENT 'SLA name',
  `description` TEXT COMMENT 'SLA description',
  `response_time_critical` INT(11) DEFAULT NULL COMMENT 'Response time in minutes for critical priority',
  `response_time_high` INT(11) DEFAULT NULL COMMENT 'Response time in minutes for high priority',
  `response_time_medium` INT(11) DEFAULT NULL COMMENT 'Response time in minutes for medium priority',
  `response_time_low` INT(11) DEFAULT NULL COMMENT 'Response time in minutes for low priority',
  `resolution_time_critical` INT(11) DEFAULT NULL COMMENT 'Resolution time in minutes for critical priority',
  `resolution_time_high` INT(11) DEFAULT NULL COMMENT 'Resolution time in minutes for high priority',
  `resolution_time_medium` INT(11) DEFAULT NULL COMMENT 'Resolution time in minutes for medium priority',
  `resolution_time_low` INT(11) DEFAULT NULL COMMENT 'Resolution time in minutes for low priority',
  `is_default` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is the default SLA',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `helpdesk_id_idx` (`helpdesk_id`),
  KEY `is_default_idx` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket macro table for canned responses
CREATE TABLE IF NOT EXISTS `support_ticket_macro` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `helpdesk_id` BIGINT(20) DEFAULT NULL COMMENT 'Helpdesk ID (null for global macros)',
  `name` VARCHAR(255) NOT NULL COMMENT 'Macro name',
  `description` TEXT COMMENT 'Macro description',
  `content` TEXT NOT NULL COMMENT 'Macro content',
  `shortcut` VARCHAR(50) DEFAULT NULL COMMENT 'Keyboard shortcut',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether macro is active',
  `created_by` BIGINT(20) DEFAULT NULL COMMENT 'Creator admin ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `helpdesk_id_idx` (`helpdesk_id`),
  KEY `is_active_idx` (`is_active`),
  KEY `created_by_idx` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket template table for ticket templates
CREATE TABLE IF NOT EXISTS `support_ticket_template` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `helpdesk_id` BIGINT(20) DEFAULT NULL COMMENT 'Helpdesk ID (null for global templates)',
  `name` VARCHAR(255) NOT NULL COMMENT 'Template name',
  `subject` VARCHAR(255) NOT NULL COMMENT 'Template subject',
  `content` TEXT NOT NULL COMMENT 'Template content',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether template is active',
  `created_by` BIGINT(20) DEFAULT NULL COMMENT 'Creator admin ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `helpdesk_id_idx` (`helpdesk_id`),
  KEY `is_active_idx` (`is_active`),
  KEY `created_by_idx` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket workflow table for automated workflows
CREATE TABLE IF NOT EXISTS `support_ticket_workflow` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `helpdesk_id` BIGINT(20) DEFAULT NULL COMMENT 'Helpdesk ID (null for global workflows)',
  `name` VARCHAR(255) NOT NULL COMMENT 'Workflow name',
  `description` TEXT COMMENT 'Workflow description',
  `trigger_type` VARCHAR(50) NOT NULL COMMENT 'Trigger type (ticket_created, ticket_updated, status_changed, etc.)',
  `trigger_conditions` TEXT COMMENT 'JSON object of trigger conditions',
  `actions` TEXT NOT NULL COMMENT 'JSON array of actions to perform',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether workflow is active',
  `sort_order` INT(11) DEFAULT 0 COMMENT 'Execution order',
  `created_by` BIGINT(20) DEFAULT NULL COMMENT 'Creator admin ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `helpdesk_id_idx` (`helpdesk_id`),
  KEY `is_active_idx` (`is_active`),
  KEY `trigger_type_idx` (`trigger_type`),
  KEY `sort_order_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add indexes to existing support_ticket_message table for better performance
ALTER TABLE `support_ticket_message` 
ADD INDEX `idx_admin_id` (`admin_id`),
ADD INDEX `idx_created_at` (`created_at`);

-- Add indexes to existing support_ticket_note table for better performance
ALTER TABLE `support_ticket_note` 
ADD INDEX `idx_admin_id` (`admin_id`),
ADD INDEX `idx_created_at` (`created_at`);

-- Create support ticket knowledge base category table
CREATE TABLE IF NOT EXISTS `support_kb_category` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT(20) DEFAULT NULL COMMENT 'Parent category ID',
  `title` VARCHAR(255) NOT NULL COMMENT 'Category title',
  `description` TEXT COMMENT 'Category description',
  `slug` VARCHAR(255) NOT NULL COMMENT 'URL slug',
  `sort_order` INT(11) DEFAULT 0 COMMENT 'Display order',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether category is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id_idx` (`parent_id`),
  KEY `is_active_idx` (`is_active`),
  KEY `sort_order_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket knowledge base article table
CREATE TABLE IF NOT EXISTS `support_kb_article` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT(20) NOT NULL COMMENT 'Category ID',
  `title` VARCHAR(255) NOT NULL COMMENT 'Article title',
  `slug` VARCHAR(255) NOT NULL COMMENT 'URL slug',
  `content` LONGTEXT NOT NULL COMMENT 'Article content',
  `excerpt` TEXT COMMENT 'Short excerpt for listings',
  `keywords` TEXT COMMENT 'SEO keywords',
  `views` BIGINT(20) DEFAULT 0 COMMENT 'View count',
  `helpful_votes` INT(11) DEFAULT 0 COMMENT 'Helpful votes count',
  `not_helpful_votes` INT(11) DEFAULT 0 COMMENT 'Not helpful votes count',
  `is_published` TINYINT(1) DEFAULT 0 COMMENT 'Whether article is published',
  `published_at` DATETIME DEFAULT NULL COMMENT 'Publication date',
  `created_by` BIGINT(20) DEFAULT NULL COMMENT 'Creator admin ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id_idx` (`category_id`),
  KEY `is_published_idx` (`is_published`),
  KEY `created_by_idx` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create support ticket knowledge base article feedback table
CREATE TABLE IF NOT EXISTS `support_kb_article_feedback` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT(20) NOT NULL COMMENT 'Article ID',
  `client_id` BIGINT(20) DEFAULT NULL COMMENT 'Client ID (null for anonymous)',
  `is_helpful` TINYINT(1) DEFAULT NULL COMMENT 'Whether feedback was helpful (1=yes, 0=no)',
  `comments` TEXT COMMENT 'Feedback comments',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of voter',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id_idx` (`article_id`),
  KEY `client_id_idx` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add advanced tax features to the existing tax table
ALTER TABLE `tax` 
ADD COLUMN `tax_type` VARCHAR(50) DEFAULT 'standard' COMMENT 'Tax type (standard, reduced, exempt, reverse_charge)',
ADD COLUMN `compound_tax` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is compound tax',
ADD COLUMN `threshold_amount` DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Threshold for tax application',
ADD COLUMN `threshold_type` VARCHAR(20) DEFAULT 'order' COMMENT 'Threshold type (order, annual)',
ADD COLUMN `exempt_reason` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for tax exemption',
ADD COLUMN `reverse_charge` TINYINT(1) DEFAULT 0 COMMENT 'Whether reverse charge applies',
ADD COLUMN `effective_from` DATETIME DEFAULT NULL COMMENT 'Effective from date',
ADD COLUMN `effective_to` DATETIME DEFAULT NULL COMMENT 'Effective to date',
ADD COLUMN `product_categories` TEXT COMMENT 'JSON array of product categories covered by tax rule',
ADD COLUMN `client_groups` TEXT COMMENT 'JSON array of client groups covered by tax rule',
ADD COLUMN `registration_required` TINYINT(1) DEFAULT 0 COMMENT 'Whether tax registration is required',
ADD COLUMN `registration_format` VARCHAR(100) DEFAULT NULL COMMENT 'Required format for tax registration',
ADD INDEX `idx_tax_type` (`tax_type`),
ADD INDEX `idx_effective_from` (`effective_from`),
ADD INDEX `idx_effective_to` (`effective_to`),
ADD INDEX `idx_registration_required` (`registration_required`);

-- Create tax exemption table for client-specific exemptions
CREATE TABLE IF NOT EXISTS `tax_exemption` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT(20) NOT NULL COMMENT 'Client ID',
  `exemption_type` VARCHAR(50) NOT NULL DEFAULT 'full' COMMENT 'Exemption type (full, partial, product_specific)',
  `exemption_reason` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for exemption',
  `exemption_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Exemption rate for partial exemptions',
  `product_categories` TEXT COMMENT 'JSON array of product categories covered by exemption',
  `valid_from` DATE DEFAULT NULL COMMENT 'Exemption valid from date',
  `valid_to` DATE DEFAULT NULL COMMENT 'Exemption valid to date',
  `certificate_number` VARCHAR(100) DEFAULT NULL COMMENT 'Tax exemption certificate number',
  `certificate_issuer` VARCHAR(255) DEFAULT NULL COMMENT 'Certificate issuer',
  `certificate_file` VARCHAR(500) DEFAULT NULL COMMENT 'Path to certificate file',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether exemption is active',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id_idx` (`client_id`),
  KEY `exemption_type_idx` (`exemption_type`),
  KEY `is_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax category mapping table for product categories
CREATE TABLE IF NOT EXISTS `tax_category_mapping` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) NOT NULL COMMENT 'Tax rule ID',
  `category_id` BIGINT(20) NOT NULL COMMENT 'Category ID',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_category` (`tax_id`, `category_id`),
  KEY `tax_id_idx` (`tax_id`),
  KEY `category_id_idx` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax client group mapping table for client groups
CREATE TABLE IF NOT EXISTS `tax_client_group_mapping` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) NOT NULL COMMENT 'Tax rule ID',
  `client_group_id` BIGINT(20) NOT NULL COMMENT 'Client group ID',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_client_group` (`tax_id`, `client_group_id`),
  KEY `tax_id_idx` (`tax_id`),
  KEY `client_group_id_idx` (`client_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax jurisdiction table for complex jurisdiction mappings
CREATE TABLE IF NOT EXISTS `tax_jurisdiction` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) NOT NULL COMMENT 'Tax rule ID',
  `jurisdiction_type` VARCHAR(50) NOT NULL COMMENT 'Jurisdiction type (country, state, county, city, district)',
  `jurisdiction_code` VARCHAR(100) NOT NULL COMMENT 'Jurisdiction code',
  `jurisdiction_name` VARCHAR(255) NOT NULL COMMENT 'Jurisdiction name',
  `parent_jurisdiction_id` BIGINT(20) DEFAULT NULL COMMENT 'Parent jurisdiction ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_id_idx` (`tax_id`),
  KEY `jurisdiction_type_idx` (`jurisdiction_type`),
  KEY `jurisdiction_code_idx` (`jurisdiction_code`),
  KEY `parent_jurisdiction_id_idx` (`parent_jurisdiction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax audit log table for tracking tax changes
CREATE TABLE IF NOT EXISTS `tax_audit_log` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) DEFAULT NULL COMMENT 'Tax rule ID',
  `invoice_id` BIGINT(20) DEFAULT NULL COMMENT 'Invoice ID',
  `client_id` BIGINT(20) DEFAULT NULL COMMENT 'Client ID',
  `action` VARCHAR(50) NOT NULL COMMENT 'Action performed (calculated, applied, exempted, adjusted)',
  `old_value` TEXT COMMENT 'Old tax value',
  `new_value` TEXT COMMENT 'New tax value',
  `reason` TEXT COMMENT 'Reason for change',
  `performed_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who performed action',
  `performed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_id_idx` (`tax_id`),
  KEY `invoice_id_idx` (`invoice_id`),
  KEY `client_id_idx` (`client_id`),
  KEY `action_idx` (`action`),
  KEY `performed_by_idx` (`performed_by`),
  KEY `performed_at_idx` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax calendar table for filing deadlines
CREATE TABLE IF NOT EXISTS `tax_calendar` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `country` VARCHAR(100) NOT NULL COMMENT 'Country code',
  `tax_type` VARCHAR(50) NOT NULL COMMENT 'Tax type (vat, income, sales, etc.)',
  `description` TEXT NOT NULL COMMENT 'Calendar event description',
  `due_date` DATETIME NOT NULL COMMENT 'Due date',
  `deadline` TINYINT(1) DEFAULT 1 COMMENT 'Whether this is a hard deadline',
  `notification_days` INT(11) DEFAULT 7 COMMENT 'Days before due date to send notification',
  `is_recurring` TINYINT(1) DEFAULT 1 COMMENT 'Whether this recurs annually',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `country_idx` (`country`),
  KEY `tax_type_idx` (`tax_type`),
  KEY `due_date_idx` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax registration table for client tax registrations
CREATE TABLE IF NOT EXISTS `tax_registration` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT(20) NOT NULL COMMENT 'Client ID',
  `country` VARCHAR(100) NOT NULL COMMENT 'Country code',
  `registration_number` VARCHAR(100) NOT NULL COMMENT 'Tax registration number',
  `registration_type` VARCHAR(50) DEFAULT 'vat' COMMENT 'Registration type (vat, gst, etc.)',
  `valid_from` DATE DEFAULT NULL COMMENT 'Registration valid from date',
  `valid_to` DATE DEFAULT NULL COMMENT 'Registration valid to date',
  `is_verified` TINYINT(1) DEFAULT 0 COMMENT 'Whether registration is verified',
  `verification_date` DATETIME DEFAULT NULL COMMENT 'Verification date',
  `verified_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who verified registration',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_country` (`client_id`, `country`),
  KEY `client_id_idx` (`client_id`),
  KEY `country_idx` (`country`),
  KEY `is_verified_idx` (`is_verified`),
  KEY `verified_by_idx` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax rate history table for tracking rate changes
CREATE TABLE IF NOT EXISTS `tax_rate_history` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) NOT NULL COMMENT 'Tax rule ID',
  `old_rate` DECIMAL(10,2) NOT NULL COMMENT 'Old tax rate',
  `new_rate` DECIMAL(10,2) NOT NULL COMMENT 'New tax rate',
  `effective_date` DATE NOT NULL COMMENT 'Date when change became effective',
  `reason` TEXT COMMENT 'Reason for rate change',
  `changed_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin who made change',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_id_idx` (`tax_id`),
  KEY `effective_date_idx` (`effective_date`),
  KEY `changed_by_idx` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add indexes to existing invoice table for better tax performance
ALTER TABLE `invoice` 
ADD INDEX `idx_taxrate` (`taxrate`),
ADD INDEX `idx_taxname` (`taxname`);

-- Add indexes to existing client table for better tax performance
ALTER TABLE `client` 
ADD INDEX `idx_company_vat` (`company_vat`);