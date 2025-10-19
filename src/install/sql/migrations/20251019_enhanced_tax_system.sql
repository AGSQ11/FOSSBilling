-- Migration to enhance tax system with advanced features
-- Add columns for advanced tax features to the existing tax table

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
  `exemption_type` VARCHAR(50) DEFAULT 'full' COMMENT 'Exemption type (full, partial, product_specific)',
  `exemption_reason` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for exemption',
  `exemption_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Exemption rate for partial exemptions',
  `product_categories` TEXT COMMENT 'JSON array of product categories covered by exemption',
  `valid_from` DATETIME DEFAULT NULL COMMENT 'Exemption valid from date',
  `valid_to` DATETIME DEFAULT NULL COMMENT 'Exemption valid to date',
  `certificate_number` VARCHAR(100) DEFAULT NULL COMMENT 'Tax exemption certificate number',
  `certificate_issuer` VARCHAR(255) DEFAULT NULL COMMENT 'Certificate issuer',
  `certificate_file` VARCHAR(500) DEFAULT NULL COMMENT 'Path to certificate file',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id_idx` (`client_id`),
  KEY `valid_from_idx` (`valid_from`),
  KEY `valid_to_idx` (`valid_to`),
  KEY `exemption_type_idx` (`exemption_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax category mapping table for product categories
CREATE TABLE IF NOT EXISTS `tax_category_mapping` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) NOT NULL COMMENT 'Tax rule ID',
  `category_id` BIGINT(20) NOT NULL COMMENT 'Product category ID',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_category` (`tax_id`, `category_id`),
  KEY `tax_id_idx` (`tax_id`),
  KEY `category_id_idx` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax client group mapping table for client group restrictions
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
  `performed_by` BIGINT(20) DEFAULT NULL COMMENT 'Admin ID who performed action',
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
  `due_date` DATE NOT NULL COMMENT 'Due date',
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
  KEY `registration_number_idx` (`registration_number`),
  KEY `is_verified_idx` (`is_verified`),
  KEY `verified_by_idx` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create tax rate history table for tracking rate changes
CREATE TABLE IF NOT EXISTS `tax_rate_history` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tax_id` BIGINT(20) NOT NULL COMMENT 'Tax rule ID',
  `old_rate` DECIMAL(10,4) NOT NULL COMMENT 'Old tax rate',
  `new_rate` DECIMAL(10,4) NOT NULL COMMENT 'New tax rate',
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