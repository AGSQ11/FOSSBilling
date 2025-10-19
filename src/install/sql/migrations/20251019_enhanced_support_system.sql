-- Migration to enhance support ticket system with advanced features
-- Add columns for advanced support ticket features

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

-- Add indexes to existing tables for better performance
ALTER TABLE `support_ticket_message` 
ADD INDEX `idx_admin_id` (`admin_id`),
ADD INDEX `idx_created_at` (`created_at`);

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