<?php
/**
 * GILAF SUPPORT TICKET SYSTEM - CORE FUNCTIONS
 * Business logic for ticket management
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/support_email_config.php';

 /**
  * Generate ticket number in format: GEddmmyyNNN (daily sequence)
  * Must be called inside an active DB transaction.
  */
 function generate_ge_ticket_id(PDO $db): string
 {
     $datePart = date('dmy'); // ddmmyy
     $prefix = 'GE' . $datePart;
     $lockName = 'gilaf_ticket_seq_' . $datePart;
 
     // Named lock ensures the first ticket of the day is also safe under concurrency.
     // (Row locks alone won't help when there are 0 rows for today.)
     $lockStmt = $db->prepare('SELECT GET_LOCK(?, 10) AS got_lock');
     $lockStmt->execute([$lockName]);
     $lockRow = $lockStmt->fetch(PDO::FETCH_ASSOC);
     if (empty($lockRow) || (int)$lockRow['got_lock'] !== 1) {
         throw new Exception('Could not acquire ticket sequence lock');
     }
 
     try {
         $stmt = $db->prepare(
             "SELECT ticket_id FROM support_tickets WHERE ticket_id LIKE ? ORDER BY ticket_id DESC LIMIT 1"
         );
         $stmt->execute([$prefix . '%']);
         $last = $stmt->fetch(PDO::FETCH_ASSOC);
 
         $nextSeq = 1;
         if (!empty($last['ticket_id'])) {
             $lastId = $last['ticket_id'];
             $suffix = substr($lastId, strlen($prefix));
             if ($suffix !== '' && ctype_digit($suffix)) {
                 $nextSeq = (int)$suffix + 1;
             }
         }
 
         // Example requested: GE01012601 (2-digit sequence). Supports >99 as 100, 101...
         $seqPart = sprintf('%02d', $nextSeq);
         return $prefix . $seqPart;
     } finally {
         $unlockStmt = $db->prepare('SELECT RELEASE_LOCK(?)');
         $unlockStmt->execute([$lockName]);
     }
 
 }

/**
 * Create a new support ticket
 */
function create_support_ticket($data) {
    try {
        $db = get_db_connection();
 
        $db->beginTransaction();
        // Generate unique ticket ID (transaction-safe)
        $ticketId = generate_ge_ticket_id($db);
        
        // Prepare ticket data
        $userId = $data['user_id'] ?? null;
        $userName = $data['user_name'];
        $userEmail = $data['user_email'];
        $subject = $data['subject'];
        $issueType = $data['issue_type'];
        $priority = $data['priority'] ?? 'medium';
        $description = $data['description'];
        
        // Insert ticket
        $stmt = $db->prepare("
            INSERT INTO support_tickets 
            (ticket_id, user_id, user_name, user_email, subject, issue_type, priority, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')
        ");
        
        $stmt->execute([
            $ticketId,
            $userId,
            $userName,
            $userEmail,
            $subject,
            $issueType,
            $priority,
            $description
        ]);

        $db->commit();
        
        // Log status history
        log_ticket_status_change($ticketId, null, 'new', 'System', 'Ticket created');
        
        // Prepare ticket data for emails
        $ticketData = [
            'ticket_id' => $ticketId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'subject' => $subject,
            'issue_type' => $issueType,
            'priority' => $priority,
            'description' => $description
        ];
        
        // Send email to user
        send_ticket_created_email($ticketData);
        
        // Send email to admin (NEW TICKET ONLY)
        send_admin_new_ticket_notification($ticketData);
        
        return [
            'success' => true,
            'ticket_id' => $ticketId,
            'message' => 'Ticket created successfully'
        ];
        
    } catch (Exception $e) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Failed to create ticket: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to create ticket. Please try again.'
        ];
    }
}

/**
 * Get ticket by ID
 */
function get_ticket_by_id($ticketId, $userId = null, $userEmail = null) {
    try {
        $db = get_db_connection();
        
        $query = "SELECT * FROM support_tickets WHERE ticket_id = ?";
        $params = [$ticketId];
        
        // If user ID provided, ensure user can only see their own tickets
        if ($userId !== null) {
            if (!empty($userEmail)) {
                $query .= " AND (user_id = ? OR (user_id IS NULL AND user_email = ?))";
                $params[] = $userId;
                $params[] = $userEmail;
            } else {
                $query .= " AND user_id = ?";
                $params[] = $userId;
            }
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to get ticket: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all tickets for a user
 */
function get_user_tickets($userId, $filters = [], $userEmail = null) {
    try {
        $db = get_db_connection();
        
        if (!empty($userEmail)) {
            $query = "SELECT * FROM support_tickets WHERE (user_id = ? OR (user_id IS NULL AND user_email = ?))";
            $params = [$userId, $userEmail];
        } else {
            $query = "SELECT * FROM support_tickets WHERE user_id = ?";
            $params = [$userId];
        }
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['issue_type'])) {
            $query .= " AND issue_type = ?";
            $params[] = $filters['issue_type'];
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to get user tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all tickets (Admin)
 */
function get_all_tickets($filters = [], $limit = 50, $offset = 0) {
    try {
        $db = get_db_connection();
        
        $query = "SELECT * FROM support_tickets WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['issue_type'])) {
            $query .= " AND issue_type = ?";
            $params[] = $filters['issue_type'];
        }
        
        if (!empty($filters['priority'])) {
            $query .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['assigned_to'])) {
            $query .= " AND assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (ticket_id LIKE ? OR subject LIKE ? OR user_name LIKE ? OR user_email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $query .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to get tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Update ticket status
 */
function update_ticket_status($ticketId, $newStatus, $changedBy, $changedByName, $notes = null) {
    try {
        $db = get_db_connection();
        
        // Get current ticket
        $ticket = get_ticket_by_id($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }
        
        $oldStatus = $ticket['status'];
        
        // Update ticket status
        $updateFields = ['status = ?'];
        $params = [$newStatus];
        
        if ($newStatus === 'resolved') {
            $updateFields[] = 'resolved_at = NOW()';
        } elseif ($newStatus === 'closed') {
            $updateFields[] = 'closed_at = NOW()';
        }
        
        $params[] = $ticketId;
        
        $stmt = $db->prepare("
            UPDATE support_tickets 
            SET " . implode(', ', $updateFields) . "
            WHERE ticket_id = ?
        ");
        $stmt->execute($params);
        
        // Log status change
        log_ticket_status_change($ticketId, $oldStatus, $newStatus, $changedByName, $notes);
        
        // Send email notification to user
        $ticketData = [
            'ticket_id' => $ticket['ticket_id'],
            'user_name' => $ticket['user_name'],
            'user_email' => $ticket['user_email'],
            'subject' => $ticket['subject']
        ];
        
        if ($newStatus === 'resolved') {
            send_ticket_resolved_email($ticketData);
        } else {
            send_ticket_status_changed_email($ticketData, $oldStatus, $newStatus);
        }
        
        return ['success' => true, 'message' => 'Status updated successfully'];
        
    } catch (Exception $e) {
        error_log("Failed to update ticket status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update status'];
    }
}

/**
 * Add comment to ticket
 */
function add_ticket_comment($ticketId, $userId, $commenterName, $commenterEmail, $comment, $isInternal = false, $isAdmin = false) {
    try {
        $db = get_db_connection();
        
        // Insert comment
        $stmt = $db->prepare("
            INSERT INTO ticket_comments 
            (ticket_id, user_id, commenter_name, commenter_email, comment, is_internal, is_admin)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticketId,
            $userId,
            $commenterName,
            $commenterEmail,
            $comment,
            $isInternal ? 1 : 0,
            $isAdmin ? 1 : 0
        ]);
        
        // Update ticket updated_at
        $db->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE ticket_id = ?")->execute([$ticketId]);
        
        // Send email notification if not internal
        if (!$isInternal) {
            $ticket = get_ticket_by_id($ticketId);
            if ($ticket) {
                $ticketData = [
                    'ticket_id' => $ticket['ticket_id'],
                    'user_name' => $ticket['user_name'],
                    'user_email' => $ticket['user_email'],
                    'subject' => $ticket['subject']
                ];
                
                send_ticket_comment_email($ticketData, $comment, $commenterName);
            }
        }
        
        return ['success' => true, 'message' => 'Comment added successfully'];
        
    } catch (Exception $e) {
        error_log("Failed to add comment: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add comment'];
    }
}

/**
 * Get ticket comments
 */
function get_ticket_comments($ticketId, $includeInternal = false) {
    try {
        $db = get_db_connection();
        
        $query = "SELECT * FROM ticket_comments WHERE ticket_id = ?";
        $params = [$ticketId];
        
        if (!$includeInternal) {
            $query .= " AND is_internal = 0";
        }
        
        $query .= " ORDER BY created_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to get comments: " . $e->getMessage());
        return [];
    }
}

/**
 * Log ticket status change
 */
function log_ticket_status_change($ticketId, $oldStatus, $newStatus, $changedByName, $notes = null) {
    try {
        $db = get_db_connection();
        
        $stmt = $db->prepare("
            INSERT INTO ticket_status_history 
            (ticket_id, old_status, new_status, changed_by_name, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$ticketId, $oldStatus, $newStatus, $changedByName, $notes]);
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to log status change: " . $e->getMessage());
        return false;
    }
}

/**
 * Get ticket status history
 */
function get_ticket_status_history($ticketId) {
    try {
        $db = get_db_connection();
        
        $stmt = $db->prepare("
            SELECT * FROM ticket_status_history 
            WHERE ticket_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$ticketId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to get status history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get ticket statistics
 */
function get_ticket_statistics() {
    try {
        $db = get_db_connection();
        
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_tickets,
                SUM(CASE WHEN status IN ('open', 'acknowledged', 'in_progress') THEN 1 ELSE 0 END) as active_tickets,
                SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_tickets,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
                SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as urgent_open,
                SUM(CASE WHEN priority = 'high' AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as high_open
            FROM support_tickets
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to get statistics: " . $e->getMessage());
        return null;
    }
}

/**
 * Assign ticket to agent
 */
function assign_ticket($ticketId, $agentId, $assignedByName) {
    try {
        $db = get_db_connection();
        
        $stmt = $db->prepare("
            UPDATE support_tickets 
            SET assigned_to = ?, updated_at = NOW()
            WHERE ticket_id = ?
        ");
        $stmt->execute([$agentId, $ticketId]);
        
        // Log assignment
        log_ticket_status_change($ticketId, null, null, $assignedByName, "Ticket assigned to agent ID: $agentId");
        
        return ['success' => true, 'message' => 'Ticket assigned successfully'];
        
    } catch (Exception $e) {
        error_log("Failed to assign ticket: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to assign ticket'];
    }
}

/**
 * Get ticket count by status
 */
function get_ticket_count_by_status($status) {
    try {
        $db = get_db_connection();
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM support_tickets WHERE status = ?");
        $stmt->execute([$status]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Failed to get ticket count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Search tickets
 */
function search_tickets($searchTerm, $userId = null) {
    try {
        $db = get_db_connection();
        
        $query = "
            SELECT * FROM support_tickets 
            WHERE (ticket_id LIKE ? OR subject LIKE ? OR description LIKE ?)
        ";
        $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
        
        if ($userId !== null) {
            $query .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Failed to search tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $badges = [
        'new' => '<span class="badge badge-primary">New</span>',
        'open' => '<span class="badge badge-info">Open</span>',
        'acknowledged' => '<span class="badge badge-secondary">Acknowledged</span>',
        'in_progress' => '<span class="badge badge-warning">In Progress</span>',
        'on_hold' => '<span class="badge badge-dark">On Hold</span>',
        'resolved' => '<span class="badge badge-success">Resolved</span>',
        'closed' => '<span class="badge badge-secondary">Closed</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Get priority badge HTML
 */
function get_priority_badge($priority) {
    $badges = [
        'low' => '<span class="badge badge-light">Low</span>',
        'medium' => '<span class="badge badge-info">Medium</span>',
        'high' => '<span class="badge badge-warning">High</span>',
        'urgent' => '<span class="badge badge-danger">Urgent</span>'
    ];
    
    return $badges[$priority] ?? '<span class="badge badge-secondary">' . ucfirst($priority) . '</span>';
}
?>
