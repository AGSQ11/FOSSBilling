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
 * Support ticket management.
 */

namespace Box\Mod\Support\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of tickets.
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
            $model = $this->di['db']->getExistingModelById('SupportTicket', $item['id'], 'Ticket not found');
            $pager['list'][$key] = $this->getService()->toApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get ticket details.
     *
     * @param array $data
     *
     * @return array
     */
    public function get($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Create new ticket.
     *
     * @param array $data
     *
     * @return int - new ticket ID
     */
    public function create($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
            'support_helpdesk_id' => 'Helpdesk ID is required',
            'subject' => 'Subject is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createTicket($data);
    }

    /**
     * Update ticket.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->updateTicket($model, $data);
    }

    /**
     * Close ticket.
     *
     * @param array $data
     *
     * @return bool
     */
    public function close($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->closeTicket($model);
    }

    /**
     * Reopen ticket.
     *
     * @param array $data
     *
     * @return bool
     */
    public function reopen($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->reopenTicket($model);
    }

    /**
     * Delete ticket.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->deleteTicket($model);
    }

    /**
     * Add message to ticket.
     *
     * @param array $data
     *
     * @return int - message ID
     */
    public function add_message($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
            'content' => 'Message content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->addMessage($model, $data);
    }

    /**
     * Get ticket messages.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_messages($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->getTicketMessages($model);
    }

    /**
     * Assign ticket to staff member.
     *
     * @param array $data
     *
     * @return bool
     */
    public function assign($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
            'staff_id' => 'Staff ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->assignTicket($model, $data['staff_id']);
    }

    /**
     * Escalate ticket priority.
     *
     * @param array $data
     *
     * @return bool
     */
    public function escalate($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        $levels = $data['levels'] ?? 1;

        return $this->getService()->escalateTicket($model, $levels);
    }

    /**
     * Get ticket statistics.
     *
     * @return array
     */
    public function get_stats()
    {
        return $this->getService()->getTicketStats();
    }

    /**
     * Create advanced ticket with SLA and workflow rules.
     *
     * @param array $data
     *
     * @return int - new ticket ID
     */
    public function create_advanced($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
            'support_helpdesk_id' => 'Helpdesk ID is required',
            'subject' => 'Subject is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createAdvancedTicket($data);
    }

    /**
     * Add internal note to ticket.
     *
     * @param array $data
     *
     * @return int - note ID
     */
    public function add_internal_note($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
            'admin_id' => 'Admin ID is required',
            'note' => 'Note content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->addInternalNote($model, $data);
    }

    /**
     * Get internal notes for ticket.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_internal_notes($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->getInternalNotes($model);
    }

    /**
     * Update ticket SLA.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_sla($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
            'sla_level' => 'SLA level is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->updateTicketSLA($model, $data['sla_level']);
    }

    /**
     * Escalate ticket through workflow.
     *
     * @param array $data
     *
     * @return bool
     */
    public function escalate_workflow($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->escalateTicketWorkflow($model, $data);
    }

    /**
     * Get ticket collaboration participants.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_collaborators($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->getCollaborators($model);
    }

    /**
     * Add collaborator to ticket.
     *
     * @param array $data
     *
     * @return bool
     */
    public function add_collaborator($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
            'admin_id' => 'Admin ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->addCollaborator($model, $data['admin_id']);
    }

    /**
     * Get ticket satisfaction survey.
     *
     * @param array $data
     *
     * @return array|null
     */
    public function get_survey($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->getTicketSurvey($model);
    }

    /**
     * Submit ticket satisfaction survey.
     *
     * @param array $data
     *
     * @return int - survey ID
     */
    public function submit_survey($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
            'rating' => 'Rating is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->submitTicketSurvey($model, $data);
    }

    /**
     * Get ticket timeline events.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_timeline($data)
    {
        $required = [
            'id' => 'Ticket ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->getTicketTimeline($model);
    }

    /**
     * Batch assign tickets to staff member.
     *
     * @param array $data
     *
     * @return bool
     */
    public function batch_assign($data)
    {
        $required = [
            'ticket_ids' => 'Ticket IDs are required',
            'staff_id' => 'Staff ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticketIds = $data['ticket_ids'];
        $staffId = $data['staff_id'];

        foreach ($ticketIds as $ticketId) {
            $model = $this->di['db']->getExistingModelById('SupportTicket', $ticketId, 'Ticket not found');
            $this->getService()->assignTicket($model, $staffId);
        }

        return true;
    }

    /**
     * Batch update ticket priorities.
     *
     * @param array $data
     *
     * @return bool
     */
    public function batch_update_priority($data)
    {
        $required = [
            'ticket_ids' => 'Ticket IDs are required',
            'priority' => 'Priority is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticketIds = $data['ticket_ids'];
        $priority = $data['priority'];

        foreach ($ticketIds as $ticketId) {
            $model = $this->di['db']->getExistingModelById('SupportTicket', $ticketId, 'Ticket not found');
            $this->getService()->updateTicket($model, ['priority' => $priority]);
        }

        return true;
    }

    /**
     * Batch close tickets.
     *
     * @param array $data
     *
     * @return bool
     */
    public function batch_close($data)
    {
        $required = [
            'ticket_ids' => 'Ticket IDs are required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticketIds = $data['ticket_ids'];

        foreach ($ticketIds as $ticketId) {
            $model = $this->di['db']->getExistingModelById('SupportTicket', $ticketId, 'Ticket not found');
            $this->getService()->closeTicket($model);
        }

        return true;
    }

    /**
     * Get ticket macros.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_macros($data)
    {
        $helpdeskId = $data['helpdesk_id'] ?? null;
        
        $macros = $this->di['db']->find('SupportTicketMacro', 'helpdesk_id = :helpdesk_id OR helpdesk_id IS NULL', [':helpdesk_id' => $helpdeskId]);
        
        $result = [];
        foreach ($macros as $macro) {
            $result[] = $this->di['db']->toArray($macro);
        }
        
        return $result;
    }

    /**
     * Create ticket macro.
     *
     * @param array $data
     *
     * @return int - macro ID
     */
    public function create_macro($data)
    {
        $required = [
            'name' => 'Macro name is required',
            'content' => 'Macro content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $macro = $this->di['db']->dispense('SupportTicketMacro');
        $macro->helpdesk_id = $data['helpdesk_id'] ?? null;
        $macro->name = $data['name'];
        $macro->description = $data['description'] ?? null;
        $macro->content = $data['content'];
        $macro->shortcut = $data['shortcut'] ?? null;
        $macro->is_active = $data['is_active'] ?? 1;
        $macro->created_by = $this->getIdentity()->id;
        $macro->created_at = date('Y-m-d H:i:s');
        $macro->updated_at = date('Y-m-d H:i:s');
        $macroId = $this->di['db']->store($macro);

        $this->di['logger']->info('Created support ticket macro #%s', $macroId);

        return $macroId;
    }

    /**
     * Update ticket macro.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_macro($data)
    {
        $required = [
            'id' => 'Macro ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $macro = $this->di['db']->getExistingModelById('SupportTicketMacro', $data['id'], 'Macro not found');
        $macro->helpdesk_id = $data['helpdesk_id'] ?? $macro->helpdesk_id;
        $macro->name = $data['name'] ?? $macro->name;
        $macro->description = $data['description'] ?? $macro->description;
        $macro->content = $data['content'] ?? $macro->content;
        $macro->shortcut = $data['shortcut'] ?? $macro->shortcut;
        $macro->is_active = $data['is_active'] ?? $macro->is_active;
        $macro->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($macro);

        $this->di['logger']->info('Updated support ticket macro #%s', $macro->id);

        return true;
    }

    /**
     * Delete ticket macro.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_macro($data)
    {
        $required = [
            'id' => 'Macro ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $macro = $this->di['db']->getExistingModelById('SupportTicketMacro', $data['id'], 'Macro not found');
        $macroId = $macro->id;
        $this->di['db']->trash($macro);

        $this->di['logger']->info('Deleted support ticket macro #%s', $macroId);

        return true;
    }

    /**
     * Get ticket templates.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_templates($data)
    {
        $helpdeskId = $data['helpdesk_id'] ?? null;
        
        $templates = $this->di['db']->find('SupportTicketTemplate', 'helpdesk_id = :helpdesk_id OR helpdesk_id IS NULL', [':helpdesk_id' => $helpdeskId]);
        
        $result = [];
        foreach ($templates as $template) {
            $result[] = $this->di['db']->toArray($template);
        }
        
        return $result;
    }

    /**
     * Create ticket template.
     *
     * @param array $data
     *
     * @return int - template ID
     */
    public function create_template($data)
    {
        $required = [
            'name' => 'Template name is required',
            'subject' => 'Template subject is required',
            'content' => 'Template content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $template = $this->di['db']->dispense('SupportTicketTemplate');
        $template->helpdesk_id = $data['helpdesk_id'] ?? null;
        $template->name = $data['name'];
        $template->subject = $data['subject'];
        $template->content = $data['content'];
        $template->is_active = $data['is_active'] ?? 1;
        $template->created_by = $this->getIdentity()->id;
        $template->created_at = date('Y-m-d H:i:s');
        $template->updated_at = date('Y-m-d H:i:s');
        $templateId = $this->di['db']->store($template);

        $this->di['logger']->info('Created support ticket template #%s', $templateId);

        return $templateId;
    }

    /**
     * Update ticket template.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_template($data)
    {
        $required = [
            'id' => 'Template ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $template = $this->di['db']->getExistingModelById('SupportTicketTemplate', $data['id'], 'Template not found');
        $template->helpdesk_id = $data['helpdesk_id'] ?? $template->helpdesk_id;
        $template->name = $data['name'] ?? $template->name;
        $template->subject = $data['subject'] ?? $template->subject;
        $template->content = $data['content'] ?? $template->content;
        $template->is_active = $data['is_active'] ?? $template->is_active;
        $template->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($template);

        $this->di['logger']->info('Updated support ticket template #%s', $template->id);

        return true;
    }

    /**
     * Delete ticket template.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_template($data)
    {
        $required = [
            'id' => 'Template ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $template = $this->di['db']->getExistingModelById('SupportTicketTemplate', $data['id'], 'Template not found');
        $templateId = $template->id;
        $this->di['db']->trash($template);

        $this->di['logger']->info('Deleted support ticket template #%s', $templateId);

        return true;
    }

    /**
     * Get knowledge base categories.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_kb_categories($data)
    {
        $parentId = $data['parent_id'] ?? null;
        
        if ($parentId) {
            $categories = $this->di['db']->find('SupportKbCategory', 'parent_id = :parent_id', [':parent_id' => $parentId]);
        } else {
            $categories = $this->di['db']->find('SupportKbCategory', 'ORDER BY sort_order');
        }
        
        $result = [];
        foreach ($categories as $category) {
            $result[] = $this->di['db']->toArray($category);
        }
        
        return $result;
    }

    /**
     * Create knowledge base category.
     *
     * @param array $data
     *
     * @return int - category ID
     */
    public function create_kb_category($data)
    {
        $required = [
            'title' => 'Category title is required',
            'slug' => 'Category slug is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $category = $this->di['db']->dispense('SupportKbCategory');
        $category->parent_id = $data['parent_id'] ?? null;
        $category->title = $data['title'];
        $category->description = $data['description'] ?? null;
        $category->slug = $data['slug'];
        $category->sort_order = $data['sort_order'] ?? 0;
        $category->is_active = $data['is_active'] ?? 1;
        $category->created_at = date('Y-m-d H:i:s');
        $category->updated_at = date('Y-m-d H:i:s');
        $categoryId = $this->di['db']->store($category);

        $this->di['logger']->info('Created knowledge base category #%s', $categoryId);

        return $categoryId;
    }

    /**
     * Update knowledge base category.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_kb_category($data)
    {
        $required = [
            'id' => 'Category ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $category = $this->di['db']->getExistingModelById('SupportKbCategory', $data['id'], 'Category not found');
        $category->parent_id = $data['parent_id'] ?? $category->parent_id;
        $category->title = $data['title'] ?? $category->title;
        $category->description = $data['description'] ?? $category->description;
        $category->slug = $data['slug'] ?? $category->slug;
        $category->sort_order = $data['sort_order'] ?? $category->sort_order;
        $category->is_active = $data['is_active'] ?? $category->is_active;
        $category->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($category);

        $this->di['logger']->info('Updated knowledge base category #%s', $category->id);

        return true;
    }

    /**
     * Delete knowledge base category.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_kb_category($data)
    {
        $required = [
            'id' => 'Category ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $category = $this->di['db']->getExistingModelById('SupportKbCategory', $data['id'], 'Category not found');
        $categoryId = $category->id;
        $this->di['db']->trash($category);

        $this->di['logger']->info('Deleted knowledge base category #%s', $categoryId);

        return true;
    }

    /**
     * Get knowledge base articles.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_kb_articles($data)
    {
        $categoryId = $data['category_id'] ?? null;
        $isPublished = $data['is_published'] ?? null;
        
        $where = '1=1';
        $params = [];
        
        if ($categoryId) {
            $where .= ' AND category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }
        
        if ($isPublished !== null) {
            $where .= ' AND is_published = :is_published';
            $params[':is_published'] = $isPublished;
        }
        
        $articles = $this->di['db']->find('SupportKbArticle', "$where ORDER BY created_at DESC", $params);
        
        $result = [];
        foreach ($articles as $article) {
            $result[] = $this->di['db']->toArray($article);
        }
        
        return $result;
    }

    /**
     * Create knowledge base article.
     *
     * @param array $data
     *
     * @return int - article ID
     */
    public function create_kb_article($data)
    {
        $required = [
            'category_id' => 'Category ID is required',
            'title' => 'Article title is required',
            'slug' => 'Article slug is required',
            'content' => 'Article content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $article = $this->di['db']->dispense('SupportKbArticle');
        $article->category_id = $data['category_id'];
        $article->title = $data['title'];
        $article->slug = $data['slug'];
        $article->content = $data['content'];
        $article->excerpt = $data['excerpt'] ?? null;
        $article->keywords = $data['keywords'] ?? null;
        $article->is_published = $data['is_published'] ?? 0;
        $article->published_at = !empty($data['published_at']) ? date('Y-m-d H:i:s', strtotime($data['published_at'])) : null;
        $article->created_by = $this->getIdentity()->id;
        $article->created_at = date('Y-m-d H:i:s');
        $article->updated_at = date('Y-m-d H:i:s');
        $articleId = $this->di['db']->store($article);

        $this->di['logger']->info('Created knowledge base article #%s', $articleId);

        return $articleId;
    }

    /**
     * Update knowledge base article.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_kb_article($data)
    {
        $required = [
            'id' => 'Article ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $article = $this->di['db']->getExistingModelById('SupportKbArticle', $data['id'], 'Article not found');
        $article->category_id = $data['category_id'] ?? $article->category_id;
        $article->title = $data['title'] ?? $article->title;
        $article->slug = $data['slug'] ?? $article->slug;
        $article->content = $data['content'] ?? $article->content;
        $article->excerpt = $data['excerpt'] ?? $article->excerpt;
        $article->keywords = $data['keywords'] ?? $article->keywords;
        $article->is_published = $data['is_published'] ?? $article->is_published;
        $article->published_at = !empty($data['published_at']) ? date('Y-m-d H:i:s', strtotime($data['published_at'])) : $article->published_at;
        $article->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($article);

        $this->di['logger']->info('Updated knowledge base article #%s', $article->id);

        return true;
    }

    /**
     * Delete knowledge base article.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_kb_article($data)
    {
        $required = [
            'id' => 'Article ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $article = $this->di['db']->getExistingModelById('SupportKbArticle', $data['id'], 'Article not found');
        $articleId = $article->id;
        $this->di['db']->trash($article);

        $this->di['logger']->info('Deleted knowledge base article #%s', $articleId);

        return true;
    }

    /**
     * Get knowledge base article feedback.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_kb_article_feedback($data)
    {
        $required = [
            'article_id' => 'Article ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $feedback = $this->di['db']->find('SupportKbArticleFeedback', 'article_id = :article_id ORDER BY created_at DESC', [':article_id' => $data['article_id']]);
        
        $result = [];
        foreach ($feedback as $fb) {
            $result[] = $this->di['db']->toArray($fb);
        }
        
        return $result;
    }

    /**
     * Get helpdesk SLA definitions.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_slas($data)
    {
        $helpdeskId = $data['helpdesk_id'] ?? null;
        
        if ($helpdeskId) {
            $slas = $this->di['db']->find('SupportHelpdeskSla', 'helpdesk_id = :helpdesk_id', [':helpdesk_id' => $helpdeskId]);
        } else {
            $slas = $this->di['db']->find('SupportHelpdeskSla');
        }
        
        $result = [];
        foreach ($slas as $sla) {
            $result[] = $this->di['db']->toArray($sla);
        }
        
        return $result;
    }

    /**
     * Create helpdesk SLA definition.
     *
     * @param array $data
     *
     * @return int - SLA ID
     */
    public function create_sla($data)
    {
        $required = [
            'helpdesk_id' => 'Helpdesk ID is required',
            'name' => 'SLA name is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $sla = $this->di['db']->dispense('SupportHelpdeskSla');
        $sla->helpdesk_id = $data['helpdesk_id'];
        $sla->name = $data['name'];
        $sla->description = $data['description'] ?? null;
        $sla->response_time_critical = $data['response_time_critical'] ?? null;
        $sla->response_time_high = $data['response_time_high'] ?? null;
        $sla->response_time_medium = $data['response_time_medium'] ?? null;
        $sla->response_time_low = $data['response_time_low'] ?? null;
        $sla->resolution_time_critical = $data['resolution_time_critical'] ?? null;
        $sla->resolution_time_high = $data['resolution_time_high'] ?? null;
        $sla->resolution_time_medium = $data['resolution_time_medium'] ?? null;
        $sla->resolution_time_low = $data['resolution_time_low'] ?? null;
        $sla->is_default = $data['is_default'] ?? 0;
        $sla->created_at = date('Y-m-d H:i:s');
        $sla->updated_at = date('Y-m-d H:i:s');
        $slaId = $this->di['db']->store($sla);

        $this->di['logger']->info('Created helpdesk SLA #%s', $slaId);

        return $slaId;
    }

    /**
     * Update helpdesk SLA definition.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_sla($data)
    {
        $required = [
            'id' => 'SLA ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $sla = $this->di['db']->getExistingModelById('SupportHelpdeskSla', $data['id'], 'SLA not found');
        $sla->name = $data['name'] ?? $sla->name;
        $sla->description = $data['description'] ?? $sla->description;
        $sla->response_time_critical = $data['response_time_critical'] ?? $sla->response_time_critical;
        $sla->response_time_high = $data['response_time_high'] ?? $sla->response_time_high;
        $sla->response_time_medium = $data['response_time_medium'] ?? $sla->response_time_medium;
        $sla->response_time_low = $data['response_time_low'] ?? $sla->response_time_low;
        $sla->resolution_time_critical = $data['resolution_time_critical'] ?? $sla->resolution_time_critical;
        $sla->resolution_time_high = $data['resolution_time_high'] ?? $sla->resolution_time_high;
        $sla->resolution_time_medium = $data['resolution_time_medium'] ?? $sla->resolution_time_medium;
        $sla->resolution_time_low = $data['resolution_time_low'] ?? $sla->resolution_time_low;
        $sla->is_default = $data['is_default'] ?? $sla->is_default;
        $sla->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($sla);

        $this->di['logger']->info('Updated helpdesk SLA #%s', $sla->id);

        return true;
    }

    /**
     * Delete helpdesk SLA definition.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_sla($data)
    {
        $required = [
            'id' => 'SLA ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $sla = $this->di['db']->getExistingModelById('SupportHelpdeskSla', $data['id'], 'SLA not found');
        $slaId = $sla->id;
        $this->di['db']->trash($sla);

        $this->di['logger']->info('Deleted helpdesk SLA #%s', $slaId);

        return true;
    }

    /**
     * Get ticket workflows.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_workflows($data)
    {
        $helpdeskId = $data['helpdesk_id'] ?? null;
        
        if ($helpdeskId) {
            $workflows = $this->di['db']->find('SupportTicketWorkflow', 'helpdesk_id = :helpdesk_id OR helpdesk_id IS NULL', [':helpdesk_id' => $helpdeskId]);
        } else {
            $workflows = $this->di['db']->find('SupportTicketWorkflow');
        }
        
        $result = [];
        foreach ($workflows as $workflow) {
            $result[] = $this->di['db']->toArray($workflow);
        }
        
        return $result;
    }

    /**
     * Create ticket workflow.
     *
     * @param array $data
     *
     * @return int - workflow ID
     */
    public function create_workflow($data)
    {
        $required = [
            'name' => 'Workflow name is required',
            'trigger_type' => 'Trigger type is required',
            'actions' => 'Actions are required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $workflow = $this->di['db']->dispense('SupportTicketWorkflow');
        $workflow->helpdesk_id = $data['helpdesk_id'] ?? null;
        $workflow->name = $data['name'];
        $workflow->description = $data['description'] ?? null;
        $workflow->trigger_type = $data['trigger_type'];
        $workflow->trigger_conditions = !empty($data['trigger_conditions']) ? json_encode($data['trigger_conditions']) : null;
        $workflow->actions = json_encode($data['actions']);
        $workflow->is_active = $data['is_active'] ?? 1;
        $workflow->sort_order = $data['sort_order'] ?? 0;
        $workflow->created_by = $this->getIdentity()->id;
        $workflow->created_at = date('Y-m-d H:i:s');
        $workflow->updated_at = date('Y-m-d H:i:s');
        $workflowId = $this->di['db']->store($workflow);

        $this->di['logger']->info('Created ticket workflow #%s', $workflowId);

        return $workflowId;
    }

    /**
     * Update ticket workflow.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update_workflow($data)
    {
        $required = [
            'id' => 'Workflow ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $workflow = $this->di['db']->getExistingModelById('SupportTicketWorkflow', $data['id'], 'Workflow not found');
        $workflow->name = $data['name'] ?? $workflow->name;
        $workflow->description = $data['description'] ?? $workflow->description;
        $workflow->trigger_type = $data['trigger_type'] ?? $workflow->trigger_type;
        $workflow->trigger_conditions = !empty($data['trigger_conditions']) ? json_encode($data['trigger_conditions']) : $workflow->trigger_conditions;
        $workflow->actions = !empty($data['actions']) ? json_encode($data['actions']) : $workflow->actions;
        $workflow->is_active = $data['is_active'] ?? $workflow->is_active;
        $workflow->sort_order = $data['sort_order'] ?? $workflow->sort_order;
        $workflow->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($workflow);

        $this->di['logger']->info('Updated ticket workflow #%s', $workflow->id);

        return true;
    }

    /**
     * Delete ticket workflow.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete_workflow($data)
    {
        $required = [
            'id' => 'Workflow ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $workflow = $this->di['db']->getExistingModelById('SupportTicketWorkflow', $data['id'], 'Workflow not found');
        $workflowId = $workflow->id;
        $this->di['db']->trash($workflow);

        $this->di['logger']->info('Deleted ticket workflow #%s', $workflowId);

        return true;
    }
}