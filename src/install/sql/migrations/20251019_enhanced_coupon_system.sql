-- Migration to enhance coupon system with advanced features
-- Add columns for advanced coupon features to the existing promo table

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