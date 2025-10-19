<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

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
     * Get ticket by ID.
     *
     * @param int $id - ticket ID
     *
     * @return \Model_SupportTicket
     */
    public function getTicketById($id)
    {
        return $this->di['db']->getExistingModelById('SupportTicket', $id, 'Ticket not found');
    }

    /**
     * Get paginated list of tickets.
     *
     * @param array $data
     *
     * @return array
     */
    public function getSearchQuery($data)
    {
        $sql = '
            SELECT st.*
            FROM support_ticket st
            LEFT JOIN client c ON c.id = st.client_id
            WHERE 1 ';

        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        $priority = $data['priority'] ?? null;
        $helpdesk = $data['helpdesk_id'] ?? null;

        $params = [];
        if ($search) {
            $sql .= ' AND (c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR st.subject LIKE :subject)';
            $params['first_name'] = "%$search%";
            $params['last_name'] = "%$search%";
            $params['subject'] = "%$search%";
        }

        if ($id) {
            $sql .= ' AND st.id = :id ';
            $params['id'] = $id;
        }

        if ($status) {
            $sql .= ' AND st.status = :status ';
            $params['status'] = $status;
        }

        if ($priority) {
            $sql .= ' AND st.priority = :priority ';
            $params['priority'] = $priority;
        }

        if ($helpdesk) {
            $sql .= ' AND st.support_helpdesk_id = :helpdesk ';
            $params['helpdesk'] = $helpdesk;
        }

        $sql .= ' ORDER BY st.updated_at DESC ';

        return [$sql, $params];
    }

    /**
     * Create new ticket.
     *
     * @param array $data
     *
     * @return int - new ticket ID
     */
    public function createTicket($data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_SupportTicket', 2);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['support_helpdesk_id'], 'Helpdesk not found');

        $model = $this->di['db']->dispense('SupportTicket');
        $model->client_id = $client->id;
        $model->support_helpdesk_id = $helpdesk->id;
        $model->subject = $data['subject'];
        $model->priority = $data['priority'] ?? 100;
        $model->status = $data['status'] ?? 'open';
        $model->rel_type = $data['rel_type'] ?? null;
        $model->rel_id = $data['rel_id'] ?? null;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $ticketId = $this->di['db']->store($model);

        // Create initial message
        if (!empty($data['content'])) {
            $message = $this->di['db']->dispense('SupportTicketMessage');
            $message->support_ticket_id = $ticketId;
            $message->client_id = $client->id;
            $message->content = $data['content'];
            $message->ip = $this->di['request']->getClientAddress();
            $message->created_at = date('Y-m-d H:i:s');
            $message->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($message);
        }

        $this->di['logger']->info('Created new support ticket #%s', $ticketId);

        return $ticketId;
    }

    /**
     * Update ticket.
     *
     * @param \Model_SupportTicket $model
     * @param array                $data
     *
     * @return bool
     */
    public function updateTicket($model, $data)
    {
        $model->subject = $data['subject'] ?? $model->subject;
        $model->priority = $data['priority'] ?? $model->priority;
        $model->status = $data['status'] ?? $model->status;
        $model->support_helpdesk_id = $data['support_helpdesk_id'] ?? $model->support_helpdesk_id;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated support ticket #%s', $model->id);

        return true;
    }

    /**
     * Close ticket.
     *
     * @param \Model_SupportTicket $model
     *
     * @return bool
     */
    public function closeTicket($model)
    {
        $model->status = 'closed';
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Closed support ticket #%s', $model->id);

        return true;
    }

    /**
     * Reopen ticket.
     *
     * @param \Model_SupportTicket $model
     *
     * @return bool
     */
    public function reopenTicket($model)
    {
        $model->status = 'open';
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Reopened support ticket #%s', $model->id);

        return true;
    }

    /**
     * Add message to ticket.
     *
     * @param \Model_SupportTicket $model
     * @param array                $data
     *
     * @return int - message ID
     */
    public function addMessage($model, $data)
    {
        $client = $data['client_id'] ? $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found') : null;

        $message = $this->di['db']->dispense('SupportTicketMessage');
        $message->support_ticket_id = $model->id;
        $message->client_id = $client ? $client->id : null;
        $message->admin_id = $data['admin_id'] ?? null;
        $message->content = $data['content'];
        $message->ip = $this->di['request']->getClientAddress();
        $message->created_at = date('Y-m-d H:i:s');
        $message->updated_at = date('Y-m-d H:i:s');
        $messageId = $this->di['db']->store($message);

        // Update ticket timestamp
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Added message to support ticket #%s', $model->id);

        return $messageId;
    }

    /**
     * Get ticket messages.
     *
     * @param \Model_SupportTicket $model
     *
     * @return array
     */
    public function getTicketMessages($model)
    {
        return $this->di['db']->find('SupportTicketMessage', 'support_ticket_id = :ticket_id ORDER BY created_at ASC', [':ticket_id' => $model->id]);
    }

    /**
     * Assign ticket to staff member.
     *
     * @param \Model_SupportTicket $model
     * @param int                  $staffId
     *
     * @return bool
     */
    public function assignTicket($model, $staffId)
    {
        $model->assigned_to = $staffId;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Assigned support ticket #%s to staff member #%s', $model->id, $staffId);

        return true;
    }

    /**
     * Escalate ticket priority.
     *
     * @param \Model_SupportTicket $model
     * @param int                  $levels
     *
     * @return bool
     */
    public function escalateTicket($model, $levels = 1)
    {
        // Reduce priority number (lower number = higher priority)
        $model->priority = max(1, $model->priority - (10 * $levels));
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Escalated support ticket #%s by %s levels', $model->id, $levels);

        return true;
    }

    /**
     * Get ticket statistics.
     *
     * @return array
     */
    public function getTicketStats()
    {
        $stats = [];

        // Get counts by status
        $statusCounts = $this->di['db']->getAll("
            SELECT status, COUNT(*) as count
            FROM support_ticket
            GROUP BY status
        ");
        foreach ($statusCounts as $row) {
            $stats['by_status'][$row['status']] = $row['count'];
        }

        // Get counts by priority
        $priorityCounts = $this->di['db']->getAll("
            SELECT 
                CASE 
                    WHEN priority <= 20 THEN 'critical'
                    WHEN priority <= 50 THEN 'high'
                    WHEN priority <= 80 THEN 'medium'
                    WHEN priority <= 100 THEN 'low'
                    ELSE 'very_low'
                END as priority_level,
                COUNT(*) as count
            FROM support_ticket
            GROUP BY priority_level
        ");
        foreach ($priorityCounts as $row) {
            $stats['by_priority'][$row['priority_level']] = $row['count'];
        }

        // Get counts by helpdesk
        $helpdeskCounts = $this->di['db']->getAll("
            SELECT sh.name, COUNT(st.id) as count
            FROM support_helpdesk sh
            LEFT JOIN support_ticket st ON sh.id = st.support_helpdesk_id
            GROUP BY sh.id
        ");
        foreach ($helpdeskCounts as $row) {
            $stats['by_helpdesk'][$row['name']] = $row['count'];
        }

        return $stats;
    }

    /**
     * Create advanced ticket with SLA and workflow rules.
     *
     * @param array $data
     *
     * @return int - new ticket ID
     */
    public function createAdvancedTicket($data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_SupportTicket', 2);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['support_helpdesk_id'], 'Helpdesk not found');

        $model = $this->di['db']->dispense('SupportTicket');
        $model->client_id = $client->id;
        $model->support_helpdesk_id = $helpdesk->id;
        $model->subject = $data['subject'];
        $model->priority = $data['priority'] ?? 100;
        $model->status = $data['status'] ?? 'open';
        $model->rel_type = $data['rel_type'] ?? null;
        $model->rel_id = $data['rel_id'] ?? null;
        
        // Advanced ticket fields
        $model->sla_level = $data['sla_level'] ?? 'standard';
        $model->workflow_state = $data['workflow_state'] ?? 'new';
        $model->tags = !empty($data['tags']) ? json_encode($data['tags']) : null;
        $model->custom_fields = !empty($data['custom_fields']) ? json_encode($data['custom_fields']) : null;
        $model->assigned_to = $data['assigned_to'] ?? null;
        $model->department = $data['department'] ?? null;
        $model->channel = $data['channel'] ?? 'web';
        $model->source = $data['source'] ?? 'customer';
        $model->escalation_level = $data['escalation_level'] ?? 0;
        
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $model->due_at = $this->calculateSLADueDate($model->sla_level, $model->priority);
        $ticketId = $this->di['db']->store($model);

        // Create initial message
        if (!empty($data['content'])) {
            $message = $this->di['db']->dispense('SupportTicketMessage');
            $message->support_ticket_id = $ticketId;
            $message->client_id = $client->id;
            $message->content = $data['content'];
            $message->ip = $this->di['request']->getClientAddress();
            $message->created_at = date('Y-m-d H:i:s');
            $message->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($message);
        }

        // Auto-assign based on workflow rules
        $this->applyWorkflowRules($model);

        $this->di['logger']->info('Created new advanced support ticket #%s', $ticketId);

        return $ticketId;
    }

    /**
     * Calculate SLA due date.
     *
     * @param string $slaLevel
     * @param int    $priority
     *
     * @return string
     */
    private function calculateSLADueDate($slaLevel, $priority)
    {
        $hours = 0;
        
        switch ($slaLevel) {
            case 'critical':
                $hours = 1;
                break;
            case 'premium':
                $hours = 4;
                break;
            case 'standard':
                $hours = 24;
                break;
            case 'basic':
                $hours = 72;
                break;
            default:
                $hours = 24;
        }

        // Adjust based on priority
        if ($priority <= 20) { // Critical priority
            $hours = max(1, $hours / 4);
        } elseif ($priority <= 50) { // High priority
            $hours = max(1, $hours / 2);
        }

        return date('Y-m-d H:i:s', strtotime("+$hours hours"));
    }

    /**
     * Apply workflow rules to ticket.
     *
     * @param \Model_SupportTicket $model
     *
     * @return void
     */
    private function applyWorkflowRules($model)
    {
        // This would typically check workflow rules and auto-assign tickets
        // For now, we'll implement a simple example
        
        // Auto-assign critical tickets
        if ($model->priority <= 20 && !$model->assigned_to) {
            // Find available staff member with lowest ticket count
            $availableStaff = $this->findAvailableStaff();
            if ($availableStaff) {
                $model->assigned_to = $availableStaff->id;
                $this->di['db']->store($model);
                
                // Notify assigned staff
                $this->notifyStaffAssignment($model, $availableStaff);
            }
        }
    }

    /**
     * Find available staff member.
     *
     * @return \Model_Admin|null
     */
    private function findAvailableStaff()
    {
        // Simplified implementation - find admin with fewest tickets
        $staff = $this->di['db']->getAll("
            SELECT a.id, COUNT(st.id) as ticket_count
            FROM admin a
            LEFT JOIN support_ticket st ON a.id = st.assigned_to AND st.status = 'open'
            WHERE a.role = 'admin'
            GROUP BY a.id
            ORDER BY ticket_count ASC
            LIMIT 1
        ");

        if (!empty($staff)) {
            return $this->di['db']->getExistingModelById('Admin', $staff[0]['id']);
        }

        return null;
    }

    /**
     * Notify staff of assignment.
     *
     * @param \Model_SupportTicket $ticket
     * @param \Model_Admin         $staff
     *
     * @return void
     */
    private function notifyStaffAssignment($ticket, $staff)
    {
        // Send notification to assigned staff
        $emailService = $this->di['mod_service']('Email');
        $emailService->sendTemplate([
            'to_staff' => $staff->id,
            'code' => 'mod_support_ticket_assigned',
            'ticket' => $this->di['db']->toArray($ticket),
            'staff_name' => $staff->name,
        ]);
    }

    /**
     * Add internal note to ticket.
     *
     * @param \Model_SupportTicket $model
     * @param array                $data
     *
     * @return int - note ID
     */
    public function addInternalNote($model, $data)
    {
        $admin = $this->di['db']->getExistingModelById('Admin', $data['admin_id'], 'Admin not found');

        $note = $this->di['db']->dispense('SupportTicketNote');
        $note->support_ticket_id = $model->id;
        $note->admin_id = $admin->id;
        $note->note = $data['note'];
        $note->created_at = date('Y-m-d H:i:s');
        $note->updated_at = date('Y-m-d H:i:s');
        $noteId = $this->di['db']->store($note);

        $this->di['logger']->info('Added internal note to support ticket #%s', $model->id);

        return $noteId;
    }

    /**
     * Get internal notes for ticket.
     *
     * @param \Model_SupportTicket $model
     *
     * @return array
     */
    public function getInternalNotes($model)
    {
        return $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :ticket_id ORDER BY created_at ASC', [':ticket_id' => $model->id]);
    }

    /**
     * Update ticket SLA.
     *
     * @param \Model_SupportTicket $model
     * @param string               $slaLevel
     *
     * @return bool
     */
    public function updateTicketSLA($model, $slaLevel)
    {
        $model->sla_level = $slaLevel;
        $model->due_at = $this->calculateSLADueDate($slaLevel, $model->priority);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated SLA for support ticket #%s to %s', $model->id, $slaLevel);

        return true;
    }

    /**
     * Escalate ticket through workflow.
     *
     * @param \Model_SupportTicket $model
     * @param array                $data
     *
     * @return bool
     */
    public function escalateTicketWorkflow($model, $data)
    {
        $currentLevel = $model->escalation_level;
        $newLevel = $currentLevel + 1;
        
        // Update escalation level
        $model->escalation_level = $newLevel;
        
        // Update workflow state
        $model->workflow_state = $data['new_state'] ?? 'escalated';
        
        // Update priority
        if (isset($data['new_priority'])) {
            $model->priority = $data['new_priority'];
        } else {
            // Auto-adjust priority based on escalation level
            $model->priority = max(1, $model->priority - 20);
        }
        
        // Update SLA
        $model->sla_level = $data['new_sla'] ?? 'premium';
        $model->due_at = $this->calculateSLADueDate($model->sla_level, $model->priority);
        
        // Re-assign if needed
        if (isset($data['new_assignee'])) {
            $model->assigned_to = $data['new_assignee'];
        }
        
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Escalated support ticket #%s to level %s', $model->id, $newLevel);

        return true;
    }

    /**
     * Get ticket collaboration participants.
     *
     * @param \Model_SupportTicket $model
     *
     * @return array
     */
    public function getCollaborators($model)
    {
        // This would typically get collaborators from a collaboration table
        // For now, we'll return assigned staff and any admins who have commented
        $collaborators = [];
        
        // Add assigned staff
        if ($model->assigned_to) {
            $staff = $this->di['db']->getExistingModelById('Admin', $model->assigned_to, 'Admin not found');
            $collaborators[] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'type' => 'staff',
            ];
        }
        
        // Add admins who have commented
        $commentingAdmins = $this->di['db']->getAll("
            SELECT DISTINCT a.id, a.name, a.email
            FROM admin a
            JOIN support_ticket_message stm ON a.id = stm.admin_id
            WHERE stm.support_ticket_id = :ticket_id AND stm.admin_id IS NOT NULL
        ", [':ticket_id' => $model->id]);
        
        foreach ($commentingAdmins as $admin) {
            $collaborators[] = [
                'id' => $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'type' => 'staff',
            ];
        }
        
        return $collaborators;
    }

    /**
     * Add collaborator to ticket.
     *
     * @param \Model_SupportTicket $model
     * @param int                  $adminId
     *
     * @return bool
     */
    public function addCollaborator($model, $adminId)
    {
        // This would typically add to a collaboration table
        // For now, we'll just log the action
        $this->di['logger']->info('Added collaborator #%s to support ticket #%s', $adminId, $model->id);

        return true;
    }

    /**
     * Get ticket satisfaction survey.
     *
     * @param \Model_SupportTicket $model
     *
     * @return array|null
     */
    public function getTicketSurvey($model)
    {
        // Check if survey exists for this ticket
        $survey = $this->di['db']->findOne('SupportTicketSurvey', 'ticket_id = :ticket_id', [':ticket_id' => $model->id]);
        
        if ($survey) {
            return $this->di['db']->toArray($survey);
        }
        
        return null;
    }

    /**
     * Submit ticket satisfaction survey.
     *
     * @param \Model_SupportTicket $model
     * @param array                $data
     *
     * @return int - survey ID
     */
    public function submitTicketSurvey($model, $data)
    {
        $survey = $this->di['db']->dispense('SupportTicketSurvey');
        $survey->ticket_id = $model->id;
        $survey->rating = $data['rating'];
        $survey->comments = $data['comments'] ?? null;
        $survey->created_at = date('Y-m-d H:i:s');
        $surveyId = $this->di['db']->store($survey);

        $this->di['logger']->info('Submitted satisfaction survey for support ticket #%s', $model->id);

        return $surveyId;
    }

    /**
     * Get ticket timeline events.
     *
     * @param \Model_SupportTicket $model
     *
     * @return array
     */
    public function getTicketTimeline($model)
    {
        $events = [];
        
        // Add ticket creation
        $events[] = [
            'type' => 'created',
            'timestamp' => $model->created_at,
            'description' => 'Ticket created',
        ];
        
        // Add status changes
        $statusChanges = $this->di['db']->getAll("
            SELECT created_at, old_status, new_status
            FROM support_ticket_status_log
            WHERE ticket_id = :ticket_id
            ORDER BY created_at ASC
        ", [':ticket_id' => $model->id]);
        
        foreach ($statusChanges as $change) {
            $events[] = [
                'type' => 'status_change',
                'timestamp' => $change['created_at'],
                'description' => "Status changed from {$change['old_status']} to {$change['new_status']}",
            ];
        }
        
        // Add messages
        $messages = $this->di['db']->getAll("
            SELECT created_at, client_id, admin_id
            FROM support_ticket_message
            WHERE support_ticket_id = :ticket_id
            ORDER BY created_at ASC
        ", [':ticket_id' => $model->id]);
        
        foreach ($messages as $message) {
            $author = $message['client_id'] ? 'Client' : 'Staff';
            $events[] = [
                'type' => 'message',
                'timestamp' => $message['created_at'],
                'description' => "Message from $author",
            ];
        }
        
        // Add internal notes
        $notes = $this->di['db']->getAll("
            SELECT created_at, admin_id
            FROM support_ticket_note
            WHERE support_ticket_id = :ticket_id
            ORDER BY created_at ASC
        ", [':ticket_id' => $model->id]);
        
        foreach ($notes as $note) {
            $events[] = [
                'type' => 'internal_note',
                'timestamp' => $note['created_at'],
                'description' => 'Internal note added',
            ];
        }
        
        // Sort events by timestamp
        usort($events, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });
        
        return $events;
    }
}