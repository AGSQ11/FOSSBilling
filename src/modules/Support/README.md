# FOSSBilling Support Module - Enhanced Features Documentation

## Overview
The enhanced Support module provides an advanced ticket management system with SLA management, automated workflows, and a comprehensive knowledge base.

## Features

### Advanced Ticket Management
- **SLA Management**: Define service level agreements for different ticket priorities and helpdesks.
- **Automated Workflows**: Create custom workflows to automate ticket routing, assignment, and escalation.
- **Ticket Collaboration**: Allow multiple staff members to collaborate on a single ticket.
- **Internal Notes**: Add private notes to tickets for internal communication.
- **Ticket Timeline**: View a complete history of all actions taken on a ticket.

### Knowledge Base
- **Article Management**: Create and manage knowledge base articles.
- **Category Management**: Organize articles into categories and subcategories.
- **Article Feedback**: Allow clients to rate articles and provide feedback.
- **Search Functionality**: Full-text search for finding relevant articles.

### Customer Satisfaction Surveys
- **Automated Surveys**: Send satisfaction surveys to clients after a ticket is closed.
- **Rating System**: Allow clients to rate their support experience.
- **Feedback Collection**: Collect comments and suggestions for improvement.

## Database Changes

### New Columns in `support_ticket` Table
- `sla_level`
- `workflow_state`
- `tags`
- `custom_fields`
- `assigned_to`
- `department`
- `channel`
- `source`
- `escalation_level`
- `due_at`
- `first_response_at`
- `resolved_at`
- `satisfaction_rating`
- `survey_sent`

### New Tables
- `support_ticket_status_log`
- `support_ticket_survey`
- `support_ticket_collaborator`
- `support_ticket_attachment`
- `support_helpdesk_sla`
- `support_ticket_macro`
- `support_ticket_template`
- `support_ticket_workflow`
- `support_kb_category`
- `support_kb_article`
- `support_kb_article_feedback`

## API Endpoints

### Admin API
- `support_create_advanced`
- `support_add_internal_note`
- `support_update_sla`
- `support_escalate_workflow`
- `support_get_collaborators`
- `support_add_collaborator`
- `support_get_survey`
- `support_submit_survey`
- `support_get_timeline`
- `support_batch_assign`
- `support_batch_update_priority`
- `support_batch_close`
- `support_get_macros`
- `support_create_macro`
- `support_update_macro`
- `support_delete_macro`
- `support_get_templates`
- `support_create_template`
- `support_update_template`
- `support_delete_template`
- `support_get_kb_categories`
- `support_create_kb_category`
- `support_update_kb_category`
- `support_delete_kb_category`
- `support_get_kb_articles`
- `support_create_kb_article`
- `support_update_kb_article`
- `support_delete_kb_article`
- `support_get_kb_article_feedback`
- `support_get_slas`
- `support_create_sla`
- `support_update_sla`
- `support_delete_sla`
- `support_get_workflows`
- `support_create_workflow`
- `support_update_workflow`
- `support_delete_workflow`

### Client API
- `support_get_kb_categories`
- `support_get_kb_articles`
- `support_get_kb_article`
- `support_submit_kb_article_feedback`

## Implementation Details
- **SLA Calculation**: Due dates are calculated based on SLA rules and ticket priority.
- **Workflow Engine**: Workflows are triggered by events and execute a series of actions.
- **Knowledge Base**: Articles are stored in the database and can be searched and categorized.

## Security Considerations
- All API endpoints have proper access control.
- Input is validated to prevent security vulnerabilities.
- Attachments are stored securely on the server.

## Extending the Module
- Add new workflow triggers and actions.
- Integrate with third-party knowledge base systems.
- Create custom SLA rules and escalation paths.