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
 * Support ticket management for clients.
 */

namespace Box\Mod\Support\Api;

class Client extends \Api_Abstract
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
        $client = $this->getIdentity();
        $data['client_id'] = $client->id;
        
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
            'support_helpdesk_id' => 'Helpdesk ID is required',
            'subject' => 'Subject is required',
            'content' => 'Message content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();
        $data['client_id'] = $client->id;

        return $this->getService()->createTicket($data);
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
        $data['client_id'] = $this->getIdentity()->id;

        return $this->getService()->addMessage($model, $data);
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
     * Get knowledge base categories.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_kb_categories($data)
    {
        return $this->di['api_admin']->support_get_kb_categories($data);
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
        return $this->di['api_admin']->support_get_kb_articles($data);
    }

    /**
     * Get knowledge base article.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_kb_article($data)
    {
        return $this->di['api_admin']->support_get_kb_article($data);
    }

    /**
     * Submit knowledge base article feedback.
     *
     * @param array $data
     *
     * @return bool
     */
    public function submit_kb_article_feedback($data)
    {
        $required = [
            'article_id' => 'Article ID is required',
            'is_helpful' => 'Feedback is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $article = $this->di['db']->getExistingModelById('SupportKbArticle', $data['article_id'], 'Article not found');
        
        $feedback = $this->di['db']->dispense('SupportKbArticleFeedback');
        $feedback->article_id = $article->id;
        $feedback->client_id = $this->getIdentity()->id;
        $feedback->is_helpful = $data['is_helpful'];
        $feedback->comments = $data['comments'] ?? null;
        $feedback->ip_address = $this->di['request']->getClientAddress();
        $feedback->created_at = date('Y-m-d H:i:s');
        $this->di['db']->store($feedback);

        return true;
    }
}