<?php
/**
 * Internal Messaging System - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

class MarketingMessaging {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Send internal message to customer
     */
    public function sendMessage($customer_id, $title, $content, $type = 'general', $sender_id = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO customer_messages (customer_id, title, content, message_type, sender_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'sent', NOW())
            ");
            
            $success = $stmt->execute([$customer_id, $title, $content, $type, $sender_id]);
            
            if ($success) {
                $message_id = $this->db->lastInsertId();
                
                // Log the message sending
                $this->logMessageEvent($message_id, 'sent', 'Message sent successfully');
                
                return ['success' => true, 'message_id' => $message_id];
            }
            
            return ['success' => false, 'error' => 'Failed to send message'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send campaign to multiple customers
     */
    public function sendCampaignMessages($campaign_id, $customers, $title, $content, $sender_id = null) {
        $sent_count = 0;
        $failed_count = 0;
        
        try {
            $this->db->beginTransaction();
            
            $message_stmt = $this->db->prepare("
                INSERT INTO customer_messages (customer_id, title, content, message_type, campaign_id, sender_id, status, created_at)
                VALUES (?, ?, ?, 'campaign', ?, ?, 'sent', NOW())
            ");
            
            $audience_stmt = $this->db->prepare("
                INSERT INTO campaign_audience (campaign_id, customer_id, status, sent_at)
                VALUES (?, ?, 'sent', NOW())
                ON DUPLICATE KEY UPDATE status = 'sent', sent_at = NOW()
            ");
            
            $log_stmt = $this->db->prepare("
                INSERT INTO campaign_logs (campaign_id, customer_id, event_type, event_data, created_at)
                VALUES (?, ?, 'sent', ?, NOW())
            ");
            
            foreach ($customers as $customer) {
                try {
                    // Send message
                    $message_success = $message_stmt->execute([
                        $customer['id'], $title, $content, $campaign_id, $sender_id
                    ]);
                    
                    if ($message_success) {
                        // Update campaign audience
                        $audience_stmt->execute([$campaign_id, $customer['id']]);
                        
                        // Log event
                        $log_stmt->execute([
                            $campaign_id, 
                            $customer['id'], 
                            json_encode(['message_id' => $this->db->lastInsertId()])
                        ]);
                        
                        $sent_count++;
                    } else {
                        $failed_count++;
                        
                        // Log failure
                        $log_stmt->execute([
                            $campaign_id, 
                            $customer['id'], 
                            json_encode(['error' => 'Failed to send message'])
                        ]);
                    }
                    
                } catch (Exception $e) {
                    $failed_count++;
                    
                    // Log error
                    $log_stmt->execute([
                        $campaign_id, 
                        $customer['id'], 
                        json_encode(['error' => $e->getMessage()])
                    ]);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'sent_count' => $sent_count,
                'failed_count' => $failed_count,
                'total_count' => count($customers)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Mark message as read
     */
    public function markAsRead($message_id, $customer_id = null) {
        try {
            $query = "UPDATE customer_messages SET status = 'read', read_at = NOW() WHERE id = ?";
            $params = [$message_id];
            
            if ($customer_id) {
                $query .= " AND customer_id = ?";
                $params[] = $customer_id;
            }
            
            $stmt = $this->db->prepare($query);
            $success = $stmt->execute($params);
            
            if ($success && $stmt->rowCount() > 0) {
                // Log read event if this is a campaign message
                $msg_stmt = $this->db->prepare("SELECT campaign_id, customer_id FROM customer_messages WHERE id = ?");
                $msg_stmt->execute([$message_id]);
                $message = $msg_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($message && $message['campaign_id']) {
                    $this->logCampaignEvent($message['campaign_id'], $message['customer_id'], 'read', [
                        'message_id' => $message_id,
                        'read_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Message not found or already read'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get customer messages
     */
    public function getCustomerMessages($customer_id, $limit = 20, $offset = 0, $unread_only = false) {
        try {
            $where_clause = "WHERE customer_id = ?";
            $params = [$customer_id];
            
            if ($unread_only) {
                $where_clause .= " AND status = 'sent'";
            }
            
            $stmt = $this->db->prepare("
                SELECT m.*, c.name as campaign_name, u.name as sender_name
                FROM customer_messages m
                LEFT JOIN campaigns c ON m.campaign_id = c.id
                LEFT JOIN users u ON m.sender_id = u.id
                {$where_clause}
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get message statistics
     */
    public function getMessageStats($campaign_id = null, $days = 30) {
        try {
            $stats = [];
            
            if ($campaign_id) {
                // Campaign-specific stats
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total_sent,
                        COUNT(CASE WHEN status = 'read' THEN 1 END) as total_read,
                        COUNT(CASE WHEN status = 'sent' THEN 1 END) as total_unread
                    FROM customer_messages 
                    WHERE campaign_id = ?
                ");
                $stmt->execute([$campaign_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Click tracking from logs
                $click_stmt = $this->db->prepare("
                    SELECT COUNT(*) as total_clicks
                    FROM campaign_logs 
                    WHERE campaign_id = ? AND event_type = 'clicked'
                ");
                $click_stmt->execute([$campaign_id]);
                $stats['total_clicks'] = $click_stmt->fetchColumn();
                
            } else {
                // Overall stats
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total_messages,
                        COUNT(CASE WHEN status = 'read' THEN 1 END) as total_read,
                        COUNT(CASE WHEN status = 'sent' THEN 1 END) as total_unread,
                        COUNT(CASE WHEN message_type = 'campaign' THEN 1 END) as campaign_messages,
                        COUNT(CASE WHEN message_type = 'general' THEN 1 END) as general_messages
                    FROM customer_messages 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $stmt->execute([$days]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Calculate read rate
            if ($stats['total_sent'] ?? $stats['total_messages'] ?? 0 > 0) {
                $total = $stats['total_sent'] ?? $stats['total_messages'];
                $read = $stats['total_read'] ?? 0;
                $stats['read_rate'] = round(($read / $total) * 100, 2);
            } else {
                $stats['read_rate'] = 0;
            }
            
            return $stats;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Log message event
     */
    private function logMessageEvent($message_id, $event_type, $event_data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO message_logs (message_id, event_type, event_data, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$message_id, $event_type, json_encode($event_data)]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log message event: " . $e->getMessage());
        }
    }
    
    /**
     * Log campaign event
     */
    private function logCampaignEvent($campaign_id, $customer_id, $event_type, $event_data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_logs (campaign_id, customer_id, event_type, event_data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$campaign_id, $customer_id, $event_type, json_encode($event_data)]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log campaign event: " . $e->getMessage());
        }
    }
    
    /**
     * Delete old messages
     */
    public function cleanupOldMessages($days = 90) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM customer_messages 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'read'
                AND message_type != 'important'
            ");
            
            $stmt->execute([$days]);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get delivery report
     */
    public function getDeliveryReport($campaign_id) {
        try {
            $report = [];
            
            // Basic stats
            $stats_stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_recipients,
                    COUNT(CASE WHEN ca.status = 'sent' THEN 1 END) as delivered,
                    COUNT(CASE WHEN ca.status = 'failed' THEN 1 END) as failed,
                    COUNT(CASE WHEN cm.status = 'read' THEN 1 END) as read
                FROM campaign_audience ca
                LEFT JOIN customer_messages cm ON ca.campaign_id = cm.campaign_id AND ca.customer_id = cm.customer_id
                WHERE ca.campaign_id = ?
            ");
            $stats_stmt->execute([$campaign_id]);
            $report['stats'] = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Timeline of events
            $timeline_stmt = $this->db->prepare("
                SELECT event_type, COUNT(*) as count, DATE(created_at) as date
                FROM campaign_logs 
                WHERE campaign_id = ?
                GROUP BY event_type, DATE(created_at)
                ORDER BY date DESC, event_type
            ");
            $timeline_stmt->execute([$campaign_id]);
            $report['timeline'] = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top engaged customers
            $engagement_stmt = $this->db->prepare("
                SELECT c.name, c.phone, COUNT(cl.id) as interactions
                FROM customers c
                JOIN campaign_logs cl ON c.id = cl.customer_id
                WHERE cl.campaign_id = ?
                GROUP BY c.id
                ORDER BY interactions DESC
                LIMIT 10
            ");
            $engagement_stmt->execute([$campaign_id]);
            $report['top_engaged'] = $engagement_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $report;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
