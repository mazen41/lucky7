<?php
/**
 * Dashboard Engine - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

class DashboardEngine {
    private $db;
    private $cache_duration = 300; // 5 minutes
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Get cached data or execute query
     */
    private function getCachedData($cache_key, $callback) {
        $cache_file = sys_get_temp_dir() . '/mai_salon_cache_' . md5($cache_key) . '.json';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $this->cache_duration) {
            return json_decode(file_get_contents($cache_file), true);
        }
        
        $data = $callback();
        file_put_contents($cache_file, json_encode($data));
        return $data;
    }
    
    /**
     * Get Today's KPIs
     */
    public function getTodayKPIs() {
        return $this->getCachedData('today_kpis', function() {
            $today = date('Y-m-d');
            $kpis = [];
            
            // Gross Sales (Total Revenue) - Check if bookings table exists
            try {
                $gross_stmt = $this->db->prepare("
                    SELECT COALESCE(SUM(CASE 
                        WHEN total_amount IS NOT NULL THEN total_amount 
                        WHEN price IS NOT NULL THEN price 
                        ELSE 0 
                    END), 0) as gross_sales
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = ? AND status IN ('completed', 'confirmed')
                ");
                $gross_stmt->execute([$today]);
                $kpis['gross_sales'] = $gross_stmt->fetchColumn();
            } catch (PDOException $e) {
                $kpis['gross_sales'] = 0;
            }
            
            // Net Sales (After returns/cancellations)
            try {
                $returns_stmt = $this->db->prepare("
                    SELECT COALESCE(SUM(CASE 
                        WHEN total_amount IS NOT NULL THEN total_amount 
                        WHEN price IS NOT NULL THEN price 
                        ELSE 0 
                    END), 0) as returns
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = ? AND status = 'cancelled'
                ");
                $returns_stmt->execute([$today]);
                $returns = $returns_stmt->fetchColumn();
                $kpis['net_sales'] = $kpis['gross_sales'] - $returns;
                $kpis['returns'] = $returns;
            } catch (PDOException $e) {
                $kpis['returns'] = 0;
                $kpis['net_sales'] = $kpis['gross_sales'];
            }
            
            // Invoice Count
            try {
                $invoice_stmt = $this->db->prepare("
                    SELECT COUNT(*) as invoice_count
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = ? AND status IN ('completed', 'confirmed')
                ");
                $invoice_stmt->execute([$today]);
                $kpis['invoice_count'] = $invoice_stmt->fetchColumn();
            } catch (PDOException $e) {
                $kpis['invoice_count'] = 0;
            }
            
            // Average Ticket
            $kpis['avg_ticket'] = $kpis['invoice_count'] > 0 ? $kpis['gross_sales'] / $kpis['invoice_count'] : 0;
            
            // Payment Mix
            try {
                $payment_stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(payment_method, 'cash') as payment_method,
                        COUNT(*) as count,
                        SUM(CASE 
                            WHEN total_amount IS NOT NULL THEN total_amount 
                            WHEN price IS NOT NULL THEN price 
                            ELSE 0 
                        END) as amount
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = ? AND status IN ('completed', 'confirmed')
                    GROUP BY payment_method
                ");
                $payment_stmt->execute([$today]);
                $kpis['payment_mix'] = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $kpis['payment_mix'] = [];
            }
            
            // Additional KPIs with error handling
            try {
                // Upcoming appointments
                $appt_stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM appointments 
                    WHERE DATE(start_at) >= ? AND status = 'confirmed'
                ");
                $appt_stmt->execute([$today]);
                $kpis['upcoming_appointments'] = $appt_stmt->fetchColumn();
            } catch (PDOException $e) {
                $kpis['upcoming_appointments'] = 0;
            }
            
            try {
                // New customers
                $cust_stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM customers 
                    WHERE DATE(created_at) = ?
                ");
                $cust_stmt->execute([$today]);
                $kpis['new_customers'] = $cust_stmt->fetchColumn();
            } catch (PDOException $e) {
                $kpis['new_customers'] = 0;
            }
            
            try {
                // Low stock items
                $stock_stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM products 
                    WHERE stock_quantity <= COALESCE(min_stock_level, 5)
                ");
                $stock_stmt->execute();
                $kpis['low_stock_items'] = $stock_stmt->fetchColumn();
            } catch (PDOException $e) {
                $kpis['low_stock_items'] = 0;
            }

            // Shift Status
            try {
                $shift_stmt = $this->db->prepare("
                    SELECT 
                        COUNT(CASE WHEN status = 'open' THEN 1 END) as open_shifts,
                        COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_shifts
                    FROM shifts 
                    WHERE DATE(shift_date) = ?
                ");
                $shift_stmt->execute([$today]);
                $kpis['shift_status'] = $shift_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $kpis['shift_status'] = ['open_shifts' => 0, 'closed_shifts' => 0];
            }
            
            return $kpis;
        });
    }
    
    /**
     * Get Sales Trend Data
     */
    public function getSalesTrend($days = 7) {
        return $this->getCachedData("sales_trend_{$days}", function() use ($days) {
            $start_date = date('Y-m-d', strtotime("-{$days} days"));
            
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        DATE(CASE 
                            WHEN booking_date IS NOT NULL THEN booking_date 
                            WHEN created_at IS NOT NULL THEN created_at 
                            ELSE NOW() 
                        END) as date,
                        SUM(CASE 
                            WHEN total_amount IS NOT NULL THEN total_amount 
                            WHEN price IS NOT NULL THEN price 
                            ELSE 0 
                        END) as sales
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) >= ? AND status IN ('completed', 'confirmed')
                    GROUP BY DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END)
                    ORDER BY date ASC
                ");
                $stmt->execute([$start_date]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [];
            }
        });
    }
    
    /**
     * Get Top Services
     */
    public function getTopServices($limit = 5) {
        return $this->getCachedData("top_services_{$limit}", function() use ($limit) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(s.title_ar, s.name, 'خدمة غير محددة') as service_name,
                        COUNT(b.id) as booking_count,
                        SUM(CASE 
                            WHEN b.total_amount IS NOT NULL THEN b.total_amount 
                            WHEN b.price IS NOT NULL THEN b.price 
                            WHEN s.price IS NOT NULL THEN s.price 
                            ELSE 0 
                        END) as total_revenue
                    FROM services s
                    LEFT JOIN bookings b ON s.id = b.service_id 
                        AND DATE(CASE 
                            WHEN b.booking_date IS NOT NULL THEN b.booking_date 
                            WHEN b.created_at IS NOT NULL THEN b.created_at 
                            ELSE NOW() 
                        END) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        AND b.status IN ('completed', 'confirmed')
                    GROUP BY s.id, s.title_ar, s.name
                    ORDER BY booking_count DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [];
            }
        });
    }
    
    /**
     * Get Payment Mix Chart Data
     */
    public function getPaymentMixChart() {
        return $this->getCachedData('payment_mix_chart', function() {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(payment_method, 'cash') as payment_method,
                        COUNT(*) as count,
                        ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM bookings WHERE DATE(CASE 
                            WHEN booking_date IS NOT NULL THEN booking_date 
                            WHEN created_at IS NOT NULL THEN created_at 
                            ELSE NOW() 
                        END) = CURDATE() AND status IN ('completed', 'confirmed')), 0)), 2) as percentage
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = CURDATE() AND status IN ('completed', 'confirmed')
                    GROUP BY payment_method
                ");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [['payment_method' => 'cash', 'count' => 0, 'percentage' => 100]];
            }
        });
    }
    
    /**
     * Get Recent Invoices
     */
    public function getRecentInvoices($limit = 5) {
        return $this->getCachedData("recent_invoices_{$limit}", function() use ($limit) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        b.id,
                        COALESCE(c.name, b.customer_name, 'عميل غير محدد') as customer_name,
                        COALESCE(s.title_ar, s.name, 'خدمة غير محددة') as service_name,
                        COALESCE(b.total_amount, b.price, s.price, 0) as total_amount,
                        b.status,
                        COALESCE(b.booking_date, b.created_at, NOW()) as booking_date
                    FROM bookings b
                    LEFT JOIN customers c ON b.customer_id = c.id
                    LEFT JOIN services s ON b.service_id = s.id
                    WHERE b.status IN ('completed', 'confirmed')
                    ORDER BY COALESCE(b.booking_date, b.created_at, NOW()) DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [];
            }
        });
    }
    
    /**
     * Get Low Stock Items
     */
    public function getLowStockItems($limit = 5) {
        return $this->getCachedData("low_stock_{$limit}", function() use ($limit) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(name, 'منتج غير محدد') as product_name,
                        COALESCE(category, 'غير مصنف') as category,
                        COALESCE(stock_quantity, 0) as stock_quantity,
                        COALESCE(min_stock_level, 5) as min_stock_level
                    FROM products 
                    WHERE COALESCE(stock_quantity, 0) <= COALESCE(min_stock_level, 5)
                    ORDER BY stock_quantity ASC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [];
            }
        });
    }
    
    /**
     * Get Upcoming Appointments
     */
    public function getUpcomingAppointments($limit = 5) {
        return $this->getCachedData("upcoming_appointments_{$limit}", function() use ($limit) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        a.id,
                        COALESCE(c.name, a.customer_name, 'عميل غير محدد') as customer_name,
                        COALESCE(s.title_ar, s.name, 'خدمة غير محددة') as service_name,
                        a.start_at,
                        a.status
                    FROM appointments a
                    LEFT JOIN customers c ON a.customer_id = c.id
                    LEFT JOIN services s ON a.service_id = s.id
                    WHERE a.start_at >= NOW() AND a.status IN ('confirmed', 'scheduled')
                    ORDER BY a.start_at ASC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [];
            }
        });
    }
    
    /**
     * Get Performance Comparison (Today vs Yesterday)
     */
    public function getPerformanceComparison() {
        return $this->getCachedData('performance_comparison', function() {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        DATE(CASE 
                            WHEN booking_date IS NOT NULL THEN booking_date 
                            WHEN created_at IS NOT NULL THEN created_at 
                            ELSE NOW() 
                        END) as date,
                        COALESCE(SUM(CASE 
                            WHEN status IN ('completed', 'confirmed') THEN 
                                CASE 
                                    WHEN total_amount IS NOT NULL THEN total_amount 
                                    WHEN price IS NOT NULL THEN price 
                                    ELSE 0 
                                END
                            ELSE 0 
                        END), 0) as sales,
                        COUNT(CASE WHEN status IN ('completed', 'confirmed') THEN 1 END) as transactions
                    FROM bookings 
                    WHERE DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) IN (?, ?)
                    GROUP BY DATE(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END)
                ");
                $stmt->execute([$today, $yesterday]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $results = [];
            }
            
            $comparison = [
                'today' => ['sales' => 0, 'transactions' => 0],
                'yesterday' => ['sales' => 0, 'transactions' => 0]
            ];
            
            foreach ($results as $row) {
                if ($row['date'] === $today) {
                    $comparison['today'] = $row;
                } elseif ($row['date'] === $yesterday) {
                    $comparison['yesterday'] = $row;
                }
            }
            
            // Calculate percentage changes
            $comparison['sales_change'] = $comparison['yesterday']['sales'] > 0 ? 
                (($comparison['today']['sales'] - $comparison['yesterday']['sales']) / $comparison['yesterday']['sales']) * 100 : 0;
                
            $comparison['transactions_change'] = $comparison['yesterday']['transactions'] > 0 ? 
                (($comparison['today']['transactions'] - $comparison['yesterday']['transactions']) / $comparison['yesterday']['transactions']) * 100 : 0;
            
            return $comparison;
        });
    }
    
    /**
     * Get Monthly Summary
     */
    public function getMonthlySummary() {
        return $this->getCachedData('monthly_summary', function() {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(SUM(CASE 
                            WHEN status IN ('completed', 'confirmed') THEN 
                                CASE 
                                    WHEN total_amount IS NOT NULL THEN total_amount 
                                    WHEN price IS NOT NULL THEN price 
                                    ELSE 0 
                                END
                            ELSE 0 
                        END), 0) as total_sales,
                        COUNT(CASE WHEN status IN ('completed', 'confirmed') THEN 1 END) as total_transactions,
                        COUNT(DISTINCT COALESCE(customer_id, 0)) as unique_customers
                    FROM bookings 
                    WHERE MONTH(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = MONTH(NOW()) 
                    AND YEAR(CASE 
                        WHEN booking_date IS NOT NULL THEN booking_date 
                        WHEN created_at IS NOT NULL THEN created_at 
                        ELSE NOW() 
                    END) = YEAR(NOW())
                ");
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return ['total_sales' => 0, 'total_transactions' => 0, 'unique_customers' => 0];
            }
        });
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $cache_files = glob(sys_get_temp_dir() . '/mai_salon_cache_*.json');
        foreach ($cache_files as $file) {
            unlink($file);
        }
        return count($cache_files);
    }
    
    /**
     * Get cache status
     */
    public function getCacheStatus() {
        $cache_files = glob(sys_get_temp_dir() . '/mai_salon_cache_*.json');
        $status = [];
        
        foreach ($cache_files as $file) {
            $age = time() - filemtime($file);
            $status[] = [
                'file' => basename($file),
                'age_seconds' => $age,
                'expired' => $age > $this->cache_duration
            ];
        }
        
        return $status;
    }
}
?>
